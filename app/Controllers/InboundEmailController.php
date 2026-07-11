<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\CasoComunicacionService;
use Throwable;

final class InboundEmailController extends Controller
{
    public function receive(Request $request): Response
    {
        $secret = (string) Config::get('app.inbound_email_secret', '');
        if ($secret !== '' && !hash_equals($secret, (string) $request->header('x-webhook-secret', ''))) {
            return Response::json(null, 'No autorizado.', 401, [], false);
        }
        if ($secret === '') {
            return Response::json(null, 'Webhook no configurado.', 401, [], false);
        }

        try {
            $this->container->get(CasoComunicacionService::class)->processInboundEmail((array) $request->input());
        } catch (Throwable $exception) {
            error_log('Inbound email webhook error: ' . get_class($exception) . ' ' . $exception->getMessage());
        }

        return $this->json(null, 'Webhook recibido.');
    }
}
