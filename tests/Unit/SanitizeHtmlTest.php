<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\BlogController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class SanitizeHtmlTest extends TestCase
{
    private function sanitize(string $html): string
    {
        $controller = new BlogController();
        $method = new ReflectionMethod(BlogController::class, 'sanitizeHtml');
        $method->setAccessible(true);

        return (string) $method->invoke($controller, $html);
    }

    public function testKeepsParagraphContent(): void
    {
        $this->assertSame('<p>Hola</p>', $this->sanitize('<p>Hola</p>'));
    }

    public function testStripsDisallowedTagsButPreservesText(): void
    {
        $this->assertSame('Hola', $this->sanitize('<script>alert(1)</script>Hola'));
    }
}
