<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\QueueService;
use App\Core\Config;
use PDO;

final class AppointmentReminderJob extends Job
{
    public function __construct(private readonly PDO $pdo, private readonly QueueService $queue)
    {
    }

    public function handle(array $payload): void
    {
        $sql = 'SELECT ba.*,u.nombre AS abogado_nombre
                FROM booking_appointments ba
                INNER JOIN usuarios u ON u.id=ba.usuario_id AND u.firma_id=ba.firma_id
                WHERE ba.estado=\'confirmada\'
                  AND TIMESTAMP(ba.fecha,ba.hora_inicio)>CURRENT_TIMESTAMP
                  AND (
                    (TIMESTAMP(ba.fecha,ba.hora_inicio)<=DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 24 HOUR) AND ba.recordatorio_24h_at IS NULL)
                    OR
                    (TIMESTAMP(ba.fecha,ba.hora_inicio)<=DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 1 HOUR) AND ba.recordatorio_1h_at IS NULL)
                  )';
        $params = [];
        if (($payload['firma_id'] ?? null) !== null) {
            $sql .= ' AND ba.firma_id=:firma_id';
            $params['firma_id'] = (int) $payload['firma_id'];
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        foreach ($statement->fetchAll() as $appointment) {
            $appointmentAt = new \DateTimeImmutable($appointment['fecha'] . ' ' . $appointment['hora_inicio']);
            $seconds = $appointmentAt->getTimestamp() - time();
            $column = $seconds <= 3600 ? 'recordatorio_1h_at' : 'recordatorio_24h_at';
            $label = $seconds <= 3600 ? '1 hora' : '24 horas';
            $body = '<p>Recordatorio de cita en ' . $label . '.</p>'
                . '<p>Profesional: ' . htmlspecialchars((string) $appointment['abogado_nombre']) . '</p>'
                . '<p>Fecha: ' . htmlspecialchars((string) $appointment['fecha'] . ' ' . substr((string) $appointment['hora_inicio'], 0, 5)) . '</p>'
                . '<p><a href="' . htmlspecialchars(rtrim((string) Config::get('app.url', ''), '/') . '/booking/cancelar/' . rawurlencode((string) $appointment['token_cancelacion'])) . '">Cancelar cita</a></p>';
            $this->queue->dispatch(SendEmailJob::class, [
                'to' => (string) $appointment['email_cliente'],
                'subject' => 'Recordatorio de cita - LegalOPS Cloud',
                'body' => $body,
            ], 'mail');
            $update = $this->pdo->prepare(
                'UPDATE booking_appointments SET ' . $column . '=CURRENT_TIMESTAMP
                 WHERE id=:id AND firma_id=:firma_id AND ' . $column . ' IS NULL'
            );
            $update->execute(['id' => (int) $appointment['id'], 'firma_id' => (int) $appointment['firma_id']]);
        }
    }
}
