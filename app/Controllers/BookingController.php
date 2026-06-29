<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\BookingService;
use App\Services\UsuarioService;

final class BookingController extends Controller
{
    public function index(Request $request): Response
    {
        $firmaId = $this->firmaId();
        $userId = $this->userId();
        $service = $this->container->get(BookingService::class);

        return $this->view('booking/config', [
            'title' => 'Reserva de citas',
            'config' => $service->configForUser($userId, $firmaId),
            'configs' => $service->configsForFirma($firmaId),
            'appointments' => $service->listForFirma($firmaId, ['fecha_desde' => date('Y-m-d')]),
            'usuarios' => $this->container->get(UsuarioService::class)->all($firmaId),
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = (array) $request->input();
        $this->validateInput($data);
        $userId = filter_var($data['usuario_id'] ?? null, FILTER_VALIDATE_INT);
        $userId = $userId === false ? $this->userId() : (int) $userId;
        $config = $this->container->get(BookingService::class)->createConfig($this->firmaId(), $userId, $data);

        return $this->json($config, 'Enlace de reserva creado correctamente.', 201);
    }

    public function update(Request $request, string $id): Response
    {
        $data = (array) $request->input();
        $this->validateInput($data);
        $config = $this->container->get(BookingService::class)->updateConfig((int) $id, $this->firmaId(), $data);

        return $this->json($config, 'Configuración actualizada correctamente.');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->container->get(BookingService::class)->deleteConfig((int) $id, $this->firmaId());

        return $this->json(null, 'Enlace de reserva eliminado.');
    }

    public function appointments(Request $request, string $id): Response
    {
        $firmaId = $this->firmaId();
        $filters = (array) $request->query();
        $filters['booking_config_id'] = (int) $id;

        return $this->view('booking/appointments', [
            'title' => 'Citas reservadas',
            'configId' => (int) $id,
            'appointments' => $this->container->get(BookingService::class)->listForFirma($firmaId, $filters),
            'filters' => $filters,
            'csrfToken' => $this->csrf->token(),
            'currentUser' => $this->currentUser(),
        ]);
    }

    public function complete(Request $request, string $id): Response
    {
        $this->container->get(BookingService::class)->completeAppointment((int) $id, $this->firmaId());

        return $this->json(null, 'Cita marcada como completada.');
    }

    /** @param array<string, mixed> $data */
    private function validateInput(array $data): void
    {
        if (trim((string) ($data['titulo'] ?? '')) === '') {
            throw new HttpException(422, 'El título es obligatorio.');
        }
        if (!isset($data['dias_activos']) || (!is_array($data['dias_activos']) && !is_string($data['dias_activos']))) {
            throw new HttpException(422, 'Seleccione los días activos.');
        }
    }

    private function firmaId(): int
    {
        $firmaId = $this->currentFirma();
        if ($firmaId === null) {
            throw new HttpException(403, 'La operación requiere una firma activa.');
        }

        return (int) $firmaId;
    }

    private function userId(): int
    {
        $id = $this->auth->id();
        if ($id === null) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        return (int) $id;
    }
}
