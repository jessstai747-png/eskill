---
description: "Implementa uma feature completa de ponta a ponta — service, controller, migration, rota, testes"
agent: Implementador
tools:
  - codebase
  - editFiles
  - runInTerminal
  - problems
  - search
  - usages
  - fetch
---

Implemente a feature descrita de forma COMPLETA e FUNCIONAL.

## ANTES de implementar:
- Leia `claude-progress.txt` para contexto do projeto
- Leia `project-status.json` para ver se a feature já existe e seu status
- Rode `bash bin/init.sh` para smoke test do ambiente
- Trabalhe em UMA feature por vez

## DEPOIS de implementar:
- Atualize `project-status.json` → marque `"passes": true` na feature
- Atualize `claude-progress.txt` → adicione entrada NO TOPO
- Faça `git commit` com mensagem descritiva

## Aja como um engenheiro sênior autônomo:
- Analise o codebase existente antes de implementar
- Tome decisões técnicas usando best practices (não pergunte)
- Implemente TUDO: service, controller, migration, rota
- Valide com `php -l` e `phpunit` automaticamente
- Corrija erros encontrados sem perguntar

## Checklist OBRIGATÓRIO:

1. **Explorar** — Leia os arquivos relevantes com `#codebase` e `#usages`
2. **Migration** — Se precisa de tabela nova → `database/migrations/`
3. **Model** — Se precisa de acesso a dados → `app/Models/` com PDO + prepared statements
4. **Service** — Lógica de negócio em `app/Services/` com type hints completos
5. **Controller** — Em `app/Controllers/` com validação de input e JSON responses
6. **Rota** — Registrar em `app/Routes/`
7. **Worker** — Se precisa de background → `bin/`
8. **Validar** — `php -l` em CADA arquivo + `phpunit`

## Padrões PHP 8+:
- `declare(strict_types=1)` em todo arquivo
- Type hints completos (parâmetros e retorno)
- Error handling com try/catch e Monolog
- PSR-4: `App\` → `app/`
- Controller → Service → Model

## Output OBRIGATÓRIO (ao final):

### ✅ Implementado
| Arquivo | Ação | Descrição |
|---------|------|-----------|

### ✔️ Validação: `php -l` OK | phpunit OK
### 💡 Decisões: [por que escolheu cada abordagem]
### 🔮 Próximos Passos
1. [ação imediata mais importante]
2. [melhoria recomendada]
3. [monitoramento pós-deploy]
4. [evolução futura]
