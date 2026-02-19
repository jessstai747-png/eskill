# Módulo de Clonagem de Anúncios

Este módulo permite a clonagem de anúncios entre contas do Mercado Livre, suportando tanto itens de catálogo quanto itens normais. O sistema é composto por uma interface web, API, e workers de backend para processamento assíncrono.

## Funcionalidades

*   **Clonagem Manual**: Clone um único item instantaneamente através da interface.
*   **Clonagem em Lote (Batch)**: Clone múltiplos itens em background com jobs na fila.
*   **Suporte Multi-conta**: Envie o mesmo item para múltiplas contas de destino.
*   **Ajuste de Preços**:
    *   Cópia exata (`copy`).
    *   Markup/Markdown percentual (`markup_percent`, `markdown_percent`).
    *   Estratégia Inteligente (`competitive`, `aggressive`, `premium`) baseada na concorrência.
*   **Pós-Processamento Automático**:
    *   Geração de Ficha Técnica (Tech Sheet).
    *   Otimização SEO (Título e atributos).
    *   Aplicação de regras de preço.
*   **Tracking Completo**: Métricas de tempo de processamento, preços, e job_id para rastreabilidade.

## Arquitetura

```
┌─────────────────┐    ┌───────────────────────────┐    ┌──────────────────────┐
│   Interface     │───▶│  CatalogCloneController   │───▶│   CatalogCloneService│
│  (Frontend)     │    │     (API Endpoints)       │    │   (Lógica de Clone)  │
└─────────────────┘    └───────────────────────────┘    └──────────────────────┘
                                    │                              │
                                    ▼                              ▼
                       ┌───────────────────────┐       ┌──────────────────────┐
                       │      JobService       │       │  MercadoLivreClient  │
                       │  (Fila de Jobs)       │       │    (API do ML)       │
                       └───────────────────────┘       └──────────────────────┘
                                    │
                                    ▼
          ┌────────────────────────────────────────────────────────┐
          │                      Workers                           │
          │  ┌─────────────────────┐  ┌───────────────────────┐    │
          │  │catalog-clone-worker │  │clone-post-actions-worker│  │
          │  └─────────────────────┘  └───────────────────────┘    │
          └────────────────────────────────────────────────────────┘
```

### Componentes Principais

1.  **Interface (Clone UI)**: `app/Views/catalog/clone.php` e `public/js/catalog-clone.js`.
2.  **Controller**: `App\Controllers\CatalogCloneController` gerencia as requisições HTTP e despacha jobs.
3.  **Services**:
    *   `App\Services\CatalogCloneService`: Lógica core de clonagem (API ML).
    *   `App\Services\ClonePostActionsService`: Gerencia ações subsequentes (SEO, Tech Sheet).
    *   `App\Services\JobService`: Gerencia a fila de processamento na tabela `jobs`.
4.  **Workers**:
    *   `bin/catalog-clone-worker.php`: Processa a fila de clonagem (`catalog_clone_item`).
    *   `bin/clone-post-actions-worker.php`: Processa otimizações pós-clone.

### Tabelas do Banco de Dados

| Tabela                  | Descrição                              |
|-------------------------|----------------------------------------|
| `cloned_items`          | Histórico de itens clonados            |
| `clone_post_actions_log`| Ações pós-clone (SEO, TechSheet)       |
| `jobs`                  | Fila unificada de processamento        |

## Como Usar

### 1. Clonagem via Interface (Modo Simples)

Acesse o menu **Catálogo > Clonar** no painel administrativo.

*   Selecione a conta de Origem.
*   Insira o ID do item (ex: `MLB12345678`) ou uma lista de IDs.
*   Selecione a(s) conta(s) de Destino.
*   Escolha a estratégia de preço.
*   Clique em Clonar.

### 2. Clonagem em Lote por Seller (Novo!)

Acesse o menu **Catálogo > Clonar em Lote** para clonar múltiplos anúncios de um vendedor.

**Funcionalidades:**

*   🔍 **Busca por Seller ID**: Carrega todos os anúncios públicos de um vendedor.
*   📂 **Filtros por Categoria/Marca**: Visualize e selecione anúncios por categoria ou marca.
*   🎯 **Seleção Inteligente**: Selecione todos de uma categoria ou marca com um clique.
*   📊 **Preview de Preços**: Veja como os preços ficarão antes de clonar.
*   ⚡ **Infinite Scroll**: Carregue mais anúncios conforme rola a lista.
*   🛡️ **Guardrails de Segurança**: Opções para copiar ou não imagens e descrições (protege contra violação de direitos autorais).

**Passo a Passo:**

1. Informe o **Seller ID** do vendedor (número encontrado na URL do perfil no ML).
2. Clique em **Carregar** para listar os anúncios.
3. Use os filtros de **Categoria** e **Marca** para encontrar os produtos desejados.
4. Clique nos anúncios para selecionar ou use o botão **✓** para selecionar todos de uma categoria/marca.
5. Configure a **estratégia de preço** (manter, aumentar % ou reduzir %).
6. Configure os **Guardrails** (copiar imagens/descrição - não recomendado).
7. Selecione a **Conta Destino**.
8. Clique em **Clonar Selecionados**.
9. Acompanhe o progresso no modal de status.

**Tabelas do Banco de Dados (Batch):**

| Tabela                      | Descrição                              |
|-----------------------------|----------------------------------------|
| `catalog_clone_jobs`        | Jobs de clonagem em lote               |
| `catalog_clone_job_items`   | Itens individuais de cada job          |

### 3. Clonagem via API

**Clone único:**
```bash
curl -X POST https://seu-dominio.com/api/catalog/clone \
  -H "Content-Type: application/json" \
  -d '{
    "source_account_id": 2,
    "source_item_id": "MLB123456789",
    "target_account_id": 1,
    "pricing_strategy": {"type": "markup_percent", "value": 10}
  }'
```

**Clone em lote (lista de IDs):**
```bash
curl -X POST https://seu-dominio.com/api/catalog/clone/batch \
  -H "Content-Type: application/json" \
  -d '{
    "source_account_id": 2,
    "items": ["MLB123", "MLB456", "MLB789"],
    "target_account_ids": [1, 3],
    "pricing_strategy": {"type": "copy"}
  }'
```

**Criar Job de Clonagem em Lote (Novo!):**
```bash
curl -X POST https://seu-dominio.com/api/catalog/clone/jobs \
  -H "Content-Type: application/json" \
  -d '{
    "item_ids": ["MLB123456789", "MLB987654321"],
    "target_account_id": 1,
    "source_type": "item_ids",
    "options": {
      "start_paused": true,
      "include_pictures": false,
      "include_description": false,
      "pricing_strategy": {"type": "markup_percent", "value": 10}
    }
  }'
```

**Listar Itens de um Seller:**
```bash
curl "https://seu-dominio.com/api/catalog/clone/source/seller/123456789/items?limit=50&offset=0"
```

**Preview de Preços:**
```bash
curl -X POST https://seu-dominio.com/api/catalog/clone/price-preview \
  -H "Content-Type: application/json" \
  -d '{
    "item_ids": ["MLB123", "MLB456"],
    "target_account_id": 1,
    "pricing_strategy": {"type": "markup_percent", "value": 15}
  }'
```

### 3. Executando os Workers

Para que o processamento em lote e as otimizações funcionem, os workers devem estar rodando (idealmente via Supervisor).

**Worker de Clonagem (Core):**
```bash
php bin/catalog-clone-worker.php --verbose
```
*Opções:*
*   `--once`: Roda apenas um job e para (útil para cron/debug).
*   `--recover-stuck`: Destrava jobs presos em 'processing' há mais de 30min.

**Worker de Pós-Ações (SEO/TechSheet):**
```bash
php bin/clone-post-actions-worker.php --verbose
```
*Opções:*
*   `--retry-failed`: Reprocessa ações que falharam.

### 4. Monitoramento

*   Acompanhe o progresso na própria tela de clonagem.
*   Verifique logs em `storage/logs/catalog-clone-worker.log`.
*   Tabelas de banco de dados envolvidas: `jobs`, `cloned_items`, `clone_post_actions_log`.

## Desenvolvimento e Testes

**Rodar teste de integração:**
```bash
php scripts/test_clone_integration.php
```

Este script testa:
- Conexão com banco de dados
- Estrutura das tabelas
- Validações do serviço
- Estratégias de preço
- Integração com API do ML
- Fila de jobs

**Script de teste básico:**
```bash
php scripts/test_real_clone.php
```

**Reprocessar falhas:**
Se um job falhar (ex: erro de API 500), ele ficará como `failed` na tabela `jobs`.
Para tentar reprocessar jobs presos, use a flag `--recover-stuck` no worker. Para jobs falhos definitivos, recomenda-se reenviar via UI (o sistema evita duplicidade checando `cloned_items`).

## Estratégias de Preço

| Estratégia         | Descrição                                         |
|--------------------|---------------------------------------------------|
| `copy`             | Mantém o preço original do item                   |
| `markup_percent`   | Aplica % de aumento (ex: 10 = +10%)               |
| `markdown_percent` | Aplica % de desconto (ex: 15 = -15%)              |
| `competitive`      | Preço baseado na mediana dos concorrentes         |
| `aggressive`       | Preço abaixo da média para ganhar Buy Box         |
| `premium`          | Preço acima da média para posicionamento premium  |

## Troubleshooting

### Workers não processam jobs
1. Verifique se o `.env` está configurado corretamente
2. Verifique os logs em `storage/logs/catalog-clone-worker.log`
3. Execute `php bin/catalog-clone-worker.php --once --verbose` para debug

### Erro "Clonagem para mesma conta não permitida"
O sistema não permite clonar um item para a mesma conta de origem. Selecione uma conta de destino diferente.

### Erro "Item duplicado detectado"
O sistema detectou que este item (ou produto de catálogo) já existe na conta de destino. Isso evita publicações duplicadas.

## Requisitos para Produção

### Checklist de Pré-deploy

1. **Contas ML Configuradas**: Pelo menos 2 contas ativas com tokens válidos
2. **Workers em Execução**: Use Supervisor para manter os workers rodando
3. **Banco de Dados**: Todas as tabelas criadas (execute `scripts/validate_clone_module.php`)
4. **Logs**: Diretório `storage/logs` com permissões de escrita

### Configuração do Supervisor

```ini
# /etc/supervisor/conf.d/catalog-clone.conf
[program:catalog-clone-worker]
command=php /var/www/app/bin/catalog-clone-worker.php
directory=/var/www/app
autostart=true
autorestart=true
numprocs=1
stdout_logfile=/var/www/app/storage/logs/catalog-clone-worker.log
stderr_logfile=/var/www/app/storage/logs/catalog-clone-worker-error.log

[program:clone-post-actions-worker]
command=php /var/www/app/bin/clone-post-actions-worker.php
directory=/var/www/app
autostart=true
autorestart=true
numprocs=1
stdout_logfile=/var/www/app/storage/logs/clone-post-actions-worker.log
stderr_logfile=/var/www/app/storage/logs/clone-post-actions-worker-error.log
```

### Validação do Módulo

Execute o script de validação para verificar se tudo está configurado:

```bash
php scripts/validate_clone_module.php
```

Resultado esperado: **30/30 testes aprovados, 0 falhas**.

---

**Versão**: 3.0  
**Última atualização**: Janeiro 2025  
**Novidades v3.0**: Sistema de clonagem em lote por Seller com filtros, guardrails e preview de preços.
