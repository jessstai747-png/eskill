---
description: "Analisa segurança do projeto — SQL injection, XSS, secrets, validação, autenticação"
agent: Revisor
tools:
  - codebase
  - runInTerminal
  - problems
  - search
  - usages
---

Faça uma análise completa de SEGURANÇA do projeto PHP.

## ANTES de analisar:
- Leia `project-status.json` para entender as features e seu status
- Leia `claude-progress.txt` para ver mudanças recentes (podem ter introduzido vulnerabilidades)
- Rode `bash bin/init.sh` para visão geral do ambiente

## Aja como um engenheiro de segurança sênior:
- Examine TODOS os vetores de ataque comuns
- Classifique cada vulnerabilidade por severidade
- Mostre exatamente como corrigir cada uma

## Verificações OBRIGATÓRIAS:

### 1. SQL Injection
```bash
grep -rn "->query\|->exec" app/ --include='*.php' | grep -v "prepare"
grep -rn '"\$\|\.\ \$' app/ --include='*.php' | grep -i "select\|insert\|update\|delete"
```

### 2. XSS
```bash
grep -rn "echo \$\|print \$" app/Views/ --include='*.php' | grep -v "htmlspecialchars\|htmlentities"
```

### 3. Secrets Hardcoded
```bash
grep -rn "password\|secret\|token\|api_key" app/ config/ --include='*.php' | grep -v "getenv\|\$_ENV\|\.env"
```

### 4. Validação de Input
- Controllers sem validação de `$_GET`, `$_POST`, `$_REQUEST`
- Dados do usuário usados diretamente sem sanitização

### 5. Autenticação/Autorização
- Rotas sem middleware de auth
- Sessões sem proteção contra fixation
- Tokens sem expiração

### 6. Configuração
- `display_errors` em produção
- Headers de segurança (CSP, X-Frame-Options, etc.)
- HTTPS enforcement
- CORS configurado corretamente

### 7. Dependências
```bash
composer audit 2>&1
```

## Output OBRIGATÓRIO:

### 🔒 RELATÓRIO DE SEGURANÇA

| Severidade | Quantidade | Status |
|------------|------------|--------|
| 🔴 Crítico | X | Corrigir AGORA |
| 🟠 Alto | X | Corrigir em breve |
| 🟡 Médio | X | Planejar correção |
| 🟢 Baixo | X | Quando possível |

### Detalhes por vulnerabilidade
[tabela com arquivo, linha, tipo, severidade, como corrigir]

### 🔮 Próximos Passos
1. **[Urgente]** — Corrigir vulnerabilidades 🔴 imediatamente
2. **[Importante]** — Corrigir 🟠 na próxima sprint
3. **[Prevenção]** — Adicionar middleware de validação global
4. **[Monitoramento]** — Configurar alertas para tentativas de ataque
