# 🔧 Troubleshooting - Clonagem em Lote

## 🚨 Problemas Comuns e Soluções

### 1. Jobs Travados (Stuck)

**Sintoma:**
- Jobs ficam com status `processing` indefinidamente
- Nenhum item sendo processado
- Dashboard não atualiza

**Diagnóstico:**
```bash
cd /home/eskill/htdocs/eskill.com.br

# Verificar jobs travados
mysql -uroot -p'Tr1unf0@' -h localhost meli -e "
  SELECT job_id, status, created_at, started_at, updated_at, 
         TIMESTAMPDIFF(MINUTE, updated_at, NOW()) as minutes_stuck
  FROM catalog_clone_jobs 
  WHERE status = 'processing' 
  AND updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
```

**Solução:**
```bash
# Recuperar automaticamente jobs travados
php bin/catalog-clone-worker.php --recover-stuck

# Ou manualmente via SQL
mysql -uroot -p'Tr1unf0@' -h localhost meli -e "
  UPDATE catalog_clone_jobs 
  SET status = 'pending', attempts = attempts + 1
  WHERE status = 'processing' 
  AND updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
```

---

### 2. Worker Não Está Processando

**Sintoma:**
- Cron está rodando mas jobs não processam
- Logs vazios ou sem atualizações

**Diagnóstico:**
```bash
# Verificar se cron está configurado
crontab -l | grep catalog-clone

# Verificar logs recentes
tail -100 storage/logs/catalog-clone-worker.log

# Testar worker manualmente
php bin/catalog-clone-worker.php --once --verbose
```

**Possíveis Causas:**

#### A) Cron não configurado
```bash
# Adicionar ao crontab
crontab -e

# Adicionar linha:
* * * * * cd /home/eskill/htdocs/eskill.com.br && php bin/catalog-clone-worker.php --once
```

#### B) Permissões de arquivo
```bash
# Verificar permissões
ls -la bin/catalog-clone-worker.php

# Corrigir se necessário
chmod +x bin/catalog-clone-worker.php
```

#### C) Lock file preso
```bash
# Remover lock file antigo
rm -f storage/locks/catalog-clone-worker.lock
```

---

### 3. Erro de Rate Limit (429)

**Sintoma:**
- Muitos erros com código 429
- Mensagem "Too Many Requests"
- Clonagem lenta ou parada

**Solução:**
```bash
# Processar com limite menor (padrão é 10)
php bin/catalog-clone-worker.php --once --limit=5

# Ajustar no código (CatalogCloneService.php)
# Aumentar delay entre requisições
```

**Configuração de Rate Limit:**
```php
// Em CatalogCloneService.php linha ~150
private const RATE_LIMIT_DELAY_MS = 1000; // 1 segundo entre requests
private const MAX_REQUESTS_PER_MINUTE = 50; // Máximo 50 req/min
```

---

### 4. Falha ao Criar Anúncio (400/422)

**Sintomas:**
- Erro "Invalid category_id"
- Erro "Required attributes missing"
- Erro "Title too long"

**Diagnóstico:**
```bash
# Ver detalhes do erro específico
mysql -uroot -p'Tr1unf0@' -h localhost meli -e "
  SELECT source_item_id, error_message, error_details
  FROM catalog_clone_job_items
  WHERE status = 'failed'
  ORDER BY updated_at DESC
  LIMIT 10"
```

**Soluções por Erro:**

#### A) Categoria Inválida
```sql
-- Verificar categorias permitidas no destino
-- Pode precisar remapeamento manual
```

#### B) Atributos Obrigatórios Faltando
```bash
# Executar dry-run para identificar
# Adicionar atributos manualmente após clone
```

#### C) Título muito longo (>60 caracteres)
```php
// Template com truncate automático
'title_suffix' => null,
'title_remove_patterns' => ['promoção', 'oferta'],
```

---

### 5. Descrições Não Clonadas

**Sintoma:**
- Anúncio criado mas sem descrição
- Descrição vazia no ML

**Possíveis Causas:**

#### A) Template com clone_description = 0
```sql
-- Verificar template
SELECT name, clone_description FROM clone_templates WHERE id = X;
```

#### B) Descrição da origem é HTML complexo
```bash
# Sistema converte para plain_text
# Pode perder formatação complexa
```

#### C) Erro 403 ao tentar criar descrição
```bash
# ML pode bloquear descrições com conteúdo proibido
# Verificar em error_details
```

**Solução:**
```bash
# Reprocessar item específico com dry-run
php bin/catalog-clone-worker.php --job=XXXXX --item=MLB123456 --dry-run
```

---

### 6. Variações Não Clonadas

**Sintoma:**
- Anúncio criado sem variações
- Ou apenas uma variação

**Causa:**
- Variações complexas (cor + tamanho + modelo) são difíceis
- API do ML tem limitações

**Solução:**
```php
// Simplificar variações ou adicionar manualmente após clone
// Ou usar template com clone_variations = 0 e criar depois
```

---

### 7. Imagens Não Carregadas

**Sintoma:**
- Anúncio com poucas imagens ou sem imagens
- Erro "Image URL not accessible"

**Diagnóstico:**
```bash
# Verificar URLs de imagem originais
mysql -uroot -p'Tr1unf0@' -h localhost meli -e "
  SELECT source_item_id, JSON_EXTRACT(source_snapshot, '$.pictures') as pictures
  FROM catalog_clone_job_items
  WHERE status = 'failed'
  AND error_message LIKE '%image%'"
```

**Possíveis Causas:**
- URL da imagem expirou
- Imagem do ML protegida
- Imagem muito pesada (>10MB)

**Solução:**
```bash
# Re-upload manual das imagens
# Ou usar imagens do catálogo (se aplicável)
```

---

### 8. Pós-Ações Não Executadas

**Sintoma:**
- Anúncio clonado mas sem Tech Sheet
- SEO não aplicado
- Preço não ajustado

**Diagnóstico:**
```bash
# Verificar log de pós-ações
tail -100 storage/logs/clone-post-actions-worker.log

# Verificar status das ações
mysql -uroot -p'Tr1unf0@' -h localhost meli -e "
  SELECT target_item_id, action_type, status, error_message
  FROM clone_post_actions_log
  WHERE status = 'failed'
  ORDER BY created_at DESC
  LIMIT 20"
```

**Solução:**
```bash
# Reprocessar ações pendentes
php bin/clone-post-actions-worker.php --once

# Reprocessar item específico
php bin/clone-post-actions-worker.php --item=MLB123456

# Marcar como pendente para reprocessar
mysql -uroot -p'Tr1unf0@' -h localhost meli -e "
  UPDATE clone_post_actions_log
  SET status = 'pending', attempts = 0
  WHERE target_item_id = 'MLB123456'"
```

---

### 9. Performance Lenta

**Sintoma:**
- Jobs demorando muito para completar
- Sistema lento durante clonagem

**Otimizações:**

#### A) Aumentar paralelismo (CUIDADO com rate limits!)
```bash
# Rodar múltiplas instâncias do worker (em terminais separados)
php bin/catalog-clone-worker.php --once &
php bin/catalog-clone-worker.php --once &
php bin/catalog-clone-worker.php --once &
```

#### B) Ajustar batch size
```php
// Em catalog-clone-worker.php
// Processar mais itens por iteração
$limit = 20; // Aumentar de 10 para 20
```

#### C) Otimizar queries
```sql
-- Adicionar índices se necessário
SHOW INDEX FROM catalog_clone_job_items;

-- Criar índice composto
CREATE INDEX idx_job_status ON catalog_clone_job_items(job_id, status);
```

---

### 10. Erros de Autenticação (401/403)

**Sintoma:**
- Erro "Unauthorized" ou "Forbidden"
- Token expirado

**Diagnóstico:**
```bash
# Verificar token da conta
mysql -uroot -p'Tr1unf0@' -h localhost meli -e "
  SELECT id, seller_id, expires_at,
         TIMESTAMPDIFF(HOUR, NOW(), expires_at) as hours_until_expiry
  FROM ml_accounts
  WHERE id = X"
```

**Solução:**
```bash
# Renovar token manualmente
# Ou implementar refresh automático
```

---

## 📊 Comandos Úteis de Diagnóstico

### Dashboard via CLI
```bash
# Status geral
mysql -uroot -p'Tr1unf0@' -h localhost meli -e "
  SELECT status, COUNT(*) as count
  FROM catalog_clone_jobs
  GROUP BY status"

# Taxa de sucesso
mysql -uroot -p'Tr1unf0@' -h localhost meli -e "
  SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    ROUND(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as success_rate
  FROM catalog_clone_jobs
  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"

# Jobs em andamento
mysql -uroot -p'Tr1unf0@' -h localhost meli -e "
  SELECT job_id, target_account_id, total_items, processed_items, 
         ROUND(processed_items / total_items * 100, 2) as progress_pct,
         TIMESTAMPDIFF(MINUTE, started_at, NOW()) as running_minutes
  FROM catalog_clone_jobs
  WHERE status IN ('processing', 'queued')"
```

### Logs em Tempo Real
```bash
# Worker principal
tail -f storage/logs/catalog-clone-worker.log

# Pós-ações
tail -f storage/logs/clone-post-actions-worker.log

# Cron
tail -f storage/logs/cron-catalog-clone.log
```

### Limpar Dados de Teste
```bash
# ⚠️ CUIDADO: Remove TODOS os jobs de teste
mysql -uroot -p'Tr1unf0@' -h localhost meli -e "
  DELETE FROM catalog_clone_job_items WHERE job_id IN (
    SELECT job_id FROM catalog_clone_jobs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
  );
  DELETE FROM catalog_clone_jobs WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);"
```

---

## 🆘 Quando Pedir Ajuda

Se nenhuma solução acima funcionar:

1. **Colete informações:**
   - Job ID específico
   - Logs relevantes (últimas 100 linhas)
   - Mensagens de erro completas
   - Item IDs que falharam

2. **Execute diagnóstico completo:**
```bash
bash bin/clone-diagnostics.sh > diagnostics-report.txt
```

3. **Envie para suporte:**
   - Email: suporte@eskill.com.br
   - Anexar: diagnostics-report.txt
   - Informar: passos para reproduzir o problema

---

**Última atualização:** 31/01/2026  
**Versão:** 2.0.0
