/**
 * Guided Tours System using Shepherd.js
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
    if (window.tourManager) {
        console.log('TourManager already initialized');
        return;
    }

    class TourManager {
        constructor() {
            this.tours = {};
            this.completedTours = this.loadCompletedTours();
            this.init();
        }

        init() {
            if (typeof Shepherd === 'undefined') {
                console.warn('Shepherd.js not loaded');
                return;
            }

            this.createDashboardTour();
            this.createSEOTour();
            this.createCatalogTour();

            // Auto-start dashboard tour for new users
            const isNewUser = !localStorage.getItem('onboarding_completed');
            if (isNewUser && window.location.pathname === '/dashboard') {
                setTimeout(() => {
                    if (!this.isTourCompleted('dashboard')) {
                        this.startDashboardTour();
                    }
                }, 1000);
            }
        }

        createDashboardTour() {
            const tour = new Shepherd.Tour({
                useModalOverlay: true,
                defaultStepOptions: {
                    classes: 'shepherd-theme-custom',
                    scrollTo: { behavior: 'smooth', block: 'center' },
                    cancelIcon: {
                        enabled: true
                    }
                }
            });

            tour.addStep({
                id: 'welcome',
                text: '<h4>Bem-vindo ao Dashboard! 🎉</h4><p>Vamos fazer um tour rápido pelas principais funcionalidades.</p>',
                buttons: [
                    {
                        text: 'Pular',
                        action: tour.cancel,
                        classes: 'btn btn-sm btn-secondary'
                    },
                    {
                        text: 'Começar',
                        action: tour.next,
                        classes: 'btn btn-sm btn-primary'
                    }
                ]
            });

            tour.addStep({
                id: 'accounts',
                text: '<h5>Contas Conectadas</h5><p>Aqui você gerencia suas contas do Mercado Livre. Conecte múltiplas contas para gerenciar todos os seus negócios em um só lugar.</p>',
                attachTo: {
                    element: '[data-widget-id="accounts-widget"]',
                    on: 'bottom'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: tour.back,
                        classes: 'btn btn-sm btn-secondary'
                    },
                    {
                        text: 'Próximo',
                        action: tour.next,
                        classes: 'btn btn-sm btn-primary'
                    }
                ]
            });

            tour.addStep({
                id: 'metrics',
                text: '<h5>Métricas Principais</h5><p>Acompanhe suas estatísticas mais importantes: contas ativas, pedidos recentes, receita total e saúde do sistema.</p>',
                attachTo: {
                    element: '[data-widget-id="stats-widget"]',
                    on: 'bottom'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: tour.back,
                        classes: 'btn btn-sm btn-secondary'
                    },
                    {
                        text: 'Próximo',
                        action: tour.next,
                        classes: 'btn btn-sm btn-primary'
                    }
                ]
            });

            tour.addStep({
                id: 'drag-drop',
                text: '<h5>Personalize seu Dashboard</h5><p>Você pode arrastar os widgets para reorganizá-los! Use o ícone <i class="bi bi-grip-vertical"></i> para mover.</p>',
                attachTo: {
                    element: '.widget-drag-handle',
                    on: 'right'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: tour.back,
                        classes: 'btn btn-sm btn-secondary'
                    },
                    {
                        text: 'Próximo',
                        action: tour.next,
                        classes: 'btn btn-sm btn-primary'
                    }
                ]
            });

            tour.addStep({
                id: 'sidebar',
                text: '<h5>Menu de Navegação</h5><p>Use o menu lateral para acessar todas as funcionalidades: SEO, Catálogo, Pedidos, Análises e muito mais!</p>',
                attachTo: {
                    element: '.sidebar',
                    on: 'right'
                },
                buttons: [
                    {
                        text: 'Anterior',
                        action: tour.back,
                        classes: 'btn btn-sm btn-secondary'
                    },
                    {
                        text: 'Concluir',
                        action: () => {
                            this.completeTour('dashboard', tour);
                        },
                        classes: 'btn btn-sm btn-success'
                    }
                ]
            });

            tour.on('cancel', () => {
                this.markTourAsSkipped('dashboard');
            });

            this.tours.dashboard = tour;
        }

        createSEOTour() {
            const tour = new Shepherd.Tour({
                useModalOverlay: true,
                defaultStepOptions: {
                    classes: 'shepherd-theme-custom',
                    scrollTo: { behavior: 'smooth', block: 'center' },
                    cancelIcon: {
                        enabled: true
                    }
                }
            });

            tour.addStep({
                id: 'seo-welcome',
                text: '<h4>Otimização SEO 🚀</h4><p>Aprenda a otimizar seus anúncios para melhorar o posicionamento no Mercado Livre.</p>',
                buttons: [
                    {
                        text: 'Pular',
                        action: tour.cancel,
                        classes: 'btn btn-sm btn-secondary'
                    },
                    {
                        text: 'Começar',
                        action: tour.next,
                        classes: 'btn btn-sm btn-primary'
                    }
                ]
            });

            tour.on('cancel', () => {
                this.markTourAsSkipped('seo');
            });

            tour.on('complete', () => {
                this.completeTour('seo', tour);
            });

            this.tours.seo = tour;
        }

        createCatalogTour() {
            const tour = new Shepherd.Tour({
                useModalOverlay: true,
                defaultStepOptions: {
                    classes: 'shepherd-theme-custom',
                    scrollTo: { behavior: 'smooth', block: 'center' },
                    cancelIcon: {
                        enabled: true
                    }
                }
            });

            tour.addStep({
                id: 'catalog-welcome',
                text: '<h4>Clonagem de Catálogo 📦</h4><p>Descubra como expandir seu catálogo automaticamente com IA.</p>',
                buttons: [
                    {
                        text: 'Pular',
                        action: tour.cancel,
                        classes: 'btn btn-sm btn-secondary'
                    },
                    {
                        text: 'Começar',
                        action: tour.next,
                        classes: 'btn btn-sm btn-primary'
                    }
                ]
            });

            tour.on('cancel', () => {
                this.markTourAsSkipped('catalog');
            });

            tour.on('complete', () => {
                this.completeTour('catalog', tour);
            });

            this.tours.catalog = tour;
        }

        startDashboardTour() {
            if (this.tours.dashboard) {
                this.tours.dashboard.start();
            }
        }

        startSEOTour() {
            if (this.tours.seo) {
                this.tours.seo.start();
            }
        }

        startCatalogTour() {
            if (this.tours.catalog) {
                this.tours.catalog.start();
            }
        }

        completeTour(tourId, tour) {
            this.completedTours.push(tourId);
            localStorage.setItem('completed_tours', JSON.stringify(this.completedTours));

            // Save to server
            this.saveTourCompletionToServer(tourId);

            if (tour) {
                tour.complete();
            }

            if (typeof Toast !== 'undefined') {
                Toast.success('Tour concluído! 🎉');
            }
        }

        markTourAsSkipped(tourId) {
            const skippedTours = JSON.parse(localStorage.getItem('skipped_tours') || '[]');
            if (!skippedTours.includes(tourId)) {
                skippedTours.push(tourId);
                localStorage.setItem('skipped_tours', JSON.stringify(skippedTours));
            }
        }

        isTourCompleted(tourId) {
            return this.completedTours.includes(tourId);
        }

        loadCompletedTours() {
            try {
                return JSON.parse(localStorage.getItem('completed_tours') || '[]');
            } catch {
                return [];
            }
        }

        async saveTourCompletionToServer(tourId) {
            try {
                await requestJson('/api/tours/complete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ tour_id: tourId })
                });
            } catch (error) {
                console.error('Error saving tour completion:', error);
            }
        }

        // Public method to restart all tours
        static resetAllTours() {
            localStorage.removeItem('completed_tours');
            localStorage.removeItem('skipped_tours');
            if (typeof Toast !== 'undefined') {
                Toast.info('Tours reiniciados! Recarregue a página para começar.');
            }
        }
    }

    // Initialize tour manager
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.tourManager = new TourManager();
        });
    } else {
        window.tourManager = new TourManager();
    }

    // Export class for static methods
    window.TourManager = TourManager;
})();
