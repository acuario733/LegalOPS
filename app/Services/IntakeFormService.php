<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Repositories\IntakeFormRepository;
use App\Repositories\ProspectoRepository;
use JsonException;
use Throwable;

final class IntakeFormService
{
    /** @var list<string> */
    private const FIELD_TYPES = ['texto', 'email', 'telefono', 'select', 'textarea', 'fecha', 'checkbox'];

    public function __construct(
        private readonly IntakeFormRepository $repository,
        private readonly ProspectoRepository $prospectos,
        private readonly Database $database,
        private readonly LocalMailService $mail
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $firmaId): array
    {
        return array_map(fn (array $form): array => $this->decodeForm($form), $this->repository->findForFirma($firmaId));
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function create(int $firmaId, array $data): array
    {
        $normalized = $this->normalize($data);
        $slug = $this->uniqueSlug($normalized['nombre']);
        $id = $this->repository->create($normalized + ['firma_id' => $firmaId, 'slug' => $slug]);

        return $this->find($id, $firmaId);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function update(int $id, int $firmaId, array $data): array
    {
        $current = $this->repository->findById($id, $firmaId)
            ?? throw new HttpException(404, 'El formulario no existe en la firma.');
        $normalized = $this->normalize($data, $current);
        $this->repository->update($id, $firmaId, $normalized);

        return $this->find($id, $firmaId);
    }

    public function delete(int $id, int $firmaId): void
    {
        if (!$this->repository->softDelete($id, $firmaId)) {
            throw new HttpException(404, 'El formulario no existe en la firma.');
        }
    }

    /**
     * @param array<string, mixed> $submissionData
     * @return array{mensaje_exito: string, prospecto_id: int}
     */
    public function processSubmission(
        int $formId,
        array $submissionData,
        string $ip,
        string $userAgent
    ): array {
        $form = $this->repository->findPublicById($formId)
            ?? throw new HttpException(404, 'El formulario público no existe.');
        if ((int) $form['activo'] !== 1 || ($form['firma_estado'] ?? '') !== 'activa') {
            throw new HttpException(404, 'El formulario público no está disponible.');
        }

        $fields = $this->decodeFields($form['campos']);
        $cleanData = $this->validateSubmission($fields, $submissionData);
        $contact = $this->contactData($fields, $cleanData);
        $firmaId = (int) $form['firma_id'];

        $result = $this->database->transaction(function () use (
            $formId,
            $firmaId,
            $cleanData,
            $contact,
            $fields,
            $ip,
            $userAgent
        ): array {
            $submissionId = $this->repository->createSubmission([
                'intake_form_id' => $formId,
                'firma_id' => $firmaId,
                'datos' => $this->encodeJson($cleanData),
                'ip_address' => mb_substr(trim($ip), 0, 45),
                'user_agent' => mb_substr(trim($userAgent), 0, 1000),
            ]);
            $prospectId = $this->prospectos->createFromPublicSource([
                'firma_id' => $firmaId,
                'nombre' => $contact['nombre'],
                'nombre_normalizado' => mb_strtolower($contact['nombre']),
                'email' => $contact['email'],
                'telefono' => $contact['telefono'],
                'fuente' => 'intake_form',
                'fuente_referencia' => (string) $submissionId,
                'notas' => $this->submissionSummary($fields, $cleanData),
                'tratamiento_datos_autorizado' => $this->truthy($cleanData['tratamiento_datos_autorizado'] ?? 0) ? 1 : 0,
            ]);
            if (!$this->repository->markSubmissionProcessed($submissionId, $prospectId, $firmaId)) {
                throw new HttpException(500, 'No fue posible completar el procesamiento del envío.');
            }

            return ['submission_id' => $submissionId, 'prospecto_id' => $prospectId];
        });

        $this->sendNotifications($form, $contact, (int) $result['submission_id']);

        return [
            'mensaje_exito' => trim((string) ($form['mensaje_exito'] ?? ''))
                ?: 'Gracias. Recibimos tu información.',
            'prospecto_id' => (int) $result['prospecto_id'],
        ];
    }

    /** @return list<string> */
    public function getFieldTypes(): array
    {
        return self::FIELD_TYPES;
    }

    /** @return array<string, mixed> */
    public function find(int $id, int $firmaId): array
    {
        $form = $this->repository->findById($id, $firmaId)
            ?? throw new HttpException(404, 'El formulario no existe en la firma.');

        return $this->decodeForm($form);
    }

    /** @return array<string, mixed> */
    public function findPublic(string $firmaSlug, string $formSlug): array
    {
        $form = $this->repository->findBySlug($firmaSlug, $formSlug)
            ?? throw new HttpException(404, 'El formulario público no existe.');
        if ((int) $form['activo'] !== 1 || ($form['firma_estado'] ?? '') !== 'activa') {
            throw new HttpException(404, 'El formulario público no está disponible.');
        }

        return $this->decodeForm($form);
    }

    /** @return list<array<string, mixed>> */
    public function submissions(int $formId, int $firmaId): array
    {
        $this->find($formId, $firmaId);

        return array_map(static function (array $submission): array {
            $decoded = json_decode((string) $submission['datos'], true);
            $submission['datos'] = is_array($decoded) ? $decoded : [];

            return $submission;
        }, $this->repository->findSubmissions($formId, $firmaId));
    }

    /** @param array<string, mixed> $data @param array<string, mixed>|null $current @return array<string, mixed> */
    private function normalize(array $data, ?array $current = null): array
    {
        $name = trim((string) ($data['nombre'] ?? ($current['nombre'] ?? '')));
        $title = trim((string) ($data['titulo'] ?? ($current['titulo'] ?? '')));
        $fields = $this->normalizeFields($data['campos'] ?? ($current['campos'] ?? []));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new HttpException(422, 'El nombre del formulario es obligatorio y admite máximo 100 caracteres.');
        }
        if ($title === '' || mb_strlen($title) > 200) {
            throw new HttpException(422, 'El título público es obligatorio y admite máximo 200 caracteres.');
        }

        return [
            'nombre' => $name,
            'titulo' => $title,
            'descripcion' => $this->nullableString($data['descripcion'] ?? ($current['descripcion'] ?? null), 2000),
            'campos' => $this->encodeJson($fields),
            'activo' => $this->booleanValue($data['activo'] ?? ($current['activo'] ?? 1)),
            'mensaje_exito' => $this->nullableString($data['mensaje_exito'] ?? ($current['mensaje_exito'] ?? null), 1000),
            'notificar_emails' => $this->normalizeEmails($data['notificar_emails'] ?? ($current['notificar_emails'] ?? null)),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function normalizeFields(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            throw new HttpException(422, 'La configuración de campos no es válida.');
        }

        $fields = [];
        foreach (array_values($value) as $index => $field) {
            if (!is_array($field)) {
                continue;
            }
            $type = (string) ($field['type'] ?? 'texto');
            if (!in_array($type, self::FIELD_TYPES, true)) {
                throw new HttpException(422, 'Existe un tipo de campo no permitido.');
            }
            $key = preg_replace('/[^a-z0-9_]+/', '_', mb_strtolower((string) ($field['key'] ?? 'campo_' . ($index + 1)))) ?? '';
            $key = trim($key, '_') ?: 'campo_' . ($index + 1);
            $special = in_array(($field['especial'] ?? ''), ['nombre', 'email', 'telefono'], true)
                ? (string) $field['especial']
                : null;
            $options = [];
            if ($type === 'select') {
                foreach ((array) ($field['opciones'] ?? []) as $option) {
                    $option = trim((string) $option);
                    if ($option !== '') {
                        $options[] = mb_substr($option, 0, 120);
                    }
                }
            }
            $fields[] = [
                'key' => $special ?? mb_substr($key, 0, 80),
                'type' => $type,
                'etiqueta' => mb_substr(trim((string) ($field['etiqueta'] ?? ucfirst($key))), 0, 160),
                'placeholder' => mb_substr(trim((string) ($field['placeholder'] ?? '')), 0, 200),
                'requerido' => $special === 'nombre' || $special === 'email'
                    ? true
                    : $this->truthy($field['requerido'] ?? false),
                'opciones' => array_values(array_unique($options)),
                'especial' => $special,
                'fijo' => $special !== null,
            ];
        }

        foreach ($this->specialFields() as $special => $default) {
            if (!array_filter($fields, static fn (array $field): bool => ($field['especial'] ?? null) === $special)) {
                array_unshift($fields, $default);
            }
        }

        return $fields;
    }

    /** @return array<string, array<string, mixed>> */
    private function specialFields(): array
    {
        return [
            'telefono' => ['key' => 'telefono', 'type' => 'telefono', 'etiqueta' => 'Teléfono', 'placeholder' => '', 'requerido' => false, 'opciones' => [], 'especial' => 'telefono', 'fijo' => true],
            'email' => ['key' => 'email', 'type' => 'email', 'etiqueta' => 'Correo electrónico', 'placeholder' => '', 'requerido' => true, 'opciones' => [], 'especial' => 'email', 'fijo' => true],
            'nombre' => ['key' => 'nombre', 'type' => 'texto', 'etiqueta' => 'Nombre completo', 'placeholder' => '', 'requerido' => true, 'opciones' => [], 'especial' => 'nombre', 'fijo' => true],
        ];
    }

    /** @param list<array<string, mixed>> $fields @param array<string, mixed> $data @return array<string, mixed> */
    private function validateSubmission(array $fields, array $data): array
    {
        $clean = [];
        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $value = $data[$key] ?? null;
            if (($field['requerido'] ?? false) && ($value === null || $value === '' || $value === false || $value === [])) {
                throw new HttpException(422, 'Complete el campo obligatorio: ' . $field['etiqueta'] . '.');
            }
            if ($value === null || $value === '') {
                $clean[$key] = $field['type'] === 'checkbox' ? false : null;
                continue;
            }
            if ($field['type'] === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                throw new HttpException(422, 'Ingrese un correo electrónico válido.');
            }
            if ($field['type'] === 'fecha' && !$this->validDate((string) $value)) {
                throw new HttpException(422, 'Ingrese una fecha válida en ' . $field['etiqueta'] . '.');
            }
            if ($field['type'] === 'select' && !in_array((string) $value, $field['opciones'], true)) {
                throw new HttpException(422, 'Seleccione una opción válida en ' . $field['etiqueta'] . '.');
            }
            $clean[$key] = $field['type'] === 'checkbox'
                ? $this->truthy($value)
                : mb_substr(trim((string) $value), 0, $field['type'] === 'textarea' ? 2000 : 500);
        }

        return $clean;
    }

    /** @param list<array<string, mixed>> $fields @param array<string, mixed> $data @return array{nombre: string, email: string, telefono: ?string} */
    private function contactData(array $fields, array $data): array
    {
        $keys = [];
        foreach ($fields as $field) {
            if (($field['especial'] ?? null) !== null) {
                $keys[(string) $field['especial']] = (string) $field['key'];
            }
        }
        $name = trim((string) ($data[$keys['nombre'] ?? 'nombre'] ?? ''));
        $email = strtolower(trim((string) ($data[$keys['email'] ?? 'email'] ?? '')));
        $phone = trim((string) ($data[$keys['telefono'] ?? 'telefono'] ?? ''));
        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new HttpException(422, 'Nombre y correo electrónico son obligatorios.');
        }

        return ['nombre' => mb_substr($name, 0, 180), 'email' => $email, 'telefono' => $phone === '' ? null : mb_substr($phone, 0, 60)];
    }

    /** @param array<string, mixed> $form @param array{nombre: string, email: string, telefono: ?string} $contact */
    private function sendNotifications(array $form, array $contact, int $submissionId): void
    {
        $emails = preg_split('/[\s,;]+/', (string) ($form['notificar_emails'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            try {
                $this->mail->send(
                    $email,
                    'Nuevo envío de formulario: ' . $form['nombre'],
                    sprintf(
                        "Se recibió el envío #%d.\nNombre: %s\nEmail: %s\nTeléfono: %s",
                        $submissionId,
                        $contact['nombre'],
                        $contact['email'],
                        $contact['telefono'] ?? 'No informado'
                    )
                );
            } catch (Throwable $exception) {
                error_log('[IntakeFormService] No fue posible encolar notificación: ' . $exception->getMessage());
            }
        }
    }

    /** @param list<array<string, mixed>> $fields @param array<string, mixed> $data */
    private function submissionSummary(array $fields, array $data): string
    {
        $lines = [];
        foreach ($fields as $field) {
            if (($field['especial'] ?? null) !== null) {
                continue;
            }
            $value = $data[$field['key']] ?? null;
            if ($value !== null && $value !== '' && $value !== false) {
                $lines[] = $field['etiqueta'] . ': ' . (is_bool($value) ? 'Sí' : (string) $value);
            }
        }

        return mb_substr(implode("\n", $lines), 0, 1000);
    }

    private function uniqueSlug(string $name): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        while ($this->repository->slugExists($slug)) {
            $slug = mb_substr($base, 0, 91) . '-' . strtolower(bin2hex(random_bytes(4)));
        }

        return $slug;
    }

    /** @param array<string, mixed> $form @return array<string, mixed> */
    private function decodeForm(array $form): array
    {
        $form['campos'] = $this->decodeFields($form['campos'] ?? []);

        return $form;
    }

    /** @return list<array<string, mixed>> */
    private function decodeFields(mixed $fields): array
    {
        if (is_string($fields)) {
            $decoded = json_decode($fields, true);
            $fields = is_array($decoded) ? $decoded : [];
        }

        return is_array($fields) ? array_values(array_filter($fields, 'is_array')) : [];
    }

    private function normalizeEmails(mixed $value): ?string
    {
        $emails = preg_split('/[\s,;]+/', trim((string) ($value ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new HttpException(422, 'Existe un correo de notificación no válido.');
            }
        }

        return $emails === [] ? null : implode(',', array_values(array_unique(array_map('strtolower', $emails))));
    }

    private function slugify(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii === false ? $value : $ascii)) ?? '';

        return trim($slug, '-') ?: 'formulario';
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function booleanValue(mixed $value): int
    {
        return $this->truthy($value) ? 1 : 0;
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'si', 'yes'], true);
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function encodeJson(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            throw new HttpException(422, 'No fue posible serializar la información del formulario.');
        }
    }
}
