<?php

declare(strict_types=1);

namespace Tests\Unit\Validators;

use App\Validators\FormValidator;
use PHPUnit\Framework\TestCase;

class FormValidatorTest extends TestCase
{
    private FormValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new FormValidator();
    }

    public function test_required_validation_fails_on_empty(): void
    {
        $errors = $this->validator->validateForm(
            ['email' => ''],
            ['email' => 'required']
        );

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function test_email_validation_accepts_valid_email(): void
    {
        $errors = $this->validator->validateForm(
            ['email' => 'test@example.com'],
            ['email' => 'email']
        );

        $this->assertEmpty($errors);
    }

    public function test_email_validation_rejects_invalid_email(): void
    {
        $errors = $this->validator->validateForm(
            ['email' => 'invalid-email'],
            ['email' => 'required|email']
        );

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function test_documento_validation(): void
    {
        $errors = $this->validator->validateForm(
            ['documento' => '123456'],
            ['documento' => 'required|documento']
        );
        $this->assertEmpty($errors);

        $errors = $this->validator->validateForm(
            ['documento' => '12345'],
            ['documento' => 'required|documento']
        );
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('documento', $errors);
    }

    public function test_phone_validation(): void
    {
        $errors = $this->validator->validateForm(
            ['telefono' => '+1234567890'],
            ['telefono' => 'required|phone']
        );
        $this->assertEmpty($errors);

        $errors = $this->validator->validateForm(
            ['telefono' => '123'],
            ['telefono' => 'required|phone']
        );
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('telefono', $errors);
    }

    public function test_multiple_rules_validation(): void
    {
        $errors = $this->validator->validateForm(
            [
                'nombre_razon_social' => 'AB',
                'email' => 'invalido',
                'numero_documento' => '12345',
            ],
            [
                'nombre_razon_social' => 'required|minLength:3',
                'email' => 'required|email',
                'numero_documento' => 'required|documento',
            ]
        );

        $this->assertCount(3, $errors);
        $this->assertArrayHasKey('nombre_razon_social', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('numero_documento', $errors);
    }

    public function test_minlength_and_maxlength_aliases(): void
    {
        $errors = $this->validator->validateForm(
            ['nombre' => 'AB'],
            ['nombre' => 'required|minLength:3']
        );
        $this->assertArrayHasKey('nombre', $errors);

        $errors = $this->validator->validateForm(
            ['nombre' => 'ABC'],
            ['nombre' => 'required|minLength:3|maxLength:10']
        );
        $this->assertEmpty($errors);
    }
}
