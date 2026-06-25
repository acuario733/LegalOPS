<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Request;
use App\Repositories\OnboardingRepository;

final class OnboardingService
{
    /** @var array<string, string> */
    private array $steps = [
        'primer_cliente' => 'Primer cliente activo',
        'primer_caso' => 'Primer caso activo',
        'primer_termino' => 'Primer termino vigente',
        'primer_documento' => 'Primer documento cargado',
        'usuario_invitado' => 'Usuario invitado',
        'portal_cliente' => 'Portal cliente asociado',
    ];

    public function __construct(
        private readonly OnboardingRepository $repository,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth
    ) {
    }

    /** @return array{steps: list<array<string, mixed>>, percent: int} */
    public function dashboard(int $firmaId): array
    {
        $this->sync($firmaId);
        $progress = array_column($this->repository->progress($firmaId), null, 'paso');
        $steps = [];
        $completed = 0;
        foreach ($this->steps as $code => $label) {
            $row = $progress[$code] ?? ['estado' => 'pendiente', 'completed_at' => null, 'metadata_json' => null];
            if (($row['estado'] ?? '') === 'completado') {
                $completed++;
            }
            $steps[] = ['codigo' => $code, 'titulo' => $label] + $row;
        }

        return ['steps' => $steps, 'percent' => (int) round(($completed / max(1, count($this->steps))) * 100)];
    }

    public function refresh(int $firmaId, Request $request): void
    {
        $this->sync($firmaId);
        $this->audit->record('ONBOARDING_ACTUALIZADO', 'onboarding', 'firma', $firmaId, [], $request, $firmaId);
    }

    private function sync(int $firmaId): void
    {
        $checks = [
            'primer_cliente' => $this->repository->countWhere('clientes', $firmaId, 'deleted_at IS NULL AND estado=\'activo\'') > 0,
            'primer_caso' => $this->repository->countWhere('casos', $firmaId, 'deleted_at IS NULL AND estado=\'activo\'') > 0,
            'primer_termino' => $this->repository->countWhere('terminos', $firmaId, 'deleted_at IS NULL AND estado NOT IN (\'cumplido\',\'cancelado\')') > 0,
            'primer_documento' => $this->repository->countWhere('documentos', $firmaId, 'deleted_at IS NULL AND current_version_id IS NOT NULL') > 0,
            'usuario_invitado' => $this->repository->countWhere('usuarios', $firmaId, 'deleted_at IS NULL AND tipo=\'interno\' AND estado=\'activo\' AND invited_at IS NOT NULL') > 0,
            'portal_cliente' => $this->repository->hasPortalUser($firmaId),
        ];
        foreach ($checks as $step => $ok) {
            $this->repository->upsert($firmaId, $step, $ok ? 'completado' : 'pendiente', $this->auth->id() === null ? null : (int) $this->auth->id(), ['auto' => true]);
        }
    }
}
