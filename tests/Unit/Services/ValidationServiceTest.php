<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ValidationService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\ValidationService
 */
class ValidationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset static custom validators between tests
        $ref = new \ReflectionClass(ValidationService::class);
        $prop = $ref->getProperty('customValidators');
        $prop->setAccessible(true);
        $prop->setValue(null, []);
    }

    private function createValidator(array $data, array $rules, array $messages = []): ValidationService
    {
        return new ValidationService($data, $rules, $messages);
    }

    // =========================================
    // required
    // =========================================

    public function testRequiredPassesWithValue(): void
    {
        $v = $this->createValidator(['name' => 'Jess'], ['name' => 'required']);
        $this->assertTrue($v->validate());
    }

    public function testRequiredFailsWithNull(): void
    {
        $v = $this->createValidator(['name' => null], ['name' => 'required']);
        $this->assertFalse($v->validate());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function testRequiredFailsWithEmptyString(): void
    {
        $v = $this->createValidator(['name' => ''], ['name' => 'required']);
        $this->assertFalse($v->validate());
    }

    public function testRequiredFailsWithWhitespaceOnly(): void
    {
        $v = $this->createValidator(['name' => '   '], ['name' => 'required']);
        $this->assertFalse($v->validate());
    }

    public function testRequiredFailsWithEmptyArray(): void
    {
        $v = $this->createValidator(['tags' => []], ['tags' => 'required']);
        $this->assertFalse($v->validate());
    }

    public function testRequiredFailsWithMissingField(): void
    {
        $v = $this->createValidator([], ['name' => 'required']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // email
    // =========================================

    public function testEmailPassesWithValidEmail(): void
    {
        $v = $this->createValidator(['email' => 'user@example.com'], ['email' => 'email']);
        $this->assertTrue($v->validate());
    }

    public function testEmailFailsWithInvalidEmail(): void
    {
        $v = $this->createValidator(['email' => 'not-an-email'], ['email' => 'email']);
        $this->assertFalse($v->validate());
    }

    public function testEmailFailsWithMissingAtSign(): void
    {
        $v = $this->createValidator(['email' => 'user.example.com'], ['email' => 'email']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // url
    // =========================================

    public function testUrlPassesWithValidUrl(): void
    {
        $v = $this->createValidator(['site' => 'https://eskill.com.br'], ['site' => 'url']);
        $this->assertTrue($v->validate());
    }

    public function testUrlFailsWithInvalidUrl(): void
    {
        $v = $this->createValidator(['site' => 'not a url'], ['site' => 'url']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // numeric
    // =========================================

    public function testNumericPassesWithInteger(): void
    {
        $v = $this->createValidator(['price' => 42], ['price' => 'numeric']);
        $this->assertTrue($v->validate());
    }

    public function testNumericPassesWithFloatString(): void
    {
        $v = $this->createValidator(['price' => '3.14'], ['price' => 'numeric']);
        $this->assertTrue($v->validate());
    }

    public function testNumericFailsWithLetters(): void
    {
        $v = $this->createValidator(['price' => 'abc'], ['price' => 'numeric']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // integer
    // =========================================

    public function testIntegerPassesWithInt(): void
    {
        $v = $this->createValidator(['qty' => 10], ['qty' => 'integer']);
        $this->assertTrue($v->validate());
    }

    public function testIntegerPassesWithIntString(): void
    {
        $v = $this->createValidator(['qty' => '10'], ['qty' => 'integer']);
        $this->assertTrue($v->validate());
    }

    public function testIntegerFailsWithFloat(): void
    {
        $v = $this->createValidator(['qty' => '3.14'], ['qty' => 'integer']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // min
    // =========================================

    public function testMinPassesForStringLength(): void
    {
        $v = $this->createValidator(['name' => 'Jessica'], ['name' => 'min:3']);
        $this->assertTrue($v->validate());
    }

    public function testMinFailsForShortString(): void
    {
        $v = $this->createValidator(['name' => 'AB'], ['name' => 'min:3']);
        $this->assertFalse($v->validate());
    }

    public function testMinPassesForNumericValue(): void
    {
        $v = $this->createValidator(['age' => 18], ['age' => 'min:18']);
        $this->assertTrue($v->validate());
    }

    public function testMinFailsForSmallNumericValue(): void
    {
        $v = $this->createValidator(['age' => 15], ['age' => 'min:18']);
        $this->assertFalse($v->validate());
    }

    public function testMinPassesForArrayCount(): void
    {
        $v = $this->createValidator(['items' => [1, 2, 3]], ['items' => 'min:2']);
        $this->assertTrue($v->validate());
    }

    public function testMinFailsForSmallArray(): void
    {
        $v = $this->createValidator(['items' => [1]], ['items' => 'min:2']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // max
    // =========================================

    public function testMaxPassesForStringLength(): void
    {
        $v = $this->createValidator(['name' => 'AB'], ['name' => 'max:5']);
        $this->assertTrue($v->validate());
    }

    public function testMaxFailsForLongString(): void
    {
        $v = $this->createValidator(['name' => 'ABCDEF'], ['name' => 'max:5']);
        $this->assertFalse($v->validate());
    }

    public function testMaxPassesForNumericValue(): void
    {
        $v = $this->createValidator(['qty' => 100], ['qty' => 'max:100']);
        $this->assertTrue($v->validate());
    }

    public function testMaxFailsForLargeNumericValue(): void
    {
        $v = $this->createValidator(['qty' => 101], ['qty' => 'max:100']);
        $this->assertFalse($v->validate());
    }

    public function testMaxPassesForArrayCount(): void
    {
        $v = $this->createValidator(['tags' => ['a', 'b']], ['tags' => 'max:3']);
        $this->assertTrue($v->validate());
    }

    public function testMaxFailsForLargeArray(): void
    {
        $v = $this->createValidator(['tags' => ['a', 'b', 'c', 'd']], ['tags' => 'max:3']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // between
    // =========================================

    public function testBetweenPassesWhenInRange(): void
    {
        $v = $this->createValidator(['age' => 25], ['age' => 'between:18,65']);
        $this->assertTrue($v->validate());
    }

    public function testBetweenFailsWhenBelowRange(): void
    {
        $v = $this->createValidator(['age' => 10], ['age' => 'between:18,65']);
        $this->assertFalse($v->validate());
    }

    public function testBetweenFailsWhenAboveRange(): void
    {
        $v = $this->createValidator(['age' => 70], ['age' => 'between:18,65']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // in
    // =========================================

    public function testInPassesWithAllowedValue(): void
    {
        $v = $this->createValidator(['status' => 'active'], ['status' => 'in:active,inactive,pending']);
        $this->assertTrue($v->validate());
    }

    public function testInFailsWithDisallowedValue(): void
    {
        $v = $this->createValidator(['status' => 'deleted'], ['status' => 'in:active,inactive,pending']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // notIn
    // =========================================

    public function testNotInPassesWithNonBlacklistedValue(): void
    {
        $v = $this->createValidator(['role' => 'editor'], ['role' => 'notIn:admin,superadmin']);
        $this->assertTrue($v->validate());
    }

    public function testNotInFailsWithBlacklistedValue(): void
    {
        $v = $this->createValidator(['role' => 'admin'], ['role' => 'notIn:admin,superadmin']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // regex
    // =========================================

    public function testRegexPassesWithMatch(): void
    {
        $v = $this->createValidator(['code' => 'ABC-123'], ['code' => 'regex:/^[A-Z]{3}-\d{3}$/']);
        $this->assertTrue($v->validate());
    }

    public function testRegexFailsWithNoMatch(): void
    {
        $v = $this->createValidator(['code' => 'abc123'], ['code' => 'regex:/^[A-Z]{3}-\d{3}$/']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // alpha
    // =========================================

    public function testAlphaPassesWithLettersOnly(): void
    {
        $v = $this->createValidator(['name' => 'João'], ['name' => 'alpha']);
        $this->assertTrue($v->validate());
    }

    public function testAlphaFailsWithNumbers(): void
    {
        $v = $this->createValidator(['name' => 'abc123'], ['name' => 'alpha']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // alphaNum
    // =========================================

    public function testAlphaNumPassesWithLettersAndDigits(): void
    {
        $v = $this->createValidator(['user' => 'user123'], ['user' => 'alphaNum']);
        $this->assertTrue($v->validate());
    }

    public function testAlphaNumFailsWithSpecialChars(): void
    {
        $v = $this->createValidator(['user' => 'user@123'], ['user' => 'alphaNum']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // alphaDash
    // =========================================

    public function testAlphaDashPassesWithDashesAndUnderscores(): void
    {
        $v = $this->createValidator(['slug' => 'my-slug_01'], ['slug' => 'alphaDash']);
        $this->assertTrue($v->validate());
    }

    public function testAlphaDashFailsWithSpaces(): void
    {
        $v = $this->createValidator(['slug' => 'my slug'], ['slug' => 'alphaDash']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // date
    // =========================================

    public function testDatePassesWithValidDate(): void
    {
        $v = $this->createValidator(['dob' => '2024-01-15'], ['dob' => 'date']);
        $this->assertTrue($v->validate());
    }

    public function testDateFailsWithInvalidDate(): void
    {
        $v = $this->createValidator(['dob' => 'not-a-date-xyz'], ['dob' => 'date']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // dateFormat
    // =========================================

    public function testDateFormatPassesWithCorrectFormat(): void
    {
        $v = $this->createValidator(['date' => '2024-06-15'], ['date' => 'dateFormat:Y-m-d']);
        $this->assertTrue($v->validate());
    }

    public function testDateFormatFailsWithWrongFormat(): void
    {
        $v = $this->createValidator(['date' => '15/06/2024'], ['date' => 'dateFormat:Y-m-d']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // before / after
    // =========================================

    public function testBeforePassesWithEarlierDate(): void
    {
        $v = $this->createValidator(['start' => '2020-01-01'], ['start' => 'before:2025-01-01']);
        $this->assertTrue($v->validate());
    }

    public function testBeforeFailsWithLaterDate(): void
    {
        $v = $this->createValidator(['start' => '2030-01-01'], ['start' => 'before:2025-01-01']);
        $this->assertFalse($v->validate());
    }

    public function testAfterPassesWithLaterDate(): void
    {
        $v = $this->createValidator(['end' => '2030-01-01'], ['end' => 'after:2025-01-01']);
        $this->assertTrue($v->validate());
    }

    public function testAfterFailsWithEarlierDate(): void
    {
        $v = $this->createValidator(['end' => '2020-01-01'], ['end' => 'after:2025-01-01']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // confirmed
    // =========================================

    public function testConfirmedPassesWhenFieldsMatch(): void
    {
        $v = $this->createValidator(
            ['password' => 'secret123', 'password_confirmation' => 'secret123'],
            ['password' => 'confirmed']
        );
        $this->assertTrue($v->validate());
    }

    public function testConfirmedFailsWhenFieldsDiffer(): void
    {
        $v = $this->createValidator(
            ['password' => 'secret123', 'password_confirmation' => 'other'],
            ['password' => 'confirmed']
        );
        $this->assertFalse($v->validate());
    }

    public function testConfirmedFailsWhenConfirmationMissing(): void
    {
        $v = $this->createValidator(
            ['password' => 'secret123'],
            ['password' => 'confirmed']
        );
        $this->assertFalse($v->validate());
    }

    // =========================================
    // same / different
    // =========================================

    public function testSamePassesWhenFieldsEqual(): void
    {
        $v = $this->createValidator(
            ['a' => 'xyz', 'b' => 'xyz'],
            ['a' => 'same:b']
        );
        $this->assertTrue($v->validate());
    }

    public function testSameFailsWhenFieldsDiffer(): void
    {
        $v = $this->createValidator(
            ['a' => 'xyz', 'b' => 'abc'],
            ['a' => 'same:b']
        );
        $this->assertFalse($v->validate());
    }

    public function testDifferentPassesWhenFieldsDiffer(): void
    {
        $v = $this->createValidator(
            ['a' => 'xyz', 'b' => 'abc'],
            ['a' => 'different:b']
        );
        $this->assertTrue($v->validate());
    }

    public function testDifferentFailsWhenFieldsEqual(): void
    {
        $v = $this->createValidator(
            ['a' => 'xyz', 'b' => 'xyz'],
            ['a' => 'different:b']
        );
        $this->assertFalse($v->validate());
    }

    // =========================================
    // array
    // =========================================

    public function testArrayPassesWithArray(): void
    {
        $v = $this->createValidator(['items' => [1, 2]], ['items' => 'array']);
        $this->assertTrue($v->validate());
    }

    public function testArrayFailsWithString(): void
    {
        $v = $this->createValidator(['items' => 'not array'], ['items' => 'array']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // boolean
    // =========================================

    public function testBooleanPassesWithTrueValue(): void
    {
        $v = $this->createValidator(['active' => true], ['active' => 'boolean']);
        $this->assertTrue($v->validate());
    }

    public function testBooleanPassesWithFalseValue(): void
    {
        $v = $this->createValidator(['active' => false], ['active' => 'boolean']);
        $this->assertTrue($v->validate());
    }

    public function testBooleanPassesWithZeroString(): void
    {
        $v = $this->createValidator(['active' => '0'], ['active' => 'boolean']);
        $this->assertTrue($v->validate());
    }

    public function testBooleanPassesWithOneInt(): void
    {
        $v = $this->createValidator(['active' => 1], ['active' => 'boolean']);
        $this->assertTrue($v->validate());
    }

    public function testBooleanFailsWithRandomString(): void
    {
        $v = $this->createValidator(['active' => 'yes'], ['active' => 'boolean']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // json
    // =========================================

    public function testJsonPassesWithValidJson(): void
    {
        $v = $this->createValidator(['payload' => '{"key":"value"}'], ['payload' => 'json']);
        $this->assertTrue($v->validate());
    }

    public function testJsonPassesWithJsonArray(): void
    {
        $v = $this->createValidator(['payload' => '[1,2,3]'], ['payload' => 'json']);
        $this->assertTrue($v->validate());
    }

    public function testJsonFailsWithInvalidJson(): void
    {
        $v = $this->createValidator(['payload' => '{bad json}'], ['payload' => 'json']);
        $this->assertFalse($v->validate());
    }

    public function testJsonFailsWithNonString(): void
    {
        $v = $this->createValidator(['payload' => 123], ['payload' => 'json']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // phone
    // =========================================

    public function testPhonePassesWithValidMobile(): void
    {
        $v = $this->createValidator(['phone' => '(16) 99123-4567'], ['phone' => 'phone']);
        $this->assertTrue($v->validate());
    }

    public function testPhonePassesWithLandline(): void
    {
        $v = $this->createValidator(['phone' => '(16) 3301-1234'], ['phone' => 'phone']);
        $this->assertTrue($v->validate());
    }

    public function testPhoneFailsWithShortNumber(): void
    {
        $v = $this->createValidator(['phone' => '12345'], ['phone' => 'phone']);
        $this->assertFalse($v->validate());
    }

    public function testPhoneFailsWithTooLong(): void
    {
        $v = $this->createValidator(['phone' => '123456789012'], ['phone' => 'phone']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // cpf
    // =========================================

    public function testCpfValidWithKnownGoodCpf(): void
    {
        $v = $this->createValidator(['cpf' => '529.982.247-25'], ['cpf' => 'cpf']);
        $this->assertTrue($v->validate());
    }

    public function testCpfValidWithDigitsOnly(): void
    {
        $v = $this->createValidator(['cpf' => '52998224725'], ['cpf' => 'cpf']);
        $this->assertTrue($v->validate());
    }

    public function testCpfFailsWithAllSameDigits(): void
    {
        $v = $this->createValidator(['cpf' => '111.111.111-11'], ['cpf' => 'cpf']);
        $this->assertFalse($v->validate());
    }

    public function testCpfFailsWithWrongCheckDigits(): void
    {
        $v = $this->createValidator(['cpf' => '123.456.789-00'], ['cpf' => 'cpf']);
        $this->assertFalse($v->validate());
    }

    public function testCpfFailsWithShortString(): void
    {
        $v = $this->createValidator(['cpf' => '123'], ['cpf' => 'cpf']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // cnpj
    // =========================================

    public function testCnpjValidWithKnownGoodCnpj(): void
    {
        $v = $this->createValidator(['cnpj' => '11.222.333/0001-81'], ['cnpj' => 'cnpj']);
        $this->assertTrue($v->validate());
    }

    public function testCnpjValidWithDigitsOnly(): void
    {
        $v = $this->createValidator(['cnpj' => '11222333000181'], ['cnpj' => 'cnpj']);
        $this->assertTrue($v->validate());
    }

    public function testCnpjFailsWithAllSameDigits(): void
    {
        $v = $this->createValidator(['cnpj' => '11.111.111/1111-11'], ['cnpj' => 'cnpj']);
        $this->assertFalse($v->validate());
    }

    public function testCnpjFailsWithShortString(): void
    {
        $v = $this->createValidator(['cnpj' => '12345'], ['cnpj' => 'cnpj']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // cep
    // =========================================

    public function testCepPassesWithFormattedCep(): void
    {
        $v = $this->createValidator(['cep' => '14801-000'], ['cep' => 'cep']);
        $this->assertTrue($v->validate());
    }

    public function testCepPassesWithDigitsOnly(): void
    {
        $v = $this->createValidator(['cep' => '14801000'], ['cep' => 'cep']);
        $this->assertTrue($v->validate());
    }

    public function testCepFailsWithWrongLength(): void
    {
        $v = $this->createValidator(['cep' => '1234'], ['cep' => 'cep']);
        $this->assertFalse($v->validate());
    }

    // =========================================
    // errors(), firstErrors(), validated()
    // =========================================

    public function testErrorsReturnsArrayKeyedByField(): void
    {
        $v = $this->createValidator(
            ['email' => 'bad', 'name' => ''],
            ['email' => 'email', 'name' => 'required']
        );
        $v->validate();
        $errors = $v->errors();

        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertIsArray($errors['email']);
    }

    public function testErrorsEmptyWhenValidationPasses(): void
    {
        $v = $this->createValidator(['name' => 'Jess'], ['name' => 'required']);
        $v->validate();
        $this->assertEmpty($v->errors());
    }

    public function testFirstErrorsReturnsOnePerField(): void
    {
        $v = $this->createValidator(
            ['email' => ''],
            ['email' => 'required|email']
        );
        $v->validate();
        $first = $v->firstErrors();

        $this->assertArrayHasKey('email', $first);
        $this->assertIsString($first['email']);
    }

    public function testValidatedReturnsOnlyRuledFields(): void
    {
        $v = $this->createValidator(
            ['name' => 'Jess', 'age' => 30, 'extra' => 'ignored'],
            ['name' => 'required', 'age' => 'integer']
        );
        $v->validate();
        $validated = $v->validated();

        $this->assertArrayHasKey('name', $validated);
        $this->assertArrayHasKey('age', $validated);
        $this->assertArrayNotHasKey('extra', $validated);
    }

    public function testValidatedExcludesNullFields(): void
    {
        $v = $this->createValidator(
            ['name' => 'Jess'],
            ['name' => 'required', 'missing' => 'required']
        );
        $v->validate();
        $validated = $v->validated();

        $this->assertArrayHasKey('name', $validated);
        $this->assertArrayNotHasKey('missing', $validated);
    }

    // =========================================
    // make() static factory
    // =========================================

    public function testMakeReturnsValidatorWithAutoValidation(): void
    {
        $v = ValidationService::make(
            ['email' => 'user@example.com'],
            ['email' => 'required|email']
        );

        $this->assertInstanceOf(ValidationService::class, $v);
        $this->assertTrue($v->passes());
        $this->assertEmpty($v->errors());
    }

    public function testMakeDetectsErrors(): void
    {
        $v = ValidationService::make(
            ['email' => 'bad'],
            ['email' => 'email']
        );

        $this->assertTrue($v->fails());
        $this->assertNotEmpty($v->errors());
    }

    public function testMakeAcceptsCustomMessages(): void
    {
        $v = ValidationService::make(
            ['name' => ''],
            ['name' => 'required'],
            ['name.required' => 'Fill name']
        );

        $this->assertSame('Fill name', $v->errors()['name'][0]);
    }

    // =========================================
    // passes() / fails()
    // =========================================

    public function testPassesReturnsTrueWhenNoErrors(): void
    {
        $v = $this->createValidator(['name' => 'Jess'], ['name' => 'required']);
        $v->validate();
        $this->assertTrue($v->passes());
        $this->assertFalse($v->fails());
    }

    public function testFailsReturnsTrueWhenHasErrors(): void
    {
        $v = $this->createValidator(['name' => ''], ['name' => 'required']);
        $v->validate();
        $this->assertTrue($v->fails());
        $this->assertFalse($v->passes());
    }

    // =========================================
    // extend() custom validators
    // =========================================

    public function testExtendRegistersCustomValidator(): void
    {
        ValidationService::extend('even', function (mixed $value): bool {
            return is_numeric($value) && (int)$value % 2 === 0;
        });

        $v = $this->createValidator(['num' => 4], ['num' => 'even']);
        $this->assertTrue($v->validate());
    }

    public function testExtendCustomValidatorCanFail(): void
    {
        ValidationService::extend('even', function (mixed $value): bool {
            return is_numeric($value) && (int)$value % 2 === 0;
        });

        $v = $this->createValidator(['num' => 3], ['num' => 'even']);
        $this->assertFalse($v->validate());
    }

    public function testExtendCustomValidatorReceivesParams(): void
    {
        ValidationService::extend('divisibleBy', function (mixed $value, array $params): bool {
            $divisor = (int)($params[0] ?? 1);
            return is_numeric($value) && (int)$value % $divisor === 0;
        });

        $v = $this->createValidator(['num' => 15], ['num' => 'divisibleBy:5']);
        $this->assertTrue($v->validate());
    }

    // =========================================
    // Dot notation
    // =========================================

    public function testDotNotationAccessesNestedData(): void
    {
        $v = $this->createValidator(
            ['address' => ['city' => 'Araraquara']],
            ['address.city' => 'required']
        );
        $this->assertTrue($v->validate());
    }

    public function testDotNotationFailsWhenNestedFieldMissing(): void
    {
        $v = $this->createValidator(
            ['address' => ['state' => 'SP']],
            ['address.city' => 'required']
        );
        $this->assertFalse($v->validate());
    }

    public function testDotNotationDeepNesting(): void
    {
        $v = $this->createValidator(
            ['user' => ['profile' => ['bio' => 'Hello']]],
            ['user.profile.bio' => 'required|min:3']
        );
        $this->assertTrue($v->validate());
    }

    public function testDotNotationValidatedIncludesNestedValues(): void
    {
        $v = $this->createValidator(
            ['address' => ['city' => 'SP', 'zip' => '14801']],
            ['address.city' => 'required']
        );
        $v->validate();
        $validated = $v->validated();
        $this->assertSame('SP', $validated['address.city']);
    }

    // =========================================
    // Multiple rules (pipe-separated)
    // =========================================

    public function testMultipleRulesAllPass(): void
    {
        $v = $this->createValidator(
            ['email' => 'user@example.com'],
            ['email' => 'required|email|min:5']
        );
        $this->assertTrue($v->validate());
    }

    public function testMultipleRulesCollectsOnlyRequiredError(): void
    {
        $v = $this->createValidator(
            ['email' => ''],
            ['email' => 'required|email|min:5']
        );
        $v->validate();
        // required fails; email and min are skipped because value is empty
        $errors = $v->errors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertCount(1, $errors['email']);
    }

    public function testMultipleRulesCollectsMultipleErrors(): void
    {
        $v = $this->createValidator(
            ['val' => 'ab'],
            ['val' => 'email|min:5']
        );
        $v->validate();
        $errors = $v->errors();
        $this->assertArrayHasKey('val', $errors);
        $this->assertCount(2, $errors['val']);
    }

    // =========================================
    // Custom error messages
    // =========================================

    public function testCustomMessageOverridesDefault(): void
    {
        $v = $this->createValidator(
            ['email' => ''],
            ['email' => 'required'],
            ['email.required' => 'Custom msg']
        );
        $v->validate();
        $errors = $v->errors();
        $this->assertSame('Custom msg', $errors['email'][0]);
    }

    public function testCustomMessageByFieldOnly(): void
    {
        $v = $this->createValidator(
            ['name' => ''],
            ['name' => 'required'],
            ['name' => 'Name is needed']
        );
        $v->validate();
        $errors = $v->errors();
        $this->assertSame('Name is needed', $errors['name'][0]);
    }

    // =========================================
    // Empty field skips non-required validators
    // =========================================

    public function testEmptyFieldSkipsNonRequiredValidators(): void
    {
        $v = $this->createValidator(
            ['email' => ''],
            ['email' => 'email']
        );
        $this->assertTrue($v->validate());
    }

    public function testNullFieldSkipsNonRequiredValidators(): void
    {
        $v = $this->createValidator(
            ['phone' => null],
            ['phone' => 'phone']
        );
        $this->assertTrue($v->validate());
    }

    public function testMissingFieldSkipsNonRequiredValidators(): void
    {
        $v = $this->createValidator(
            [],
            ['website' => 'url']
        );
        $this->assertTrue($v->validate());
    }

    // =========================================
    // Rules as array syntax
    // =========================================

    public function testRulesAcceptArraySyntax(): void
    {
        $v = $this->createValidator(
            ['name' => 'Jess'],
            ['name' => ['required', 'min:3']]
        );
        $this->assertTrue($v->validate());
    }

    public function testRulesArraySyntaxDetectsFailures(): void
    {
        $v = $this->createValidator(
            ['name' => 'AB'],
            ['name' => ['required', 'min:3']]
        );
        $this->assertFalse($v->validate());
    }

    // =========================================
    // Edge cases
    // =========================================

    public function testEmptyRulesAlwaysPass(): void
    {
        $v = $this->createValidator(['any' => 'data'], []);
        $this->assertTrue($v->validate());
    }

    public function testBooleanFalseIsNotEmpty(): void
    {
        $v = $this->createValidator(['flag' => false], ['flag' => 'boolean']);
        $this->assertTrue($v->validate());
    }

    public function testZeroIsNotEmpty(): void
    {
        $v = $this->createValidator(['qty' => 0], ['qty' => 'integer']);
        $this->assertTrue($v->validate());
    }
}
