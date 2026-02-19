# ✅ Checklist de Produção - Mercado Livre Manager

## 🔴 CRÍTICO - Obrigatório para Produção

### 1. Configurações de Ambiente (.env)
- [ ] **APP_ENV=production** (não development)
- [ ] **APP_DEBUG=false** (nunca true em produção)
- [ ] **APP_KEY** configurado com chave forte (64 caracteres)
- [ ] **APP_URL** configurado com URL de produção (HTTPS)
- [ ] **DB_HOST, DB_NAME, DB_USER, DB_PASS** configurados corretamente
- [ ] **ML_APP_ID e ML_CLIENT_SECRET** configurados
- [ ] **ML_REDIRECT_URI** apontando para produção (HTTPS)

### 2. Segurança
- [ ] **HTTPS/SSL obrigatório** configurado
- [ ] **APP_KEY** gerado e seguro (não usar a mesma de desenvolvimento)
- [ ] **Senhas do banco** fortes e diferentes de desenvolvimento
- [ ] **Permissões de arquivos** corretas (755 para diretórios, 644 para arquivos)
- [ ] **.env** com permissões restritas (600 ou 640)
- [ ] **vendor/** e **storage/** não acessíveis via web
- [ ] **Arquivos sensíveis** protegidos (.env, .git, etc.)

### 3. Tratamento de Erros
- [ ] **display_errors=0** em produção
- [ ] **log_errors=1** ativado
- [ ] **error_log** configurado e monitorado
- [ ] **Página de erro 500** personalizada (não mostrar detalhes)
- [ ] **Página de erro 404** personalizada
- [ ] **Exceptions** capturadas e logadas (não exibidas)

### 4. Banco de Dados
- [ ] **Usuário dedicado** para aplicação (não root)
- [ ] **Senha forte** para usuário do banco
- [ ] **Privilégios mínimos** necessários
- [ ] **Backup automatizado** configurado
- [ ] **Índices** criados e otimizados
- [ ] **Migrations** todas executadas

---

## 🟡 IMPORTANTE - Recomendado para Produção

### 5. Performance
- [ ] **Redis** instalado e configurado (cache)
- [ ] **OPcache** habilitado no PHP
- [ ] **Composer** com `--no-dev --optimize-autoloader`
- [ ] **Assets minificados** (CSS/JS)
- [ ] **Gzip/Brotli** habilitado no servidor
- [ ] **CDN** configurado (opcional)

### 6. Monitoramento
- [ ] **Logs estruturados** implementados
- [ ] **Monitoramento de saúde** ativo
- [ ] **Alertas** configurados (Telegram/Email)
- [ ] **Métricas** coletadas e visualizadas
- [ ] **Uptime monitoring** configurado
- [ ] **Logs rotacionados** (logrotate)

### 7. Backup
- [ ] **Backup automático** do banco configurado (diário)
- [ ] **Backup de arquivos** importantes
- [ ] **Teste de restauração** realizado
- [ ] **Retenção** de backups definida (30 dias mínimo)
- [ ] **Backup off-site** configurado

### 8. SSL/HTTPS
- [ ] **Certificado SSL** instalado (Let's Encrypt recomendado)
- [ ] **HTTPS obrigatório** (redirect HTTP → HTTPS)
- [ ] **HSTS** habilitado
- [ ] **Renovação automática** do certificado

### 9. Servidor Web
- [ ] **Apache/Nginx** configurado corretamente
- [ ] **mod_rewrite** habilitado (Apache)
- [ ] **PHP-FPM** configurado (recomendado)
- [ ] **Timeouts** ajustados adequadamente
- [ ] **Limites de memória** adequados
- [ ] **Upload max** configurado

### 10. Segurança Adicional
- [ ] **Firewall** configurado
- [ ] **Fail2ban** ou similar (proteção contra brute force)
- [ ] **Headers de segurança** configurados (CSP, X-Frame-Options, etc.)
- [ ] **Rate limiting** ajustado para produção
- [ ] **CORS** configurado se necessário
- [ ] **IP whitelist** para admin (opcional)

---

## 🟢 OPCIONAL - Melhorias Adicionais

### 11. Testes
- [ ] **Testes automatizados** executados
- [ ] **Testes de carga** realizados
- [ ] **Testes de segurança** realizados
- [ ] **Testes de integração** realizados

### 12. Documentação
- [ ] **README** atualizado para produção
- [ ] **Guia de deploy** revisado
- [ ] **Documentação de API** completa
- [ ] **Runbook** de operações criado

### 13. CI/CD
- [ ] **Pipeline de deploy** configurado
- [ ] **Testes automáticos** no pipeline
- [ ] **Deploy automatizado** (opcional)

### 14. Observabilidade
- [ ] **APM** configurado (New Relic, Datadog, etc.)
- [ ] **Error tracking** (Sentry, Rollbar, etc.)
- [ ] **Log aggregation** (ELK, Loki, etc.)

---

## 📋 Checklist Rápido

### Antes de Colocar em Produção:

1. ✅ **Configurar .env para produção**
2. ✅ **Gerar APP_KEY forte**
3. ✅ **Configurar HTTPS/SSL**
4. ✅ **Desabilitar debug**
5. ✅ **Configurar logs**
6. ✅ **Criar usuário dedicado no banco**
7. ✅ **Configurar backup automático**
8. ✅ **Testar restauração de backup**
9. ✅ **Configurar monitoramento**
10. ✅ **Revisar permissões de arquivos**

---

## 🚨 Ações Imediatas Necessárias

### 1. Criar .env de Produção
```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=sua_chave_forte_de_64_caracteres_aqui
APP_URL=https://seudominio.com.br

DB_HOST=localhost
DB_NAME=mercadolivre_db
DB_USER=ml_user
DB_PASS=senha_forte_aqui

ML_APP_ID=seu_app_id
ML_CLIENT_SECRET=seu_secret
ML_REDIRECT_URI=https://seudominio.com.br/auth/callback

CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 2. Configurar PHP para Produção
```ini
display_errors = Off
log_errors = On
error_log = /var/log/php/errors.log
expose_php = Off
```

### 3. Configurar Servidor Web

**Apache (.htaccess):**
```apache
# Forçar HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Headers de segurança
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
```

**Nginx:**
```nginx
# Forçar HTTPS
server {
    listen 80;
    server_name seudominio.com.br;
    return 301 https://$server_name$request_uri;
}

# Headers de segurança
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
```

---

## 📊 Status Atual

### ✅ Já Implementado
- Sistema de autenticação completo
- Criptografia de tokens
- Rate limiting
- Proteção CSRF
- Logs de auditoria
- Cache (Redis/File)
- Backup via API
- Monitoramento básico
- Tratamento de erros básico

### ⚠️ Precisa Configurar
- Variáveis de ambiente de produção
- SSL/HTTPS
- Logs estruturados
- Monitoramento avançado
- Backup automatizado (CRON)
- Páginas de erro personalizadas
- Headers de segurança no servidor

### ❌ Não Implementado (Opcional)
- Testes automatizados
- CI/CD
- APM/Error tracking
- CDN

---

## 🔧 Scripts de Produção Necessários

### 1. Script de Deploy
### 2. Script de Backup Automatizado
### 3. Script de Verificação de Saúde
### 4. Script de Limpeza de Logs

---

**Próximo passo:** Implementar as configurações críticas listadas acima.
