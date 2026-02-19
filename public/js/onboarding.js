/**
 * Interactive Onboarding System
 */

(function () {
    'use strict';

    async function requestJson(url, options = {}) {
        if (window.ApiClient) return window.ApiClient.request(url, options);
        const resp = await fetch(url, { credentials: 'include', ...options });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        return resp.json();
    }

    // Check if already initialized
    if (window.onboardingManager) {
        console.log('OnboardingManager already initialized');
        return;
    }

    class OnboardingManager {
        constructor() {
            this.steps = [
                {
                    id: 'welcome',
                    title: 'Bem-vindo ao eSkill! 🎉',
                    content: 'Vamos configurar sua conta em poucos passos simples.',
                    icon: 'bi-rocket-takeoff',
                    action: null
                },
                {
                    id: 'connect-account',
                    title: 'Conecte sua Conta Mercado Livre',
                    content: 'Para começar a usar todas as funcionalidades, conecte sua conta do Mercado Livre.',
                    icon: 'bi-link-45deg',
                    action: 'connect'
                },
                {
                    id: 'explore-features',
                    title: 'Conheça as Funcionalidades',
                    content: 'Descubra ferramentas poderosas para otimizar seus anúncios e aumentar suas vendas.',
                    icon: 'bi-stars',
                    action: 'tour'
                },
                {
                    id: 'complete',
                    title: 'Tudo Pronto! ✨',
                    content: 'Você está pronto para começar. Explore o dashboard e descubra tudo que podemos fazer por você!',
                    icon: 'bi-check-circle',
                    action: 'finish'
                }
            ];

            this.currentStep = 0;
            this.container = null;
            this.init();
        }

        init() {
            // Check if onboarding should be shown
            const onboardingComplete = localStorage.getItem('onboarding_completed');
            const onboardingSkipped = localStorage.getItem('onboarding_skipped');

            if (!onboardingComplete && !onboardingSkipped) {
                this.show();
            }
        }

        show() {
            this.createContainer();
            this.render();
        }

        createContainer() {
            // Create modal container
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = 'onboardingModal';
            modal.setAttribute('data-bs-backdrop', 'static');
            modal.setAttribute('data-bs-keyboard', 'false');
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0">
                            <div class="w-100">
                                <div class="progress" style="height: 4px;">
                                    <div class="progress-bar" id="onboarding-progress" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-body text-center py-5" id="onboarding-content">
                            <!-- Content will be injected here -->
                        </div>
                        <div class="modal-footer border-0 justify-content-between">
                            <button type="button" class="btn btn-link text-muted" id="onboarding-skip">Pular</button>
                            <div>
                                <button type="button" class="btn btn-outline-primary me-2" id="onboarding-prev" style="display: none;">
                                    <i class="bi bi-arrow-left"></i> Anterior
                                </button>
                                <button type="button" class="btn btn-primary" id="onboarding-next">
                                    Próximo <i class="bi bi-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            this.container = new bootstrap.Modal(modal);

            // Setup event listeners
            document.getElementById('onboarding-skip').addEventListener('click', () => this.skip());
            document.getElementById('onboarding-prev').addEventListener('click', () => this.prev());
            document.getElementById('onboarding-next').addEventListener('click', () => this.next());

            this.container.show();
        }

        render() {
            const step = this.steps[this.currentStep];
            const content = document.getElementById('onboarding-content');
            const progress = document.getElementById('onboarding-progress');
            const prevBtn = document.getElementById('onboarding-prev');
            const nextBtn = document.getElementById('onboarding-next');

            // Update progress
            const progressPercent = ((this.currentStep + 1) / this.steps.length) * 100;
            progress.style.width = `${progressPercent}%`;

            // Update content
            content.innerHTML = `
                <div class="mb-4">
                    <i class="bi ${step.icon} display-1 text-primary"></i>
                </div>
                <h3 class="mb-3">${step.title}</h3>
                <p class="text-muted mb-4">${step.content}</p>
                ${this.getStepAction(step)}
            `;

            // Update buttons
            prevBtn.style.display = this.currentStep > 0 ? 'inline-block' : 'none';

            if (this.currentStep === this.steps.length - 1) {
                nextBtn.innerHTML = 'Começar <i class="bi bi-check"></i>';
            } else {
                nextBtn.innerHTML = 'Próximo <i class="bi bi-arrow-right"></i>';
            }

            // Save progress
            this.saveProgress();
        }

        getStepAction(step) {
            switch (step.action) {
                case 'connect':
                    return `
                        <a href="/auth/authorize" class="btn btn-lg btn-success">
                            <i class="bi bi-link-45deg"></i> Conectar Conta ML
                        </a>
                    `;
                case 'tour':
                    return `
                        <button class="btn btn-lg btn-info" onclick="onboardingManager.startTour()">
                            <i class="bi bi-map"></i> Iniciar Tour
                        </button>
                    `;
                default:
                    return '';
            }
        }

        next() {
            if (this.currentStep < this.steps.length - 1) {
                this.currentStep++;
                this.render();
            } else {
                this.complete();
            }
        }

        prev() {
            if (this.currentStep > 0) {
                this.currentStep--;
                this.render();
            }
        }

        skip() {
            if (confirm('Tem certeza que deseja pular o tutorial? Você pode acessá-lo novamente pelo menu de ajuda.')) {
                localStorage.setItem('onboarding_skipped', 'true');
                this.close();
            }
        }

        complete() {
            localStorage.setItem('onboarding_completed', 'true');
            localStorage.removeItem('onboarding_step');

            // Save to server
            this.saveCompletionToServer();

            this.close();

            // Show success message
            if (typeof Toast !== 'undefined') {
                Toast.success('Onboarding concluído! Bem-vindo ao eSkill! 🎉');
            }
        }

        close() {
            if (this.container) {
                this.container.hide();
                setTimeout(() => {
                    document.getElementById('onboardingModal')?.remove();
                }, 300);
            }
        }

        saveProgress() {
            localStorage.setItem('onboarding_step', this.currentStep.toString());
        }

        async saveCompletionToServer() {
            try {
                await requestJson('/api/onboarding/complete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
            } catch (error) {
                console.error('Error saving onboarding completion:', error);
            }
        }

        startTour() {
            this.complete();
            // Start guided tour (will be implemented with Shepherd.js)
            if (window.tourManager) {
                window.tourManager.startDashboardTour();
            }
        }

        // Public method to restart onboarding
        static restart() {
            localStorage.removeItem('onboarding_completed');
            localStorage.removeItem('onboarding_skipped');
            localStorage.removeItem('onboarding_step');
            window.location.reload();
        }
    }

    // Initialize onboarding manager
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.onboardingManager = new OnboardingManager();
        });
    } else {
        window.onboardingManager = new OnboardingManager();
    }

    // Export class for static methods
    window.OnboardingManager = OnboardingManager;
})();
