<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\JobMonitorService;

final class SuperadminJobController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('superadmin/jobs/index', [
            'title' => 'Monitoreo de jobs',
            'jobs' => $this->container->get(JobMonitorService::class)->dashboard(),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ], 'superadmin');
    }

    public function retry(Request $request, string $id): Response
    {
        $jobId = (int) $id;
        $this->container->get(JobMonitorService::class)->retryFailed($jobId);
        $this->container->get(Audit::class)->record([
            'usuario_id' => $this->auth->id(),
            'action' => 'JOB_RETRY',
            'module' => 'queue',
            'entity_type' => 'failed_job',
            'entity_id' => $jobId,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->json(['id' => $jobId], 'Job reenviado a la cola.');
    }
}
