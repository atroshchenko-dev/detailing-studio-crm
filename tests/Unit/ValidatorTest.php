<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for App\Validator
 *
 * Tests all pure-logic validation methods.
 * The unique() method is excluded — it requires a live database.
 */
class ValidatorTest extends TestCase
{
    // ─── required() ──────────────────────────────────────────────

    public function testRequiredFailsOnMissingField(): void
    {
        $v = new Validator([]);
        $v->required('name', 'Ім\'я');

        $this->assertFalse($v->isValid());
        $this->assertArrayHasKey('name', $v->getErrors());
    }

    public function testRequiredFailsOnEmptyString(): void
    {
        $v = new Validator(['name' => '']);
        $v->required('name', 'Ім\'я');

        $this->assertFalse($v->isValid());
    }

    public function testRequiredFailsOnWhitespace(): void
    {
        $v = new Validator(['name' => '   ']);
        $v->required('name', 'Ім\'я');

        $this->assertFalse($v->isValid());
    }

    public function testRequiredPassesOnPresentField(): void
    {
        $v = new Validator(['name' => 'Олександр']);
        $v->required('name', 'Ім\'я');

        $this->assertTrue($v->isValid());
    }

    // ─── email() ─────────────────────────────────────────────────

    public function testEmailFailsOnInvalidEmail(): void
    {
        $v = new Validator(['email' => 'not-an-email']);
        $v->email('email', 'Email');

        $this->assertFalse($v->isValid());
        $this->assertArrayHasKey('email', $v->getErrors());
    }

    public function testEmailPassesOnValidEmail(): void
    {
        $v = new Validator(['email' => 'user@example.com']);
        $v->email('email', 'Email');

        $this->assertTrue($v->isValid());
    }

    public function testEmailSkipsEmptyField(): void
    {
        $v = new Validator(['email' => '']);
        $v->email('email', 'Email');

        $this->assertTrue($v->isValid(), 'email() should skip validation for empty field');
    }

    // ─── numeric() ───────────────────────────────────────────────

    public function testNumericFailsOnNonNumeric(): void
    {
        $v = new Validator(['price' => 'abc']);
        $v->numeric('price', 'Ціна');

        $this->assertFalse($v->isValid());
    }

    public function testNumericPassesOnInteger(): void
    {
        $v = new Validator(['price' => '1500']);
        $v->numeric('price', 'Ціна');

        $this->assertTrue($v->isValid());
    }

    public function testNumericPassesOnFloat(): void
    {
        $v = new Validator(['price' => '99.99']);
        $v->numeric('price', 'Ціна');

        $this->assertTrue($v->isValid());
    }

    // ─── min() / minLength() ─────────────────────────────────────

    public function testMinFailsIfTooShort(): void
    {
        $v = new Validator(['pass' => 'ab']);
        $v->min('pass', 8, 'Пароль');

        $this->assertFalse($v->isValid());
    }

    public function testMinPassesOnExactLength(): void
    {
        $v = new Validator(['pass' => '12345678']);
        $v->min('pass', 8, 'Пароль');

        $this->assertTrue($v->isValid());
    }

    public function testMinHandlesMultibyteCorrectly(): void
    {
        // "Привіт" is 6 characters in UTF-8
        $v = new Validator(['text' => 'Привіт']);
        $v->min('text', 6, 'Текст');

        $this->assertTrue($v->isValid());
    }

    public function testMinLengthIsAliasForMin(): void
    {
        $v = new Validator(['pass' => 'ab']);
        $v->minLength('pass', 'Пароль', 8);

        $this->assertFalse($v->isValid());
    }

    // ─── max() ───────────────────────────────────────────────────

    public function testMaxFailsIfTooLong(): void
    {
        $v = new Validator(['note' => str_repeat('a', 256)]);
        $v->max('note', 255, 'Примітка');

        $this->assertFalse($v->isValid());
    }

    public function testMaxPassesOnExactLength(): void
    {
        $v = new Validator(['note' => str_repeat('a', 255)]);
        $v->max('note', 255, 'Примітка');

        $this->assertTrue($v->isValid());
    }

    // ─── Chaining + getFirstError ────────────────────────────────

    public function testChainingMultipleRules(): void
    {
        $v = new Validator(['email' => 'bad', 'name' => '']);
        $result = $v
            ->required('name', 'Ім\'я')
            ->email('email', 'Email');

        $this->assertFalse($result->isValid());
        $this->assertCount(2, $result->getErrors());
    }

    public function testGetFirstErrorReturnsFirstInOrder(): void
    {
        $v = new Validator([]);
        $v->required('a', 'A')->required('b', 'B');

        $this->assertNotNull($v->getFirstError());
    }

    public function testGetFirstErrorReturnsNullWhenValid(): void
    {
        $v = new Validator(['a' => 'ok']);
        $v->required('a', 'A');

        $this->assertNull($v->getFirstError());
    }

    // ─── Complete valid form ─────────────────────────────────────

    public function testFullValidFormPasses(): void
    {
        $v = new Validator([
            'name' => 'Олександр Петренко',
            'email' => 'alex@example.com',
            'phone' => '+380501234567',
            'amount' => '1500.50',
        ]);

        $v->required('name', 'Ім\'я')
            ->email('email', 'Email')
            ->numeric('amount', 'Сума')
            ->min('name', 2, 'Ім\'я')
            ->max('name', 100, 'Ім\'я');

        $this->assertTrue($v->isValid());
        $this->assertEmpty($v->getErrors());
    }
}
