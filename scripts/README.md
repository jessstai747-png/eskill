# Scripts de Validação e Utilitários

Esta pasta contém scripts para validação, manutenção e operação do sistema.

## 🔍 Scripts de Validação de Segurança

### quick-check.php
**Validação rápida de hardenings**

```bash
php scripts/quick-check.php
```

**O que verifica:**
- Presença de método `wantsJson()` no ExceptionHandler
- Detecção de API path no ExceptionHandler
- Flag `SECURITY_MW_RATE_LIMIT_ENABLED` no SecurityMiddleware
- Flag `SECURITY_HEADERS_LEGACY_ENABLED` no SecurityHeadersMiddleware
- Método correto `updateSettings()` no CloneAdvancedController
- Helper correto `SessionHelper::getUserAccounts()` no SettingsController

**Saída:**
- ✅ Component OK para cada validação passou
- ❌ Error para validações falhadas
- Exit code 0 se tudo OK, 1 se houver falhas

**Tempo de execução:** ~1 segundo

---

### validate-hardenings.php
**Validação completa standalone**

```bash
php scripts/validate-hardenings.php
```

**O que verifica:**
1. **Sintaxe PHP** de 7 arquivos críticos via `php -l`
2. **Flags de Segurança**: presença e configuração correta
3. **Referências de Métodos**: chamadas corretas sem métodos inexistentes
4. **Estrutura de Arquivos**: presença de todos os arquivos necessários
5. **Configuração**: .env, entrypoint, middlewares

**Saída:**
- Relatório detalhado por categoria
- Contadores de passed/failed
- Taxa de sucesso percentual
- Recomendações de próximos passos

**Tempo de execução:** ~3-5 segundos

**Requisitos:**
- PHP CLI disponível no PATH
- Acesso de leitura aos arquivos do projeto

---

## 📊 Comparação dos Scripts

| Feature | quick-check.php | validate-hardenings.php |
|---------|----------------|------------------------|
| Velocidade | ⚡ Rápido (~1s) | 🐢 Moderado (~5s) |
| Cobertura | 🎯 Básica | 🔍 Completa |
| Dependências | Nenhuma | `php -l` |
| Output | Minimalista | Detalhado |
| Uso | CI/Pre-commit | Validação manual |

---

## 🚀 Recomendações de Uso

### Durante Desenvolvimento
```bash
# Antes de commit
php scripts/quick-check.php && git commit -m "feat: ..."
```

### Antes de Deploy
```bash
# Validação completa
php scripts/validate-hardenings.php

# Se passar, executar testes
composer test-unit
```

### Em CI/CD
```yaml
# .github/workflows/validate.yml
- name: Quick Security Check
  run: php scripts/quick-check.php

- name: Full Validation
  run: php scripts/validate-hardenings.php
```

---

## 📝 Outros Scripts Importantes

### ../install-sandbox-deps.sh
Instala dependências do sistema (ripgrep, bubblewrap, socat) necessárias para o terminal VSCode/GitHub Copilot.

```bash
# Execute no HOST (fora do sandbox)
bash install-sandbox-deps.sh
```

### Adicionar Novos Scripts

Ao adicionar novos scripts de validação:

1. **Nomeie descritivamente**: `validate-*.php` ou `check-*.php`
2. **Retorne exit codes**: 0 = sucesso, 1 = falha
3. **Output estruturado**: ✅/❌ + mensagens claras
4. **Documente aqui**: adicione seção neste README
5. **Seja independente**: minimize dependências externas

---

## 🔗 Links Úteis

- [HARDENING_STATUS.md](../HARDENING_STATUS.md) - Status consolidado
- [COMPLETION_CHECKLIST.md](../COMPLETION_CHECKLIST.md) - Checklist completo
- [docs/VALIDATION_GUIDE.md](../docs/VALIDATION_GUIDE.md) - Guia detalhado
- [docs/SECURITY_AUDIT_REPORT.md](../docs/SECURITY_AUDIT_REPORT.md) - Auditoria técnica

---

**Última atualização**: 2026-02-15
