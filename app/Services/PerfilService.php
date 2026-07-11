<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\PerfilRepository;
use App\Validators\PerfilValidator;
use PDOException;

final class PerfilService
{
    /** @var list<string> */
    private const PERSONAL_INPUT = [
        'nombres',
        'apellidos',
        'tipo_documento_id',
        'numero_documento',
        'telefono',
    ];

    /** @var list<string> */
    private const REQUEST_METADATA = ['_method', '_token'];

    private const PASSWORD_MIN_LENGTH = 12;

    private const PASSWORD_INPUT = ['password_actual', 'password_nuevo', 'password_confirmacion'];

    /** @var list<string> */
    private const PROFESSIONAL_INPUT = [
        'es_abogado',
        'tiene_tarjeta_profesional',
        'numero_tarjeta_profesional',
    ];

    /** @var array<string, string> */
    private const PHOTO_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const PHOTO_MAX_BYTES = 5242880;

    public function __construct(
        private readonly PerfilRepository $repository,
        private readonly PerfilValidator $validator,
        private readonly Database $database,
        private readonly AuditoriaService $audit,
        private readonly Auth $auth,
        private readonly SensitiveDataService $sensitive
    ) {
    }

    /** @return array<string, mixed> */
    public function own(): array
    {
        $userId = $this->currentUserId();
        $profile = $this->repository->findOwn($userId)
            ?? throw new HttpException(404, 'No fue posible encontrar el perfil autenticado.');
        $profile['roles'] = $this->repository->roles(
            $userId,
            $profile['firma_id'] === null ? null : (int) $profile['firma_id']
        );
        $profile['numero_documento_enmascarado'] = $this->sensitive->maskDocument(
            (string) ($profile['numero_documento_normalizado'] ?? $profile['numero_documento'] ?? '')
        );
        $profile['numero_tarjeta_profesional_enmascarado'] = $this->sensitive->maskProfessionalCard(
            (string) ($profile['numero_tarjeta_profesional_normalizado'] ?? $profile['numero_tarjeta_profesional'] ?? '')
        );
        $profile['foto_perfil_url'] = trim((string) ($profile['foto_perfil_path'] ?? '')) === '' ? null : '/mi-perfil/foto';

        return $profile;
    }

    /** @return list<array{id: int|string, codigo: string, etiqueta: string}> */
    public function documentTypes(): array
    {
        return $this->repository->documentTypes();
    }

    /** @param array<string, mixed> $input */
    public function updatePersonal(array $input, Request $request): void
    {
        $userId = $this->currentUserId();
        $this->assertOnlyPersonalInput($input);
        $before = $this->own();
        $normalized = $this->normalize($input);

        if (!$this->validator->validatePersonal($normalized)) {
            throw new HttpException(422, 'Revise los datos personales.', $this->validator->errors());
        }

        $documentTypeId = (int) $normalized['tipo_documento_id'];
        if (!$this->repository->documentTypeIsAllowed($documentTypeId)) {
            throw new HttpException(422, 'El tipo de documento no pertenece al catálogo global autorizado.', [
                'tipo_documento_id' => ['Seleccione un tipo de documento válido.'],
            ]);
        }

        $document = $this->normalizeDocument((string) $normalized['numero_documento']);
        if (mb_strlen($document) < 3) {
            throw new HttpException(422, 'El número de documento no es válido.', [
                'numero_documento' => ['El documento debe contener al menos tres letras o números.'],
            ]);
        }

        $firmaId = $before['firma_id'] === null ? null : (int) $before['firma_id'];
        if ($this->repository->documentExists($firmaId, $documentTypeId, $document, $userId)) {
            throw new HttpException(409, 'El documento ya está registrado para otro usuario de la firma.', [
                'numero_documento' => ['El documento ya existe en esta firma.'],
            ]);
        }

        $record = [
            'nombre' => trim($normalized['nombres'] . ' ' . $normalized['apellidos']),
            'nombres' => $normalized['nombres'],
            'apellidos' => $normalized['apellidos'],
            'tipo_documento_id' => $documentTypeId,
            'numero_documento' => trim((string) $normalized['numero_documento']),
            'numero_documento_normalizado' => $document,
            'telefono' => $normalized['telefono'] === '' ? null : $normalized['telefono'],
        ];
        $changedFields = $this->changedFields($before, $record);
        $photo = $this->preparePhotoUpload($request->file('foto_perfil'), $before, $userId);
        if ($photo !== null) {
            $changedFields[] = 'foto_perfil';
        }

        try {
            $this->database->transaction(function () use ($userId, $record, $changedFields, $request, $firmaId, $before, $photo): void {
                $this->repository->updatePersonal($userId, $record);
                if ($photo !== null) {
                    $this->repository->updatePhotoPath($userId, $photo['relative_path']);
                }
                $this->recordSensitiveChange(
                    $firmaId,
                    $userId,
                    $userId,
                    'numero_documento',
                    (string) ($before['numero_documento_normalizado'] ?? ''),
                    (string) $record['numero_documento_normalizado'],
                    'mi_perfil',
                    $request
                );
                $this->audit->record(
                    'PERFIL_PERSONAL_MODIFICADO',
                    'perfil',
                    'usuario',
                    $userId,
                    ['campos' => $changedFields, 'origen' => 'mi_perfil'],
                    $request,
                    $firmaId
                );
                if ($photo !== null) {
                    $this->audit->record(
                        'PERFIL_FOTO_MODIFICADA',
                        'perfil',
                        'usuario',
                        $userId,
                        ['origen' => 'mi_perfil'],
                        $request,
                        $firmaId
                    );
                }
            });
        } catch (PDOException $exception) {
            if ($photo !== null && is_file($photo['absolute_path'])) {
                @unlink($photo['absolute_path']);
            }
            if ($this->isDuplicateDocumentException($exception)) {
                throw new HttpException(409, 'El documento ya está registrado para otro usuario de la firma.', [
                    'numero_documento' => ['El documento ya existe en esta firma.'],
                ]);
            }

            throw $exception;
        }

        if ($photo !== null) {
            $this->deletePreviousPhoto((string) ($before['foto_perfil_path'] ?? ''), $photo['absolute_path']);
        }

        $this->auth->update(['name' => $record['nombre']]);
    }

    /** @param array<string, mixed> $input */
    public function updateProfessional(array $input, Request $request): void
    {
        $userId = $this->currentUserId();
        $this->assertOnlyProfessionalInput($input);
        $before = $this->own();
        $firmaId = $before['firma_id'] === null ? null : (int) $before['firma_id'];
        if ($firmaId === null) {
            throw new HttpException(403, 'La gestión profesional requiere una firma activa.');
        }

        $normalized = $this->normalizeProfessional($input);
        if (!$this->validator->validateProfessional($normalized)) {
            throw new HttpException(422, 'Revise la información profesional.', $this->validator->errors());
        }

        $hasCard = (int) $normalized['tiene_tarjeta_profesional'] === 1;
        $card = $hasCard ? trim((string) $normalized['numero_tarjeta_profesional']) : null;
        $normalizedCard = $hasCard ? $this->normalizeDocument((string) $card) : null;
        $record = [
            'es_abogado' => (int) $normalized['es_abogado'],
            'tiene_tarjeta_profesional' => (int) $normalized['tiene_tarjeta_profesional'],
            'numero_tarjeta_profesional' => $card,
            'numero_tarjeta_profesional_normalizado' => $normalizedCard,
            'tarjeta_profesional_verificacion_estado' => $hasCard ? 'pendiente' : null,
        ];
        $changedFields = $this->changedProfessionalFields($before, $record);

        $this->database->transaction(function () use ($userId, $firmaId, $record, $before, $request, $changedFields): void {
            $this->repository->updateProfessional($userId, $record);
            $this->recordSensitiveChange(
                $firmaId,
                $userId,
                $userId,
                'numero_tarjeta_profesional',
                (string) ($before['numero_tarjeta_profesional_normalizado'] ?? ''),
                (string) ($record['numero_tarjeta_profesional_normalizado'] ?? ''),
                'mi_perfil',
                $request
            );
            $this->audit->record(
                'PERFIL_PROFESIONAL_MODIFICADO',
                'perfil',
                'usuario',
                $userId,
                ['campos' => $changedFields, 'estado_verificacion' => $record['tarjeta_profesional_verificacion_estado'], 'origen' => 'mi_perfil'],
                $request,
                $firmaId
            );
        });
    }

    /** @param array<string, mixed> $input */
    public function changePassword(array $input, Request $request): void
    {
        $userId = $this->currentUserId();
        $this->assertOnlyPasswordInput($input);

        $current = trim((string) ($input['password_actual'] ?? ''));
        $new = (string) ($input['password_nuevo'] ?? '');
        $confirm = (string) ($input['password_confirmacion'] ?? '');

        if ($current === '') {
            throw new HttpException(422, 'Debe ingresar su contraseña actual.', [
                'password_actual' => ['La contraseña actual es obligatoria.'],
            ]);
        }

        $this->validateNewPassword($new, $confirm);

        $hash = $this->repository->findPasswordHash($userId);
        if ($hash === null || !password_verify($current, $hash)) {
            throw new HttpException(422, 'La contraseña actual no es correcta.', [
                'password_actual' => ['La contraseña actual ingresada no coincide.'],
            ]);
        }

        if (password_verify($new, $hash)) {
            throw new HttpException(422, 'La nueva contraseña debe ser diferente a la actual.', [
                'password_nuevo' => ['Elija una contraseña distinta a la que usa actualmente.'],
            ]);
        }

        $profile = $this->own();
        $firmaId = $profile['firma_id'] === null ? null : (int) $profile['firma_id'];

        $this->repository->updatePasswordHash($userId, password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]));

        $this->audit->record(
            'CONTRASENA_CAMBIADA',
            'perfil',
            'usuario',
            $userId,
            ['origen' => 'mi_perfil'],
            $request,
            $firmaId,
            'warning'
        );
    }

    /** @return array{path: string, mime: string} */
    public function ownPhoto(): array
    {
        $profile = $this->own();
        $relative = trim((string) ($profile['foto_perfil_path'] ?? ''));
        if ($relative === '') {
            throw new HttpException(404, 'No hay fotografía registrada.');
        }

        $path = $this->absoluteStoragePath($relative);
        if (!is_file($path) || !is_readable($path)) {
            throw new HttpException(404, 'La fotografía no está disponible.');
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';

        return ['path' => $path, 'mime' => $mime];
    }

    /** @param array<string, mixed> $input */
    private function assertOnlyPersonalInput(array $input): void
    {
        $allowed = array_merge(self::PERSONAL_INPUT, self::REQUEST_METADATA);
        $unexpected = array_values(array_diff(array_map('strval', array_keys($input)), $allowed));
        if ($unexpected !== []) {
            throw new HttpException(422, 'Mi perfil solo permite modificar información personal autorizada.', [
                'campos' => ['Campos no permitidos: ' . implode(', ', $unexpected) . '.'],
            ]);
        }
    }

    /** @param array<string, mixed> $input */
    private function assertOnlyPasswordInput(array $input): void
    {
        $allowed = array_merge(self::PASSWORD_INPUT, self::REQUEST_METADATA);
        $unexpected = array_values(array_diff(array_map('strval', array_keys($input)), $allowed));
        if ($unexpected !== []) {
            throw new HttpException(422, 'Campos no permitidos en cambio de contraseña.', [
                'campos' => ['Campos no permitidos: ' . implode(', ', $unexpected) . '.'],
            ]);
        }
    }

    private function validateNewPassword(string $new, string $confirm): void
    {
        if (mb_strlen($new) < self::PASSWORD_MIN_LENGTH) {
            throw new HttpException(422, 'La contraseña nueva no cumple los requisitos de seguridad.', [
                'password_nuevo' => ['La contraseña debe tener al menos ' . self::PASSWORD_MIN_LENGTH . ' caracteres.'],
            ]);
        }
        if (!preg_match('/[A-Z]/', $new)) {
            throw new HttpException(422, 'La contraseña nueva no cumple los requisitos de seguridad.', [
                'password_nuevo' => ['Debe incluir al menos una letra mayúscula.'],
            ]);
        }
        if (!preg_match('/[0-9]/', $new)) {
            throw new HttpException(422, 'La contraseña nueva no cumple los requisitos de seguridad.', [
                'password_nuevo' => ['Debe incluir al menos un número.'],
            ]);
        }
        if (!preg_match('/[^A-Za-z0-9]/', $new)) {
            throw new HttpException(422, 'La contraseña nueva no cumple los requisitos de seguridad.', [
                'password_nuevo' => ['Debe incluir al menos un carácter especial.'],
            ]);
        }
        if ($new !== $confirm) {
            throw new HttpException(422, 'Las contraseñas no coinciden.', [
                'password_confirmacion' => ['La confirmación no coincide con la nueva contraseña.'],
            ]);
        }
    }

    /** @param array<string, mixed> $input */
    private function assertOnlyProfessionalInput(array $input): void
    {
        $allowed = array_merge(self::PROFESSIONAL_INPUT, self::REQUEST_METADATA);
        $unexpected = array_values(array_diff(array_map('strval', array_keys($input)), $allowed));
        if ($unexpected !== []) {
            throw new HttpException(422, 'Mi perfil solo permite modificar información profesional autorizada.', [
                'campos' => ['Campos no permitidos: ' . implode(', ', $unexpected) . '.'],
            ]);
        }
    }

    /** @param array<string, mixed> $input @return array<string, string|int> */
    private function normalize(array $input): array
    {
        return [
            'nombres' => $this->normalizeName((string) ($input['nombres'] ?? '')),
            'apellidos' => $this->normalizeName((string) ($input['apellidos'] ?? '')),
            'tipo_documento_id' => (string) ($input['tipo_documento_id'] ?? ''),
            'numero_documento' => trim((string) ($input['numero_documento'] ?? '')),
            'telefono' => preg_replace('/\s+/', ' ', trim((string) ($input['telefono'] ?? ''))) ?? '',
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function normalizeProfessional(array $input): array
    {
        return [
            'es_abogado' => $this->normalizeBoolean($input['es_abogado'] ?? 0),
            'tiene_tarjeta_profesional' => $this->normalizeBoolean($input['tiene_tarjeta_profesional'] ?? 0),
            'numero_tarjeta_profesional' => preg_replace('/\s+/', ' ', trim((string) ($input['numero_tarjeta_profesional'] ?? ''))) ?? '',
        ];
    }

    private function normalizeBoolean(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return in_array((string) $value, ['1', 'true', 'on', 'si', 'sí'], true) ? 1 : 0;
    }

    private function normalizeName(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    }

    private function normalizeDocument(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]/u', '', mb_strtoupper(trim($value))) ?? '';
    }

    /** @param mixed $file @param array<string, mixed> $profile @return array{relative_path: string, absolute_path: string}|null */
    private function preparePhotoUpload(mixed $file, array $profile, int $userId): ?array
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new HttpException(422, 'No fue posible recibir la fotografía.');
        }
        if ((int) ($file['size'] ?? 0) > self::PHOTO_MAX_BYTES) {
            throw new HttpException(422, 'La fotografía no puede superar 5 MB.', [
                'foto_perfil' => ['Seleccione una imagen de máximo 5 MB.'],
            ]);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new HttpException(422, 'La fotografía cargada no es válida.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if (!array_key_exists($mime, self::PHOTO_MIMES)) {
            throw new HttpException(422, 'La fotografía debe ser JPG, PNG o WebP.', [
                'foto_perfil' => ['Formato permitido: JPG, PNG o WebP.'],
            ]);
        }

        $firmaSegment = $profile['firma_id'] === null ? 'global' : 'firmas/' . (int) $profile['firma_id'];
        $relativeDirectory = 'profile_photos/' . $firmaSegment . '/usuarios/' . $userId;
        $absoluteDirectory = $this->absoluteStoragePath($relativeDirectory);
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            throw new HttpException(500, 'No fue posible preparar el almacenamiento privado de la fotografía.');
        }

        $relativePath = $relativeDirectory . '/' . bin2hex(random_bytes(16)) . '.' . self::PHOTO_MIMES[$mime];
        $absolutePath = $this->absoluteStoragePath($relativePath);
        if (!move_uploaded_file($tmp, $absolutePath)) {
            throw new HttpException(500, 'No fue posible guardar la fotografía.');
        }

        return ['relative_path' => $relativePath, 'absolute_path' => $absolutePath];
    }

    private function absoluteStoragePath(string $relativePath): string
    {
        $base = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage';
        $clean = str_replace(['\\', "\0"], ['/', ''], ltrim($relativePath, '/\\'));
        if (str_contains($clean, '..')) {
            throw new HttpException(400, 'Ruta de archivo no válida.');
        }

        return $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean);
    }

    private function deletePreviousPhoto(string $relativePath, string $newAbsolutePath): void
    {
        if (trim($relativePath) === '') {
            return;
        }
        $oldPath = $this->absoluteStoragePath($relativePath);
        if ($oldPath !== $newAbsolutePath && is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    private function currentUserId(): int
    {
        $userId = $this->auth->id();
        if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
            throw new HttpException(401, 'Debe iniciar sesión para consultar su perfil.');
        }

        return (int) $userId;
    }

    private function recordSensitiveChange(
        ?int $firmaId,
        int $affectedUserId,
        ?int $actorUserId,
        string $field,
        string $oldValue,
        string $newValue,
        string $origin,
        Request $request
    ): void {
        if ($firmaId === null || trim($oldValue) === trim($newValue)) {
            return;
        }

        $masker = $field === 'numero_tarjeta_profesional'
            ? $this->sensitive->maskProfessionalCard(...)
            : $this->sensitive->maskDocument(...);

        $this->repository->recordSensitiveChange([
            'firma_id' => $firmaId,
            'usuario_afectado_id' => $affectedUserId,
            'usuario_actor_id' => $actorUserId,
            'campo' => $field,
            'valor_anterior_enmascarado' => $masker($oldValue),
            'valor_nuevo_enmascarado' => $masker($newValue),
            'valor_anterior_hash' => $this->sensitive->hash($oldValue),
            'valor_nuevo_hash' => $this->sensitive->hash($newValue),
            'origen' => $origin,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr($request->userAgent(), 0, 255),
        ]);
    }

    private function isDuplicateDocumentException(PDOException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'uq_usuarios_documento_firma') || str_contains($message, 'Duplicate entry');
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after @return list<string> */
    private function changedFields(array $before, array $after): array
    {
        $changed = [];
        foreach (['nombres', 'apellidos', 'tipo_documento_id', 'numero_documento', 'telefono'] as $field) {
            $old = $field === 'numero_documento'
                ? $this->normalizeDocument((string) ($before[$field] ?? ''))
                : trim((string) ($before[$field] ?? ''));
            $new = $field === 'numero_documento'
                ? (string) $after['numero_documento_normalizado']
                : trim((string) ($after[$field] ?? ''));
            if ($old !== $new) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /** @param array<string, mixed> $before @param array<string, mixed> $after @return list<string> */
    private function changedProfessionalFields(array $before, array $after): array
    {
        $changed = [];
        foreach (['es_abogado', 'tiene_tarjeta_profesional', 'numero_tarjeta_profesional'] as $field) {
            $old = $field === 'numero_tarjeta_profesional'
                ? (string) ($before['numero_tarjeta_profesional_normalizado'] ?? '')
                : (string) (int) ($before[$field] ?? 0);
            $new = $field === 'numero_tarjeta_profesional'
                ? (string) ($after['numero_tarjeta_profesional_normalizado'] ?? '')
                : (string) (int) ($after[$field] ?? 0);
            if ($old !== $new) {
                $changed[] = $field;
            }
        }

        if ($changed !== [] && in_array('numero_tarjeta_profesional', $changed, true)) {
            $changed[] = 'tarjeta_profesional_verificacion_estado';
        }

        return array_values(array_unique($changed));
    }
}
