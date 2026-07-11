<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\DocumentTemplateRepository;

final class DocumentTemplateService
{
    public function __construct(
        private readonly DocumentTemplateRepository $repository,
        private readonly TemplateVariableService $variables,
        private readonly DocumentoService $documentos,
        private readonly DocxService $docx
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $firmaId, ?string $categoria): array
    {
        return array_map($this->decodeRow(...), $this->repository->findForFirma($firmaId, $categoria));
    }

    /** @return list<string> */
    public function categorias(int $firmaId): array
    {
        return $this->repository->getCategorias($firmaId);
    }

    /** @return array<string, mixed> */
    public function find(int $id, int $firmaId): array
    {
        $template = $this->repository->findById($id, $firmaId) ?? throw new HttpException(404, 'La plantilla no existe en la firma.');

        return $this->decodeRow($template);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function create(int $firmaId, array $data): array
    {
        $payload = $this->normalize($firmaId, $data);
        $id = $this->repository->create($payload);

        return $this->find($id, $firmaId);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function update(int $id, int $firmaId, array $data): array
    {
        $this->find($id, $firmaId);
        $payload = $this->normalize($firmaId, $data);
        $this->repository->update($id, $firmaId, $payload);

        return $this->find($id, $firmaId);
    }

    public function delete(int $id, int $firmaId): void
    {
        if (!$this->repository->softDelete($id, $firmaId)) {
            throw new HttpException(404, 'La plantilla no existe en la firma.');
        }
    }

    /** @param array<string, mixed> $customVars @return array<string, mixed> */
    public function generate(int $templateId, int $firmaId, int $casoId, int $usuarioId, array $customVars, Request $request): array
    {
        $template = $this->find($templateId, $firmaId);
        if ((int) ($template['activo'] ?? 0) !== 1) {
            throw new HttpException(422, 'La plantilla no esta activa.');
        }

        $context = $this->variables->buildContext($casoId, $firmaId, $usuarioId, $customVars);
        $content = $this->variables->resolve((string) $template['contenido'], $context);
        $name = (string) $template['nombre'] . ' — ' . date('Y-m-d');
        $plainText = html_entity_decode(trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n\n"], $content))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $documentId = $this->documentos->createGeneratedDocx($firmaId, $casoId, $name, $this->docx->fromText($plainText), $request);

        return [
            'documento_id' => $documentId,
            'nombre' => $name,
            'url_descarga' => '/documentos/' . $documentId . '/descargar',
            'contenido' => $content,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalize(int $firmaId, array $data): array
    {
        $name = trim((string) ($data['nombre'] ?? ''));
        if ($name === '') {
            throw new HttpException(422, 'El nombre de la plantilla es obligatorio.');
        }
        $content = (string) ($data['contenido'] ?? '');
        if (trim(strip_tags($content)) === '' && !str_contains($content, '{{')) {
            throw new HttpException(422, 'El contenido de la plantilla es obligatorio.');
        }
        $variables = $this->variables->extractFromContent($content);

        return [
            'firma_id' => $firmaId,
            'nombre' => mb_substr($name, 0, 200),
            'descripcion' => $this->nullableString($data['descripcion'] ?? null, 2000),
            'categoria' => $this->nullableString($data['categoria_nueva'] ?? ($data['categoria'] ?? null), 100),
            'contenido' => $content,
            'variables_usadas' => json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'activo' => $this->truthy($data['activo'] ?? 1) ? 1 : 0,
        ];
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'on', 'si', 'yes'], true);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function decodeRow(array $row): array
    {
        if (is_string($row['variables_usadas'] ?? null)) {
            $decoded = json_decode((string) $row['variables_usadas'], true);
            $row['variables_usadas'] = is_array($decoded) ? $decoded : [];
        }

        return $row;
    }
}
