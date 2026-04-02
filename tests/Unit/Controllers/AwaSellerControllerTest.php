<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\AwaSellerController;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Controllers\AwaSellerController
 */
class AwaSellerControllerTest extends TestCase
{
    private string $source;

    protected function setUp(): void
    {
        parent::setUp();

        $path = dirname(__DIR__, 3) . '/app/Controllers/AwaSellerController.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->source = $contents;
    }

    public function testDeveExistirComoControllerBase(): void
    {
        $this->assertTrue(class_exists(AwaSellerController::class));
        $this->assertTrue(is_subclass_of(AwaSellerController::class, 'App\\Controllers\\BaseController'));
    }

    public function testDeveExigirPermissaoDeAuditoriaParaAcessoLeitura(): void
    {
        $this->assertStringContainsString("private const VIEW_PERMISSION = 'audit';", $this->source);
        $this->assertStringContainsString('private function ensureViewPermission(): void', $this->source);
        $this->assertStringContainsString('Acesso negado. Permissão de auditoria necessária para acessar AWA Sellers.', $this->source);
        $this->assertStringContainsString("'required_role' => 'admin, manager ou viewer'", $this->source);
        $this->assertStringContainsString("'canViewAwaSellers' => \$canViewAwaSellers", $this->source);
    }

    public function testDeveExigirPermissaoDeGestaoParaAcoesMutaveis(): void
    {
        $this->assertStringContainsString("private const MANAGE_PERMISSION = 'manager';", $this->source);
        $this->assertStringContainsString('private function ensureManagePermission(): void', $this->source);
        $this->assertStringContainsString('Acesso negado. Permissão de gestão necessária para executar ações no módulo AWA Sellers.', $this->source);
        $this->assertStringContainsString("'required_role' => 'admin ou manager'", $this->source);
        $this->assertStringContainsString('$this->ensureManagePermission();', $this->source);
    }
}
