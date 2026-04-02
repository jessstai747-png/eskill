<?php

declare(strict_types=1);

namespace Tests\Unit\Views;

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ModernSidebarViewTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $path = dirname(__DIR__, 3) . '/app/Views/layouts/modern/sidebar.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->source = $contents;
    }

    public function testDeveMapearPerfisDeGestaoParaNavegacaoAWA(): void
    {
        $this->assertStringContainsString('$sessionUserRole = (string) (', $this->source);
        $this->assertStringContainsString('$isManager = $sessionUserRole === \'manager\';', $this->source);
        $this->assertStringContainsString('<?php if ($isAdmin || $isManager || $isViewer): ?>', $this->source);
    }

    public function testDeveExibirAtalhoDoModuloAWASellersNoMenuDeInteligencia(): void
    {
        $this->assertStringContainsString('href="/dashboard/awa-sellers"', $this->source);
        $this->assertStringContainsString('<span>AWA Sellers</span>', $this->source);
        $this->assertStringContainsString('<span class="nav-badge">RO</span>', $this->source);
    }
}
