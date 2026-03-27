<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\EmailService;

/**
 * Testes do EmailService
 */
class EmailServiceTest extends TestCase
{
    private EmailService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailService();
    }

    // =============================
    // TESTES DE INSTANCIAÇÃO
    // =============================

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(EmailService::class, $this->service);
    }

    // =============================
    // TESTES DE MÉTODOS
    // =============================

    public function testHasRequiredMethods(): void
    {
        $methods = [
            'isEnabled',
            'send',
            'sendPasswordReset',
            'sendVerification',
            'sendWelcome',
            'sendNewOrderNotification',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->service, $method),
                "EmailService deve ter método {$method}()"
            );
        }
    }

    // =============================
    // TESTES DE isEnabled
    // =============================

    public function testIsEnabledReturnsBool(): void
    {
        $result = $this->service->isEnabled();
        $this->assertIsBool($result);
    }

    // =============================
    // TESTES DE ESTRUTURA
    // =============================

    public function testHasConfigProperty(): void
    {
        $reflection = new \ReflectionClass(EmailService::class);
        $this->assertTrue($reflection->hasProperty('config'));
    }

    public function testHasEnabledProperty(): void
    {
        $reflection = new \ReflectionClass(EmailService::class);
        $this->assertTrue($reflection->hasProperty('enabled'));
    }

    // =============================
    // TESTES DE PARÂMETROS
    // =============================

    public function testSendAcceptsRequiredParameters(): void
    {
        $reflection = new \ReflectionMethod(EmailService::class, 'send');
        $params = $reflection->getParameters();

        $this->assertGreaterThanOrEqual(3, count($params));
        $this->assertEquals('to', $params[0]->getName());
        $this->assertEquals('subject', $params[1]->getName());
        $this->assertEquals('message', $params[2]->getName());
    }

    public function testSendHasTypeParameter(): void
    {
        $reflection = new \ReflectionMethod(EmailService::class, 'send');
        $params = $reflection->getParameters();

        // Verificar se existe parâmetro type (4º parâmetro)
        if (count($params) > 3) {
            $this->assertEquals('type', $params[3]->getName());
            $this->assertTrue($params[3]->isDefaultValueAvailable());
            $this->assertEquals('html', $params[3]->getDefaultValue());
        }
    }

    public function testSendPasswordResetAcceptsParameters(): void
    {
        $reflection = new \ReflectionMethod(EmailService::class, 'sendPasswordReset');
        $params = $reflection->getParameters();

        $this->assertGreaterThanOrEqual(4, count($params));
        $this->assertEquals('to', $params[0]->getName());
        $this->assertEquals('name', $params[1]->getName());
        $this->assertEquals('resetToken', $params[2]->getName());
        $this->assertEquals('resetUrl', $params[3]->getName());
    }

    // =============================
    // TESTES DE RETORNO
    // =============================

    public function testSendReturnsBool(): void
    {
        $reflection = new \ReflectionMethod(EmailService::class, 'send');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    public function testIsEnabledReturnsBoolType(): void
    {
        $reflection = new \ReflectionMethod(EmailService::class, 'isEnabled');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    public function testSendPasswordResetReturnsBool(): void
    {
        $reflection = new \ReflectionMethod(EmailService::class, 'sendPasswordReset');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    // =============================
    // TESTES DE DOCUMENTAÇÃO
    // =============================

    public function testSendHasDocumentation(): void
    {
        $reflection = new \ReflectionMethod(EmailService::class, 'send');
        $docComment = $reflection->getDocComment();

        $this->assertNotFalse($docComment);
    }

    public function testIsEnabledHasDocumentation(): void
    {
        $reflection = new \ReflectionMethod(EmailService::class, 'isEnabled');
        $docComment = $reflection->getDocComment();

        $this->assertNotFalse($docComment);
    }

    // =============================
    // TESTES DE COMPORTAMENTO (quando desabilitado)
    // =============================

    public function testSendReturnsFalseWhenDisabled(): void
    {
        // EmailService retorna false quando desabilitado
        // Isso é comportamento esperado em ambiente de teste
        $result = $this->service->send('test@example.com', 'Test', 'Test message');

        // Se não está habilitado, deve retornar false
        if (!$this->service->isEnabled()) {
            $this->assertFalse($result);
        } else {
            // Se está habilitado, o resultado pode ser true ou false dependendo da config
            $this->assertIsBool($result);
        }
    }
}
