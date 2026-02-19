(function () {
    const safeText = (v) => String(v ?? '');
    const escapeHtml = (str) => safeText(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const apiFetch = async (url, options = {}) => {
        if (window.SEOKiller?.utils?.fetchAPI) {
            return window.SEOKiller.utils.fetchAPI(url, options);
        }
        const res = await fetch(url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                ...(options.headers || {})
            }
        });
        const data = await res.json().catch(() => null);
        if (!res.ok) {
            throw new Error(data?.error || `HTTP ${res.status}`);
        }
        return data;
    };

    const notify = {
        info(msg) { window.SEOKiller?.showInfo ? window.SEOKiller.showInfo(msg) : console.log(msg); },
        success(msg) { window.SEOKiller?.showSuccess ? window.SEOKiller.showSuccess(msg) : console.log(msg); },
        error(msg) { window.SEOKiller?.showError ? window.SEOKiller.showError(msg) : console.error(msg); },
    };

    const AIInsights = {
        state: {
            initialized: false,
            strategic: null,
            trends: null,
            sentiment: null,
            recommendations: [],
            abTests: []
        },

        init() {
            if (this.state.initialized) return;
            this.state.initialized = true;
            this.refreshAll(false).catch(() => { });
        },

        async refreshAll(showToast = true) {
            if (showToast) notify.info('Atualizando insights...');
            await Promise.allSettled([
                this.loadStrategicInsights(),
                this.loadTrendsAnalysis(),
                this.loadMarketSentiment(),
                this.loadPrioritizedRecommendations(),
                this.loadABTestSuggestions(),
            ]);
        },

        async loadStrategicInsights() {
            const loadingEl = document.getElementById('strategicAssessmentLoading');
            const contentEl = document.getElementById('strategicAssessmentContent');
            if (!loadingEl || !contentEl) return;
            loadingEl.style.display = 'block';
            contentEl.style.display = 'none';

            try {
                const result = await apiFetch('/api/ai/insights/strategic', {
                    method: 'POST',
                    body: JSON.stringify({
                        include_opportunities: true,
                        include_risks: true,
                        include_next_steps: true
                    })
                });
                if (!result?.success || !result?.data) {
                    throw new Error(result?.error || 'Formato de resposta inválido');
                }

                const data = result.data;
                const insights = data.insights || data;
                this.state.strategic = data;

                const overallAssessmentEl = document.getElementById('overallAssessment');
                if (overallAssessmentEl) {
                    overallAssessmentEl.textContent = safeText(insights.overall_assessment || data.overall_assessment || 'Análise estratégica carregada com sucesso.');
                }

                const confRaw = Number(data.confidence ?? 0.7);
                const confPct = confRaw <= 1 ? Math.round(confRaw * 100) : Math.round(confRaw);
                const confEl = document.getElementById('assessmentConfidence');
                if (confEl) confEl.textContent = String(confPct);

                const strengthsList = document.getElementById('strengthsList');
                const strengths = Array.isArray(insights.strengths || data.strengths) ? (insights.strengths || data.strengths) : [];
                if (strengthsList) {
                    strengthsList.innerHTML = strengths.length
                        ? strengths.map(s => `<li class="mb-1">${escapeHtml(s)}</li>`).join('')
                        : '<li class="text-muted">Nenhum ponto forte identificado</li>';
                }

                const weaknessesList = document.getElementById('weaknessesList');
                const weaknesses = Array.isArray(insights.weaknesses || data.weaknesses) ? (insights.weaknesses || data.weaknesses) : [];
                if (weaknessesList) {
                    weaknessesList.innerHTML = weaknesses.length
                        ? weaknesses.map(w => `<li class="mb-1">${escapeHtml(w)}</li>`).join('')
                        : '<li class="text-muted">Nenhuma fraqueza identificada</li>';
                }

                const opportunitiesList = document.getElementById('opportunitiesList');
                const opportunities = Array.isArray(insights.opportunities || data.opportunities) ? (insights.opportunities || data.opportunities) : [];
                if (opportunitiesList) {
                    opportunitiesList.innerHTML = opportunities.slice(0, 3).map((opp) => {
                        const impact = safeText(opp.impact || 'medium');
                        return `
                            <div class="opportunity-card border-start border-4 border-${escapeHtml(this.getImpactColor(impact))} ps-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">${escapeHtml(opp.title || '')}</h6>
                                        <p class="text-muted mb-1">${escapeHtml(opp.description || '')}</p>
                                    </div>
                                    <span class="badge bg-${escapeHtml(this.getImpactColor(impact))}">${escapeHtml(impact)}</span>
                                </div>
                            </div>
                        `;
                    }).join('');
                }

                const risks = Array.isArray(insights.risks || data.risks) ? (insights.risks || data.risks) : [];
                const risksSection = document.getElementById('risksSection');
                const risksList = document.getElementById('risksList');
                if (risksSection && risksList) {
                    if (risks.length > 0) {
                        risksList.innerHTML = risks.map(r => `<li>${escapeHtml(r)}</li>`).join('');
                        risksSection.style.display = 'block';
                    } else {
                        risksSection.style.display = 'none';
                    }
                }

                const nextStepsList = document.getElementById('nextStepsList');
                const nextSteps = Array.isArray(insights.next_steps || data.next_steps || data.actionable_items)
                    ? (insights.next_steps || data.next_steps || data.actionable_items)
                    : [];
                if (nextStepsList) {
                    nextStepsList.innerHTML = nextSteps.map((step) => {
                        const priority = safeText(step.priority || 'medium');
                        const effort = safeText(step.effort || 'medium');
                        const action = safeText(step.action || 'Ação');
                        const expected = safeText(step.expected_impact || '');
                        const isHigh = priority === 'high';
                        return `
                            <div class="next-step-item d-flex align-items-start mb-3 p-3 border rounded ${isHigh ? 'border-danger bg-danger bg-opacity-10' : ''}">
                                <div class="flex-shrink-0 me-3">
                                    <span class="badge bg-${escapeHtml(this.getPriorityColor(priority))} badge-lg">${escapeHtml(priority)}</span>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">${escapeHtml(action)}</h6>
                                    <p class="text-muted mb-1">${escapeHtml(expected)}</p>
                                    <small class="text-muted">Esforço: ${escapeHtml(effort)}</small>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" onclick="executeAction('${escapeHtml(action)}')">Executar</button>
                            </div>
                        `;
                    }).join('');
                }

                loadingEl.style.display = 'none';
                contentEl.style.display = 'block';
            } catch (error) {
                loadingEl.innerHTML = `<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Não foi possível carregar os insights estratégicos.<br><small class="text-muted">${escapeHtml(error.message)}</small></div>`;
            }
        },

        async loadTrendsAnalysis() {
            const daysEl = document.getElementById('trendsTimeRange');
            const loadingEl = document.getElementById('trendsLoading');
            const contentEl = document.getElementById('trendsContent');
            if (!daysEl || !loadingEl || !contentEl) return;

            const days = Number(daysEl.value || 30);
            loadingEl.style.display = 'block';
            contentEl.style.display = 'none';

            try {
                const result = await apiFetch(`/api/ai/insights/trends?days=${encodeURIComponent(String(days))}`);
                if (!result?.success || !result?.data) {
                    throw new Error(result?.error || 'Formato de resposta inválido');
                }

                const data = result.data;
                const trends = data.trends || data;
                this.state.trends = data;

                const renderTrendList = (elId, arr, variant) => {
                    const el = document.getElementById(elId);
                    if (!el) return;
                    if (!Array.isArray(arr) || !arr.length) {
                        el.innerHTML = '<p class="text-muted">Nenhum dado disponível.</p>';
                        return;
                    }
                    el.innerHTML = arr.map((t) => {
                        const text = typeof t === 'string' ? t : (t.metric || t.pattern || '');
                        const change = (typeof t === 'object' && t && t.change_percentage !== undefined)
                            ? `${t.change_percentage > 0 ? '+' : ''}${escapeHtml(String(t.change_percentage))}%`
                            : '';
                        const badge = change ? `<span class="badge bg-${variant}">${change}</span>` : '';
                        return `<div class="trend-item d-flex justify-content-between align-items-center mb-2 p-2 bg-${variant} bg-opacity-10 rounded"><span>${escapeHtml(text)}</span>${badge}</div>`;
                    }).join('');
                };

                renderTrendList('risingTrendsList', trends.rising || data.rising_trends || [], 'success');
                renderTrendList('decliningTrendsList', trends.declining || data.declining_trends || [], 'danger');

                const seasonalList = document.getElementById('seasonalPatternsList');
                const seasonalPatterns = trends.seasonal || data.seasonal_patterns || [];
                if (seasonalList) {
                    seasonalList.innerHTML = Array.isArray(seasonalPatterns) && seasonalPatterns.length
                        ? seasonalPatterns.map((p) => {
                            const text = typeof p === 'string' ? p : (p.pattern || '');
                            return `<div class="pattern-item mb-2 p-2 bg-light rounded"><small><i class="bi bi-calendar-check"></i> ${escapeHtml(text)}</small></div>`;
                        }).join('')
                        : '<p class="text-muted">Nenhum padrão sazonal identificado.</p>';
                }

                const forecastEl = document.getElementById('forecastText');
                const forecast = trends.forecast || data.forecast_30_days || data.forecast;
                if (forecastEl) {
                    if (forecast && typeof forecast === 'object') {
                        const next = safeText(forecast.next_30_days || 'N/A');
                        const confRaw = Number(forecast.confidence ?? 0.7);
                        const confPct = confRaw <= 1 ? Math.round(confRaw * 100) : Math.round(confRaw);
                        forecastEl.innerHTML = `Projeção próximos 30 dias: <strong>${escapeHtml(next)}</strong> otimizações<br><small class="text-muted">Confiança: ${confPct}%</small>`;
                    } else {
                        forecastEl.textContent = '';
                    }
                }

                loadingEl.style.display = 'none';
                contentEl.style.display = 'block';
            } catch (error) {
                loadingEl.innerHTML = `<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Não foi possível carregar as tendências.<br><small class="text-muted">${escapeHtml(error.message)}</small></div>`;
            }
        },

        async loadMarketSentiment() {
            const loadingEl = document.getElementById('sentimentLoading');
            const contentEl = document.getElementById('sentimentContent');
            if (!loadingEl || !contentEl) return;
            loadingEl.style.display = 'block';
            contentEl.style.display = 'none';

            try {
                const result = await apiFetch('/api/ai/insights/sentiment');
                if (!result?.success) {
                    throw new Error(result?.error || 'Erro ao carregar sentimento');
                }
                const data = result.data || {};
                this.state.sentiment = data;

                const sentiment = safeText(data.sentiment || 'neutral').toLowerCase();
                const confRaw = Number(data.confidence ?? 0.7);
                const confPct = confRaw <= 1 ? Math.round(confRaw * 100) : Math.round(confRaw);
                this.drawSentimentGauge(sentiment, confPct);

                const sentimentColors = { bullish: 'success', neutral: 'warning', bearish: 'danger' };
                const labelEl = document.getElementById('sentimentLabel');
                if (labelEl) {
                    labelEl.innerHTML = `<span class="text-${sentimentColors[sentiment] || 'secondary'}">${escapeHtml(sentiment.toUpperCase())}</span>`;
                }

                const descEl = document.getElementById('sentimentDescription');
                if (descEl) {
                    descEl.textContent = safeText(data.market_conditions || 'Mercado estável');
                }

                const factorsList = document.getElementById('sentimentFactorsList');
                const factors = Array.isArray(data.factors || data.key_factors) ? (data.factors || data.key_factors) : [];
                if (factorsList) {
                    factorsList.innerHTML = factors.length
                        ? factors.map(f => `<li class="mb-2"><i class="bi bi-chevron-right text-primary"></i> ${escapeHtml(f)}</li>`).join('')
                        : '<li class="text-muted">Nenhum fator identificado</li>';
                }

                const recEl = document.getElementById('sentimentRecommendation');
                if (recEl) recEl.textContent = safeText(data.recommendation || 'Monitorar condições do mercado');

                loadingEl.style.display = 'none';
                contentEl.style.display = 'block';
            } catch (error) {
                loadingEl.innerHTML = `<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Não foi possível carregar o sentimento do mercado.<br><small class="text-muted">${escapeHtml(error.message)}</small></div>`;
            }
        },

        async loadPrioritizedRecommendations() {
            const loadingEl = document.getElementById('recommendationsLoading');
            const contentEl = document.getElementById('recommendationsContent');
            if (!loadingEl || !contentEl) return;
            loadingEl.style.display = 'block';
            contentEl.style.display = 'none';

            try {
                const result = await apiFetch('/api/ai/insights/recommendations?limit=10');
                if (!result?.success) throw new Error(result?.error || 'Erro ao carregar recomendações');
                const data = result.data || {};
                const recommendations = Array.isArray(data.recommendations)
                    ? data.recommendations
                    : Array.isArray(data)
                        ? data
                        : [];
                this.state.recommendations = recommendations;
                this.renderRecommendations(recommendations);
                loadingEl.style.display = 'none';
                contentEl.style.display = 'block';
            } catch (error) {
                loadingEl.innerHTML = `<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Não foi possível carregar as recomendações.<br><small class="text-muted">${escapeHtml(error.message)}</small></div>`;
            }
        },

        renderRecommendations(recommendations) {
            const listEl = document.getElementById('recommendationsList');
            if (!listEl) return;
            const recs = Array.isArray(recommendations) ? recommendations : [];
            listEl.innerHTML = recs.map((rec, index) => {
                const impact = safeText(rec.impact || 'medium').toLowerCase();
                const effort = safeText(rec.effort || 'medium').toLowerCase();
                const category = safeText(rec.category || 'all').toLowerCase();
                const action = safeText(rec.action || 'Recomendação');
                const description = safeText(rec.description || rec.explanation || rec.details || '');
                const isQuickWin = impact === 'high' && effort === 'low';
                return `
                    <div class="recommendation-card mb-3 p-3 border rounded ${isQuickWin ? 'border-success border-2' : ''}"
                        data-category="${escapeHtml(category)}"
                        data-quick-win="${isQuickWin ? 'true' : 'false'}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-secondary me-2">#${index + 1}</span>
                                    <h6 class="mb-0">${escapeHtml(action)}</h6>
                                    ${isQuickWin ? '<span class="badge bg-success ms-2"><i class="bi bi-lightning"></i> Quick Win</span>' : ''}
                                </div>
                                <p class="text-muted mb-2">${escapeHtml(description)}</p>
                                <div class="d-flex gap-2 flex-wrap">
                                    <span class="badge bg-${escapeHtml(this.getImpactColor(impact))}">Impacto: ${escapeHtml(impact)}</span>
                                    <span class="badge bg-secondary">Esforço: ${escapeHtml(effort)}</span>
                                    <span class="badge bg-info">${escapeHtml(category)}</span>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-primary ms-3" onclick="applyRecommendation(${index})">
                                <i class="bi bi-play-fill"></i> Aplicar
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        },

        filterRecommendations(filter, el) {
            const f = safeText(filter || 'all');
            const cards = document.querySelectorAll('.recommendation-card');
            const tabs = document.querySelectorAll('#recommendationTabs .nav-link');

            tabs.forEach(tab => tab.classList.remove('active'));
            if (el && el.classList) {
                el.classList.add('active');
            }

            cards.forEach(card => {
                if (f === 'all') {
                    card.style.display = 'block';
                } else if (f === 'quick-wins') {
                    card.style.display = card.dataset.quickWin === 'true' ? 'block' : 'none';
                } else {
                    card.style.display = card.dataset.category === f ? 'block' : 'none';
                }
            });
        },

        async loadABTestSuggestions() {
            const loadingEl = document.getElementById('abTestsLoading');
            const contentEl = document.getElementById('abTestsContent');
            const listEl = document.getElementById('abTestsList');
            if (!loadingEl || !contentEl || !listEl) return;
            loadingEl.style.display = 'block';
            contentEl.style.display = 'none';

            try {
                const result = await apiFetch('/api/ai/insights/ab-tests', {
                    method: 'POST',
                    body: JSON.stringify({ focus_area: 'all' })
                });
                if (!result?.success) {
                    throw new Error(result?.error || 'Erro ao carregar testes A/B');
                }

                const data = result.data || {};
                const suggestedTests = Array.isArray(data.suggested_tests) ? data.suggested_tests : Array.isArray(data) ? data : [];
                this.state.abTests = suggestedTests;

                if (!suggestedTests.length) {
                    listEl.innerHTML = `<div class="col-12"><div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Nenhuma sugestão de teste A/B disponível no momento.</div></div>`;
                    loadingEl.style.display = 'none';
                    contentEl.style.display = 'block';
                    return;
                }

                listEl.innerHTML = suggestedTests.map((test, idx) => {
                    const name = safeText(test.name || test.test_name || 'Teste A/B');
                    const description = safeText(test.description || test.what_to_test || '');
                    const variantA = safeText(test.variant_a?.example || test.variant_a || 'Versão atual');
                    const variantB = safeText(test.variant_b?.example || test.variant_b || 'Versão otimizada');
                    const impact = safeText(test.expected_impact || test.impact || 'medium').toLowerCase();
                    const duration = Number(test.recommended_duration || test.duration || 14);

                    return `
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h6 class="card-title">${escapeHtml(name)}</h6>
                                    <p class="card-text text-muted">${escapeHtml(description)}</p>

                                    <div class="ab-variants mb-3">
                                        <div class="variant mb-2 p-2 bg-light rounded">
                                            <small class="text-muted d-block">Versão A (Atual):</small>
                                            <strong>${escapeHtml(variantA)}</strong>
                                        </div>
                                        <div class="variant p-2 bg-primary bg-opacity-10 rounded">
                                            <small class="text-muted d-block">Versão B (Otimizada):</small>
                                            <strong>${escapeHtml(variantB)}</strong>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-${escapeHtml(this.getImpactColor(impact))}">${escapeHtml(impact)} impact</span>
                                        <small class="text-muted">${Number.isFinite(duration) ? duration : 14} dias</small>
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-top">
                                    <button class="btn btn-sm btn-primary w-100" onclick="createABTest(${idx})">
                                        <i class="bi bi-plus-circle"></i> Criar Teste
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');

                loadingEl.style.display = 'none';
                contentEl.style.display = 'block';
            } catch (error) {
                loadingEl.innerHTML = `<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Não foi possível carregar as sugestões de testes A/B.<br><small class="text-muted">${escapeHtml(error.message)}</small></div>`;
            }
        },

        drawSentimentGauge(sentiment, confidencePct) {
            const canvas = document.getElementById('sentimentGaugeCanvas');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;
            const radius = 80;

            ctx.clearRect(0, 0, canvas.width, canvas.height);

            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, 0.75 * Math.PI, 0.25 * Math.PI);
            ctx.lineWidth = 20;
            ctx.strokeStyle = '#e0e0e0';
            ctx.stroke();

            const sentimentAngles = {
                bearish: 0.75 * Math.PI,
                neutral: Math.PI,
                bullish: 0.25 * Math.PI
            };
            const endAngle = sentimentAngles[sentiment] || Math.PI;

            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, 0.75 * Math.PI, endAngle);
            ctx.lineWidth = 20;
            ctx.strokeStyle = sentiment === 'bullish' ? '#28a745' : sentiment === 'bearish' ? '#dc3545' : '#ffc107';
            ctx.stroke();

            const pct = Math.max(0, Math.min(100, Number(confidencePct || 0)));
            ctx.font = 'bold 24px Arial';
            ctx.fillStyle = '#333';
            ctx.textAlign = 'center';
            ctx.fillText(`${Math.round(pct)}%`, centerX, centerY + 10);
        },

        applyRecommendation(index) {
            const i = Number(index);
            const rec = this.state.recommendations[i];
            if (!rec) return;
            const action = safeText(rec.action || rec.description || '');
            if (action) {
                notify.success('Aplicando recomendação...');
                this.executeAction(action);
            }
        },

        executeAction(action) {
            const a = safeText(action).toLowerCase();
            if (a.includes('diagnóstico') || a.includes('diagnostico')) {
                window.SEOKiller?.runDiagnosis?.();
                return;
            }
            if (a.includes('lote') || a.includes('bulk')) {
                window.SEOKiller?.showBulkOptimizer?.();
                return;
            }
            if (a.includes('título') || a.includes('titulo')) {
                window.SEOKiller?.openTitleGenerator?.();
                return;
            }
            if (a.includes('descrição') || a.includes('descricao')) {
                window.SEOKiller?.openDescriptionGenerator?.();
                return;
            }
            if (a.includes('atributo')) {
                window.SEOKiller?.openAttributeFiller?.();
                return;
            }
            if (a.includes('imagem')) {
                window.SEOKiller?.openImageAnalyzer?.();
                return;
            }
            notify.info('Ação registrada.');
        },

        async createABTest(index) {
            const i = Number(index);
            const test = this.state.abTests[i];
            if (!test) return;
            const name = safeText(test.name || test.test_name || 'Teste A/B');
            const variantB = safeText(test.variant_b?.example || test.variant_b || '').trim();
            if (!variantB) {
                notify.error('Sugestão inválida: variante B ausente.');
                return;
            }

            const itemId = prompt(`Informe o ID do anúncio (ex: MLB123...) para iniciar: ${name}`);
            if (!itemId) return;
            const type = safeText(test.type || test.focus_area || 'title').toLowerCase();
            const duration = Number(test.recommended_duration || test.duration || 14);

            try {
                notify.info('Criando teste A/B...');
                const result = await apiFetch('/api/seo-killer/ab-test', {
                    method: 'POST',
                    body: JSON.stringify({
                        item_id: itemId.trim(),
                        type,
                        variant_b: variantB,
                        duration: Number.isFinite(duration) ? duration : 14,
                    })
                });
                if (result?.error) {
                    throw new Error(result.error);
                }
                notify.success('Teste A/B criado com sucesso!');
            } catch (e) {
                notify.error('Erro ao criar teste A/B: ' + safeText(e.message));
            }
        },

        getImpactColor(impact) {
            const colors = { high: 'danger', medium: 'warning', low: 'info' };
            return colors[String(impact || '').toLowerCase()] || 'secondary';
        },

        getPriorityColor(priority) {
            const colors = { high: 'danger', medium: 'warning', low: 'info' };
            return colors[String(priority || '').toLowerCase()] || 'secondary';
        }
    };

    window.SEOAIInsights = AIInsights;
    window.initAIInsightsDashboard = () => AIInsights.init();
    window.refreshAllInsights = () => AIInsights.refreshAll(true);
    window.loadStrategicInsights = () => AIInsights.loadStrategicInsights();
    window.loadTrendsAnalysis = () => AIInsights.loadTrendsAnalysis();
    window.loadMarketSentiment = () => AIInsights.loadMarketSentiment();
    window.loadPrioritizedRecommendations = () => AIInsights.loadPrioritizedRecommendations();
    window.loadABTestSuggestions = () => AIInsights.loadABTestSuggestions();
    window.filterRecommendations = (filter, el) => AIInsights.filterRecommendations(filter, el);
    window.applyRecommendation = (index) => AIInsights.applyRecommendation(index);
    window.executeAction = (action) => AIInsights.executeAction(action);
    window.createABTest = (index) => AIInsights.createABTest(index);
})();
