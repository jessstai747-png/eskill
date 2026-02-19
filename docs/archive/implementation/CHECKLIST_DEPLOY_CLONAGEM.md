# ✅ Checklist de Deploy - Clonador de Anúncios em Lote

## 📋 Pré-Deploy

### Ambiente e Requisitos
- [ ] PHP 8.0+ instalado e configurado
- [ ] MySQL 8.0+ acessível
- [ ] Composer instalado
- [ ] Extensões PHP necessárias:
  - [ ] PDO
  - [ ] PDO_MySQL
  - [ ] JSON
  - [ ] cURL
  - [ ] mbstring
- [ ] Cron habilitado e funcionando
- [ ] Storage mínimo: 10GB livres

### Banco de Dados
- [ ] Backup do banco de dados realizado
- [ ] Migrations testadas em ambiente de staging:
  - [ ] `2026_01_30_create_catalog_clone_batch_tables.sql`
  - [ ] `2026_01_30_create_clone_templates_tables.sql`
- [ ] Tabelas verificadas:
  ```sql
  SHOW TABLES LIKE 'catalog_clone%';
  SHOW TABLES LIKE 'clone_%';
  ```
- [ ] Templates padrão populados (5 templates)
- [ ] Índices criados corretamente

### Configuração
- [ ] `.env` configurado corretamente:
  - [ ] `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
  - [ ] `ML_CLIENT_ID`, `ML_CLIENT_SECRET`
  - [ ] Outras variáveis do sistema
- [ ] Permissões de arquivos configuradas:
  ```bash
  chmod +x bin/catalog-clone-worker.php
  chmod +x bin/clone-post-actions-worker.php
  chmod +x bin/clone-diagnostics.sh
  chmod -R 775 storage/logs
  chmod -R 775 storage/locks
  chmod -R 775 storage/cache
  ```

---

## 🚀 Deploy

### 1. Aplicar Migrations
```bash
cd /home/eskill/htdocs/eskill.com.br

# Aplicar migrations
mysql -u root -p meli < database/migrations/2026_01_30_create_catalog_clone_batch_tables.sql
mysql -u root -p meli < database/migrations/2026_01_30_create_clone_templates_tables.sql

# Verificar
mysql -u root -p meli -e "SHOW TABLES LIKE 'clone%'"
```
- [ ] Migrations aplicadas sem erros
- [ ] 7 tabelas criadas: `catalog_clone_jobs`, `catalog_clone_job_items`, `clone_templates`, `clone_post_actions_log`, `clone_metrics`, `clone_alerts`, `clone_health_metrics`

### 2. Instalar Dependências
```bash
composer install --no-dev --optimize-autoloader
```
- [ ] Dependências instaladas
- [ ] Autoloader otimizado

### 3. Configurar Cron
```bash
# Editar crontab
crontab -e

# Adicionar (ajuste o PATH):
PROJECT_DIR=/home/eskill/htdocs/eskill.com.br

# Worker principal (a cada 1 minuto)
* * * * * cd $PROJECT_DIR && php bin/catalog-clone-worker.php --once >> storage/logs/cron-catalog-clone.log 2>&1

# Post-actions (a cada 2 minutos)
*/2 * * * * cd $PROJECT_DIR && php bin/clone-post-actions-worker.php --once >> storage/logs/cron-post-actions.log 2>&1

# Recovery (a cada 15 minutos)
*/15 * * * * cd $PROJECT_DIR && php bin/catalog-clone-worker.php --recover-stuck >> storage/logs/cron-recovery.log 2>&1

# Salvar e sair
```
- [ ] Crontab configurado
- [ ] Verificar com: `crontab -l`

### 4. Executar Diagnóstico
```bash
bash bin/clone-diagnostics.sh
```
- [ ] Todos os checks passaram (✓)
- [ ] Nenhum erro crítico (✗) encontrado
- [ ] Workers executam sem erros

### 5. Teste de Fumaça
```bash
# Teste manual do worker
php bin/catalog-clone-worker.php --once --verbose

# Teste manual de pós-ações
php bin/clone-post-actions-worker.php --once

# Verificar logs
tail -50 storage/logs/catalog-clone-worker.log
```
- [ ] Workers executam sem erros
- [ ] Logs escritos corretamente
- [ ] Sem exceptions ou fatais

---

## 🧪 Testes Pós-Deploy

### Teste 1: Clone Simples (Dry-Run)
1. [ ] Acessar `/dashboard/catalog/clone/batch`
2. [ ] Informar um Seller ID válido
3. [ ] Selecionar 1-2 anúncios
4. [ ] Escolher template "Replicação Exata"
5. [ ] Executar **Dry-Run**
6. [ ] Verificar resultado da prévia

**Critério de Sucesso:**
- [ ] Preview retorna sem erros
- [ ] Payload gerado corretamente
- [ ] Riscos identificados (se houver)

### Teste 2: Clone Real (1 item)
1. [ ] Selecionar 1 anúncio simples (sem variações)
2. [ ] Usar template "Replicação Exata"
3. [ ] Selecionar conta de destino
4. [ ] Iniciar clonagem
5. [ ] Acompanhar progresso
6. [ ] Verificar resultado no ML

**Critério de Sucesso:**
- [ ] Job criado com sucesso
- [ ] Item processado (status: completed)
- [ ] Anúncio criado no ML
- [ ] Logs sem erros

### Teste 3: Clone em Lote (5-10 itens)
1. [ ] Selecionar 5-10 anúncios
2. [ ] Usar template "Dropshipping +30%"
3. [ ] Executar dry-run primeiro
4. [ ] Iniciar clonagem
5. [ ] Monitorar progresso
6. [ ] Verificar taxa de sucesso

**Critério de Sucesso:**
- [ ] Taxa de sucesso > 80%
- [ ] Erros (se houver) são documentados
- [ ] Dashboard atualiza corretamente

### Teste 4: Pós-Ações
1. [ ] Clonar item com template "SEO Otimizado"
2. [ ] Aguardar execução de pós-ações (2-5 min)
3. [ ] Verificar log: `storage/logs/clone-post-actions-worker.log`
4. [ ] Verificar se ações foram aplicadas

**Critério de Sucesso:**
- [ ] Tech Sheet aplicado (se configurado)
- [ ] SEO Optimize executado
- [ ] Sem erros críticos

### Teste 5: Recovery de Jobs Travados
1. [ ] Simular job travado (parar worker no meio)
2. [ ] Executar: `php bin/catalog-clone-worker.php --recover-stuck`
3. [ ] Verificar se job foi recuperado
4. [ ] Job continua processamento

**Critério de Sucesso:**
- [ ] Job marcado como 'pending' novamente
- [ ] Processamento retomado
- [ ] Itens não duplicados

---

## 📊 Monitoramento Inicial (Primeiros 7 Dias)

### Diário
- [ ] Verificar logs de erro:
  ```bash
  tail -100 storage/logs/catalog-clone-worker.log | grep -i error
  ```
- [ ] Verificar jobs travados:
  ```sql
  SELECT * FROM catalog_clone_jobs 
  WHERE status = 'processing' 
  AND updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE);
  ```
- [ ] Verificar taxa de sucesso:
  ```sql
  SELECT status, COUNT(*) FROM catalog_clone_jobs 
  WHERE created_at >= CURDATE() 
  GROUP BY status;
  ```

### Semanal
- [ ] Executar diagnóstico completo:
  ```bash
  bash bin/clone-diagnostics.sh > diagnostics-$(date +%Y%m%d).txt
  ```
- [ ] Analisar métricas no dashboard
- [ ] Revisar templates mais usados
- [ ] Identificar erros recorrentes
- [ ] Ajustar rate limits se necessário

---

## 🔧 Rollback (Se Necessário)

### Se houver problemas críticos:

1. **Parar Workers**
```bash
# Remover do crontab
crontab -e
# Comentar linhas relacionadas ao clone
```

2. **Marcar Jobs como Failed**
```sql
UPDATE catalog_clone_jobs 
SET status = 'failed', error_message = 'Rollback manual' 
WHERE status IN ('pending', 'processing');
```

3. **Backup de Dados**
```bash
mysqldump -u root -p meli catalog_clone_jobs catalog_clone_job_items > backup_jobs_$(date +%Y%m%d).sql
```

4. **Reverter Migrations (se necessário)**
```sql
DROP TABLE IF EXISTS clone_health_metrics;
DROP TABLE IF EXISTS clone_alerts;
DROP TABLE IF EXISTS clone_metrics;
DROP TABLE IF EXISTS clone_post_actions_log;
DROP TABLE IF EXISTS catalog_clone_job_items;
DROP TABLE IF EXISTS catalog_clone_jobs;
DROP TABLE IF EXISTS clone_templates;
-- Manter cloned_items (histórico)
```

---

## 📞 Contatos de Emergência

### Suporte Técnico
- Email: suporte@eskill.com.br
- Slack: #tech-support
- Phone: (11) XXXX-XXXX

### Escalação
1. Nível 1: Suporte técnico (problemas comuns)
2. Nível 2: DevOps (infraestrutura)
3. Nível 3: Arquiteto de Software (problemas críticos)

---

## 📝 Notas de Deploy

### Deploy realizado por:
- [ ] Nome: ___________________________
- [ ] Data: ___________________________
- [ ] Horário: ________________________

### Ambiente:
- [ ] Produção
- [ ] Staging
- [ ] Homologação

### Observações:
```
_______________________________________________________________
_______________________________________________________________
_______________________________________________________________
_______________________________________________________________
```

### Aprovação Final:
- [ ] Todos os testes passaram
- [ ] Monitoramento configurado
- [ ] Documentação atualizada
- [ ] Equipe notificada

**Assinatura:** _________________________  
**Data:** _________________________

---

## ✅ Checklist Final

- [ ] ✅ Pré-Deploy completo
- [ ] ✅ Deploy executado
- [ ] ✅ Testes pós-deploy realizados
- [ ] ✅ Monitoramento configurado
- [ ] ✅ Equipe treinada
- [ ] ✅ Documentação disponível
- [ ] ✅ Plano de rollback documentado

**STATUS:** 🟢 PRONTO PARA PRODUÇÃO

---

**Versão do Checklist:** 1.0  
**Última atualização:** 31/01/2026
