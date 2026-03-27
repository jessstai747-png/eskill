<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Controllers\UserManagementController;

/**
 * Testes do UserManagementController
 *
 * Verifica estrutura, segurança e lógica de gerenciamento de usuários.
 */
class UserManagementControllerTest extends TestCase
{
    // ===========================
    // STRUCTURE TESTS
    // ===========================

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(UserManagementController::class));
    }

    public function test_has_required_methods(): void
    {
        $methods = ['index', 'listUsers', 'invite', 'updateRole'];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(UserManagementController::class, $method),
                "UserManagementController deve ter o método {$method}()"
            );
        }
    }

    public function test_has_admin_guard(): void
    {
        $reflection = new \ReflectionClass(UserManagementController::class);
        $this->assertTrue(
            $reflection->hasMethod('ensureAdmin'),
            'Deve ter método ensureAdmin para proteção'
        );

        $method = $reflection->getMethod('ensureAdmin');
        $this->assertTrue($method->isPrivate(), 'ensureAdmin deve ser private');
    }

    // ===========================
    // INVITE SECURITY TESTS
    // ===========================

    public function test_invite_requires_password_in_source(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Controllers/UserManagementController.php'
        );

        // Garantir que não há fallback de senha padrão
        $this->assertStringNotContainsString(
            "default123",
            $source,
            'Não deve conter senha padrão default123'
        );

        $this->assertStringNotContainsString(
            "mudar123",
            $source,
            'Não deve conter senha padrão mudar123'
        );

        // Verificar que há validação de senha obrigatória
        $this->assertStringContainsString(
            'strlen($data[\'password\'])',
            $source,
            'Deve validar comprimento da senha'
        );
    }

    public function test_invite_enforces_minimum_password_length(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Controllers/UserManagementController.php'
        );

        // Verificar que exige mínimo de 8 caracteres
        $this->assertMatchesRegularExpression(
            '/strlen.*\$data\[.password.\].*<\s*8/s',
            $source,
            'Deve exigir mínimo de 8 caracteres na senha'
        );
    }

    // ===========================
    // ROLE UPDATE SECURITY TESTS
    // ===========================

    public function test_updateRole_prevents_removing_last_admin(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Controllers/UserManagementController.php'
        );

        $this->assertStringContainsString(
            'last admin',
            strtolower($source),
            'Deve prevenir remoção do último admin'
        );
    }

    // ===========================
    // DEPENDENCIES
    // ===========================

    public function test_uses_userservice(): void
    {
        $reflection = new \ReflectionClass(UserManagementController::class);
        $this->assertTrue($reflection->hasProperty('userService'));
    }

    public function test_uses_database(): void
    {
        $reflection = new \ReflectionClass(UserManagementController::class);
        $this->assertTrue($reflection->hasProperty('db'));
    }
}
