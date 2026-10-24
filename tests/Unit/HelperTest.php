<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Utils\Helper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for App\Utils\Helper
 *
 * Covers pure formatting helpers. processImage() is excluded —
 * it needs real uploaded files and the GD extension.
 */
class HelperTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // ─── formatPhone() ───────────────────────────────────────────

    /** @return array<string, array{?string, ?string}> */
    public static function phoneProvider(): array
    {
        return [
            'already normalized'   => ['+380501234567', '+380501234567'],
            'local 10 digits'      => ['0501234567', '+380501234567'],
            'local with spaces'    => ['050 123 45 67', '+380501234567'],
            'local with dashes'    => ['(050) 123-45-67', '+380501234567'],
            'country code no plus' => ['380501234567', '+380501234567'],
            'other international'  => ['48123456789', '+48123456789'],
            'too short kept as is' => ['12345', '12345'],
            'empty string'         => ['', null],
            'null'                 => [null, null],
        ];
    }

    #[DataProvider('phoneProvider')]
    public function testFormatPhone(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, Helper::formatPhone($input));
    }

    // ─── maskPhone() / displayPhone() ────────────────────────────

    public function testMaskPhoneShowsOnlyLastTwoDigits(): void
    {
        $this->assertSame('**********67', Helper::maskPhone('+380501234567'));
    }

    public function testMaskPhoneHidesShortNumbersCompletely(): void
    {
        $this->assertSame('****', Helper::maskPhone('1234'));
    }

    public function testMaskPhoneReturnsDashForEmpty(): void
    {
        $this->assertSame('—', Helper::maskPhone(null));
        $this->assertSame('—', Helper::maskPhone(''));
    }

    public function testDisplayPhoneShowsFullNumberToManagers(): void
    {
        $_SESSION['user_role'] = 'manager';
        $this->assertSame('+380501234567', Helper::displayPhone('+380501234567'));
    }

    public function testDisplayPhoneEscapesHtmlForManagers(): void
    {
        $_SESSION['user_role'] = 'director';
        $this->assertSame('&lt;b&gt;', Helper::displayPhone('<b>'));
    }

    public function testDisplayPhoneMasksNumberForExecutors(): void
    {
        $_SESSION['user_role'] = 'washer';
        $this->assertSame('**********67', Helper::displayPhone('+380501234567'));
    }

    public function testDisplayPhoneMasksNumberForGuests(): void
    {
        $this->assertSame('**********67', Helper::displayPhone('+380501234567'));
    }

    // ─── formatCurrency() / formatDate() ─────────────────────────

    public function testFormatCurrency(): void
    {
        $this->assertSame('1 234.50 ₴', Helper::formatCurrency(1234.5));
        $this->assertSame('0.00 ₴', Helper::formatCurrency(null));
        $this->assertSame('99.90 ₴', Helper::formatCurrency('99.9'));
    }

    public function testFormatDate(): void
    {
        $this->assertSame('24.10.2026 12:30', Helper::formatDate('2026-10-24 12:30:00'));
        $this->assertSame('24.10.2026', Helper::formatDate('2026-10-24', 'd.m.Y'));
        $this->assertSame('', Helper::formatDate(null));
        $this->assertSame('', Helper::formatDate(''));
    }

    // ─── getBadgeClass() ─────────────────────────────────────────

    public function testBadgeClassForKnownValues(): void
    {
        $this->assertSame('bg-success', Helper::getBadgeClass('status', 'closed'));
        $this->assertSame('bg-danger', Helper::getBadgeClass('priority', 'high'));
    }

    public function testBadgeClassFallsBackToSecondary(): void
    {
        $this->assertSame('bg-secondary', Helper::getBadgeClass('status', 'unknown'));
        $this->assertSame('bg-secondary', Helper::getBadgeClass('unknown', 'new'));
    }

    // ─── Number generators ───────────────────────────────────────

    public function testGenerateNumberFormat(): void
    {
        $this->assertMatchesRegularExpression('/^INV-[0-9A-Z]{8}$/', Helper::generateNumber('INV', 8));
        $this->assertMatchesRegularExpression('/^ORD-[0-9A-Z]{5}$/', Helper::generateOrderNumber());
    }

    public function testGeneratePurchaseRequestNumberFormat(): void
    {
        $this->assertMatchesRegularExpression(
            '/^PUR-' . date('Ymd') . '-[0-9A-F]{6}$/',
            Helper::generatePurchaseRequestNumber()
        );
    }

    // ─── emptyToNull() ───────────────────────────────────────────

    public function testEmptyToNull(): void
    {
        $this->assertNull(Helper::emptyToNull(''));
        $this->assertNull(Helper::emptyToNull(null));
        $this->assertSame('0', Helper::emptyToNull('0'));
        $this->assertSame(0, Helper::emptyToNull(0));
        $this->assertSame('text', Helper::emptyToNull('text'));
    }
}
