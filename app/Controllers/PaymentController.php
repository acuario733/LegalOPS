<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\BillingService;
use App\Services\StripeService;

final class PaymentController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        try {
            $invoice = $this->container->get(BillingService::class)->publicInvoice($token);
            return Response::html($this->views->render('payments/show', [
                'invoice' => $invoice,
                'token' => $token,
                'stripeKey' => (string) Config::env('STRIPE_KEY', ''),
            ], null));
        } catch (HttpException $exception) {
            return Response::html(
                '<!doctype html><html lang="es"><meta charset="utf-8"><title>Pago no disponible</title>'
                . '<body><main><h1>Pago no disponible</h1><p>' . htmlspecialchars($exception->getMessage()) . '</p></main></body></html>',
                $exception->status()
            );
        }
    }

    public function intent(Request $request, string $token): Response
    {
        return $this->json($this->container->get(StripeService::class)->payFromToken($token));
    }

    public function webhook(Request $request): Response
    {
        $this->container->get(StripeService::class)->handleWebhook(
            $request->rawBody(),
            (string) $request->header('stripe-signature', '')
        );

        return Response::json(['received' => true]);
    }
}
