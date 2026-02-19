# Validação de Hardenings - Guia de Execução

**Data**: 2026-02-15  
**Status**: Aguardando runtime test execution

## Contexto

Todos os hardenings de segurança foram implementados e validados estaticamente. Para completar a validação, precisamos executar testes PHP no runtime, o que requer instalação de dependências do sistema.

## Dependências do Sistema

O terminal VSCode/GitHub Copilot requer:
- `ripgrep` (rg)
- `bubblewrap` (bwrap)
- `socat`

## Passo 1: Instalar Dependências

Execute no HOST (fora do sandbox VSCode):

```bash
bash /home/eskill/htdocs/eskill.com.br/install-sandbox-deps.sh
```

Ou manualmente:

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y ripgrep bubblewrap socat

# Fedora/RHEL/CentOS
sudo dnf install -y ripgrep bubblewrap socat
# ou
sudo yum install -y ripgrep bubblewrap socat

# Arch/Manjaro
sudo pacman -Sy --noconfirm ripgrep bubblewrap socat
```

## Passo 2: Validação PHP Syntax

```bash
cd /home/eskill/htdocs/eskill.com.br

# Arquivos críticos de hardening
php -l app/Core/ExceptionHandler.php
php -l app/Middleware/SecurityMiddleware.php
php -l app/Middleware/RateLimitMiddleware.php
php -l app/Middleware/SecurityHeadersMiddleware.php

# Testes unitários
php -l tests/Unit/ExceptionHandlerTest.php
php -l tests/Unit/SecurityPatchesTest.php
```

## Passo 3: Testes Unitários

```bash
# Suite completa de testes unitários
composer test-unit

# Teste específico de ExceptionHandler
php vendor/bin/phpunit tests/Unit/ExceptionHandlerTest.php

# Teste específico de Security Patches
php vendor/bin/phpunit tests/Unit/SecurityPatchesTest.php

# Suite completa (unit + integration)
composer test
```

## Passo 4: Validação de Configuração

```bash
# Verificar flags de segurança
php -r "echo 'APP_DEBUG: ' . (getenv('APP_DEBUG') ?: 'false') . PHP_EOL;"
php -r "echo 'APP_ENV: ' . (getenv('APP_ENV') ?: 'production') . PHP_EOL;"
php -r "echo 'FORCE_HTTPS: ' . (getenv('FORCE_HTTPS') ?: 'false') . PHP_EOL;"
php -r "echo 'SECURITY_MW_RATE_LIMIT_ENABLED: ' . (getenv('SECURITY_MW_RATE_LIMIT_ENABLED') ?: 'false') . PHP_EOL;"
php -r "echo 'SECURITY_HEADERS_LEGACY_ENABLED: ' . (getenv('SECURITY_HEADERS_LEGACY_ENABLED') ?: 'false') . PHP_EOL;"
```

## Resultado Esperado

### ✅ PHP Syntax Check
Todos os arquivos devem retornar:
```
No syntax errors detected in <arquivo>.php
```

### ✅ Testes Unitários
```
OK (X tests, Y assertions)
```

### ✅ Configuração
```
APP_DEBUG: false
APP_ENV: production
FORCE_HTTPS: true
SECURITY_MW_RATE_LIMIT_ENABLED: false
SECURITY_HEADERS_LEGACY_ENABLED: false
```

## Hardenings Implementados

### 1. Rate Limit Duplicado Removido
- **Arquivo**: `app/Middleware/SecurityMiddleware.php`
- **Flag**: `SECURITY_MW_RATE_LIMIT_ENABLED` (default: `false`)
- **Impacto**: Zero risco de 429 duplicado

### 2. ExceptionHandler Diferencia API vs HTML
- **Arquivo**: `app/Core/ExceptionHandler.php`
- **Método**: `wantsJson()` detecta contexto
- **Teste**: `tests/Unit/ExceptionHandlerTest.php`

### 3. Drift de Headers Reduzido
- **Arquivo**: `app/Middleware/SecurityHeadersMiddleware.php`
- **Flag**: `SECURITY_HEADERS_LEGACY_ENABLED` (default: `false`)
- **Fonte única**: `SecurityMiddleware`

### 4. Method Reference Fixes
- **Arquivos**: 
  - `app/Controllers/CloneAdvancedController.php`
  - `app/Controllers/SettingsController.php`
- **Validação**: Codacy clean, 0 erros estáticos

## Validações Já Realizadas (Estáticas)

✅ Codacy CLI: 0 issues em 4 arquivos críticos  
✅ Diagnóstico estático: 0 erros reportados  
✅ Análise de sintaxe via get_errors: clean  
✅ Cobertura de teste: `ExceptionHandlerTest` presente

## Próximos Passos

1. Executar validações acima no host
2. Se houver falhas, reportar os erros específicos
3. Ajustar conforme necessário
4. Commit final dos hardenings

## Referências

- Script de instalação: `/home/eskill/htdocs/eskill.com.br/install-sandbox-deps.sh`
- Relatório de auditoria: `/home/eskill/htdocs/eskill.com.br/docs/SECURITY_AUDIT_REPORT.md`
- Testes: `/home/eskill/htdocs/eskill.com.br/tests/Unit/`
