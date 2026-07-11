<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Services\MfaService;
use App\Services\QueueService;
use App\Services\SensitiveDataService;
use PDO;
use Predis\Client as RedisClient;

/**
 * Registra MfaService en el contenedor de la aplicacion.
 * Se invoca desde App::bootstrap() al procesar config/app.php → providers.
 */
final class MfaServiceProvider
{
    public static function register(Container $container): void
    {
        $container->singleton(MfaService::class, static function (Container $c): MfaService {
            $redis = null;
            try {
                $redis = $c->get(RedisClient::class);
            } catch (\Throwable) {
                // Redis opcional; sin el, el rate-limit MFA es en memoria
            }

            return new MfaService(
                $c->get(PDO::class),
                $c->get(SensitiveDataService::class),
                $c->get(QueueService::class),
                $redis
            );
        });
    }
}
