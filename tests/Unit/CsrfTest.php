<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Csrf;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for App\Csrf
 *
 * verify() and verifyApi() are excluded on failure paths —
 * they send headers and call exit.
 */
class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testTokenIsGeneratedOnceAndReused(): void
    {
        $token = Csrf::getToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, Csrf::getToken());
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    public function testCheckAcceptsValidToken(): void
    {
        $this->assertTrue(Csrf::check(Csrf::getToken()));
    }

    public function testCheckRejectsWrongToken(): void
    {
        Csrf::getToken();
        $this->assertFalse(Csrf::check(str_repeat('0', 64)));
    }

    public function testCheckRejectsEmptyToken(): void
    {
        Csrf::getToken();
        $this->assertFalse(Csrf::check(''));
    }

    public function testCheckRejectsWhenSessionHasNoToken(): void
    {
        $this->assertFalse(Csrf::check('anything'));
    }

    public function testInputAndMetaContainToken(): void
    {
        $token = Csrf::getToken();

        $this->assertSame('<input type="hidden" name="csrf_token" value="' . $token . '">', Csrf::input());
        $this->assertSame('<meta name="csrf-token" content="' . $token . '">', Csrf::meta());
    }

    public function testVerifyApiSkipsNonPostRequests(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertTrue(Csrf::verifyApi());
    }

    public function testVerifyApiAcceptsHeaderToken(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::getToken();

        $this->assertTrue(Csrf::verifyApi());

        unset($_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_X_CSRF_TOKEN']);
    }
}
