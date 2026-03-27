<!-- Modal Configurações AutoPilot -->
<div class="modal fade" id="autopilotConfigModal" tabindex="-1" aria-labelledby="autopilotConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title" id="autopilotConfigModalLabel">
                    <i class="bi bi-robot"></i> Configurações do AutoPilot
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <!-- Coluna Esquerda: Configurações -->
                    <div class="col-lg-8">
                        <!-- Seção 1: Frequência -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-clock"></i> Frequência de Execução</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Periodicidade</label>
                                        <div class="frequency-options">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="frequency" id="freq-daily" value="daily" checked>
                                                <label class="form-check-label" for="freq-daily">
                                                    <strong>Diário</strong> <span class="badge bg-success">Recomendado</span>
                                                    <br><small class="text-muted">Otimizações constantes para melhor performance</small>
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="frequency" id="freq-2days" value="2days">
                                                <label class="form-check-label" for="freq-2days">
                                                    <strong>A cada 2 dias</strong>
                                                    <br><small class="text-muted">Bom equilíbrio entre frequência e estabilidade</small>
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="frequency" id="freq-weekly" value="weekly">
                                                <label class="form-check-label" for="freq-weekly">
                                                    <strong>Semanal</strong>
                                                    <br><small class="text-muted">Para catálogos mais estáveis</small>
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="frequency" id="freq-monthly" value="monthly">
                                                <label class="form-check-label" for="freq-monthly">
                                                    <strong>Mensal</strong>
                                                    <br><small class="text-muted">Otimizações pontuais e revisões gerais</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Horário de Execução</label>
                                        <select class="form-select mb-3" id="execution-time">
                                            <option value="00:00">00:00 - Madrugada</option>
                                            <option value="02:00" selected>02:00 - Madrugada (Recomendado)</option>
                                            <option value="06:00">06:00 - Manhã Cedo</option>
                                            <option value="09:00">09:00 - Manhã</option>
                                            <option value="12:00">12:00 - Meio-dia</option>
                                            <option value="15:00">15:00 - Tarde</option>
                                            <option value="18:00">18:00 - Final da Tarde</option>
                                            <option value="21:00">21:00 - Noite</option>
                                        </select>

                                        <div class="alert alert-info mb-0">
                                            <small>
                                                <i class="bi bi-lightbulb"></i> <strong>Dica:</strong>
                                                Executar na madrugada evita impactos durante picos de venda.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Seção 2: Otimizações Ativas -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-toggles"></i> Otimizações Ativas</h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Selecione quais otimizações o AutoPilot deve executar automaticamente:</p>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="optimization-toggle">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="opt-titles" checked>
                                                <label class="form-check-label" for="opt-titles">
                                                    <strong>📝 Otimizar Títulos</strong>
                                                    <br><small>Melhora títulos para aumentar visibilidade</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="optimization-toggle">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="opt-descriptions" checked>
                                                <label class="form-check-label" for="opt-descriptions">
                                                    <strong>📄 Otimizar Descrições</strong>
                                                    <br><small>Cria descrições persuasivas e completas</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="optimization-toggle">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="opt-attributes" checked>
                                                <label class="form-check-label" for="opt-attributes">
                                                    <strong>🏷️ Preencher Atributos</strong>
                                                    <br><small>Completa atributos faltantes automaticamente</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="optimization-toggle">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="opt-images">
                                                <label class="form-check-label" for="opt-images">
                                                    <strong>📸 Analisar Imagens</strong>
                                                    <br><small>Identifica problemas nas imagens</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="optimization-toggle">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="opt-pricing">
                                                <label class="form-check-label" for="opt-pricing">
                                                    <strong>💰 Ajustar Preços Competitivos</strong>
                                                    <br><small>Sugere ajustes baseados na concorrência</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="optimization-toggle">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="opt-keywords">
                                                <label class="form-check-label" for="opt-keywords">
                                                    <strong>🔑 Atualizar Keywords</strong>
                                                    <br><small>Mantém keywords relevantes e atuais</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Seção 3: Limites e Segurança -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-shield-check"></i> Limites e Segurança</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Máximo de Itens por Execução</label>
                                        <div class="d-flex align-items-center">
                                            <input type="range" class="form-range flex-grow-1" min="10" max="100" step="10" value="50" id="max-items-slider">
                                            <span class="ms-3 badge bg-primary" id="max-items-value" style="min-width: 50px;">50</span>
                                        </div>
                                        <small class="text-muted">Limita quantos produtos serão otimizados por vez</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Priorizar Itens com Score Abaixo de</label>
                                        <select class="form-select" id="priority-score">
                                            <option value="30">30 (Críticos)</option>
                                            <option value="50">50 (Baixo)</option>
                                            <option value="70" selected>70 (Médio)</option>
                                            <option value="100">100 (Todos)</option>
                                        </select>
                                        <small class="text-muted">Foca nos produtos que mais precisam</small>
                                    </div>
                                </div>

                                <hr>

                                <div class="safety-options">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="auto-apply">
                                        <label class="form-check-label" for="auto-apply">
                                            <strong>⚡ Aplicar Mudanças Automaticamente (Sem Aprovação)</strong>
                                            <br><small class="text-muted">As otimizações serão aplicadas direto no ML sem revisão manual</small>
                                            <br><small class="text-warning">⚠️ Use com cuidado: mudanças são irreversíveis</small>
                                        </label>
                                    </div>

                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="notify-critical" checked>
                                        <label class="form-check-label" for="notify-critical">
                                            <strong>🔔 Notificar Antes de Mudanças Críticas</strong>
                                            <br><small class="text-muted">Receba alerta antes de alterações importantes (ex: mudança de categoria)</small>
                                        </label>
                                    </div>

                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="backup-enabled" checked>
                                        <label class="form-check-label" for="backup-enabled">
                                            <strong>💾 Criar Backup Antes de Aplicar</strong>
                                            <br><small class="text-muted">Salva versão anterior para possível restauração</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Seção 4: Notificações -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-bell"></i> Notificações</h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Configure como deseja ser notificado sobre as otimizações:</p>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="notify-email" checked>
                                            <label class="form-check-label" for="notify-email">
                                                <strong>📧 Email</strong>
                                            </label>
                                        </div>
                                        <div id="email-config" class="ms-4">
                                            <input type="email" class="form-control form-control-sm" id="notify-email-address" placeholder="seu@email.com">
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="notify-whatsapp">
                                            <label class="form-check-label" for="notify-whatsapp">
                                                <strong>💬 WhatsApp</strong>
                                            </label>
                                        </div>
                                        <div id="whatsapp-config" class="ms-4" style="display: none;">
                                            <input type="tel" class="form-control form-control-sm" id="notify-whatsapp-phone" placeholder="+55 11 99999-9999">
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="notify-dashboard" checked>
                                            <label class="form-check-label" for="notify-dashboard">
                                                <strong>🔔 Dashboard</strong>
                                            </label>
                                        </div>
                                        <small class="text-muted ms-4">Notificações in-app</small>
                                    </div>
                                </div>

                                <hr>

                                <div class="row">
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Notificar Quando</label>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="notify-on-complete" checked>
                                            <label class="form-check-label" for="notify-on-complete">
                                                Otimização concluída
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="notify-on-error" checked>
                                            <label class="form-check-label" for="notify-on-error">
                                                Ocorrer erro
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="notify-on-start">
                                            <label class="form-check-label" for="notify-on-start">
                                                Iniciar otimização
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="notify-weekly-report" checked>
                                            <label class="form-check-label" for="notify-weekly-report">
                                                Relatório semanal
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Seção 5: Exclusões -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="bi bi-x-circle"></i> Listas de Exclusão</h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Defina produtos ou categorias que o AutoPilot NUNCA deve otimizar:</p>

                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Produtos Específicos</label>
                                        <div class="input-group mb-2">
                                            <input type="text" class="form-control" id="exclude-product-input" placeholder="ID ou nome do produto">
                                            <button class="btn btn-outline-primary" onclick="AutoPilotConfig.addExcludedProduct()">
                                                <i class="bi bi-plus"></i> Adicionar
                                            </button>
                                        </div>
                                        <div id="excluded-products-list" class="exclusion-list">
                                            <!-- Lista preenchida dinamicamente -->
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Categorias Completas</label>
                                        <div class="input-group mb-2">
                                            <select class="form-select" id="exclude-category-select">
                                                <option value="">Selecione uma categoria...</option>
                                            </select>
                                            <button class="btn btn-outline-primary" onclick="AutoPilotConfig.addExcludedCategory()">
                                                <i class="bi bi-plus"></i> Adicionar
                                            </button>
                                        </div>
                                        <div id="excluded-categories-list" class="exclusion-list">
                                            <!-- Lista preenchida dinamicamente -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Coluna Direita: Preview e Status -->
                    <div class="col-lg-4">
                        <div class="card sticky-top" style="top: 20px;">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0"><i class="bi bi-eye"></i> Preview das Configurações</h6>
                            </div>
                            <div class="card-body">
                                <!-- Status Atual -->
                                <div class="text-center mb-4">
                                    <div class="autopilot-status-badge" id="autopilot-status">
                                        <i class="bi bi-circle-fill"></i> <span id="status-text">Desativado</span>
                                    </div>
                                    <p class="text-muted mt-2 mb-0" id="last-run-text">Nunca executado</p>
                                </div>

                                <hr>

                                <!-- Resumo das Configurações -->
                                <h6 class="mb-3">📋 Resumo</h6>
                                <div class="config-summary">
                                    <div class="summary-item">
                                        <span class="summary-label">Frequência:</span>
                                        <span class="summary-value" id="summary-frequency">Diário</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Horário:</span>
                                        <span class="summary-value" id="summary-time">02:00</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Otimizações:</span>
                                        <span class="summary-value" id="summary-opts">6 ativas</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Limite por run:</span>
                                        <span class="summary-value" id="summary-limit">50 itens</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Auto-aplicar:</span>
                                        <span class="summary-value" id="summary-auto">Não</span>
                                    </div>
                                    <div class="summary-item">
                                        <span class="summary-label">Notificações:</span>
                                        <span class="summary-value" id="summary-notify">Email, Dashboard</span>
                                    </div>
                                </div>

                                <hr>

                                <!-- Próxima Execução -->
                                <div class="next-run-card">
                                    <h6 class="mb-2">⏰ Próxima Execução</h6>
                                    <div class="next-run-time" id="next-run-time">
                                        Aguardando configuração...
                                    </div>
                                    <small class="text-muted" id="next-run-countdown"></small>
                                </div>

                                <hr>

                                <!-- Estimativa de Impacto -->
                                <div class="impact-estimate">
                                    <h6 class="mb-3">📊 Impacto Estimado</h6>
                                    <div class="impact-item">
                                        <div class="impact-label">Produtos afetados:</div>
                                        <div class="impact-value" id="impact-products">~50/execução</div>
                                    </div>
                                    <div class="impact-item">
                                        <div class="impact-label">Melhoria média esperada:</div>
                                        <div class="impact-value text-success">+15-25 pontos</div>
                                    </div>
                                    <div class="impact-item">
                                        <div class="impact-label">Tempo de execução:</div>
                                        <div class="impact-value" id="impact-time">~10-15 min</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-outline-warning" onclick="AutoPilotConfig.restoreDefaults()">
                    <i class="bi bi-arrow-counterclockwise"></i> Restaurar Padrão
                </button>
                <button type="button" class="btn btn-outline-primary" onclick="AutoPilotConfig.testRun()">
                    <i class="bi bi-play-circle"></i> Executar Teste
                </button>
                <button type="button" class="btn btn-success" onclick="AutoPilotConfig.saveConfig()">
                    <i class="bi bi-check-circle"></i> Salvar Configurações
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .frequency-options .form-check {
        padding: 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 10px;
        transition: all 0.3s;
    }

    .frequency-options .form-check:hover {
        border-color: #667eea;
        background: #f8f9ff;
    }

    .frequency-options .form-check-input:checked+.form-check-label {
        color: #667eea;
    }

    .optimization-toggle {
        padding: 12px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 12px;
        transition: all 0.3s;
    }

    .optimization-toggle:hover {
        background: #f8f9fa;
    }

    .form-check-input:checked {
        background-color: #667eea;
        border-color: #667eea;
    }

    .safety-options .form-check {
        padding: 12px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .exclusion-list {
        max-height: 200px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .exclusion-item {
        background: #FFF3CD;
        padding: 8px 12px;
        border-radius: 6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
    }

    .exclusion-item button {
        padding: 2px 8px;
        font-size: 11px;
    }

    .autopilot-status-badge {
        display: inline-block;
        padding: 10px 20px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 14px;
    }

    .autopilot-status-badge.active {
        background: #E8F5E9;
        color: #27AE60;
    }

    .autopilot-status-badge.inactive {
        background: #FADBD8;
        color: #E74C3C;
    }

    .config-summary {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
    }

    .summary-label {
        color: #666;
    }

    .summary-value {
        font-weight: 600;
        color: #333;
    }

    .next-run-card {
        background: linear-gradient(135deg, #E8F5E9 0%, #FFFFFF 100%);
        padding: 15px;
        border-radius: 8px;
        text-align: center;
    }

    .next-run-time {
        font-size: 18px;
        font-weight: bold;
        color: #27AE60;
        margin: 10px 0;
    }

    .impact-estimate {
        background: #F8F9FA;
        padding: 15px;
        border-radius: 8px;
    }

    .impact-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 13px;
    }

    .impact-label {
        color: #666;
    }

    .impact-value {
        font-weight: 600;
        color: #333;
    }
</style>

<script nonce="<?= CSP_NONCE ?>">
    const AutoPilotConfig = {
        config: {
            frequency: 'daily',
            execution_time: '02:00',
            optimizations: {
                titles: true,
                descriptions: true,
                attributes: true,
                images: false,
                pricing: false,
                keywords: false
            },
            max_items: 50,
            priority_score: 70,
            auto_apply: false,
            notify_critical: true,
            backup_enabled: true,
            notifications: {
                email: true,
                whatsapp: false,
                dashboard: true,
                on_complete: true,
                on_error: true,
                on_start: false,
                weekly_report: true
            },
            excluded_products: [],
            excluded_categories: []
        },

        init() {
            console.log('AutoPilot Config initialized');
            this.loadConfig();
            this.setupListeners();
            this.loadCategories();
        },

        setupListeners() {
            // Slider de itens
            document.getElementById('max-items-slider').addEventListener('input', (e) => {
                document.getElementById('max-items-value').textContent = e.target.value;
                this.updateSummary();
            });

            // Toggle WhatsApp config
            document.getElementById('notify-whatsapp').addEventListener('change', (e) => {
                document.getElementById('whatsapp-config').style.display = e.target.checked ? 'block' : 'none';
            });

            // Radio buttons de frequência
            document.querySelectorAll('input[name="frequency"]').forEach(radio => {
                radio.addEventListener('change', () => this.updateSummary());
            });

            // Checkboxes de otimizações
            ['titles', 'descriptions', 'attributes', 'images', 'pricing', 'keywords'].forEach(opt => {
                document.getElementById(`opt-${opt}`).addEventListener('change', () => this.updateSummary());
            });

            // Auto-apply checkbox
            document.getElementById('auto-apply').addEventListener('change', () => this.updateSummary());
        },

        async loadConfig() {
            try {
                const data = await requestJson('/api/seo-killer/autopilot/config');

                if (data.success && data.config) {
                    this.config = data.config;
                    this.applyConfigToForm();
                }

                this.updateSummary();
                this.updateStatus();
            } catch (error) {
                console.error('Erro ao carregar configurações:', error);
            }
        },

        applyConfigToForm() {
            // Frequência
            document.getElementById(`freq-${this.config.frequency}`).checked = true;
            document.getElementById('execution-time').value = this.config.execution_time;

            // Otimizações
            Object.entries(this.config.optimizations).forEach(([key, value]) => {
                const checkbox = document.getElementById(`opt-${key}`);
                if (checkbox) checkbox.checked = value;
            });

            // Limites
            document.getElementById('max-items-slider').value = this.config.max_items;
            document.getElementById('max-items-value').textContent = this.config.max_items;
            document.getElementById('priority-score').value = this.config.priority_score;

            // Segurança
            document.getElementById('auto-apply').checked = this.config.auto_apply;
            document.getElementById('notify-critical').checked = this.config.notify_critical;
            document.getElementById('backup-enabled').checked = this.config.backup_enabled;

            // Notificações
            Object.entries(this.config.notifications).forEach(([key, value]) => {
                const checkbox = document.getElementById(`notify-${key.replace('_', '-')}`);
                if (checkbox) checkbox.checked = value;
            });

            // Exclusões
            this.renderExcludedProducts();
            this.renderExcludedCategories();
        },

        async loadCategories() {
            try {
                const data = await requestJson('/api/categories');

                if (data.categories) {
                    const select = document.getElementById('exclude-category-select');
                    select.innerHTML = '<option value="">Selecione uma categoria...</option>' +
                        data.categories.map(cat =>
                            `<option value="${cat.id}">${cat.name}</option>`
                        ).join('');
                }
            } catch (error) {
                console.error('Erro ao carregar categorias:', error);
            }
        },

        updateSummary() {
            // Frequência
            const frequency = document.querySelector('input[name="frequency"]:checked').value;
            const frequencyTexts = {
                daily: 'Diário',
                '2days': 'A cada 2 dias',
                weekly: 'Semanal',
                monthly: 'Mensal'
            };
            document.getElementById('summary-frequency').textContent = frequencyTexts[frequency];

            // Horário
            const time = document.getElementById('execution-time').value;
            document.getElementById('summary-time').textContent = time;

            // Otimizações ativas
            const activeOpts = ['titles', 'descriptions', 'attributes', 'images', 'pricing', 'keywords']
                .filter(opt => document.getElementById(`opt-${opt}`).checked).length;
            document.getElementById('summary-opts').textContent = `${activeOpts} ativas`;

            // Limite
            const limit = document.getElementById('max-items-slider').value;
            document.getElementById('summary-limit').textContent = `${limit} itens`;

            // Auto-apply
            const autoApply = document.getElementById('auto-apply').checked;
            document.getElementById('summary-auto').textContent = autoApply ? 'Sim' : 'Não';

            // Notificações
            const notifyTypes = [];
            if (document.getElementById('notify-email').checked) notifyTypes.push('Email');
            if (document.getElementById('notify-whatsapp').checked) notifyTypes.push('WhatsApp');
            if (document.getElementById('notify-dashboard').checked) notifyTypes.push('Dashboard');
            document.getElementById('summary-notify').textContent = notifyTypes.join(', ') || 'Nenhuma';

            // Próxima execução
            this.calculateNextRun();
        },

        calculateNextRun() {
            const frequency = document.querySelector('input[name="frequency"]:checked').value;
            const time = document.getElementById('execution-time').value;

            const now = new Date();
            const [hour, minute] = time.split(':');
            const nextRun = new Date(now);
            nextRun.setHours(parseInt(hour), parseInt(minute), 0);

            if (nextRun <= now) {
                nextRun.setDate(nextRun.getDate() + 1);
            }

            const formattedDate = nextRun.toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            const formattedTime = nextRun.toLocaleTimeString('pt-BR', {
                hour: '2-digit',
                minute: '2-digit'
            });

            document.getElementById('next-run-time').textContent = `${formattedDate} às ${formattedTime}`;

            // Countdown
            const diff = nextRun - now;
            const hours = Math.floor(diff / (1000 * 60 * 60));
            document.getElementById('next-run-countdown').textContent = `Faltam ${hours}h`;
        },

        updateStatus() {
            const statusBadge = document.getElementById('autopilot-status');
            const isActive = this.config.enabled;

            statusBadge.className = `autopilot-status-badge ${isActive ? 'active' : 'inactive'}`;
            document.getElementById('status-text').textContent = isActive ? 'Ativo' : 'Desativado';
        },

        addExcludedProduct() {
            const input = document.getElementById('exclude-product-input');
            const value = input.value.trim();

            if (!value) {
                SEOKiller.showError('Digite o ID ou nome do produto');
                return;
            }

            if (!this.config.excluded_products.includes(value)) {
                this.config.excluded_products.push(value);
                this.renderExcludedProducts();
                input.value = '';
            }
        },

        removeExcludedProduct(product) {
            this.config.excluded_products = this.config.excluded_products.filter(p => p !== product);
            this.renderExcludedProducts();
        },

        renderExcludedProducts() {
            const container = document.getElementById('excluded-products-list');

            if (this.config.excluded_products.length === 0) {
                container.innerHTML = '<small class="text-muted">Nenhum produto excluído</small>';
                return;
            }

            container.innerHTML = this.config.excluded_products.map(product => `
            <div class="exclusion-item">
                <span>${product}</span>
                <button class="btn btn-sm btn-outline-danger" onclick="AutoPilotConfig.removeExcludedProduct('${product}')">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `).join('');
        },

        addExcludedCategory() {
            const select = document.getElementById('exclude-category-select');
            const value = select.value;
            const text = select.options[select.selectedIndex].text;

            if (!value) {
                SEOKiller.showError('Selecione uma categoria');
                return;
            }

            if (!this.config.excluded_categories.find(c => c.id === value)) {
                this.config.excluded_categories.push({
                    id: value,
                    name: text
                });
                this.renderExcludedCategories();
                select.value = '';
            }
        },

        removeExcludedCategory(categoryId) {
            this.config.excluded_categories = this.config.excluded_categories.filter(c => c.id !== categoryId);
            this.renderExcludedCategories();
        },

        renderExcludedCategories() {
            const container = document.getElementById('excluded-categories-list');

            if (this.config.excluded_categories.length === 0) {
                container.innerHTML = '<small class="text-muted">Nenhuma categoria excluída</small>';
                return;
            }

            container.innerHTML = this.config.excluded_categories.map(cat => `
            <div class="exclusion-item">
                <span>${cat.name}</span>
                <button class="btn btn-sm btn-outline-danger" onclick="AutoPilotConfig.removeExcludedCategory('${cat.id}')">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `).join('');
        },

        gatherConfig() {
            return {
                frequency: document.querySelector('input[name="frequency"]:checked').value,
                execution_time: document.getElementById('execution-time').value,
                optimizations: {
                    titles: document.getElementById('opt-titles').checked,
                    descriptions: document.getElementById('opt-descriptions').checked,
                    attributes: document.getElementById('opt-attributes').checked,
                    images: document.getElementById('opt-images').checked,
                    pricing: document.getElementById('opt-pricing').checked,
                    keywords: document.getElementById('opt-keywords').checked
                },
                max_items: parseInt(document.getElementById('max-items-slider').value),
                priority_score: parseInt(document.getElementById('priority-score').value),
                auto_apply: document.getElementById('auto-apply').checked,
                notify_critical: document.getElementById('notify-critical').checked,
                backup_enabled: document.getElementById('backup-enabled').checked,
                notifications: {
                    email: document.getElementById('notify-email').checked,
                    email_address: document.getElementById('notify-email-address').value,
                    whatsapp: document.getElementById('notify-whatsapp').checked,
                    whatsapp_phone: document.getElementById('notify-whatsapp-phone').value,
                    dashboard: document.getElementById('notify-dashboard').checked,
                    on_complete: document.getElementById('notify-on-complete').checked,
                    on_error: document.getElementById('notify-on-error').checked,
                    on_start: document.getElementById('notify-on-start').checked,
                    weekly_report: document.getElementById('notify-weekly-report').checked
                },
                excluded_products: this.config.excluded_products,
                excluded_categories: this.config.excluded_categories
            };
        },

        async saveConfig() {
            const config = this.gatherConfig();

            try {
                const data = await requestJson('/api/seo-killer/autopilot/config', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(config)
                });

                if (data.success) {
                    SEOKiller.showSuccess('Configurações salvas com sucesso!');
                    this.config = config;
                    this.updateStatus();
                    bootstrap.Modal.getInstance(document.getElementById('autopilotConfigModal')).hide();
                } else {
                    throw new Error(data.error || 'Erro ao salvar configurações');
                }
            } catch (error) {
                SEOKiller.showError(`Erro: ${error.message}`);
            }
        },

        restoreDefaults() {
            const confirmed = confirm('Deseja restaurar as configurações padrão? Suas configurações atuais serão perdidas.');
            if (!confirmed) return;

            // Resetar para valores padrão
            document.getElementById('freq-daily').checked = true;
            document.getElementById('execution-time').value = '02:00';

            ['titles', 'descriptions', 'attributes'].forEach(opt => {
                document.getElementById(`opt-${opt}`).checked = true;
            });
            ['images', 'pricing', 'keywords'].forEach(opt => {
                document.getElementById(`opt-${opt}`).checked = false;
            });

            document.getElementById('max-items-slider').value = 50;
            document.getElementById('priority-score').value = 70;
            document.getElementById('auto-apply').checked = false;
            document.getElementById('notify-critical').checked = true;
            document.getElementById('backup-enabled').checked = true;

            this.config.excluded_products = [];
            this.config.excluded_categories = [];

            this.updateSummary();
            this.renderExcludedProducts();
            this.renderExcludedCategories();

            SEOKiller.showSuccess('Configurações padrão restauradas');
        },

        async testRun() {
            const confirmed = confirm('Executar AutoPilot agora em modo de teste?\n\nO teste irá processar até 10 itens sem aplicar mudanças.');
            if (!confirmed) return;

            try {
                const data = await requestJson('/api/seo-killer/autopilot/run', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        test_mode: true
                    })
                });

                if (data.success) {
                    SEOKiller.showSuccess('Teste iniciado! Você será notificado quando concluir.');
                    bootstrap.Modal.getInstance(document.getElementById('autopilotConfigModal')).hide();
                } else {
                    throw new Error(data.error || 'Erro ao iniciar teste');
                }
            } catch (error) {
                SEOKiller.showError(`Erro: ${error.message}`);
            }
        }
    };

    // Função global para abrir o modal
    window.configureAutoPilot = function() {
        const modal = new bootstrap.Modal(document.getElementById('autopilotConfigModal'));
        modal.show();
        AutoPilotConfig.init();
    };
</script>
