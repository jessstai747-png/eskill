---
description: "Faz uma auditoria completa do projeto PHP — estrutura, dependências, tipagem, segurança, performance"
agent: agent
tools:
  - codebase
  - runInTerminal
  - problems
  - search
  - usages
---

Faça uma auditoria completa deste projeto PHP.

## ANTES de auditar:
- Leia `project-status.json` para entender features e seus status
- Leia `claude-progress.txt` para entender mudanças recentes
- Rode `bash bin/init.sh` para visão geral do sistema

## Verificações:

### 1. Estrutura
- Listar estrutura de pastas (`app/`, `config/`, `bin/`, etc.)
- Verificar organização MVC (Controllers, Services, Models separados)
- Identificar arquivos órfãos ou não utilizados
- Verificar PSR-4 autoloading

### 2. Dependências
- `composer audit` para vulnerabilidades
- `composer outdated` para dependências desatualizadas
- Identificar dependências não utilizadas
- Verificar conflitos de versão no `composer.lock`

### 3. PHP / Tipagem
- Buscar arquivos sem `declare(strict_types=1)`
- Buscar por `mixed` sem justificativa: `grep -r ': mixed' app/`
- Verificar type hints faltando em parâmetros e retornos
- Verificar PHP 8.0+ features (match, named args, etc.)

### 4. Segurança
- SQL injection: buscar queries sem prepared statements
- XSS: outputs sem `htmlspecialchars()`
- Secrets hardcoded: `grep -r 'password\|secret\|token' app/ --include='*.php'`
- Validação de input em controllers/routes

### 5. Código
- Buscar `var_dump`, `print_r`, `echo` em produção
- Buscar `TODO`, `FIXME`, `HACK`
- Buscar catches vazios: `catch (\Exception`
- Verificar se Controllers têm lógica de negócio (deveria estar em Services)

### 6. Testes
- Rodar: `php vendor/bin/phpunit --no-coverage 2>&1 | tail -20`
- Identificar módulos sem testes
- Verificar cobertura

### 7. Performance
- Queries N+1 (loops com SQL dentro)
- Falta de cache Redis
- Guzzle sem timeout configurado
- Jobs/workers sem rate limiting

## Output OBRIGATÓRIO:

### 📊 RELATÓRIO DE AUDITORIA — eskill.com.br

| Categoria | Status | Detalhes |
|-----------|--------|----------|
| Estrutura | ✅/⚠️/🔴 | resumo |
| Dependências | ✅/⚠️/🔴 | resumo |
| Tipagem PHP | ✅/⚠️/🔴 | resumo |
| Segurança | ✅/⚠️/🔴 | resumo |
| Código | ✅/⚠️/🔴 | resumo |
| Testes | ✅/⚠️/🔴 | resumo |
| Performance | ✅/⚠️/🔴 | resumo |

**Nota Geral:** X/10

### 🔴 CRÍTICOS (corrigir imediatamente)
1. [problema] — [arquivo:linha] — [como corrigir]

### ⚠️ ATENÇÃO (corrigir em breve)
1. [problema] — [arquivo:linha] — [como corrigir]

### ✅ BEM FEITO
- [elogiar o que está bom no projeto]

### 🔮 Próximos Passos
1. **[Urgente]** — Corrigir itens críticos de segurança
2. **[Importante]** — Melhorar tipagem nos arquivos listados
3. **[Recomendado]** — Adicionar testes para módulos sem cobertura
4. **[Evolução]** — Otimizações de performance sugeridas
