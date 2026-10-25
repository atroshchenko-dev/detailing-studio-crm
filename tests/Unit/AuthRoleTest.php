<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the session-based role checks in App\Auth
 *
 * Login, remember-me and requireAuth() are excluded — they need
 * the database and send redirects.
 */
class AuthRoleTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testGuestWhenNoRoleInSession(): void
    {
        $_SESSION = [];
        $this->assertSame('guest', Auth::getUserRole());
        $this->assertFalse(Auth::isDirector());
    }

    public function testHasRoleAndHasAnyRole(): void
    {
        $_SESSION['user_role'] = 'manager';

        $this->assertTrue(Auth::isManager());
        $this->assertTrue(Auth::hasAnyRole(['director', 'manager']));
        $this->assertFalse(Auth::hasAnyRole(['director']));
    }

    public function testExecutorGroupIncludesMechanicAndWasher(): void
    {
        foreach (['executor', 'mechanic', 'washer'] as $role) {
            $_SESSION['user_role'] = $role;
            $this->assertTrue(Auth::isExecutor(), $role);
        }

        $_SESSION['user_role'] = 'manager';
        $this->assertFalse(Auth::isExecutor());
    }

    /** @return array<string, array{string, string, bool}> */
    public static function hierarchyProvider(): array
    {
        return [
            'director over manager'   => ['director', 'manager', true],
            'manager over executor'   => ['manager', 'executor', true],
            'manager equals manager'  => ['manager', 'manager', true],
            'washer below manager'    => ['washer', 'manager', false],
            'manager below director'  => ['manager', 'director', false],
            'guest below executor'    => ['guest', 'executor', false],
            'unknown required role'   => ['director', 'superadmin', false],
        ];
    }

    #[DataProvider('hierarchyProvider')]
    public function testIsAtLeast(string $current, string $required, bool $expected): void
    {
        $_SESSION['user_role'] = $current;
        $this->assertSame($expected, Auth::isAtLeast($required));
    }
}
