<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\CommercialStatusMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\FirmaMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\InternalUserMiddleware;
use App\Middleware\MiddlewareInterface;
use App\Middleware\PermissionMiddleware;
use App\Middleware\PlanLimitMiddleware;
use App\Middleware\PortalClienteMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\SuperadminMiddleware;
use App\Services\LimitePlanService;
use App\Services\SessionService;
use App\Repositories\LoginAttemptRepository;
use PDO;
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

        $errors = new ErrorHandler((string) Config::get('app.log_path', $basePath . '/storage/logs/app.log'));
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
        $auth = new Auth($session);
        $permissions = new Permission();
        $views = new View($basePath . '/app/Views');
        $audit = new Audit((string) Config::get('app.audit_log_path', $basePath . '/storage/logs/audit.log'));
        $database = new Database((array) Config::get('database', []));
        $container = new Container();
        $container->instance(Container::class, $container);
        $container->instance(View::class, $views);
        $container->instance(Session::class, $session);
        $container->instance(Auth::class, $auth);
        $container->instance(Permission::class, $permissions);
        $container->instance(Csrf::class, $csrf);
        $container->instance(Audit::class, $audit);
        $container->instance(Database::class, $database);
        $container->singleton(PDO::class, static fn (): PDO => $database->connection());

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
                'commercial' => new CommercialStatusMiddleware($auth),
                'csrf' => new CsrfMiddleware($csrf),
                'permission' => new PermissionMiddleware($permissions, $auth, $parameter),
                'plan' => new PlanLimitMiddleware(
                    static fn (string $resource): bool => $auth->firmaId() !== null
                        && $container->get(LimitePlanService::class)->canCreate((int) $auth->firmaId(), $resource),
                    $parameter
                ),
                'portal' => new PortalClienteMiddleware($auth),
                'superadmin' => new SuperadminMiddleware($auth),
                'rate_limit' => new RateLimitMiddleware(static function (Request $request) use ($container): array {
                    $email = strtolower(trim((string) $request->input('email', '')));
                    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                        return ['allowed' => true];
                    }
                    $failures = $container->get(LoginAttemptRepository::class)->countRecentFailures(hash('sha256', $email), $request->ip());

                    return ['allowed' => $failures < 5, 'retry_after' => 60];
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
        foreach (['web.php', 'api.php', 'portal.php', 'superadmin.php'] as $routeFile) {
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
