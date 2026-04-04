<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Core\Validator
 */
class ValidatorTest extends TestCase
{
    // ─── required ────────────────────────────────────────────────────────────

    public function testRequiredPassesWhenPresent(): void
    {
        $v = Validator::make(['name' => 'João'], ['name' => 'required']);
        $this->assertFalse($v->fails());
    }

    public function testRequiredFailsWhenMissing(): void
    {
        $v = Validator::make([], ['name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function testRequiredFailsOnEmptyString(): void
    {
        $v = Validator::make(['name' => ''], ['name' => 'required']);
        $this->assertTrue($v->fails());
    }

    // ─── nullable ────────────────────────────────────────────────────────────

    public function testNullableSkipsOtherRulesWhenEmpty(): void
    {
        $v = Validator::make(['website' => ''], ['website' => 'nullable|url']);
        $this->assertFalse($v->fails());
    }

    public function testNullableValidatesWhenValuePresent(): void
    {
        $v = Validator::make(['website' => 'not-a-url'], ['website' => 'nullable|url']);
        $this->assertTrue($v->fails());
    }

    public function testNullablePassesValidUrl(): void
    {
        $v = Validator::make(['website' => 'https://eskill.com.br'], ['website' => 'nullable|url']);
        $this->assertFalse($v->fails());
    }

    // ─── string ──────────────────────────────────────────────────────────────

    public function testStringPassesForString(): void
    {
        $v = Validator::make(['name' => 'AWA'], ['name' => 'string']);
        $this->assertFalse($v->fails());
    }

    public function testStringFailsForArray(): void
    {
        $v = Validator::make(['name' => ['a', 'b']], ['name' => 'string']);
        $this->assertTrue($v->fails());
    }

    // ─── numeric ─────────────────────────────────────────────────────────────

    public function testNumericPassesForIntString(): void
    {
        $v = Validator::make(['price' => '49.99'], ['price' => 'numeric']);
        $this->assertFalse($v->fails());
    }

    public function testNumericFailsForText(): void
    {
        $v = Validator::make(['price' => 'abc'], ['price' => 'numeric']);
        $this->assertTrue($v->fails());
    }

    // ─── integer ─────────────────────────────────────────────────────────────

    public function testIntegerPassesForIntValue(): void
    {
        $v = Validator::make(['count' => 5], ['count' => 'integer']);
        $this->assertFalse($v->fails());
    }

    public function testIntegerPassesForIntString(): void
    {
        $v = Validator::make(['count' => '42'], ['count' => 'integer']);
        $this->assertFalse($v->fails());
    }

    public function testIntegerFailsForFloat(): void
    {
        $v = Validator::make(['count' => '3.14'], ['count' => 'integer']);
        $this->assertTrue($v->fails());
    }

    // ─── boolean ─────────────────────────────────────────────────────────────

    public function testBooleanPassesForTrue(): void
    {
        $v = Validator::make(['active' => true], ['active' => 'boolean']);
        $this->assertFalse($v->fails());
    }

    public function testBooleanPassesForStringOne(): void
    {
        $v = Validator::make(['active' => '1'], ['active' => 'boolean']);
        $this->assertFalse($v->fails());
    }

    public function testBooleanFailsForArbitraryString(): void
    {
        $v = Validator::make(['active' => 'yes'], ['active' => 'boolean']);
        $this->assertTrue($v->fails());
    }

    // ─── email ───────────────────────────────────────────────────────────────

    public function testEmailPassesForValidEmail(): void
    {
        $v = Validator::make(['email' => 'jess@eskill.com.br'], ['email' => 'email']);
        $this->assertFalse($v->fails());
    }

    public function testEmailFailsForInvalidEmail(): void
    {
        $v = Validator::make(['email' => 'not-an-email'], ['email' => 'email']);
        $this->assertTrue($v->fails());
    }

    // ─── url ─────────────────────────────────────────────────────────────────

    public function testUrlPassesForHttps(): void
    {
        $v = Validator::make(['link' => 'https://mercadolivre.com.br'], ['link' => 'url']);
        $this->assertFalse($v->fails());
    }

    public function testUrlFailsForPlainText(): void
    {
        $v = Validator::make(['link' => 'just-text'], ['link' => 'url']);
        $this->assertTrue($v->fails());
    }

    // ─── min / max ───────────────────────────────────────────────────────────

    public function testMinPassesForValidNumber(): void
    {
        $v = Validator::make(['age' => 18], ['age' => 'min:18']);
        $this->assertFalse($v->fails());
    }

    public function testMinFailsForNumberBelowLimit(): void
    {
        $v = Validator::make(['age' => 17], ['age' => 'min:18']);
        $this->assertTrue($v->fails());
    }

    public function testMaxPassesForValidNumber(): void
    {
        $v = Validator::make(['stock' => 100], ['stock' => 'max:999']);
        $this->assertFalse($v->fails());
    }

    public function testMaxFailsForNumberAboveLimit(): void
    {
        $v = Validator::make(['stock' => 1000], ['stock' => 'max:999']);
        $this->assertTrue($v->fails());
    }

    // ─── minLength / maxLength ───────────────────────────────────────────────

    public function testMinLengthPassesForValidString(): void
    {
        $v = Validator::make(['title' => 'Ab'], ['title' => 'minLength:2']);
        $this->assertFalse($v->fails());
    }

    public function testMinLengthFailsForShortString(): void
    {
        $v = Validator::make(['title' => 'A'], ['title' => 'minLength:2']);
        $this->assertTrue($v->fails());
    }

    public function testMaxLengthPassesForShortString(): void
    {
        $v = Validator::make(['title' => 'A'], ['title' => 'maxLength:10']);
        $this->assertFalse($v->fails());
    }

    public function testMaxLengthFailsForLongString(): void
    {
        $v = Validator::make(['title' => 'This is too long'], ['title' => 'maxLength:5']);
        $this->assertTrue($v->fails());
    }

    // ─── in ──────────────────────────────────────────────────────────────────

    public function testInPassesForAllowedValue(): void
    {
        $v = Validator::make(['role' => 'admin'], ['role' => 'in:admin,user,viewer']);
        $this->assertFalse($v->fails());
    }

    public function testInFailsForDisallowedValue(): void
    {
        $v = Validator::make(['role' => 'superuser'], ['role' => 'in:admin,user,viewer']);
        $this->assertTrue($v->fails());
    }

    // ─── regex ───────────────────────────────────────────────────────────────

    public function testRegexPassesForMatchingPattern(): void
    {
        $v = Validator::make(['cpf' => '123.456.789-09'], ['cpf' => 'regex:\d{3}\.\d{3}\.\d{3}-\d{2}']);
        $this->assertFalse($v->fails());
    }

    public function testRegexFailsForNonMatchingPattern(): void
    {
        $v = Validator::make(['cpf' => 'abc'], ['cpf' => 'regex:^\d+$']);
        $this->assertTrue($v->fails());
    }

    // ─── date ────────────────────────────────────────────────────────────────

    public function testDatePassesForValidDate(): void
    {
        $v = Validator::make(['birthday' => '2000-01-15'], ['birthday' => 'date']);
        $this->assertFalse($v->fails());
    }

    public function testDateFailsForInvalidDate(): void
    {
        $v = Validator::make(['birthday' => 'not-a-date'], ['birthday' => 'date']);
        $this->assertTrue($v->fails());
    }

    // ─── chaining and validated() ────────────────────────────────────────────

    public function testChainsMultipleRules(): void
    {
        $v = Validator::make(
            ['age' => 25, 'email' => 'user@test.com'],
            ['age' => 'required|integer|min:18|max:120', 'email' => 'required|email']
        );
        $this->assertFalse($v->fails());
    }

    public function testValidatedReturnsOnlyDeclaredFields(): void
    {
        $v = Validator::make(
            ['email' => 'user@test.com', 'secret' => 'should-not-appear'],
            ['email' => 'required|email']
        );
        $this->assertFalse($v->fails());
        $validated = $v->validated();
        $this->assertArrayHasKey('email', $validated);
        $this->assertArrayNotHasKey('secret', $validated);
    }

    public function testPassesMethodReturnsTrueWhenValid(): void
    {
        $v = Validator::make(['n' => '5'], ['n' => 'numeric']);
        $this->assertTrue($v->passes());
    }

    public function testMultipleErrorsAccumulate(): void
    {
        $v = Validator::make(
            [],
            ['name' => 'required', 'email' => 'required|email']
        );
        $this->assertTrue($v->fails());
        $errors = $v->errors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testErrorMessagesAreInPortuguese(): void
    {
        $v = Validator::make([], ['campo' => 'required']);
        $v->fails();
        $errors = $v->errors();
        $this->assertStringContainsString('obrigatório', $errors['campo'][0]);
    }
}
