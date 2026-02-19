---
description: "Diagnostica e corrige um bug PHP — encontra causa raiz antes de aplicar fix"
agent: Debugger
tools:
  - codebase
  - runInTerminal
  - editFiles
  - problems
  - usages
  - search
---

Diagnostique e corrija o bug descrito.

## ANTES de debugar:
- Leia `claude-progress.txt` para ver mudanças recentes (podem ser a causa)
- Rode `git log --oneline -10` e `git diff HEAD~1` para detectar regressões
- Verifique `project-status.json` para o status da feature afetada
- Leia os logs em `storage/logs/`

## DEPOIS de corrigir:
- Atualize `project-status.json` se a feature voltou a funcionar
- Atualize `claude-progress.txt` com descrição do bug e fix
- Faça `git commit -m "fix: [descrição]"`

## Workflow OBRIGATÓRIO:

1. **Reproduza** — Entenda exatamente o que está errado
2. **Leia os logs** — Verifique `storage/logs/` (Monolog) para stack traces
3. **Localize** — Encontre o arquivo e linha do problema
4. **Analise dependências** — Use `#usages` para ver quem chama esse código
5. **Identifique a causa raiz** — Não o sintoma, a CAUSA
6. **Corrija** — Aplique o fix MÍNIMO necessário
7. **Teste** — Rode `php -l arquivo.php` e `php vendor/bin/phpunit`
8. **Explique** — Descreva o que causou o bug e como foi corrigido

## Técnicas de Diagnóstico PHP:
- Verifique logs Monolog em `storage/logs/`
- Use `php -l` para erros de sintaxe
- Verifique queries SQL (prepared statements, PDO errors)
- Verifique conexões externas (Guzzle timeouts, API rates)
- Verifique Redis (conexão, keys expiradas)
- Cheque variáveis de ambiente (.env)
- Use `git log` e `git diff` para mudanças recentes

## Regras:
- NUNCA aplique fix sem entender a causa raiz
- NUNCA refatore código que não está relacionado ao bug
- Se for um workaround, diga explicitamente
- Corrija o mínimo necessário
- Se o fix pode quebrar outra coisa, avise

## Output OBRIGATÓRIO (ao final):

### 🐛 Diagnóstico: [causa raiz em 1 frase]
### 🔧 Fix: [o que foi mudado em 1 frase]
### ✔️ Validação: `php -l` OK | phpunit OK
### 🔮 Próximos Passos
1. [verificação pós-fix]
2. [teste a criar]
3. [prevenção futura]
