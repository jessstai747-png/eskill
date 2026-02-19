# Plano de Modernização do Sistema ML Manager

## Objetivo
Padronizar todas as views do sistema para usar o layout moderno, garantindo consistência visual e melhor experiência do usuário.

## Estado Atual (Atualizado: 2026-01-18)
- **112 views** no total (dashboard + autenticação)
- **51 views (46%)** - Já usando layout moderno ✅
- **59 views (53%)** - Standalone sem layout padrão
- **0 views (0%)** - Usando layout antigo ✅ (CORRIGIDO)

## Fase 1: Correções Críticas ✅ CONCLUÍDA
**Status: COMPLETO**

### 1.1 Migrar `home.php` para layout moderno ✅
- Arquivo: `app/Views/dashboard/home.php`
- Status: **CONCLUÍDO** - Migrado para `layouts/modern/app.php`
- Melhorias: Adicionado page-header, breadcrumbs, stat-cards modernos, action-cards

### 1.2 Migrar `catalog/clone.php` para layout moderno ✅
- Arquivo: `app/Views/catalog/clone.php`
- Status: **CONCLUÍDO** - Migrado para `layouts/modern/app.php`
- Melhorias: Adicionado page-header, breadcrumbs

## Fase 2: Páginas de Alto Impacto ✅ CONCLUÍDA
**Status: COMPLETO**

### 2.1 Modernizar página de Pedidos ✅
- Arquivo: `app/Views/dashboard/orders-content.php` (555 linhas)
- Status: **CONCLUÍDO** - Adicionado page-header, breadcrumbs via controller

### 2.2 Modernizar AI Dashboard ✅
- Arquivo: `app/Views/dashboard/ai-dashboard.php` (1016 linhas)
- Status: **CONCLUÍDO** - Adicionado page-header, breadcrumbs via controller

### 2.3 Modernizar página de Análise ✅
- Arquivo: `app/Views/dashboard/analysis.php` (3430 linhas)
- Status: **CONCLUÍDO** - Adicionado page-header, breadcrumbs

### 2.4 Modernizar Automação ✅
- Arquivo: `app/Views/dashboard/automation.php` (735 linhas)
- Status: **CONCLUÍDO** - Migrado para layouts/modern/app.php, adicionado page-header

### 2.5 Modernizar Advanced Analytics ✅
- Arquivo: `app/Views/dashboard/advanced.php` (866 linhas)
- Status: **CONCLUÍDO** - Migrado para layouts/modern/app.php, adicionado page-header

### 2.6 Modernizar Predictive Analytics
- Arquivo: `app/Views/dashboard/predictive-analytics.php` (860 linhas)
- Melhorias: Integração com layout moderno

### 2.7 Modernizar Alertas
- Arquivo: `app/Views/dashboard/alerts.php` (164 linhas)
- Melhorias: Page header, notificações modernas

### 2.8 Modernizar página de Items (Produtos)
- Arquivo: `app/Views/dashboard/items.php`
- Melhorias: Grid responsivo, filtros avançados

## Fase 3: Padronização Geral
**Prioridade: MÉDIA | Esforço: 40-60 horas**

Migrar as 52+ views restantes em lotes:
- Lote 1: Views de Finanças (financials, settlement)
- Lote 2: Views de SEO (seo, seo-killer, tech-sheet)
- Lote 3: Views de Inventário e Logística
- Lote 4: Views de Configurações e Admin
- Lote 5: Views de Relatórios

## Padrão de Código

### Estrutura de View Moderna
```php
<?php
$pageTitle = 'Título da Página';
$pageDescription = 'Descrição breve';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '/dashboard'],
    ['label' => 'Seção', 'url' => '/dashboard/secao'],
    ['label' => 'Página Atual']
];
?>

<!-- Page Header -->
<?php include __DIR__ . '/../layouts/modern/partials/page-header.php'; ?>

<!-- Account Selector (se aplicável) -->
<?php include __DIR__ . '/../components/account-selector.php'; ?>

<!-- Conteúdo da Página -->
<div class="row g-4">
    <!-- Cards, tabelas, etc -->
</div>
```

### CSS Classes Padrão
- Cards: `.card`, `.card-header`, `.card-body`
- Botões: `.btn`, `.btn-primary`, `.btn-outline-*`
- Gradientes: `var(--primary-gradient)`, `var(--success-gradient)`
- Loading: `.skeleton`, `.card-loading`, `.btn.loading`

## Métricas de Sucesso
- [ ] 100% das views usando layout moderno
- [ ] Tempo de carregamento < 2s em todas as páginas
- [ ] Score de consistência visual > 90%
- [ ] Zero estilos inline duplicados

## Próximos Passos Imediatos
1. ~~Iniciar com `home.php` - migração para layout moderno~~ ✅ CONCLUÍDO
2. ~~Iniciar com `catalog/clone.php` - migração para layout moderno~~ ✅ CONCLUÍDO
3. ~~Modernizar página de Pedidos~~ ✅ CONCLUÍDO
4. ~~Modernizar páginas de alto impacto (AI Dashboard, Analysis, etc.)~~ ✅ CONCLUÍDO
5. Continuar com Predictive Analytics, Alerts e Items (Fase 2 restante)
6. Testar em ambiente de desenvolvimento
7. Validar responsividade mobile
8. Aplicar em produção
