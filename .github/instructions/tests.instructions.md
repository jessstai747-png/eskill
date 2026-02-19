---
applyTo: "**/*Test.php,**/tests/**/*.php"
---

# Regras para Testes PHPUnit

## Framework
- PHPUnit 9 + Faker para dados de teste
- Rodar: `php vendor/bin/phpunit`

## Estrutura do Arquivo de Teste
```php
<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\ExemploService
 */
class ExemploServiceTest extends TestCase
{
    private ExemploService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // setup comum
    }

    public function testDeveRetornarDadosQuandoInputValido(): void
    {
        // Arrange
        // Act
        // Assert
    }

    public function testDeveLancarExcecaoQuandoInputInvalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Act
    }
}
```

## Padrões
- Testar COMPORTAMENTO, não implementação
- Nome do teste: `testDeve[Comportamento]Quando[Condicao]`
- Um assert por teste (preferencialmente)
- Mock apenas o necessário (APIs externas, banco) com `createMock()`
- Testar happy path E error cases
- Testar edge cases (null, array vazio, string vazia, zero)
- Usar `@covers` annotation para rastreabilidade
- Usar Faker para gerar dados de teste realísticos

## Para Services com API externa
- Mockar o HTTP client (Guzzle) com `createMock()`
- Testar retorno de sucesso
- Testar tratamento de erros (401, 429, 500)
- Testar retry logic

## NUNCA
- Testes que dependem de ordem de execução
- Testes que acessam banco de dados real sem transação
- `$this->assertTrue(true)` — testes sem valor
- Testes que passam mesmo com o código errado
- `var_dump` ou `echo` nos testes (use assertions)
