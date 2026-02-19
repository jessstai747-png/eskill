# ✅ Checklist de Deploy - Hardenings de Segurança

## Pre-Deploy (Ambiente Local/Dev)

### 1. Validação de Código
```bash
# Validação rápida de patterns
php scripts/quick-check.php

# Validação completa standalone
php scripts/validate-hardenings.php

# Relatório consolidado
php scripts/generate-final-report.php

# Testes unitários
composer test-unit

# Teste específico do ExceptionHandler
php vendor/bin/phpunit tests/Unit/ExceptionHandlerTest.php
```

**Esperado**: Todos com status ✅ PASS

---

### 2. Verificação de Flags de Configuração
```bash
# Verificar se as novas flags estão no .env.example
grep -E "SECURITY_MW_RATE_LIMIT_ENABLED|SECURITY_HEADERS_LEGACY_ENABLED" .env.example
```

**Esperado**: 
```
SECURITY_MW_RATE_LIMIT_ENABLED=false
SECURITY_HEADERS_LEGACY_ENABLED=false
```

---

### 3. Análise Estática
```bash
# Codacy (se disponível)
# codacy-cli analyze --tool codacy --directory .

# Verificar erros via IDE/editor
# Todos os arquivos devem estar sem erros
```

**Esperado**: 0 issues, 0 erros

---

## Deploy para Staging

### 1. Atualizar Repositório
```bash
git status  # Verificar mudanças
git add .
git commit -m "chore: implement critical security hardenings

- Rate limiting unificado (SECURITY_MW_RATE_LIMIT_ENABLED)
- Exception handler contextual (wantsJson method)
- Headers consolidados (SECURITY_HEADERS_LEGACY_ENABLED)
- Correções de métodos (CloneAdvancedController, SettingsController)
- Documentação completa e scripts de validação
"
git push origin staging
```

---

### 2. Configurar .env de Staging
```bash
# No servidor de staging, adicione ao .env:
SECURITY_MW_RATE_LIMIT_ENABLED=false
SECURITY_HEADERS_LEGACY_ENABLED=false
```

**Nota**: Use `false` (recomendado) para ativar os hardenings.

---

### 3. Deploy em Staging
```bash
# SSH no servidor de staging
ssh user@staging-server

# Atualizar código
cd /var/www/app
git pull origin staging

# Instalar dependências (se necessário)
composer install --no-dev

# Limpar caches
php artisan cache:clear  # Se Laravel
# ou
rm -rf storage/cache/*   # Cache manual

# Verificar permissões
chmod -R 775 storage/
chown -R www-data:www-data storage/
```

---

### 4. Validação Pós-Deploy (Staging)
```bash
# No servidor, executar validações
php scripts/quick-check.php
php scripts/validate-hardenings.php

# Verificar logs
tail -f storage/logs/app-$(date +%Y-%m-%d).log
```

**Esperado**: Sem erros, logs limpos

---

### 5. Testes Manuais em Staging

#### Teste 1: Rate Limiting
```bash
# Fazer múltiplas requests para qualquer rota
for i in {1..110}; do
  curl -s https://staging.domain.com/dashboard > /dev/null
  echo "Request $i"
done

# Esperado: Apenas 1 erro 429 após o limite
# Verificar logs: deve aparecer apenas 1x a mensagem de rate limit
```

#### Teste 2: Exception Handler (API)
```bash
# Forçar erro em rota API
curl -X POST https://staging.domain.com/api/seo/analyze/INVALID_ID \
  -H "Content-Type: application/json"

# Esperado: Resposta JSON com erro
# {"success": false, "error": "..."}
```

#### Teste 3: Exception Handler (HTML)
```bash
# Forçar erro em rota HTML (acessar no navegador)
# https://staging.domain.com/dashboard?force_error=1

# Esperado: Página HTML de erro 500 (não JSON)
```

#### Teste 4: Headers de Segurança
```bash
# Verificar headers HTTP
curl -I https://staging.domain.com/dashboard

# Esperado: 
# - Content-Security-Policy (apenas 1x)
# - X-Frame-Options (apenas 1x)
# - Strict-Transport-Security (apenas 1x)
# - X-Content-Type-Options (apenas 1x)
```

---

## Deploy para Produção

### 1. Aprovar Staging
- [ ] Todos os testes manuais passaram
- [ ] Logs sem erros críticos
- [ ] Performance aceitável
- [ ] UX de erro apropriada

---

### 2. Merge para Main/Production
```bash
git checkout main
git merge staging
git push origin main
```

---

### 3. Configurar .env de Produção
```bash
# No servidor de produção, adicione ao .env:
SECURITY_MW_RATE_LIMIT_ENABLED=false
SECURITY_HEADERS_LEGACY_ENABLED=false
```

---

### 4. Deploy em Produção
```bash
# SSH no servidor de produção
ssh user@production-server

# Backup antes do deploy
cd /var/www/app
tar -czf backup-$(date +%Y%m%d-%H%M%S).tar.gz .

# Atualizar código
git pull origin main

# Instalar dependências
composer install --no-dev --optimize-autoloader

# Limpar caches
php artisan optimize  # Se Laravel
# ou
rm -rf storage/cache/*

# Verificar permissões
chmod -R 775 storage/
chown -R www-data:www-data storage/

# Recarregar PHP-FPM (se necessário)
sudo systemctl reload php8.0-fpm
```

---

### 5. Validação Pós-Deploy (Produção)
```bash
# Validações rápidas
php scripts/quick-check.php

# Monitorar logs em tempo real
tail -f storage/logs/app-$(date +%Y-%m-%d).log
```

---

### 6. Smoke Tests em Produção

#### Teste 1: Rotas Principais
```bash
# Dashboard
curl -I https://domain.com/dashboard

# API
curl https://domain.com/api/accounts

# Login
curl -I https://domain.com/login
```

**Esperado**: Status 200 ou 302 (redirect)

#### Teste 2: Rate Limiting
```bash
# Fazer 10 requests rápidas
for i in {1..10}; do
  curl -s https://domain.com/dashboard > /dev/null
  echo "Request $i"
done

# Esperado: Todas com sucesso (dentro do limite)
```

#### Teste 3: Headers de Segurança
```bash
curl -I https://domain.com/dashboard | grep -E "Content-Security-Policy|X-Frame-Options|Strict-Transport-Security"

# Esperado: Headers presentes (1x cada)
```

---

## Monitoramento Pós-Deploy (48h)

### Métricas para Acompanhar

#### 1. Taxa de Erro 429
```bash
# Contar ocorrências de 429 nos logs
grep -c "429" storage/logs/app-$(date +%Y-%m-%d).log

# Esperado: ~50% menos que antes (1x ao invés de 2x)
```

#### 2. Erros de Exception Handler
```bash
# Verificar erros 500
grep "500" storage/logs/app-$(date +%Y-%m-%d).log

# Esperado: Formato apropriado (JSON para API, HTML para views)
```

#### 3. Performance de Rate Limiting
```bash
# Verificar tempo de resposta
curl -w "@curl-format.txt" -o /dev/null -s https://domain.com/dashboard

# Esperado: Sem degradação (mesma latência)
```

#### 4. Logs de Segurança
```bash
# Verificar se headers estão sendo aplicados
tail -100 /var/log/nginx/access.log | grep "CSP"

# Esperado: Headers presentes em todas as respostas
```

---

## Rollback (Se Necessário)

### Opção 1: Rollback de Configuração
```bash
# Reverter para comportamento legado via .env
SECURITY_MW_RATE_LIMIT_ENABLED=true
SECURITY_HEADERS_LEGACY_ENABLED=true

# Recarregar PHP-FPM
sudo systemctl reload php8.0-fpm
```

### Opção 2: Rollback de Código
```bash
# Restaurar backup
cd /var/www/app
tar -xzf backup-YYYYMMDD-HHMMSS.tar.gz

# Ou via Git
git reset --hard HEAD~1
git push --force origin main

# Recarregar PHP-FPM
sudo systemctl reload php8.0-fpm
```

---

## Checklist Final

### Pre-Deploy
- [ ] Validação local com `php scripts/validate-hardenings.php`
- [ ] Testes unitários com `composer test-unit`
- [ ] Flags documentadas em `.env.example`
- [ ] Código commitado e pushed

### Staging
- [ ] Deploy em staging executado
- [ ] .env configurado com flags corretas
- [ ] Validações pós-deploy executadas
- [ ] Testes manuais aprovados
- [ ] Logs sem erros críticos

### Produção
- [ ] Backup criado
- [ ] Deploy em produção executado
- [ ] .env configurado com flags corretas
- [ ] Smoke tests aprovados
- [ ] Monitoramento ativo (48h)

### Pós-Deploy
- [ ] Métricas de 429 verificadas (deve cair ~50%)
- [ ] Formato de erro verificado (JSON para API, HTML para views)
- [ ] Headers de segurança verificados (sem duplicação)
- [ ] Performance verificada (sem degradação)

---

## Contatos de Suporte

Se encontrar problemas durante o deploy:

1. **Consulte**: [docs/VALIDATION_GUIDE.md](docs/VALIDATION_GUIDE.md)
2. **Execute**: `php scripts/validate-hardenings.php`
3. **Verifique**: `storage/logs/app-*.log`
4. **Rollback**: Use flags `=true` para comportamento legado

---

## Recursos Adicionais

- [IMPLEMENTATION_100_COMPLETE.md](IMPLEMENTATION_100_COMPLETE.md) - Relatório técnico completo
- [EXECUTIVE_SUMMARY.md](EXECUTIVE_SUMMARY.md) - Resumo executivo
- [SECURITY_CHANGELOG.md](SECURITY_CHANGELOG.md) - Changelog detalhado
- [docs/VALIDATION_GUIDE.md](docs/VALIDATION_GUIDE.md) - Guia de validação

---

**Última Atualização**: 2026-02-15  
**Versão**: 1.0.0  
**Status**: ✅ Pronto para Deploy
