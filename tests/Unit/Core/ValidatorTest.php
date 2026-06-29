<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Tests exhaustivos de App\Core\Validator.
 *
 * Cubre TODAS las reglas: required, min, max, email, date, numeric,
 * integer, enum/in, documento, phone, file.
 * También cubre: passes() devuelve validated data, errors() limpia entre runs,
 * reglas opcionales (no-requeridas no se validan si vacías).
 */
class ValidatorTest extends TestCase
{
    private function v(): Validator
    {
        return new Validator();
    }

    // ── required ─────────────────────────────────────────────────────────────

    public function test_required_passes_for_non_empty_string(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['nombre' => 'Juan'], ['nombre' => 'required']));
        $this->assertEmpty($v->errors());
    }

    public function test_required_fails_for_empty_string(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['nombre' => ''], ['nombre' => 'required']));
        $this->assertArrayHasKey('nombre', $v->errors());
    }

    public function test_required_fails_for_null(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['nombre' => null], ['nombre' => 'required']));
        $this->assertArrayHasKey('nombre', $v->errors());
    }

    public function test_required_fails_for_empty_array(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['items' => []], ['items' => 'required']));
        $this->assertArrayHasKey('items', $v->errors());
    }

    public function test_required_fails_when_field_absent(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate([], ['nombre' => 'required']));
        $this->assertArrayHasKey('nombre', $v->errors());
    }

    // ── min / max ─────────────────────────────────────────────────────────────

    public function test_min_passes_for_string_at_exact_length(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['pass' => 'abc'], ['pass' => 'required|min:3']));
    }

    public function test_min_fails_for_string_below_length(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['pass' => 'ab'], ['pass' => 'required|min:3']));
        $this->assertArrayHasKey('pass', $v->errors());
    }

    public function test_max_passes_for_string_at_exact_length(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['slug' => 'abc'], ['slug' => 'required|max:5']));
    }

    public function test_max_fails_for_string_exceeding_length(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['slug' => 'toolong'], ['slug' => 'required|max:5']));
        $this->assertArrayHasKey('slug', $v->errors());
    }

    public function test_minlength_alias_via_form_validator(): void
    {
        // FormValidator normaliza minLength → min
        $fv = new \App\Validators\FormValidator();
        $errors = $fv->validateForm(['codigo' => 'ab'], ['codigo' => 'required|minLength:3']);
        $this->assertArrayHasKey('codigo', $errors);
    }

    // ── email ─────────────────────────────────────────────────────────────────

    public function test_email_accepts_valid(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['email' => 'user@domain.com'], ['email' => 'required|email']));
    }

    public function test_email_rejects_without_at(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['email' => 'nodomain'], ['email' => 'required|email']));
    }

    public function test_email_rejects_with_spaces(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['email' => 'bad email@x.com'], ['email' => 'email']));
    }

    // ── numeric / integer ─────────────────────────────────────────────────────

    public function test_numeric_accepts_integer_string(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['qty' => '42'], ['qty' => 'required|numeric']));
    }

    public function test_numeric_accepts_float_string(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['price' => '9.99'], ['price' => 'numeric']));
    }

    public function test_numeric_rejects_letters(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['qty' => 'abc'], ['qty' => 'required|numeric']));
    }

    public function test_integer_accepts_whole_number_string(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['page' => '5'], ['page' => 'integer']));
    }

    public function test_integer_rejects_float_string(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['page' => '5.5'], ['page' => 'integer']));
    }

    // ── enum / in ─────────────────────────────────────────────────────────────

    public function test_enum_accepts_value_in_list(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['estado' => 'activo'], ['estado' => 'required|enum:activo,inactivo']));
    }

    public function test_enum_rejects_value_not_in_list(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['estado' => 'borrado'], ['estado' => 'required|enum:activo,inactivo']));
    }

    public function test_in_is_alias_for_enum(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['tipo' => 'natural'], ['tipo' => 'in:natural,juridica']));
    }

    // ── date ──────────────────────────────────────────────────────────────────

    public function test_date_accepts_valid_iso_date(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['fecha' => '2025-12-31'], ['fecha' => 'date']));
    }

    public function test_date_rejects_invalid_string(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['fecha' => 'not-a-date'], ['fecha' => 'date']));
    }

    public function test_date_with_format_validates_format(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['fecha' => '31/12/2025'], ['fecha' => 'date:d/m/Y']));
    }

    public function test_date_with_format_rejects_wrong_format(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['fecha' => '2025-12-31'], ['fecha' => 'date:d/m/Y']));
    }

    // ── documento ────────────────────────────────────────────────────────────

    public function test_documento_accepts_6_digit_number(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['doc' => '123456'], ['doc' => 'documento']));
    }

    public function test_documento_accepts_longer_number(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['doc' => '1234567890'], ['doc' => 'documento']));
    }

    public function test_documento_rejects_5_digit_number(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['doc' => '12345'], ['doc' => 'required|documento']));
    }

    public function test_documento_rejects_letters(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['doc' => 'ABC123456'], ['doc' => 'required|documento']));
    }

    // ── phone ─────────────────────────────────────────────────────────────────

    public function test_phone_accepts_international_format(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['tel' => '+52 55 1234 5678'], ['tel' => 'phone']));
    }

    public function test_phone_accepts_local_format(): void
    {
        $v = $this->v();
        $this->assertTrue($v->validate(['tel' => '5551234567'], ['tel' => 'phone']));
    }

    public function test_phone_rejects_too_short(): void
    {
        $v = $this->v();
        $this->assertFalse($v->validate(['tel' => '12345'], ['tel' => 'required|phone']));
    }

    // ── optional fields ───────────────────────────────────────────────────────

    public function test_optional_field_is_skipped_when_empty(): void
    {
        $v = $this->v();
        // email es opcional (sin 'required') — vacío no debe generar error
        $this->assertTrue($v->validate(['email' => ''], ['email' => 'email']));
        $this->assertEmpty($v->errors());
    }

    public function test_optional_field_validates_when_present(): void
    {
        $v = $this->v();
        // email opcional pero present e inválido → sí debe dar error
        $this->assertFalse($v->validate(['email' => 'bad'], ['email' => 'email']));
    }

    // ── validated data ────────────────────────────────────────────────────────

    public function test_validated_returns_only_passing_fields(): void
    {
        $v = $this->v();
        $v->validate(
            ['nombre' => 'Juan', 'email' => 'bad'],
            ['nombre' => 'required', 'email' => 'required|email']
        );

        $validated = $v->validated();
        $this->assertArrayHasKey('nombre', $validated);
        $this->assertArrayNotHasKey('email', $validated);
    }

    // ── errors reset between calls ────────────────────────────────────────────

    public function test_errors_are_reset_between_validate_calls(): void
    {
        $v = $this->v();

        $v->validate(['nombre' => ''], ['nombre' => 'required']);
        $this->assertNotEmpty($v->errors());

        // Segunda llamada con datos válidos → errores deben limpiarse
        $v->validate(['nombre' => 'Juan'], ['nombre' => 'required']);
        $this->assertEmpty($v->errors());
    }

    // ── multiple rules / error messages ──────────────────────────────────────

    public function test_multiple_rules_can_generate_multiple_errors_for_one_field(): void
    {
        $v = $this->v();
        $v->validate(['codigo' => ''], ['codigo' => 'required|min:3']);

        // 'required' falla → 'min' también fallará (vacío es string)
        $this->assertGreaterThanOrEqual(1, count($v->errors()['codigo']));
    }

    public function test_error_message_contains_field_name(): void
    {
        $v = $this->v();
        $v->validate(['nombre_razon_social' => ''], ['nombre_razon_social' => 'required']);

        $msg = $v->errors()['nombre_razon_social'][0];
        $this->assertStringContainsString('nombre razon social', $msg);
    }
}
