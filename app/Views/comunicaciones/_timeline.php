<?php
declare(strict_types=1);
$items = is_array($comunicaciones ?? null) ? $comunicaciones : [];
$icons = ['email' => 'bi-envelope-at', 'llamada' => 'bi-telephone', 'mensaje' => 'bi-chat-dots', 'reunion' => 'bi-people', 'otro' => 'bi-journal-text'];
$badge = ['entrante' => 'text-bg-primary', 'saliente' => 'text-bg-success', 'interno' => 'text-bg-secondary'];
$formatDate = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return '';
    }
    $days = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
    $months = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return $days[(int) date('w', $timestamp)] . ' ' . date('d', $timestamp) . ' ' . $months[(int) date('n', $timestamp)] . ' ' . date('Y, H:i', $timestamp);
};
?>
<?php if ($items === []): ?>
    <div class="text-center text-secondary py-5 border rounded bg-light">
        <i class="bi bi-inboxes display-6 d-block mb-2"></i>
        <p class="mb-0">Sin comunicaciones registradas</p>
    </div>
<?php else: ?>
    <div class="legalops-comms-timeline">
        <?php foreach ($items as $item): ?>
            <?php
            $tipo = (string) ($item['tipo'] ?? 'otro');
            $direccion = (string) ($item['direccion'] ?? 'saliente');
            $body = (string) ($item['cuerpo'] ?? '');
            $short = mb_strlen($body) > 120 ? mb_substr($body, 0, 120) . '...' : $body;
            $participants = $item['participantes'] ?? null;
            ?>
            <article class="d-flex gap-3 pb-3 mb-3 border-bottom" data-comunicacion-id="<?= (int) $item['id'] ?>">
                <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:42px;height:42px">
                    <i class="bi <?= e($icons[$tipo] ?? $icons['otro']) ?>"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between gap-3 flex-wrap">
                        <div>
                            <span class="badge <?= e($badge[$direccion] ?? 'text-bg-secondary') ?>"><?= e(ucfirst($direccion)) ?></span>
                            <span class="badge text-bg-light border"><?= e(ucfirst($tipo)) ?></span>
                            <span class="text-secondary small ms-2"><?= e($formatDate($item['fecha_comunicacion'] ?? '')) ?></span>
                        </div>
                        <?php if (($canDelete ?? false) === true): ?>
                            <button class="btn btn-sm btn-outline-danger" type="button" data-delete-comunicacion="<?= (int) $item['id'] ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if (($item['asunto'] ?? null) !== null): ?><div class="fw-semibold mt-2"><?= e($item['asunto']) ?></div><?php endif; ?>
                    <?php if ($body !== ''): ?>
                        <p class="mb-1 mt-1">
                            <span data-cuerpo-short="<?= (int) $item['id'] ?>"><?= e($short) ?></span>
                            <?php if (mb_strlen($body) > 120): ?>
                                <span data-cuerpo-full="<?= (int) $item['id'] ?>" hidden><?= nl2br(e($body)) ?></span>
                                <button class="btn btn-link btn-sm p-0" type="button" data-expand-cuerpo="<?= (int) $item['id'] ?>">Ver mas</button>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if (is_array($participants) && $participants !== []): ?>
                        <div class="text-secondary small">Participantes: <?= e(implode(', ', array_map(static fn (mixed $value): string => is_scalar($value) ? (string) $value : json_encode($value), $participants))) ?></div>
                    <?php endif; ?>
                    <?php if (($item['duracion_minutos'] ?? null) !== null): ?>
                        <div class="text-secondary small">Duracion: <?= (int) $item['duracion_minutos'] ?> minutos</div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
