<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\BookingService;

final class BookingPublicController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $config = $this->container->get(BookingService::class)->publicConfig($slug);

        return $this->view('public/booking', [
            'title' => $config['titulo'],
            'config' => $config,
            'csrfToken' => $this->csrf->token(),
            'publicScript' => '/assets/js/booking-calendar.js',
        ], 'public_form');
    }

    public function slots(Request $request, string $slug): Response
    {
        $date = trim((string) $request->query('fecha', ''));
        $slots = $this->container->get(BookingService::class)->getAvailableSlots($slug, $date);

        return $this->json(['fecha' => $date, 'slots' => $slots], 'Horarios disponibles.');
    }

    public function store(Request $request, string $slug): Response
    {
        $appointment = $this->container->get(BookingService::class)->createAppointment(
            $slug,
            (array) $request->input()
        );

        return $this->json([
            'appointment' => $appointment,
            'google_calendar_url' => $this->googleCalendarUrl($appointment),
            'ics_url' => '/booking/' . rawurlencode($slug) . '/ics/' . rawurlencode((string) $appointment['token_cancelacion']),
        ], 'Tu cita está confirmada.', 201);
    }

    public function cancel(Request $request, string $token): Response
    {
        $service = $this->container->get(BookingService::class);
        $appointment = $service->appointmentByToken($token);
        if ($request->query('confirmar') === '1' && $appointment['estado'] === 'confirmada') {
            $service->cancelByToken($token);
            $appointment = $service->appointmentByToken($token);
        }

        return $this->view('public/booking_cancel', [
            'title' => 'Cancelar cita',
            'appointment' => $appointment,
            'csrfToken' => $this->csrf->token(),
            'publicScript' => null,
        ], 'public_form');
    }

    public function ics(Request $request, string $slug, string $token): Response
    {
        $appointment = $this->container->get(BookingService::class)->appointmentByToken($token);
        $contents = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//LegalOPS Cloud//Booking//ES',
            'BEGIN:VEVENT',
            'UID:' . $appointment['token_cancelacion'] . '@legalops.local',
            'DTSTART:' . str_replace('-', '', (string) $appointment['fecha']) . 'T' . str_replace(':', '', (string) $appointment['hora_inicio']),
            'DTEND:' . str_replace('-', '', (string) $appointment['fecha']) . 'T' . str_replace(':', '', (string) $appointment['hora_fin']),
            'SUMMARY:' . $this->icsEscape((string) $appointment['booking_titulo']),
            'DESCRIPTION:' . $this->icsEscape((string) ($appointment['notas'] ?? 'Consulta legal')),
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);

        return Response::html($contents)
            ->withHeader('Content-Type', 'text/calendar; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="cita-legalops.ics"');
    }

    /** @param array<string, mixed> $appointment */
    private function googleCalendarUrl(array $appointment): string
    {
        $start = str_replace('-', '', (string) $appointment['fecha']) . 'T' . str_replace(':', '', (string) $appointment['hora_inicio']);
        $end = str_replace('-', '', (string) $appointment['fecha']) . 'T' . str_replace(':', '', (string) $appointment['hora_fin']);

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action' => 'TEMPLATE',
            'text' => (string) $appointment['booking_titulo'],
            'dates' => $start . '/' . $end,
            'details' => (string) ($appointment['notas'] ?? 'Consulta legal'),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function icsEscape(string $value): string
    {
        return str_replace(["\\", ",", ";", "\r", "\n"], ["\\\\", "\\,", "\\;", '', "\\n"], $value);
    }
}
