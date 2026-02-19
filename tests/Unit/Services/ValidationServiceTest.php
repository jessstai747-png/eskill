<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ValidationService;

class ValidationServiceTest extends TestCase
{
    // =============================
    // TESTES BÁSICOS
    // =============================

    public function testCanBeInstantiated(): void
    {
        $validator = new ValidationService();
        $this->assertInstanceOf(ValidationService::class, $validator);
    }

    public function testEmptyDataWithNoRulesPass(): void
    {
        $validator = new ValidationService([], []);
        $this->assertTrue($validator->validate());
    }

    // =============================
    // TESTES DE REQUIRED
    // =============================

    public function testRequiredPassesWithValue(): void
    {
        $validator = ValidationService::make(
            ['name' => 'John'],
            ['name' => 'required']
        );
        $this->assertTrue($validator->passes());
    }

    public function testRequiredFailsWithNull(): void
    {
        $validator = ValidationService::make(
            ['name' => null],
            ['name' => 'required']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors());
    }

    public function testRequiredFailsWithEmptyString(): void
    {
        $validator = ValidationService::make(
            ['name' => ''],
            ['name' => 'required']
        );
        $this->assertTrue($validator->fails());
        $this->assertNotEmpty($validator->errors());
    }

    public function testRequiredFailsWithMissingField(): void
    {
        $validator = ValidationService::make(
            [],
            ['name' => 'required']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors());
    }

    // =============================
    // TESTES DE EMAIL
    // =============================

    public function testEmailPassesWithValidEmail(): void
    {
        $validator = ValidationService::make(
            ['email' => 'test@example.com'],
            ['email' => 'email']
        );
        $this->assertTrue($validator->passes());
    }

    public function testEmailFailsWithInvalidEmail(): void
    {
        $validator = ValidationService::make(
            ['email' => 'not-an-email'],
            ['email' => 'email']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors());
    }

    public function testEmailSkipsWhenEmpty(): void
    {
        $validator = ValidationService::make(
            ['email' => ''],
            ['email' => 'email']
        );
        // Não é required, então empty passa
        $this->assertTrue($validator->passes());
    }

    // =============================
    // TESTES DE URL
    // =============================

    public function testUrlPassesWithValidUrl(): void
    {
        $validator = ValidationService::make(
            ['website' => 'https://example.com'],
            ['website' => 'url']
        );
        $this->assertTrue($validator->passes());
    }

    public function testUrlFailsWithInvalidUrl(): void
    {
        $validator = ValidationService::make(
            ['website' => 'not-a-url'],
            ['website' => 'url']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('website', $validator->errors());
    }

    // =============================
    // TESTES DE MIN/MAX
    // =============================

    public function testMinPassesWithSufficientLength(): void
    {
        $validator = ValidationService::make(
            ['password' => 'secret123'],
            ['password' => 'min:6']
        );
        $this->assertTrue($validator->passes());
    }

    public function testMinFailsWithShortString(): void
    {
        $validator = ValidationService::make(
            ['password' => 'abc'],
            ['password' => 'min:6']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors());
    }

    public function testMaxPassesWithShortString(): void
    {
        $validator = ValidationService::make(
            ['title' => 'Short Title'],
            ['title' => 'max:50']
        );
        $this->assertTrue($validator->passes());
    }

    public function testMaxFailsWithLongString(): void
    {
        $validator = ValidationService::make(
            ['title' => str_repeat('a', 100)],
            ['title' => 'max:50']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('title', $validator->errors());
    }

    public function testMinWithNumericValue(): void
    {
        $validator = ValidationService::make(
            ['age' => 25],
            ['age' => 'min:18']
        );
        $this->assertTrue($validator->passes());
    }

    public function testMaxWithNumericValue(): void
    {
        $validator = ValidationService::make(
            ['quantity' => 5],
            ['quantity' => 'max:10']
        );
        $this->assertTrue($validator->passes());
    }

    // =============================
    // TESTES DE BETWEEN
    // =============================

    public function testBetweenPassesWithValueInRange(): void
    {
        $validator = ValidationService::make(
            ['age' => 25],
            ['age' => 'between:18,65']
        );
        $this->assertTrue($validator->passes());
    }

    public function testBetweenFailsWithValueOutOfRange(): void
    {
        $validator = ValidationService::make(
            ['age' => 10],
            ['age' => 'between:18,65']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('age', $validator->errors());
    }

    // =============================
    // TESTES DE IN/NOT_IN
    // =============================

    public function testInPassesWithValidValue(): void
    {
        $validator = ValidationService::make(
            ['status' => 'active'],
            ['status' => 'in:active,inactive,pending']
        );
        $this->assertTrue($validator->passes());
    }

    public function testInFailsWithInvalidValue(): void
    {
        $validator = ValidationService::make(
            ['status' => 'unknown'],
            ['status' => 'in:active,inactive,pending']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('status', $validator->errors());
    }

    public function testNotInPassesWithValueNotInList(): void
    {
        $validator = ValidationService::make(
            ['role' => 'admin'],
            ['role' => 'notIn:banned,suspended']
        );
        $this->assertTrue($validator->passes());
    }

    // =============================
    // TESTES DE NUMERIC/INTEGER
    // =============================

    public function testNumericPassesWithNumber(): void
    {
        $validator = ValidationService::make(
            ['price' => '19.99'],
            ['price' => 'numeric']
        );
        $this->assertTrue($validator->passes());
    }

    public function testIntegerPassesWithInteger(): void
    {
        $validator = ValidationService::make(
            ['quantity' => '42'],
            ['quantity' => 'integer']
        );
        $this->assertTrue($validator->passes());
    }

    public function testIntegerFailsWithFloat(): void
    {
        $validator = ValidationService::make(
            ['quantity' => '3.14'],
            ['quantity' => 'integer']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('quantity', $validator->errors());
    }

    // =============================
    // TESTES DE DATE
    // =============================

    public function testDatePassesWithValidDate(): void
    {
        $validator = ValidationService::make(
            ['birthday' => '1990-05-15'],
            ['birthday' => 'date']
        );
        $this->assertTrue($validator->passes());
    }

    public function testDateFormatPasses(): void
    {
        $validator = ValidationService::make(
            ['date' => '15/05/1990'],
            ['date' => 'dateFormat:d/m/Y']
        );
        $this->assertTrue($validator->passes());
    }

    public function testDateFormatFails(): void
    {
        $validator = ValidationService::make(
            ['date' => '1990-05-15'],
            ['date' => 'dateFormat:d/m/Y']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('date', $validator->errors());
    }

    // =============================
    // TESTES DE CPF
    // =============================

    public function testCpfPassesWithValid(): void
    {
        // CPF válido para teste
        $validator = ValidationService::make(
            ['cpf' => '529.982.247-25'],
            ['cpf' => 'cpf']
        );
        $this->assertTrue($validator->passes());
    }

    public function testCpfFailsWithInvalid(): void
    {
        $validator = ValidationService::make(
            ['cpf' => '111.111.111-11'],
            ['cpf' => 'cpf']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('cpf', $validator->errors());
    }

    public function testCpfFailsWithWrongLength(): void
    {
        $validator = ValidationService::make(
            ['cpf' => '123456'],
            ['cpf' => 'cpf']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('cpf', $validator->errors());
    }

    // =============================
    // TESTES DE CNPJ
    // =============================

    public function testCnpjPassesWithValid(): void
    {
        // CNPJ válido para teste
        $validator = ValidationService::make(
            ['cnpj' => '11.222.333/0001-81'],
            ['cnpj' => 'cnpj']
        );
        $this->assertTrue($validator->passes());
    }

    public function testCnpjFailsWithInvalid(): void
    {
        $validator = ValidationService::make(
            ['cnpj' => '11.111.111/1111-11'],
            ['cnpj' => 'cnpj']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('cnpj', $validator->errors());
    }

    // =============================
    // TESTES DE CEP E PHONE
    // =============================

    public function testCepPasses(): void
    {
        $validator = ValidationService::make(
            ['cep' => '01310-100'],
            ['cep' => 'cep']
        );
        $this->assertTrue($validator->passes());
    }

    public function testPhonePasses(): void
    {
        $validator = ValidationService::make(
            ['phone' => '(11) 99999-9999'],
            ['phone' => 'phone']
        );
        $this->assertTrue($validator->passes());
    }

    // =============================
    // TESTES DE JSON E BOOLEAN
    // =============================

    public function testJsonPasses(): void
    {
        $validator = ValidationService::make(
            ['data' => '{"key": "value"}'],
            ['data' => 'json']
        );
        $this->assertTrue($validator->passes());
    }

    public function testJsonFails(): void
    {
        $validator = ValidationService::make(
            ['data' => '{invalid}'],
            ['data' => 'json']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('data', $validator->errors());
    }

    public function testBooleanPasses(): void
    {
        $validator = ValidationService::make(
            ['active' => true],
            ['active' => 'boolean']
        );
        $this->assertTrue($validator->passes());
    }

    // =============================
    // TESTES DE CONFIRMED/SAME
    // =============================

    public function testConfirmedPasses(): void
    {
        $validator = ValidationService::make(
            [
                'password' => 'secret123',
                'password_confirmation' => 'secret123'
            ],
            ['password' => 'confirmed']
        );
        $this->assertTrue($validator->passes());
    }

    public function testConfirmedFails(): void
    {
        $validator = ValidationService::make(
            [
                'password' => 'secret123',
                'password_confirmation' => 'different'
            ],
            ['password' => 'confirmed']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors());
    }

    public function testSamePasses(): void
    {
        $validator = ValidationService::make(
            ['email' => 'test@test.com', 'email2' => 'test@test.com'],
            ['email' => 'same:email2']
        );
        $this->assertTrue($validator->passes());
    }

    public function testDifferentPasses(): void
    {
        $validator = ValidationService::make(
            ['old_password' => 'old123', 'new_password' => 'new456'],
            ['new_password' => 'different:old_password']
        );
        $this->assertTrue($validator->passes());
    }

    // =============================
    // TESTES DE ALPHA
    // =============================

    public function testAlphaPasses(): void
    {
        $validator = ValidationService::make(
            ['name' => 'JohnDoe'],
            ['name' => 'alpha']
        );
        $this->assertTrue($validator->passes());
    }

    public function testAlphaNumPasses(): void
    {
        $validator = ValidationService::make(
            ['username' => 'user123'],
            ['username' => 'alphaNum']
        );
        $this->assertTrue($validator->passes());
    }

    public function testAlphaDashPasses(): void
    {
        $validator = ValidationService::make(
            ['slug' => 'my-slug_123'],
            ['slug' => 'alphaDash']
        );
        $this->assertTrue($validator->passes());
    }

    // =============================
    // TESTES DE MÚLTIPLAS REGRAS
    // =============================

    public function testMultipleRulesPass(): void
    {
        $validator = ValidationService::make(
            ['email' => 'test@example.com'],
            ['email' => 'required|email|max:255']
        );
        $this->assertTrue($validator->passes());
    }

    public function testMultipleRulesFailOnOne(): void
    {
        $validator = ValidationService::make(
            ['email' => 'not-an-email'],
            ['email' => 'required|email|max:255']
        );
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors());
    }

    // =============================
    // TESTES DE MENSAGENS PERSONALIZADAS
    // =============================

    public function testCustomMessage(): void
    {
        $validator = ValidationService::make(
            ['name' => ''],
            ['name' => 'required'],
            ['name.required' => 'Por favor, informe seu nome.']
        );
        $validator->validate();

        $errors = $validator->firstErrors();
        $this->assertEquals('Por favor, informe seu nome.', $errors['name']);
    }

    // =============================
    // TESTES DE MÉTODOS AUXILIARES
    // =============================

    public function testValidatedReturnsOnlyValidatedFields(): void
    {
        $validator = ValidationService::make(
            ['name' => 'John', 'email' => 'john@example.com', 'extra' => 'ignored'],
            ['name' => 'required', 'email' => 'email']
        );
        $validator->validate();

        $validated = $validator->validated();

        $this->assertArrayHasKey('name', $validated);
        $this->assertArrayHasKey('email', $validated);
        $this->assertArrayNotHasKey('extra', $validated);
    }

    public function testFirstErrorsReturnsOnlyFirstError(): void
    {
        $validator = ValidationService::make(
            ['password' => 'ab'],
            ['password' => 'required|min:6|max:20']
        );
        $validator->validate();

        $firstErrors = $validator->firstErrors();

        $this->assertArrayHasKey('password', $firstErrors);
        $this->assertIsString($firstErrors['password']);
    }

    // =============================
    // TESTES DE EXTEND
    // =============================

    public function testCustomValidatorCanBeRegistered(): void
    {
        ValidationService::extend('customRule', function ($value) {
            return $value === 'valid';
        });

        $validator = ValidationService::make(
            ['field' => 'valid'],
            ['field' => 'customRule']
        );
        $this->assertTrue($validator->passes());

        $validator2 = ValidationService::make(
            ['field' => 'invalid'],
            ['field' => 'customRule']
        );
        $this->assertTrue($validator2->fails());
    }

    // =============================
    // TESTES DE NESTED DATA (DOT NOTATION)
    // =============================

    public function testNestedFieldValidation(): void
    {
        $validator = ValidationService::make(
            ['user' => ['email' => 'test@example.com']],
            ['user.email' => 'required|email']
        );
        $this->assertTrue($validator->passes());
    }

    public function testNestedFieldValidationFails(): void
    {
        $validator = ValidationService::make(
            ['user' => ['email' => 'invalid']],
            ['user.email' => 'email']
        );
        $this->assertTrue($validator->fails());
        $this->assertNotEmpty($validator->errors());
    }
}
