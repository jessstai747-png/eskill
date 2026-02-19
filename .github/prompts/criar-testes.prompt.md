---
description: "Cria testes PHPUnit completos para um service, controller ou model"
agent: Implementador
tools:
  - codebase
  - editFiles
  - runInTerminal
  - problems
  - search
  - usages
---

Crie testes PHPUnit COMPLETOS para o código especificado.

## ANTES de criar testes:
- Leia `project-status.json` para entender o contexto da feature
- Leia `claude-progress.txt` para contexto recente

## DEPOIS de criar testes:
- Atualize `claude-progress.txt` → adicione entrada NO TOPO
- Faça `git commit -m "test: testes para [componente]"`

## Aja como um engenheiro de QA sênior:
- Analise o código fonte para entender todos os fluxos
- Crie testes para happy path E edge cases
- Use Faker para dados de teste
- Rode os testes automaticamente

## Workflow:

1. **Leia o código** — Entenda métodos, dependências, e fluxos
2. **Identifique cenários** — Happy path, errors, edge cases, null values
3. **Crie o test file** — Em `tests/` com namespace correto
4. **Implemente testes** — Com @covers annotation e nomes descritivos
5. **Rode** — `php vendor/bin/phpunit --filter NomeDoTest`
6. **Corrija** — Se falhar, corrija até passar

## Padrões de Teste:

```php
<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\XxxService
 */
class XxxServiceTest extends TestCase
{
    // test_nomeDoMetodo_cenario_resultadoEsperado
    public function test_methodName_withValidData_returnsExpectedResult(): void
    {
        // Arrange
        // Act
        // Assert
    }
}
```

## Cenários OBRIGATÓRIOS:
- ✅ Input válido → resultado esperado
- ❌ Input inválido → exception ou erro tratado
- 🔲 Input vazio/null → comportamento definido
- 🔄 Edge cases → valores limite, strings grandes, zero, negativo

## Output OBRIGATÓRIO:

### ✅ Testes Criados
| Arquivo | Testes | Cenários |
|---------|--------|----------|

### ✔️ Resultado: X testes, X assertions, 0 failures
### 🔮 Próximos Passos
1. [adicionar testes de integração se aplicável]
2. [verificar cobertura: `phpunit --coverage-text`]
3. [adicionar ao CI/CD pipeline]
