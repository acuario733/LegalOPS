<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\CasoRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\FirmaRepository;
use App\Repositories\UsuarioRepository;
use IntlDateFormatter;

final class TemplateVariableService
{
    /** @var array<string, array<string, string>> */
    public const AVAILABLE_VARIABLES = [
        'caso' => [
            'numero' => 'ID interno del caso',
            'titulo' => 'Titulo del caso',
            'tipo' => 'Tipo de proceso',
            'estado' => 'Estado del caso',
            'fecha_inicio' => 'Fecha de apertura',
            'descripcion' => 'Descripcion del caso',
        ],
        'cliente' => [
            'nombre' => 'Nombre o razon social',
            'documento' => 'Documento de identidad',
            'email' => 'Correo electronico',
            'telefono' => 'Telefono',
            'direccion' => 'Direccion',
        ],
        'firma' => [
            'nombre' => 'Nombre de la firma',
            'rfc' => 'Identificacion fiscal',
            'telefono' => 'Telefono de la firma',
            'email' => 'Correo de la firma',
            'direccion' => 'Direccion de la firma',
        ],
        'abogado' => [
            'nombre' => 'Nombre del abogado',
            'cargo' => 'Cargo',
            'email' => 'Correo del abogado',
        ],
        'fecha' => [
            'hoy' => 'Fecha actual corta',
            'hoy_largo' => 'Fecha actual larga',
            'anio' => 'Anio actual',
        ],
        'custom' => [
            'campo_1' => 'Campo personalizado 1',
            'campo_2' => 'Campo personalizado 2',
            'campo_3' => 'Campo personalizado 3',
            'campo_4' => 'Campo personalizado 4',
            'campo_5' => 'Campo personalizado 5',
        ],
    ];

    public function __construct(
        private readonly CasoRepository $casos,
        private readonly ClienteRepository $clientes,
        private readonly FirmaRepository $firmas,
        private readonly UsuarioRepository $usuarios
    ) {
    }

    /** @return array<string, array<string, string>> */
    public function getAvailableVariables(): array
    {
        return self::AVAILABLE_VARIABLES;
    }

    /** @return list<string> */
    public function extractFromContent(string $html): array
    {
        preg_match_all('/{{\s*([a-z][a-z0-9_]*\.[a-z][a-z0-9_]*)\s*}}/i', $html, $matches);
        $variables = array_map(static fn (string $value): string => strtolower(trim($value)), $matches[1] ?? []);

        return array_values(array_unique($variables));
    }

    /** @param array<string, array<string, mixed>> $context */
    public function resolve(string $html, array $context): string
    {
        return (string) preg_replace_callback(
            '/{{\s*([a-z][a-z0-9_]*)\.([a-z][a-z0-9_]*)\s*}}/i',
            static function (array $matches) use ($context): string {
                $group = strtolower((string) $matches[1]);
                $key = strtolower((string) $matches[2]);
                if (!array_key_exists($group, $context) || !array_key_exists($key, $context[$group])) {
                    return (string) $matches[0];
                }

                return htmlspecialchars((string) $context[$group][$key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            },
            $html
        );
    }

    /** @param array<string, mixed> $customVars @return array<string, array<string, mixed>> */
    public function buildContext(int $casoId, int $firmaId, int $usuarioId, array $customVars): array
    {
        $case = $this->casos->findForFirma($firmaId, $casoId) ?? throw new HttpException(404, 'El caso no existe en la firma.');
        $client = $this->clientes->findForFirma($firmaId, (int) $case['cliente_id']) ?? [];
        $firm = $this->firmas->find($firmaId) ?? [];
        $lawyer = $this->usuarios->findForFirma($firmaId, $usuarioId) ?? [];

        return [
            'caso' => [
                'numero' => (string) $case['id'],
                'titulo' => (string) ($case['titulo'] ?? ''),
                'tipo' => (string) ($case['tipo_proceso'] ?? ''),
                'estado' => (string) ($case['estado'] ?? ''),
                'fecha_inicio' => (string) ($case['fecha_apertura'] ?? ''),
                'descripcion' => (string) ($case['descripcion'] ?? ''),
            ],
            'cliente' => [
                'nombre' => (string) ($client['nombre_razon_social'] ?? ''),
                'documento' => trim((string) ($client['tipo_documento'] ?? '') . ' ' . (string) ($client['numero_documento'] ?? '')),
                'email' => (string) ($client['email'] ?? ''),
                'telefono' => (string) ($client['telefono'] ?? ''),
                'direccion' => (string) ($client['direccion'] ?? ''),
            ],
            'firma' => [
                'nombre' => (string) ($firm['nombre'] ?? ''),
                'rfc' => (string) ($firm['rfc'] ?? ''),
                'telefono' => (string) ($firm['telefono'] ?? ''),
                'email' => (string) ($firm['email'] ?? ''),
                'direccion' => (string) ($firm['direccion'] ?? ''),
            ],
            'abogado' => [
                'nombre' => (string) ($lawyer['nombre'] ?? ''),
                'cargo' => (string) ($lawyer['cargo'] ?? ''),
                'email' => (string) ($lawyer['email'] ?? ''),
            ],
            'fecha' => $this->dateVariables(),
            'custom' => $this->customVariables($customVars),
        ];
    }

    /** @return array<string, string> */
    private function dateVariables(): array
    {
        $timestamp = time();
        $long = null;
        if (class_exists(IntlDateFormatter::class)) {
            $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
            $long = $formatter->format($timestamp);
        }

        return [
            'hoy' => date('d/m/Y', $timestamp),
            'hoy_largo' => is_string($long) && $long !== '' ? $long : $this->spanishDate($timestamp),
            'anio' => date('Y', $timestamp),
        ];
    }

    private function spanishDate(int $timestamp): string
    {
        $months = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return date('j', $timestamp) . ' de ' . $months[(int) date('n', $timestamp)] . ' de ' . date('Y', $timestamp);
    }

    /** @param array<string, mixed> $customVars @return array<string, string> */
    private function customVariables(array $customVars): array
    {
        $values = [];
        for ($i = 1; $i <= 5; $i++) {
            $key = 'campo_' . $i;
            $values[$key] = (string) ($customVars[$key] ?? '');
        }

        return $values;
    }
}
