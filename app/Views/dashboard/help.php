<?php

declare(strict_types=1);

$pageTitle = 'Central de Ajuda';
$activePage = 'help';
?>

<div class="container-fluid px-0 px-md-4 py-4">
    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card sticky-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-question-circle"></i> Tópicos</h5>
                </div>
                <div class="list-group list-group-flush" id="help-nav">
                    <button class="list-group-item list-group-item-action active" data-help-section="getting-started">
                        <i class="bi bi-play-circle"></i> Começando
                    </button>
                    <button class="list-group-item list-group-item-action" data-help-section="accounts">
                        <i class="bi bi-person-badge"></i> Contas ML
                    </button>
                    <button class="list-group-item list-group-item-action" data-help-section="analysis">
                        <i class="bi bi-graph-up"></i> Análises
                    </button>
                    <button class="list-group-item list-group-item-action" data-help-section="orders">
                        <i class="bi bi-cart"></i> Pedidos
                    </button>
                    <button class="list-group-item list-group-item-action" data-help-section="reports">
                        <i class="bi bi-file-earmark-text"></i> Relatórios
                    </button>
                    <button class="list-group-item list-group-item-action" data-help-section="troubleshooting">
                        <i class="bi bi-tools"></i> Solução de Problemas
                    </button>
                </div>
            </div>
        </div>
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex flex-column flex-md-row align-items-md-center gap-2">
                        <div>
                            <h4 class="mb-0">Central de Ajuda</h4>
                            <small class="text-muted">Guia rápido para aproveitar 100% das funcionalidades</small>
                        </div>
                        <div class="ms-md-auto">
                            <a class="btn btn-outline-primary btn-sm" href="/docs/USER_MANUAL.pdf" target="_blank">
                                <i class="bi bi-file-earmark-text"></i> Manual Completo
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="help-sections">
                        <section id="getting-started" class="help-section">
                            <div class="section-header">
                                <h5><i class="bi bi-play-circle"></i> Começando</h5>
                                <p class="mb-0 text-muted">Primeiros passos para configurar sua operação no Mercado Livre Manager.</p>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <h6><i class="bi bi-1-circle"></i> Criar Conta</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li>• Acesse a página de registro</li>
                                            <li>• Preencha nome, e-mail e senha</li>
                                            <li>• Confirme o e-mail para ativar</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <h6><i class="bi bi-2-circle"></i> Vincular Conta ML</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li>• Clique em “Vincular Conta ML”</li>
                                            <li>• Autorize a aplicação</li>
                                            <li>• Confirme o sucesso do vínculo</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <h6><i class="bi bi-3-circle"></i> Explorar Recursos</h6>
                                        <ul class="list-unstyled mb-0">
                                            <li>• Catálogo inteligente</li>
                                            <li>• Análises e SEO</li>
                                            <li>• Pedidos e notificações</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section id="accounts" class="help-section" hidden>
                            <div class="section-header">
                                <h5><i class="bi bi-person-badge"></i> Contas do Mercado Livre</h5>
                                <p class="mb-0 text-muted">Gerencie múltiplas contas com segurança e conformidade.</p>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <h6>Vincular múltiplas contas</h6>
                                        <p class="mb-0">Conecte quantas contas precisar; cada uma mantém tokens e permissões independentes.</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <h6>Renovação automática</h6>
                                        <p class="mb-0">Tokens são renovados automaticamente; notificamos sempre que uma ação manual for necessária.</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <h6>Remover ou pausar</h6>
                                        <p class="mb-0">Pausa temporária via painel ou remova definitivamente em Configurações &rarr; Contas.</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section id="analysis" class="help-section" hidden>
                            <div class="section-header">
                                <h5><i class="bi bi-graph-up"></i> Análise de Anúncios</h5>
                                <p class="mb-0 text-muted">Como extrair insights de categorias, marcas e concorrentes.</p>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6>Fluxo de análise</h6>
                                        <ol class="mb-0">
                                            <li>Acesse “Análise” no menu.</li>
                                            <li>Escolha categoria e marca.</li>
                                            <li>Defina filtros opcionais.</li>
                                            <li>Clique em “Analisar”.</li>
                                        </ol>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6>Filtros disponíveis</h6>
                                        <ul class="mb-0">
                                            <li>Condição: novo, usado ou todos.</li>
                                            <li>Preço: faixa mínima e máxima.</li>
                                            <li>Frete: grátis ou pago.</li>
                                            <li>Tipo: catálogo, comum ou ambos.</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="info-card">
                                        <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">Exportar resultados</h6>
                                                <p class="mb-0">Após gerar uma análise, exporte em CSV ou JSON para compartilhar com seu time.</p>
                                            </div>
                                            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.location.href='/api/export/analysis/sample'">
                                                <i class="bi bi-download"></i> Baixar exemplo
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section id="orders" class="help-section" hidden>
                            <div class="section-header">
                                <h5><i class="bi bi-cart"></i> Gestão de Pedidos</h5>
                                <p class="mb-0 text-muted">Entenda como a sincronização e o monitoramento funcionam.</p>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <h6>Sincronização</h6>
                                        <p class="mb-0">Atualização automática a cada 30 minutos (ajustável nas Configurações &rarr; Automações).</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <h6>Visualização</h6>
                                        <p class="mb-0">Painel unificado com pedidos recentes, status e conta de origem.</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-card">
                                        <h6>Filtros</h6>
                                        <p class="mb-0">Filtre por status, data, conta e SLA para priorizar atendimentos.</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section id="reports" class="help-section" hidden>
                            <div class="section-header">
                                <h5><i class="bi bi-file-earmark-text"></i> Relatórios</h5>
                                <p class="mb-0 text-muted">Indicadores operacionais e financeiros para cada cenário.</p>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6>Tipos disponíveis</h6>
                                        <ul class="mb-0">
                                            <li>Por conta: performance individual.</li>
                                            <li>Por categoria ou marca.</li>
                                            <li>Consolidado de todas as operações.</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6>Exportação</h6>
                                        <p class="mb-0">PDF para executivos, CSV para BI e JSON para integrações personalizadas.</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section id="troubleshooting" class="help-section" hidden>
                            <div class="section-header">
                                <h5><i class="bi bi-tools"></i> Solução de Problemas</h5>
                                <p class="mb-0 text-muted">Checklist rápido para os incidentes mais comuns.</p>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6>Erro ao vincular conta</h6>
                                        <ul class="mb-0">
                                            <li>Confirme a URL de callback no ML.</li>
                                            <li>Garanta que está logado no sistema.</li>
                                            <li>Repita o processo após alguns minutos.</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-card">
                                        <h6>Dados não aparecem</h6>
                                        <ul class="mb-0">
                                            <li>Verifique contas vinculadas.</li>
                                            <li>Aguarde a sincronização automática.</li>
                                            <li>Forçe uma sincronização em Configurações.</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="info-card">
                                        <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">Precisa de suporte avançado?</h6>
                                                <p class="mb-0">Execute o diagnóstico automático ou abra um ticket com logs anexos.</p>
                                            </div>
                                            <div class="btn-group">
                                                <a href="/diagnostic.php" class="btn btn-primary"><i class="bi bi-tools"></i> Diagnóstico</a>
                                                <a href="mailto:suporte@eskill.com.br" class="btn btn-outline-secondary"><i class="bi bi-envelope"></i> Abrir ticket</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .sticky-card {
        position: sticky;
        top: 1rem;
    }

    #help-nav .list-group-item {
        border: 0;
        border-bottom: 1px solid var(--bs-border-color-translucent);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    #help-nav .list-group-item:last-child {
        border-bottom: 0;
    }

    #help-nav .list-group-item.active {
        background-color: var(--bs-primary);
        color: #fff;
    }

    .section-header {
        margin-bottom: 1.25rem;
    }

    .info-card {
        border: 1px solid var(--bs-border-color-translucent);
        border-radius: var(--bs-border-radius);
        padding: 1rem;
        height: 100%;
        background: var(--bs-body-bg);
    }

    .info-card h6 {
        font-weight: 600;
        margin-bottom: 0.75rem;
    }

    .info-card ul,
    .info-card ol {
        padding-left: 1.25rem;
    }

    @media (max-width: 991.98px) {
        .sticky-card {
            position: static;
        }
    }
</style>

<script nonce="<?= CSP_NONCE ?>">
    (() => {
        const navItems = document.querySelectorAll('[data-help-section]');
        const sections = document.querySelectorAll('.help-section');

        const showSection = sectionId => {
            sections.forEach(section => {
                section.hidden = section.id !== sectionId;
            });

            navItems.forEach(item => {
                item.classList.toggle('active', item.dataset.helpSection === sectionId);
            });
        };

        navItems.forEach(item => {
            item.addEventListener('click', () => {
                const target = item.dataset.helpSection;
                showSection(target);
            });
        });

        const params = new URLSearchParams(window.location.search);
        const initial = params.get('section');
        const exists = initial && document.getElementById(initial);
        showSection(exists ? initial : 'getting-started');
    })();
</script>