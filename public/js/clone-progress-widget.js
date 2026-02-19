/**
 * Clone Progress Widget
 * 
 * Componente JavaScript reutilizável para exibir progresso de jobs de clonagem
 * com tracking granular por fase e atualização em tempo real.
 * 
 * @version 1.0.0
 * @author eskill.com.br
 */

class CloneProgressWidget {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            throw new Error(`Container with id "${containerId}" not found`);
        }

        this.options = {
            jobId: options.jobId || null,
            accountId: options.accountId || null,
            autoRefresh: options.autoRefresh !== false, // default true
            refreshInterval: options.refreshInterval || 2000, // 2 seconds
            showPhaseDetails: options.showPhaseDetails !== false, // default true
            showETA: options.showETA !== false, // default true
            compact: options.compact || false,
            onComplete: options.onComplete || null,
            onError: options.onError || null,
            onUpdate: options.onUpdate || null,
            ...options
        };

        this.refreshTimer = null;
        this.lastProgress = null;
        this.init();
    }

    init() {
        this.render();
        if (this.options.autoRefresh && this.options.jobId) {
            this.startAutoRefresh();
        }
    }

    render() {
        if (this.options.compact) {
            this.container.innerHTML = this.renderCompact();
        } else {
            this.container.innerHTML = this.renderFull();
        }
    }

    renderFull() {
        return `
            <div class="clone-progress-widget">
                <div class="progress-header">
                    <div class="progress-title">
                        <span class="job-icon">⚙️</span>
                        <span>Job #<span id="job-id-display">-</span></span>
                    </div>
                    <div class="progress-status">
                        <span class="status-badge" id="status-badge">-</span>
                    </div>
                </div>

                <div class="progress-main">
                    <div class="progress-bar-container">
                        <div class="progress-bar" id="main-progress-bar">
                            <div class="progress-fill" id="main-progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="progress-text" id="main-progress-text">0%</div>
                    </div>

                    <div class="progress-info" id="progress-info">
                        <div class="info-item">
                            <span class="info-label">Items:</span>
                            <span class="info-value" id="items-progress">-</span>
                        </div>
                        ${this.options.showETA ? `
                            <div class="info-item">
                                <span class="info-label">ETA:</span>
                                <span class="info-value" id="eta-display">-</span>
                            </div>
                        ` : ''}
                    </div>
                </div>

                ${this.options.showPhaseDetails ? `
                    <div class="progress-phases" id="progress-phases"></div>
                ` : ''}

                <div class="progress-footer" id="progress-footer"></div>
            </div>
        `;
    }

    renderCompact() {
        return `
            <div class="clone-progress-widget compact">
                <div class="progress-bar-container">
                    <div class="progress-bar" id="main-progress-bar">
                        <div class="progress-fill" id="main-progress-fill" style="width: 0%"></div>
                    </div>
                </div>
                <div class="compact-info">
                    <span class="progress-text" id="main-progress-text">0%</span>
                    <span class="status-badge" id="status-badge">-</span>
                </div>
            </div>
        `;
    }

    async fetchProgress() {
        if (!this.options.jobId) return null;

        try {
            const response = await fetch(
                `/api/catalog/clone/progress/${this.options.jobId}` +
                (this.options.accountId ? `?account_id=${this.options.accountId}` : '')
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Error fetching progress:', error);
            if (this.options.onError) {
                this.options.onError(error);
            }
            return null;
        }
    }

    async update() {
        const progress = await this.fetchProgress();
        if (!progress) return;

        this.lastProgress = progress;
        this.updateDisplay(progress);

        if (this.options.onUpdate) {
            this.options.onUpdate(progress);
        }

        // Check if completed
        if (progress.overall_progress >= 100 || 
            ['completed', 'failed', 'cancelled'].includes(progress.status)) {
            this.stopAutoRefresh();
            if (this.options.onComplete) {
                this.options.onComplete(progress);
            }
        }
    }

    updateDisplay(progress) {
        // Update job ID
        const jobIdEl = document.getElementById('job-id-display');
        if (jobIdEl) {
            jobIdEl.textContent = progress.job_id;
        }

        // Update main progress bar
        const fillEl = document.getElementById('main-progress-fill');
        const textEl = document.getElementById('main-progress-text');
        if (fillEl && textEl) {
            const percentage = Math.round(progress.overall_progress);
            fillEl.style.width = `${percentage}%`;
            textEl.textContent = `${percentage}%`;
        }

        // Update status badge
        const statusEl = document.getElementById('status-badge');
        if (statusEl) {
            statusEl.className = `status-badge ${progress.status}`;
            statusEl.textContent = progress.status;
        }

        // Update items progress
        const itemsEl = document.getElementById('items-progress');
        if (itemsEl && progress.items_completed !== undefined) {
            itemsEl.textContent = `${progress.items_completed}/${progress.items_total}`;
        }

        // Update ETA
        const etaEl = document.getElementById('eta-display');
        if (etaEl && progress.estimated_completion) {
            etaEl.textContent = this.formatETA(progress.estimated_completion);
        }

        // Update phases
        if (this.options.showPhaseDetails && progress.phases) {
            this.updatePhases(progress.phases, progress.current_phase);
        }

        // Update footer with timing info
        if (!this.options.compact) {
            this.updateFooter(progress);
        }
    }

    updatePhases(phases, currentPhase) {
        const phasesContainer = document.getElementById('progress-phases');
        if (!phasesContainer || !phases) return;

        phasesContainer.innerHTML = Object.entries(phases).map(([phaseName, phase]) => {
            const isCurrent = phaseName === currentPhase;
            const isCompleted = phase.status === 'completed';
            const statusIcon = isCompleted ? '✓' : (isCurrent ? '⚙️' : '○');
            const statusClass = isCompleted ? 'completed' : (isCurrent ? 'active' : 'pending');

            return `
                <div class="phase-item ${statusClass}">
                    <div class="phase-header">
                        <span class="phase-icon">${statusIcon}</span>
                        <span class="phase-name">${this.formatPhaseName(phaseName)}</span>
                        <span class="phase-progress">${Math.round(phase.progress)}%</span>
                    </div>
                    ${isCurrent || isCompleted ? `
                        <div class="phase-bar">
                            <div class="phase-fill" style="width: ${phase.progress}%"></div>
                        </div>
                    ` : ''}
                </div>
            `;
        }).join('');
    }

    updateFooter(progress) {
        const footerEl = document.getElementById('progress-footer');
        if (!footerEl) return;

        const startTime = new Date(progress.started_at);
        const elapsed = Math.floor((Date.now() - startTime.getTime()) / 1000);

        footerEl.innerHTML = `
            <div class="footer-info">
                <span>Iniciado: ${startTime.toLocaleTimeString()}</span>
                <span>Tempo decorrido: ${this.formatDuration(elapsed)}</span>
                ${progress.avg_item_processing_time ? `
                    <span>Tempo médio/item: ${progress.avg_item_processing_time.toFixed(1)}s</span>
                ` : ''}
            </div>
        `;
    }

    formatPhaseName(phaseName) {
        const names = {
            validation: 'Validação',
            preparation: 'Preparação',
            publication: 'Publicação',
            post_actions: 'Pós-Ações'
        };
        return names[phaseName] || phaseName;
    }

    formatETA(isoDate) {
        const eta = new Date(isoDate);
        const now = new Date();
        const diff = Math.floor((eta - now) / 1000);

        if (diff <= 0) return 'Concluindo...';
        return this.formatDuration(diff);
    }

    formatDuration(seconds) {
        if (seconds < 60) return `${seconds}s`;
        if (seconds < 3600) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${minutes}m ${secs}s`;
        }
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${hours}h ${minutes}m`;
    }

    startAutoRefresh() {
        this.stopAutoRefresh();
        this.update(); // Immediate update
        this.refreshTimer = setInterval(() => {
            this.update();
        }, this.options.refreshInterval);
    }

    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }

    destroy() {
        this.stopAutoRefresh();
        this.container.innerHTML = '';
    }

    setJobId(jobId) {
        this.options.jobId = jobId;
        if (this.options.autoRefresh) {
            this.startAutoRefresh();
        }
    }

    getLastProgress() {
        return this.lastProgress;
    }
}

// CSS Styles (add to your stylesheet)
const widgetStyles = `
.clone-progress-widget {
    background: white;
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.clone-progress-widget.compact {
    padding: 12px;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.progress-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #2c3e50;
}

.job-icon {
    font-size: 18px;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.status-badge.processing {
    background: #d1ecf1;
    color: #0c5460;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.completed {
    background: #d4edda;
    color: #155724;
}

.status-badge.failed {
    background: #f8d7da;
    color: #721c24;
}

.progress-bar-container {
    position: relative;
    margin-bottom: 12px;
}

.progress-bar {
    width: 100%;
    height: 24px;
    background: #e9ecef;
    border-radius: 12px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #007bff, #0056b3);
    transition: width 0.3s ease;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 12px;
    font-weight: 600;
    color: #333;
    text-shadow: 0 0 2px white;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    font-size: 14px;
}

.info-label {
    color: #6c757d;
}

.info-value {
    font-weight: 600;
    color: #2c3e50;
}

.progress-phases {
    border-top: 1px solid #e9ecef;
    padding-top: 12px;
    margin-bottom: 12px;
}

.phase-item {
    margin-bottom: 8px;
}

.phase-item:last-child {
    margin-bottom: 0;
}

.phase-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
    font-size: 13px;
}

.phase-icon {
    font-size: 14px;
}

.phase-name {
    flex: 1;
    color: #6c757d;
}

.phase-item.active .phase-name {
    color: #007bff;
    font-weight: 600;
}

.phase-item.completed .phase-name {
    color: #28a745;
}

.phase-progress {
    font-weight: 600;
    color: #2c3e50;
    font-size: 12px;
}

.phase-bar {
    height: 4px;
    background: #e9ecef;
    border-radius: 2px;
    overflow: hidden;
}

.phase-fill {
    height: 100%;
    background: #007bff;
    transition: width 0.3s ease;
}

.phase-item.completed .phase-fill {
    background: #28a745;
}

.progress-footer {
    border-top: 1px solid #e9ecef;
    padding-top: 12px;
}

.footer-info {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #6c757d;
}

.compact-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
}
`;

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CloneProgressWidget;
}

// Example Usage:
/*
<!-- HTML -->
<div id="job-progress-1"></div>

<!-- JavaScript -->
<script>
    const widget = new CloneProgressWidget('job-progress-1', {
        jobId: 123,
        accountId: 'ACC123',
        autoRefresh: true,
        refreshInterval: 2000,
        showPhaseDetails: true,
        showETA: true,
        onComplete: (progress) => {
            console.log('Job completed:', progress);
            alert(`Job #${progress.job_id} completed!`);
        },
        onError: (error) => {
            console.error('Widget error:', error);
        },
        onUpdate: (progress) => {
            console.log('Progress update:', progress);
        }
    });

    // Dynamic control
    widget.setJobId(456); // Change job
    widget.startAutoRefresh(); // Resume refresh
    widget.stopAutoRefresh(); // Pause refresh
    widget.destroy(); // Cleanup
</script>
*/
