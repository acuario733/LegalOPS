<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\ApiAuthMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CommercialStatusMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\FirmaMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\InternalUserMiddleware;
use App\Middleware\LegalAcceptanceMiddleware;
use App\Middleware\MiddlewareInterface;
use App\Middleware\PermissionMiddleware;
use App\Middleware\PlanLimitMiddleware;
use App\Middleware\PortalClienteMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\SuperadminMiddleware;
use App\Monitoring\ErrorReporter;
use App\Security\RateLimiter;
use App\Security\RedisRateLimiter;
use App\Services\LimitePlanService;
use App\Services\SessionService;
use App\Repositories\LoginAttemptRepository;
use App\Repositories\UsuarioRepository;
use PDO;
use Predis\Client as RedisClient;
use RuntimeException;
use Throwable;

final class App
{
    public function __construct(
        private readonly Router $router,
        private readonly ErrorHandler $errors,
        private readonly Audit $audit,
        private readonly Container $container
    ) {
    }

    public static function bootstrap(string $basePath): self
    {
        Config::load($basePath);
        date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

        $reporter = new ErrorReporter(
            sentryDsn:       (string) Config::get('monitoring.sentry_dsn', ''),
            slackWebhookUrl: (string) Config::get('monitoring.slack_webhook_url', ''),
            appEnv:          (string) Config::get('app.environment', 'production'),
            appUrl:          (string) Config::get('app.url', ''),
        );
        $errors = new ErrorHandler(
            logPath:  (string) Config::get('app.log_path', $basePath . '/storage/logs/app.log'),
            reporter: $reporter,
        );
        $errors->register();

        $session = new Session((array) Config::get('security.session', []));
        $session->start();

        $csrfConfig = (array) Config::get('security.csrf', []);
        $csrf = new Csrf(
            $session,
            (string) ($csrfConfig['session_key'] ?? '_csrf_token'),
            (string) ($csrfConfig['header'] ?? 'X-CSRF-TOKEN'),
            (string) ($csrfConfig['input'] ?? '_token')
        );
        $container = new Container();
        $auth = new Auth($session);
        $permissions = new Permission(static function (array $user) use ($container): array {
            if (($user['tipo'] ?? null) === 'superadmin') {
                return ['*'];
            }

            $sessionPermissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
            $userId = filter_var($user['id'] ?? null, FILTER_VALIDATE_INT);
            $firmaId = filter_var($user['firma_id'] ?? null, FILTER_VALIDATE_INT);
            if ($userId === false || $firmaId === false) {
                return $sessionPermissions;
            }

            try {
                $databasePermissions = $container->get(UsuarioRepository::class)->permissionCodes((int) $userId, (int) $firmaId);
            } catch (Throwable) {
                return $sessionPermissions;
            }

            return array_merge($sessionPermissions, $databasePermissions);
        });
        $views = new View($basePath . '/app/Views');
        $audit = new Audit((string) Config::get('app.audit_log_path', $basePath . '/storage/logs/audit.log'));
        $database = new Database((array) Config::get('database', []));
        $container->instance(Container::class, $container);
        $container->instance(View::class, $views);
        $container->instance(Session::class, $session);
        $container->instance(Auth::class, $auth);
        $container->instance(Permission::class, $permissions);
        $container->instance(Csrf::class, $csrf);
        $container->instance(Audit::class, $audit);
        $container->instance(Database::class, $database);
        $container->singleton(PDO::class, static fn (): PDO => $database->connection());
        $container->singleton(RateLimiter::class, static fn (): RateLimiter => new RateLimiter());

        // Redis — solo si REDIS_HOST está configurado en .env
        $redisHost = (string) Config::get('redis.host', '');
        if ($redisHost !== '') {
            $container->singleton(RedisClient::class, static function () use ($basePath): RedisClient {
                return new RedisClient([
                    'scheme' => (string) Config::get('redis.scheme', 'tcp'),
                    'host'   => (string) Config::get('redis.host', '127.0.0.1'),
                    'port'   => (int)    Config::get('redis.port', 6379),
                ]);
            });
            $container->singleton(RedisRateLimiter::class, static function (Container $c): RedisRateLimiter {
                return new RedisRateLimiter(
                    $c->get(RedisClient::class),
                    defaultLimit: 60,
                    defaultWindowSeconds: 60,
                );
            });
        }

        $controllerResolver = static function (string $controller) use ($container): object {
            if (!class_exists($controller) || !is_subclass_of($controller, Controller::class)) {
                throw new RuntimeException('El controlador solicitado no es válido.');
            }

            return $container->get($controller);
        };

        $middlewareResolver = static function (mixed $definition) use ($auth, $csrf, $permissions, $container): mixed {
            if ($definition instanceof MiddlewareInterface || (is_callable($definition) && !is_string($definition))) {
                return $definition;
            }
            if (!is_string($definition)) {
                throw new RuntimeException('Definición de middleware no válida.');
            }

            [$name, $parameter] = array_pad(explode(':', $definition, 2), 2, '');

            return match ($name) {
                'auth' => new AuthMiddleware($auth, static fn (): bool => $container->get(SessionService::class)->currentIsActive()),
                'guest' => new GuestMiddleware($auth),
                'internal' => new InternalUserMiddleware($auth),
                'firma' => new FirmaMiddleware($auth),
                'commercial' => new CommercialStatusMiddleware($auth, $container->get(\App\Services\CommercialStatusService::class)),
                'legal_pending' => new LegalAcceptanceMiddleware($container->get(\App\Services\AceptacionLegalService::class)),
                'csrf' => new CsrfMiddleware($csrf),
                'permission' => new PermissionMiddleware($permissions, $auth, $parameter),
                'plan' => new PlanLimitMiddleware(
                    static fn (string $resource, Request $request): bool => $auth->firmaId() !== null
                        && $container->get(LimitePlanService::class)->canCreate((int) $auth->firmaId(), $resource, $request),
                    $parameter
                ),
                'portal' => new PortalClienteMiddleware($auth),
                'superadmin' => new SuperadminMiddleware($auth),
                'api_auth' => new ApiAuthMiddleware($container->get(PDO::class)),
                'rate_limit' => new RateLimitMiddleware(static function (Request $request) use ($container, $parameter): array {
                    if ($parameter === 'public') {
                        $limiter = $container->get(RateLimiter::class);
                        $clientId = 'ip:' . $request->ip();
                        $endpoint = $request->method() . ':' . $request->uri();
                        $allowed = $limiter->allowPersistent($clientId, $endpoint, limit: 5, windowSeconds: 60);

                        return [
                            'allowed' => $allowed,
                            'retry_after' => $allowed
                                ? 0
                                : $limiter->getPersistentRetryAfter($clientId, $endpoint, windowSeconds: 60),
                        ];
                    }
                    $email = strtolower(trim((string) $request->input('email', '')));
                    $subject = filter_var($email, FILTER_VALIDATE_EMAIL) === false ? 'ip:' . $request->ip() : $email;
                    $failures = $container->get(LoginAttemptRepository::class)->countRecentFailures(hash('sha256', $subject), $request->ip());

                    return ['allowed' => $failures < 5, 'retry_after' => 60];
                }),
                'reveal_limit' => new RateLimitMiddleware(static function (Request $request) use ($container): array {
                    $limiter = $container->get(RateLimiter::class);
                    $clientId = 'ip:' . $request->ip();
                    $allowed = $limiter->allow($clientId, 'reveal', limit: 10, windowSeconds: 60);
                    $retryAfter = $allowed ? 0 : $limiter->getRetryAfter($clientId, 'reveal', windowSeconds: 60);

                    return ['allowed' => $allowed, 'retry_after' => $retryAfter];
                }),
                default => throw new RuntimeException(sprintf('Middleware "%s" no registrado.', $name)),
            };
        };

        $router = new Router($controllerResolver, $middlewareResolver);
        $router->middleware(new CsrfMiddleware($csrf));
        self::loadRoutes($router, $basePath . '/routes');

        return new self($router, $errors, $audit, $container);
    }

    public function run(): void
    {
        $this->handle(Request::capture())->send();
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $exception) {
            return $this->errors->handle($exception, $request);
        }
    }

    public function audit(): Audit
    {
        return $this->audit;
    }

    public function container(): Container
    {
        return $this->container;
    }

    private static function loadRoutes(Router $router, string $routePath): void
    {
        foreach (['web.php', 'api.php', 'api_v1.php', 'portal.php', 'superadmin.php'] as $routeFile) {
            $path = $routePath . DIRECTORY_SEPARATOR . $routeFile;
            if (!is_file($path)) {
                continue;
            }

            $registrar = require $path;
            if (!is_callable($registrar)) {
                throw new RuntimeException(sprintf('El archivo de rutas "%s" no devolvió un registrador válido.', $routeFile));
            }
            $registrar($router);
        }
    }
}
