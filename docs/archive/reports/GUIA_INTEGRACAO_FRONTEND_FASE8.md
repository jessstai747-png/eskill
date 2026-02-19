# Fase 8: Guia de Integração Frontend
**Sistema de Clonagem de Catálogo - UX Melhorias**

## 📋 Índice
1. [Arquivos Criados](#arquivos-criados)
2. [Dashboard Real-Time (SSE)](#dashboard-real-time)
3. [Exportação de Relatórios](#exportação-de-relatórios)
4. [Widget de Progresso](#widget-de-progresso)
5. [Integração Completa](#integração-completa)
6. [Troubleshooting](#troubleshooting)

---

## Arquivos Criados

### Backend (Fase 8)
- **Services**:
  - `app/Services/CloneRealtimeDashboardService.php` (615 linhas)
  - `app/Services/CloneReportExportService.php` (950 linhas)
  - `app/Services/CloneSeoIntegrationService.php` (800 linhas)
  - `app/Services/CloneProgressTrackerService.php` (650 linhas)

- **Controller Extensions**:
  - `app/Controllers/CatalogCloneController.php` (+13 endpoints)

- **Migrations**:
  - `database/migrations/2026_02_01_fase8_progress_tracking_seo.sql`

### Frontend (Novos Arquivos)
- **Exemplos HTML**:
  - `public/dashboard-realtime-example.html` (Dashboard SSE)
  - `public/relatorios-export-example.html` (UI Exportação)

- **JavaScript Widgets**:
  - `public/js/clone-progress-widget.js` (Widget reutilizável)

---

## Dashboard Real-Time (SSE)

### 1. Setup do Backend

#### 1.1 Aplicar Migration
```bash
mysql -u root -p eskill_ml < database/migrations/2026_02_01_fase8_progress_tracking_seo.sql
```

#### 1.2 Verificar Endpoints
```bash
# Testar SSE stream
curl -N http://localhost/api/catalog/clone/dashboard/stream

# Testar snapshot
curl http://localhost/api/catalog/clone/dashboard/snapshot
```

### 2. Integração Frontend

#### 2.1 HTML Base
```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Clone Dashboard</title>
    <link rel="stylesheet" href="/css/dashboard-styles.css">
</head>
<body>
    <div id="dashboard-container"></div>
    <script src="/js/dashboard-realtime.js"></script>
</body>
</html>
```

#### 2.2 JavaScript Conexão SSE
```javascript
// Arquivo: public/js/dashboard-realtime.js

class CloneDashboard {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.accountId = options.accountId || null;
        this.eventSource = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        
        this.connect();
    }

    connect() {
        const url = `/api/catalog/clone/dashboard/stream` +
                    (this.accountId ? `?account_id=${this.accountId}` : '');

        this.eventSource = new EventSource(url);

        this.eventSource.onopen = () => {
            console.log('Dashboard SSE connected');
            this.reconnectAttempts = 0;
            this.updateConnectionStatus(true);
        };

        this.eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.updateDashboard(data);
            } catch (error) {
                console.error('Error parsing SSE data:', error);
            }
        };

        this.eventSource.onerror = (error) => {
            console.error('SSE error:', error);
            this.updateConnectionStatus(false);
            this.eventSource.close();
            this.reconnect();
        };
    }

    reconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);
            console.log(`Reconnecting in ${delay}ms...`);
            setTimeout(() => this.connect(), delay);
        }
    }

    updateDashboard(data) {
        this.updateMetrics(data.metrics);
        this.updateAlerts(data.alerts);
        this.updateActiveJobs(data.active_jobs);
        this.updateSystemHealth(data.system_health);
    }

    // ... outros métodos de atualização
}

// Inicializar
const dashboard = new CloneDashboard('dashboard-container', {
    accountId: 'ACC123' // Opcional
});
```

### 3. Recursos do Dashboard

#### 3.1 Métricas Exibidas
- **Jobs últimas 24h**: Total de jobs e items clonados
- **Taxa de sucesso**: Porcentagem de jobs completados com sucesso
- **Taxa atual**: Jobs/minuto em tempo real
- **System Health**: Status geral do sistema (healthy/degraded/critical)

#### 3.2 Alertas em Tempo Real
```javascript
// Exemplo de alerta
{
    "severity": "warning",
    "type": "high_failure_rate",
    "message": "Taxa de falha acima de 10% (atual: 15.3%)",
    "timestamp": "2026-02-01T10:30:00Z"
}
```

#### 3.3 Jobs Ativos
- Job ID e nome da conta
- Status atual (processing, pending, completed, failed)
- Progresso visual com barra e porcentagem
- Items completados/total
- Tempo decorrido e ETA

---

## Exportação de Relatórios

### 1. UI de Exportação

#### 1.1 Formulário Básico
```html
<form id="export-form">
    <!-- Formato -->
    <select name="format">
        <option value="pdf">PDF</option>
        <option value="excel">Excel</option>
        <option value="csv">CSV</option>
        <option value="html">HTML</option>
    </select>

    <!-- Filtros -->
    <input type="text" name="account_id" placeholder="Conta (opcional)">
    <input type="date" name="date_start">
    <input type="date" name="date_end">

    <!-- Status -->
    <label><input type="checkbox" name="status[]" value="completed" checked> Completed</label>
    <label><input type="checkbox" name="status[]" value="processing" checked> Processing</label>
    <label><input type="checkbox" name="status[]" value="failed"> Failed</label>

    <!-- Seções -->
    <label><input type="checkbox" name="sections[]" value="summary" checked> Resumo</label>
    <label><input type="checkbox" name="sections[]" value="jobs" checked> Lista de Jobs</label>
    <label><input type="checkbox" name="sections[]" value="charts" checked> Gráficos</label>

    <button type="submit">Gerar Relatório</button>
</form>
```

#### 1.2 JavaScript Export
```javascript
async function exportReport(formData) {
    const response = await fetch('/api/catalog/clone/reports/export', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            format: formData.format,
            filters: {
                account_id: formData.account_id || undefined,
                date_start: formData.date_start,
                date_end: formData.date_end,
                status: formData.status
            },
            include_sections: formData.sections
        })
    });

    const result = await response.json();

    if (result.download_url) {
        // Baixar arquivo
        window.location.href = result.download_url;
    }
}

document.getElementById('export-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    await exportReport(Object.fromEntries(formData));
});
```

### 2. Formatos Suportados

#### 2.1 PDF (Requer TCPDF)
```bash
composer require tecnickcom/tcpdf
```

**Recursos**:
- Header e footer customizados
- Gráficos em SVG/PNG
- Multi-páginas automático
- Fontes customizadas

#### 2.2 Excel (Requer PhpSpreadsheet)
```bash
composer require phpoffice/phpspreadsheet
```

**Recursos**:
- Multi-abas (Summary, Jobs, Charts, Errors)
- Formatação rica (cores, bordas, bold)
- Fórmulas (AVERAGE, SUM)
- Gráficos nativos do Excel

#### 2.3 CSV (Nativo)
**Recursos**:
- Encoding UTF-8 com BOM
- Delimitador `;` (Excel-friendly)
- Headers automáticos
- Escape de aspas

#### 2.4 HTML (Nativo)
**Recursos**:
- Bootstrap 5 para estilo
- Chart.js para gráficos
- Responsivo
- Print-friendly

### 3. Exemplo de Uso API

```bash
# POST /api/catalog/clone/reports/export
curl -X POST http://localhost/api/catalog/clone/reports/export \
  -H "Content-Type: application/json" \
  -d '{
    "format": "pdf",
    "filters": {
      "account_id": "ACC123",
      "date_start": "2026-01-01",
      "date_end": "2026-01-31",
      "status": ["completed", "processing"]
    },
    "include_sections": ["summary", "jobs", "charts"]
  }'

# Response
{
  "success": true,
  "download_url": "/api/catalog/clone/reports/download/abc123.pdf"
}
```

---

## Widget de Progresso

### 1. Instalação

#### 1.1 Incluir Arquivo
```html
<script src="/js/clone-progress-widget.js"></script>
<link rel="stylesheet" href="/css/clone-progress-widget.css">
```

#### 1.2 Incluir CSS Inline
```html
<style>
    /* Copiar CSS do arquivo clone-progress-widget.js (seção widgetStyles) */
</style>
```

### 2. Uso Básico

#### 2.1 Widget Completo
```html
<div id="job-progress-1"></div>

<script>
    const widget = new CloneProgressWidget('job-progress-1', {
        jobId: 123,
        accountId: 'ACC123',
        autoRefresh: true,
        refreshInterval: 2000, // 2 segundos
        showPhaseDetails: true,
        showETA: true
    });
</script>
```

#### 2.2 Widget Compacto
```html
<div id="job-progress-compact"></div>

<script>
    const compactWidget = new CloneProgressWidget('job-progress-compact', {
        jobId: 456,
        compact: true, // Modo compacto
        showPhaseDetails: false,
        showETA: false
    });
</script>
```

### 3. Callbacks

#### 3.1 onComplete
```javascript
const widget = new CloneProgressWidget('progress', {
    jobId: 123,
    onComplete: (progress) => {
        console.log('Job completed!', progress);
        
        if (progress.status === 'completed') {
            alert(`✓ Job #${progress.job_id} concluído com sucesso!`);
        } else if (progress.status === 'failed') {
            alert(`✗ Job #${progress.job_id} falhou.`);
        }
    }
});
```

#### 3.2 onUpdate
```javascript
const widget = new CloneProgressWidget('progress', {
    jobId: 123,
    onUpdate: (progress) => {
        // Atualizar título da página
        document.title = `${Math.round(progress.overall_progress)}% - Clone Job`;
        
        // Atualizar badge de notificação
        updateNotificationBadge(progress.items_completed);
    }
});
```

#### 3.3 onError
```javascript
const widget = new CloneProgressWidget('progress', {
    jobId: 123,
    onError: (error) => {
        console.error('Widget error:', error);
        
        // Exibir mensagem de erro
        showErrorToast('Erro ao buscar progresso do job');
        
        // Tentar reconectar manualmente
        setTimeout(() => widget.startAutoRefresh(), 5000);
    }
});
```

### 4. Métodos de Controle

```javascript
const widget = new CloneProgressWidget('progress', { jobId: 123 });

// Controlar refresh
widget.startAutoRefresh();
widget.stopAutoRefresh();

// Trocar job dinamicamente
widget.setJobId(456);

// Obter último progresso
const lastProgress = widget.getLastProgress();
console.log('Current progress:', lastProgress.overall_progress);

// Destruir widget (cleanup)
widget.destroy();
```

---

## Integração Completa

### 1. Página de Listagem de Jobs

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Jobs de Clonagem</title>
    <link rel="stylesheet" href="/css/clone-progress-widget.css">
</head>
<body>
    <h1>Jobs de Clonagem</h1>

    <!-- Botão de exportar -->
    <button onclick="showExportModal()">📊 Exportar Relatório</button>

    <!-- Lista de jobs -->
    <table id="jobs-table">
        <thead>
            <tr>
                <th>Job ID</th>
                <th>Conta</th>
                <th>Status</th>
                <th>Progresso</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <!-- Será preenchido dinamicamente -->
        </tbody>
    </table>

    <script src="/js/clone-progress-widget.js"></script>
    <script>
        // Fetch jobs
        async function loadJobs() {
            const response = await fetch('/api/catalog/clone/jobs?status=processing,pending');
            const jobs = await response.json();

            const tbody = document.querySelector('#jobs-table tbody');
            tbody.innerHTML = jobs.map(job => `
                <tr>
                    <td>#${job.id}</td>
                    <td>${job.account_name}</td>
                    <td><span class="badge ${job.status}">${job.status}</span></td>
                    <td>
                        <div id="progress-${job.id}"></div>
                    </td>
                    <td>
                        <button onclick="viewDetails(${job.id})">Ver Detalhes</button>
                    </td>
                </tr>
            `).join('');

            // Criar widgets de progresso
            jobs.forEach(job => {
                new CloneProgressWidget(`progress-${job.id}`, {
                    jobId: job.id,
                    compact: true,
                    autoRefresh: job.status === 'processing'
                });
            });
        }

        loadJobs();
        setInterval(loadJobs, 10000); // Refresh list every 10s
    </script>
</body>
</html>
```

### 2. Modal de Detalhes do Job

```html
<div id="job-details-modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2>Job #<span id="modal-job-id">-</span></h2>
        
        <!-- Widget de progresso completo -->
        <div id="modal-progress-widget"></div>

        <!-- Histórico de progresso -->
        <h3>Histórico de Progresso</h3>
        <div id="progress-history"></div>

        <!-- Análise SEO (se aplicável) -->
        <div id="seo-analysis"></div>
    </div>
</div>

<script>
    let detailsWidget = null;

    async function viewDetails(jobId) {
        document.getElementById('modal-job-id').textContent = jobId;
        document.getElementById('job-details-modal').style.display = 'block';

        // Criar widget de progresso completo
        if (detailsWidget) detailsWidget.destroy();
        detailsWidget = new CloneProgressWidget('modal-progress-widget', {
            jobId: jobId,
            autoRefresh: true,
            showPhaseDetails: true,
            showETA: true
        });

        // Carregar histórico
        const historyResponse = await fetch(`/api/catalog/clone/progress/${jobId}/history`);
        const history = await historyResponse.json();
        renderProgressHistory(history);

        // Carregar análise SEO (se disponível)
        loadSeoAnalysis(jobId);
    }

    function closeModal() {
        document.getElementById('job-details-modal').style.display = 'none';
        if (detailsWidget) {
            detailsWidget.destroy();
            detailsWidget = null;
        }
    }

    function renderProgressHistory(history) {
        const container = document.getElementById('progress-history');
        // Renderizar gráfico de linha com progresso ao longo do tempo
        // Usar Chart.js ou similar
    }

    async function loadSeoAnalysis(jobId) {
        try {
            const response = await fetch(`/api/catalog/clone/seo/analyze/${jobId}`);
            const analysis = await response.json();
            
            const container = document.getElementById('seo-analysis');
            container.innerHTML = `
                <h3>Análise SEO</h3>
                <p><strong>Score:</strong> ${analysis.score}/100</p>
                <p><strong>Otimizações aplicadas:</strong> ${analysis.optimizations_applied.length}</p>
                <!-- Mais detalhes... -->
            `;
        } catch (error) {
            console.log('SEO analysis not available for this job');
        }
    }
</script>
```

### 3. Dashboard Principal

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Clone Dashboard</title>
    <link rel="stylesheet" href="/css/dashboard-styles.css">
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar com filtros -->
        <aside class="sidebar">
            <h2>Filtros</h2>
            <select id="account-filter">
                <option value="">Todas as contas</option>
                <!-- Populado dinamicamente -->
            </select>
        </aside>

        <!-- Dashboard principal -->
        <main class="dashboard-main">
            <iframe src="/dashboard-realtime-example.html" 
                    id="dashboard-iframe"
                    frameborder="0"
                    style="width: 100%; min-height: 800px;"></iframe>
        </main>
    </div>

    <script>
        // Atualizar iframe quando filtro mudar
        document.getElementById('account-filter').addEventListener('change', (e) => {
            const accountId = e.target.value;
            const iframe = document.getElementById('dashboard-iframe');
            iframe.src = `/dashboard-realtime-example.html?account_id=${accountId}`;
        });
    </script>
</body>
</html>
```

---

## Troubleshooting

### 1. SSE Não Conecta

**Problema**: `EventSource failed to connect`

**Soluções**:

```bash
# 1. Verificar se o endpoint está acessível
curl -N http://localhost/api/catalog/clone/dashboard/stream

# 2. Verificar headers SSE
curl -I http://localhost/api/catalog/clone/dashboard/stream

# Headers esperados:
# Content-Type: text/event-stream
# Cache-Control: no-cache
# Connection: keep-alive

# 3. Verificar timeout do PHP
# Em php.ini:
max_execution_time = 0
set_time_limit(0)

# 4. Verificar timeout do Nginx
# Em nginx.conf:
proxy_read_timeout 3600;
proxy_buffering off;
```

### 2. Widget Não Atualiza

**Problema**: Widget inicializa mas não atualiza progresso

**Debug**:

```javascript
const widget = new CloneProgressWidget('progress', {
    jobId: 123,
    autoRefresh: true,
    onUpdate: (progress) => {
        console.log('Update received:', progress);
    },
    onError: (error) => {
        console.error('Widget error:', error);
    }
});

// Verificar se job existe
fetch('/api/catalog/clone/progress/123')
    .then(r => r.json())
    .then(d => console.log('Job data:', d))
    .catch(e => console.error('Fetch error:', e));
```

### 3. Exportação Falha

**Problema**: Erro ao gerar PDF/Excel

**Verificar dependências**:

```bash
# 1. Verificar instalação TCPDF
composer show tecnickcom/tcpdf

# 2. Verificar instalação PhpSpreadsheet
composer show phpoffice/phpspreadsheet

# 3. Verificar permissões
chmod 755 storage/exports
chown www-data:www-data storage/exports

# 4. Testar fallback para HTML/CSV
curl -X POST http://localhost/api/catalog/clone/reports/export \
  -H "Content-Type: application/json" \
  -d '{"format":"csv","filters":{}}'
```

### 4. CORS em Produção

**Problema**: `CORS policy blocked` ao fazer fetch

**Solução em CatalogCloneController.php**:

```php
private function setCorsHeaders(): void
{
    header('Access-Control-Allow-Origin: https://seudominio.com');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 3600');
}

public function streamDashboard(): void
{
    $this->setCorsHeaders();
    // ... resto do método
}
```

### 5. Performance SSE

**Problema**: Dashboard lento com muitos jobs

**Otimizações**:

```php
// Em CloneRealtimeDashboardService.php

private function getActiveJobs(?string $accountId = null): array
{
    // ADICIONAR: Limitar jobs retornados
    $query = "SELECT * FROM catalog_clone_jobs 
              WHERE status IN ('processing', 'pending')";
    
    if ($accountId) {
        $query .= " AND account_id = :account_id";
    }
    
    // NOVO: Limitar a 20 jobs mais recentes
    $query .= " ORDER BY created_at DESC LIMIT 20";
    
    // ... resto da lógica
}
```

---

## Checklist de Deploy

### Backend
- [ ] Migrations aplicadas (`2026_02_01_fase8_progress_tracking_seo.sql`)
- [ ] Dependências instaladas (TCPDF, PhpSpreadsheet - opcional)
- [ ] Diretório `storage/exports` criado com permissões 755
- [ ] PHP `max_execution_time` = 0 para SSE
- [ ] Nginx/Apache configurado para SSE (timeouts, buffering off)

### Frontend
- [ ] Arquivos JS/CSS copiados para `public/`
- [ ] Dashboard HTML acessível via navegador
- [ ] SSE conectando corretamente
- [ ] Widgets de progresso renderizando
- [ ] Exportação de relatórios funcionando

### Testes
- [ ] Dashboard SSE stream (`/api/catalog/clone/dashboard/stream`)
- [ ] Dashboard snapshot (`/api/catalog/clone/dashboard/snapshot`)
- [ ] Export PDF (`format=pdf`)
- [ ] Export Excel (`format=excel`)
- [ ] Export CSV (`format=csv`)
- [ ] Widget progresso (`/api/catalog/clone/progress/{id}`)
- [ ] Histórico progresso (`/api/catalog/clone/progress/{id}/history`)

---

## Próximos Passos

### Melhorias Futuras (Fase 9+)
1. **Notificações Push**: WebSockets para alertas instantâneos
2. **Dashboard Analytics**: Gráficos de tendências e previsões
3. **AI Insights**: Recomendações automáticas baseadas em ML
4. **Mobile App**: Versão nativa iOS/Android
5. **Multi-idioma**: i18n para interface

### Performance
1. **CDN**: Servir assets estáticos via CDN
2. **Service Workers**: Cache offline do dashboard
3. **Lazy Loading**: Carregar jobs sob demanda
4. **Virtualização**: Virtual scroll para listas longas

---

**Desenvolvido por eskill.com.br - Fase 8**  
**Versão**: 1.0.0  
**Data**: 2026-02-01
