<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\FirmaRepository;
use App\Repositories\PlanRepository;
use App\Repositories\UserSessionRepository;
use DateTimeImmutable;
use Exception;

final class CommercialBillingService
{
    private const PERIOD_MONTHLY = 'monthly';

    public function __construct(
        private readonly PlanRepository $plans,
        private readonly FirmaRepository $firmas,
        private readonly UserSessionRepository $sessions,
        private readonly Database $database,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth,
        private readonly CommercialStatusService $statuses
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalizeForPlanAssignment(array $data, DateTimeImmutable $effectiveAt, string $commercialStatus): array
    {
        $period = $this->normalizePeriod($data['billing_period'] ?? self::PERIOD_MONTHLY);
        $anchorDay = $this->normalizeAnchorDay($data['billing_anchor_day'] ?? $effectiveAt->format('j'));
        $renewsAt = $this->parseOptionalDateTime($data['renews_at'] ?? null) ?? $this->nextMonthlyRenewal($effectiveAt, $anchorDay);
        $trialStartedAt = $commercialStatus === CommercialStatusService::TRIAL
            ? ($this->parseOptionalDateTime($data['trial_started_at'] ?? null) ?? $effectiveAt)
            : null;
        $trialEndsAt = $commercialStatus === CommercialStatusService::TRIAL
            ? $this->parseOptionalDateTime($data['trial_ends_at'] ?? null, true)
            : null;
        $paymentDueAt = $this->parseOptionalDateTime($data['payment_due_at'] ?? null, true);
        $graceEndsAt = $this->parseOptionalDateTime($data['grace_ends_at'] ?? null, true);
        $autoSuspendAt = $this->parseOptionalDateTime($data['auto_suspend_at'] ?? null);
        $proration = $this->normalizeProration($data);

        $this->assertBillingOrder($effectiveAt, $renewsAt, $trialStartedAt, $trialEndsAt, $paymentDueAt, $graceEndsAt, $autoSuspendAt);

        return $this->toDatabaseFields($period, $anchorDay, $renewsAt, $trialStartedAt, $trialEndsAt, $paymentDueAt, $graceEndsAt, $autoSuspendAt) + $proration;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $currentAssignment
     * @return array<string, mixed>
     */
    public function normalizeForBillingUpdate(array $data, array $currentAssignment, string $commercialStatus): array
    {
        $effectiveAt = $this->storedDate($currentAssignment['effective_at'] ?? $currentAssignment['starts_at'] ?? null) ?? new DateTimeImmutable();
        $period = $this->normalizePeriod($data['billing_period'] ?? $currentAssignment['billing_period'] ?? self::PERIOD_MONTHLY);
        $anchorDay = $this->normalizeAnchorDay($data['billing_anchor_day'] ?? $currentAssignment['billing_anchor_day'] ?? $effectiveAt->format('j'));
        $renewsAt = $this->inputOrStoredDate($data['renews_at'] ?? null, $currentAssignment['renews_at'] ?? null)
            ?? $this->nextMonthlyRenewal($effectiveAt, $anchorDay);
        $trialStartedAt = null;
        $trialEndsAt = null;
        if ($commercialStatus === CommercialStatusService::TRIAL) {
            $trialStartedAt = $this->inputOrStoredDate($data['trial_started_at'] ?? null, $currentAssignment['trial_started_at'] ?? null) ?? $effectiveAt;
            $trialEndsAt = $this->inputOrStoredDate($data['trial_ends_at'] ?? null, $currentAssignment['trial_ends_at'] ?? null, true);
        }
        $paymentDueAt = $this->inputOrStoredDate($data['payment_due_at'] ?? null, $currentAssignment['payment_due_at'] ?? null, true);
        $graceEndsAt = $this->inputOrStoredDate($data['grace_ends_at'] ?? null, $currentAssignment['grace_ends_at'] ?? null, true);
        $autoSuspendAt = $this->inputOrStoredDate($data['auto_suspend_at'] ?? null, $currentAssignment['auto_suspend_at'] ?? null);
        $proration = $this->normalizeProration($data, $currentAssignment);

        $this->assertBillingOrder($effectiveAt, $renewsAt, $trialStartedAt, $trialEndsAt, $paymentDueAt, $graceEndsAt, $autoSuspendAt);

        return $this->toDatabaseFields($period, $anchorDay, $renewsAt, $trialStartedAt, $trialEndsAt, $paymentDueAt, $graceEndsAt, $autoSuspendAt) + $proration;
    }

    /** @return array{checked: int, suspended: int, skipped: int} */
    public function suspendOverdue(Request $request): array
    {
        $now = new DateTimeImmutable();
        $rows = $this->plans->automaticSuspensionCandidates($now->format('Y-m-d H:i:s'));
        $result = ['checked' => count($rows), 'suspended' => 0, 'skipped' => 0];

        $this->database->transaction(function () use ($rows, $request, $now, &$result): void {
            foreach ($rows as $row) {
                $previousStatus = $this->statuses->normalize($row['firma_estado'] ?? null);
                if ($previousStatus !== CommercialStatusService::PAYMENT_DUE) {
                    $result['skipped']++;
                    continue;
                }

                $firmaId = (int) $row['firma_id'];
                $reason = 'Suspension automatica por no pago vencido.';
                $this->firmas->setCommercialStatus($firmaId, CommercialStatusService::SUSPENDED, $reason);
                $this->sessions->revokeAllForFirma($firmaId, 'suspension_automatica_no_pago');

                $historyId = $this->plans->recordCommercialEvent([
                    'firma_id' => $firmaId,
                    'evento' => 'firma_suspendida_automatica',
                    'estado_comercial' => CommercialStatusService::SUSPENDED,
                    'estado_comercial_anterior' => $previousStatus,
                    'plan_id' => $row['plan_id'] ?? null,
                    'firma_plan_id' => $row['id'] ?? null,
                    'effective_at' => $now->format('Y-m-d H:i:s'),
                    'starts_at' => $row['starts_at'] ?? null,
                    'renews_at' => $row['renews_at'] ?? null,
                    'motivo' => $reason,
                    'usuario_id' => $this->nullableInt($this->auth->id()),
                    'metadata' => [
                        'payment_due_at' => $row['payment_due_at'] ?? null,
                        'grace_ends_at' => $row['grace_ends_at'] ?? null,
                        'auto_suspend_at' => $row['auto_suspend_at'] ?? null,
                        'consequence' => $this->statuses->consequence(CommercialStatusService::SUSPENDED),
                    ],
                ]);

                $this->audit->record('FIRMA_SUSPENDIDA_AUTOMATICA', 'firmas', 'firma', $firmaId, [
                    'historial_id' => $historyId,
                    'estado_anterior' => $previousStatus,
                    'payment_due_at' => $row['payment_due_at'] ?? null,
                    'grace_ends_at' => $row['grace_ends_at'] ?? null,
                    'auto_suspend_at' => $row['auto_suspend_at'] ?? null,
                ], $request, $firmaId, 'warning');

                $result['suspended']++;
            }
        });

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function toDatabaseFields(
        string $period,
        int $anchorDay,
        DateTimeImmutable $renewsAt,
        ?DateTimeImmutable $trialStartedAt,
        ?DateTimeImmutable $trialEndsAt,
        ?DateTimeImmutable $paymentDueAt,
        ?DateTimeImmutable $graceEndsAt,
        ?DateTimeImmutable $autoSuspendAt
    ): array {
        return [
            'renews_at' => $this->format($renewsAt),
            'billing_period' => $period,
            'billing_anchor_day' => $anchorDay,
            'trial_started_at' => $this->format($trialStartedAt),
            'trial_ends_at' => $this->format($trialEndsAt),
            'payment_due_at' => $this->format($paymentDueAt),
            'grace_ends_at' => $this->format($graceEndsAt),
            'auto_suspend_at' => $this->format($autoSuspendAt),
        ];
    }

    private function assertBillingOrder(
        DateTimeImmutable $effectiveAt,
        DateTimeImmutable $renewsAt,
        ?DateTimeImmutable $trialStartedAt,
        ?DateTimeImmutable $trialEndsAt,
        ?DateTimeImmutable $paymentDueAt,
        ?DateTimeImmutable $graceEndsAt,
        ?DateTimeImmutable $autoSuspendAt
    ): void {
        if ($renewsAt <= $effectiveAt) {
            throw new HttpException(422, 'La fecha de renovacion debe ser posterior al inicio del plan.');
        }
        if ($trialEndsAt !== null && $trialEndsAt <= ($trialStartedAt ?? $effectiveAt)) {
            throw new HttpException(422, 'El fin de prueba debe ser posterior al inicio de prueba.');
        }
        if ($paymentDueAt !== null && $paymentDueAt < $effectiveAt) {
            throw new HttpException(422, 'El vencimiento de pago no puede ser anterior al inicio del plan.');
        }
        if ($graceEndsAt !== null && $paymentDueAt === null) {
            throw new HttpException(422, 'El fin de gracia requiere registrar vencimiento de pago.');
        }
        if ($graceEndsAt !== null && $paymentDueAt !== null && $graceEndsAt < $paymentDueAt) {
            throw new HttpException(422, 'El fin de gracia no puede ser anterior al vencimiento de pago.');
        }
        $minimumSuspensionAt = $graceEndsAt ?? $paymentDueAt;
        if ($autoSuspendAt !== null && $minimumSuspensionAt === null) {
            throw new HttpException(422, 'La suspension automatica requiere vencimiento de pago o fin de gracia.');
        }
        if ($autoSuspendAt !== null && $minimumSuspensionAt !== null && $autoSuspendAt < $minimumSuspensionAt) {
            throw new HttpException(422, 'La suspension automatica no puede ocurrir antes del vencimiento o fin de gracia.');
        }
    }

    private function normalizePeriod(mixed $value): string
    {
        $period = strtolower(trim((string) ($value ?: self::PERIOD_MONTHLY)));
        if ($period !== self::PERIOD_MONTHLY) {
            throw new HttpException(422, 'En esta fase solo esta aprobada la facturacion mensual.');
        }

        return $period;
    }

    private function normalizeAnchorDay(mixed $value): int
    {
        $day = (int) $value;
        if ($day < 1 || $day > 31) {
            throw new HttpException(422, 'El dia de corte mensual debe estar entre 1 y 31.');
        }

        return $day;
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $currentAssignment */
    private function normalizeProration(array $data, array $currentAssignment = []): array
    {
        $policy = strtolower(trim((string) ($data['proration_policy'] ?? $currentAssignment['proration_policy'] ?? 'manual_review')));
        if (!in_array($policy, ['manual_review', 'none'], true)) {
            throw new HttpException(422, 'La politica de prorrateo aprobada debe ser manual_review o none.');
        }
        $note = trim((string) ($data['proration_note'] ?? $currentAssignment['proration_note'] ?? ''));
        if ($policy === 'manual_review' && $note === '') {
            $note = 'Prorrateo sujeto a revision administrativa; no calcula cobros automaticos.';
        }

        return [
            'proration_policy' => $policy,
            'proration_note' => $note === '' ? null : mb_substr($note, 0, 500),
        ];
    }

    private function inputOrStoredDate(mixed $input, mixed $stored, bool $endOfDay = false): ?DateTimeImmutable
    {
        return $this->parseOptionalDateTime($input, $endOfDay) ?? $this->storedDate($stored);
    }

    private function storedDate(mixed $value): ?DateTimeImmutable
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($raw);
        } catch (Exception) {
            return null;
        }
    }

    private function parseOptionalDateTime(mixed $value, bool $endOfDay = false): ?DateTimeImmutable
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $raw = str_replace('T', ' ', $raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            $raw .= $endOfDay ? ' 23:59:59' : ' 00:00:00';
        }

        try {
            return new DateTimeImmutable($raw);
        } catch (Exception) {
            throw new HttpException(422, 'La fecha comercial indicada no es valida.');
        }
    }

    private function nextMonthlyRenewal(DateTimeImmutable $effectiveAt, int $anchorDay): DateTimeImmutable
    {
        $year = (int) $effectiveAt->format('Y');
        $month = (int) $effectiveAt->format('n') + 1;
        if ($month > 12) {
            $month = 1;
            $year++;
        }
        $day = min($anchorDay, cal_days_in_month(CAL_GREGORIAN, $month, $year));

        return $effectiveAt->setDate($year, $month, $day);
    }

    private function format(?DateTimeImmutable $date): ?string
    {
        return $date?->format('Y-m-d H:i:s');
    }

    private function nullableInt(int|string|null $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }
}
