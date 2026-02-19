---
description: "Refatora código PHP mantendo a mesma funcionalidade — melhora legibilidade, performance e tipagem"
agent: Implementador
tools:
  - codebase
  - runInTerminal
  - editFiles
  - problems
  - usages
  - search
---

Refatore o código especificado mantendo a mesma funcionalidade.

## ANTES de refatorar:
- Leia `claude-progress.txt` e `project-status.json` para contexto
- Rode testes ANTES para garantir baseline
- Verifique usos com `#usages` para não quebrar dependências

## DEPOIS de refatorar:
- Atualize `claude-progress.txt` → adicione entrada NO TOPO
- Faça `git commit -m "refactor: [descrição]"`

## Workflow:

1. **Leia o código** — Entenda completamente o que ele faz
2. **Identifique usos** — Use `#usages` para ver onde é usado/importado
3. **Rode testes ANTES** — `php vendor/bin/phpunit` para garantir baseline
4. **Refatore** — Aplique as melhorias necessárias
5. **Rode testes DEPOIS** — Confirme que nada quebrou
6. **Syntax check** — `php -l arquivo.php`

## Melhorias em ordem de prioridade:
1. Adicionar `declare(strict_types=1)` se faltar
2. Adicionar type hints completos (parâmetros e retorno)
3. Remover `mixed` e substituir por tipos específicos
4. Extrair lógica duplicada para Services ou Traits
5. Simplificar condicionais (usar match(), null coalescing, early return)
6. Melhorar nomes de variáveis e métodos
7. Adicionar error handling com try/catch onde falta
8. Mover lógica de negócio de Controller para Service
9. Remover código morto (var_dump, echo, código comentado)
10. Otimizar queries SQL (N+1, índices faltando)

## Regras:
- NUNCA mude o comportamento externo
- NUNCA refatore código que não foi pedido
- Se não existirem testes, CRIE testes antes de refatorar
- Mantenha todos os namespaces e autoloading funcionando
- Se renomear classes/métodos, atualize TODOS os usos

## Output OBRIGATÓRIO (ao final):

### ✅ Refatorado
| Arquivo | Mudança | Impacto |
|---------|---------|---------|

### ✔️ Validação: `php -l` OK | phpunit ANTES=OK DEPOIS=OK
### 🔮 Próximos Passos
1. [verificar se não há breaking changes em usos dependentes]
2. [rodar suite completa de testes]
3. [considerar refatorações adicionais identificadas]
