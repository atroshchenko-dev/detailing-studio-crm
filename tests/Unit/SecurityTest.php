<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

use function App\e;

/**
 * Unit tests for the global e() XSS escape helper
 * defined in App\Security (Security.php)
 *
 * Note: Security::sendHeaders() mutates HTTP headers and
 * is not unit-testable without a web SAPI or integration harness.
 */
class SecurityTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Ensure Security.php (and the e() function) is loaded
        require_once __DIR__ . '/../../includes/Security.php';
    }

    // ─── e() XSS escape ─────────────────────────────────────────

    public function testEscapesHtmlTags(): void
    {
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
    }

    public function testEscapesDoubleQuotes(): void
    {
        $this->assertSame('value=&quot;x&quot;', e('value="x"'));
    }

    public function testEscapesSingleQuotes(): void
    {
        $this->assertSame('it&#039;s', e("it's"));
    }

    public function testEscapesAmpersand(): void
    {
        $this->assertSame('a &amp; b', e('a & b'));
    }

    public function testHandlesNull(): void
    {
        $this->assertSame('', e(null));
    }

    public function testHandlesEmptyString(): void
    {
        $this->assertSame('', e(''));
    }

    public function testPreservesPlainText(): void
    {
        $this->assertSame('Hello World', e('Hello World'));
    }

    public function testHandlesUkrainianText(): void
    {
        $input = 'Привіт, Олександре!';
        $this->assertSame($input, e($input));
    }

    public function testHandlesMixedContent(): void
    {
        $this->assertSame(
            'Ціна: &lt;b&gt;1500&lt;/b&gt; грн',
            e('Ціна: <b>1500</b> грн')
        );
    }

    public function testHandlesNestedQuotes(): void
    {
        $this->assertSame(
            '&lt;a href=&quot;x&quot; onclick=&quot;alert(&#039;xss&#039;)&quot;&gt;',
            e('<a href="x" onclick="alert(\'xss\')">')
        );
    }
}
