/**
 * SEO Killer - Utility Functions
 * Helper functions for notifications, formatting, and common operations
 */

// ============================================
// NOTIFICATION SYSTEM
// ============================================

const Notifications = {
    /**
     * Show success notification
     * @param {string} message - Success message to display
     * @param {number} duration - Duration in milliseconds (default: 3000)
     */
    success(message, duration = 3000) {
        announce(message);
        if (typeof Toastify !== 'undefined') {
            Toastify({
                text: message,
                duration: duration,
                gravity: "top",
                position: "right",
                style: { background: "#28a745" },
                stopOnFocus: true,
                className: "toast-success"
            }).showToast();
        } else {
            this.fallbackNotification(message, 'success');
        }
    },

    /**
     * Show error notification
     * @param {string} message - Error message to display
     * @param {number} duration - Duration in milliseconds (default: 5000)
     */
    error(message, duration = 5000) {
        announce(message);
        if (typeof Toastify !== 'undefined') {
            Toastify({
                text: message,
                duration: duration,
                gravity: "top",
                position: "right",
                style: { background: "#dc3545" },
                stopOnFocus: true,
                className: "toast-error"
            }).showToast();
        } else {
            this.fallbackNotification(message, 'error');
        }
    },

    /**
     * Show info notification
     * @param {string} message - Info message to display
     * @param {number} duration - Duration in milliseconds (default: 3000)
     */
    info(message, duration = 3000) {
        announce(message);
        if (typeof Toastify !== 'undefined') {
            Toastify({
                text: message,
                duration: duration,
                gravity: "top",
                position: "right",
                style: { background: "#17a2b8" },
                stopOnFocus: true,
                className: "toast-info"
            }).showToast();
        } else {
            this.fallbackNotification(message, 'info');
        }
    },

    /**
     * Show warning notification
     * @param {string} message - Warning message to display
     * @param {number} duration - Duration in milliseconds (default: 4000)
     */
    warning(message, duration = 4000) {
        announce(message);
        if (typeof Toastify !== 'undefined') {
            Toastify({
                text: message,
                duration: duration,
                gravity: "top",
                position: "right",
                style: { background: "#ffc107" },
                stopOnFocus: true,
                className: "toast-warning"
            }).showToast();
        } else {
            this.fallbackNotification(message, 'warning');
        }
    },

    /**
     * Fallback notification using Bootstrap alerts
     */
    fallbackNotification(message, type) {
        announce(message);
        const alertClass = `alert-${type === 'error' ? 'danger' : type}`;
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" 
                 role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', alertHtml);

        // Auto-remove after duration
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) alert.remove();
        }, 5000);
    }
};

function announce(message) {
    try {
        const el = document.getElementById('seo-killer-live');
        if (el) el.textContent = String(message ?? '');
    } catch (e) { }
}

// ============================================
// LOADING STATES
// ============================================

const Loading = {
    /**
     * Show loading skeleton
     * @param {string} containerId - ID of container element
     * @param {number} rows - Number of skeleton rows
     */
    showSkeleton(containerId, rows = 3) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const skeletonHtml = Array(rows).fill(0).map(() => `
            <div class="skeleton-item mb-3">
                <div class="skeleton-line skeleton-line-full mb-2"></div>
                <div class="skeleton-line skeleton-line-medium mb-2"></div>
                <div class="skeleton-line skeleton-line-short"></div>
            </div>
        `).join('');

        container.innerHTML = `<div class="skeleton-loader">${skeletonHtml}</div>`;
    },

    /**
     * Show loading spinner
     * @param {string} containerId - ID of container element
     * @param {string} message - Optional loading message
     */
    showSpinner(containerId, message = 'Carregando...') {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted mt-3">${message}</p>
            </div>
        `;
    },

    /**
     * Set button loading state
     * @param {HTMLElement|string} button - Button element or ID
     * @param {boolean} isLoading - Loading state
     * @param {string} originalText - Original button text
     */
    setButtonLoading(button, isLoading, originalText = '') {
        const btn = typeof button === 'string' ? document.getElementById(button) : button;
        if (!btn) return;

        if (isLoading) {
            btn.dataset.originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Processando...
            `;
        } else {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalText || originalText;
        }
    }
};

// ============================================
// FORMATTING UTILITIES
// ============================================

const Format = {
    /**
     * Format number with thousands separator
     * @param {number} num - Number to format
     * @returns {string} Formatted number
     */
    number(num) {
        return new Intl.NumberFormat('pt-BR').format(num);
    },

    /**
     * Format currency in BRL
     * @param {number} value - Value to format
     * @returns {string} Formatted currency
     */
    currency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    },

    /**
     * Format date to Brazilian format
     * @param {string|Date} date - Date to format
     * @returns {string} Formatted date
     */
    date(date) {
        return new Date(date).toLocaleDateString('pt-BR');
    },

    /**
     * Format datetime to Brazilian format
     * @param {string|Date} datetime - Datetime to format
     * @returns {string} Formatted datetime
     */
    datetime(datetime) {
        return new Date(datetime).toLocaleString('pt-BR');
    },

    /**
     * Format time to Brazilian format
     * @param {string|Date} time - Time to format
     * @returns {string} Formatted time
     */
    time(time) {
        return new Date(time).toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    /**
     * Truncate text with ellipsis
     * @param {string} text - Text to truncate
     * @param {number} length - Maximum length
     * @returns {string} Truncated text
     */
    truncate(text, length = 50) {
        if (!text) return '';
        return text.length > length ? text.substring(0, length) + '...' : text;
    },

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// ============================================
// DEBOUNCE & THROTTLE
// ============================================

/**
 * Debounce function calls
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @returns {Function} Debounced function
 */
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function calls
 * @param {Function} func - Function to throttle
 * @param {number} limit - Time limit in milliseconds
 * @returns {Function} Throttled function
 */
function throttle(func, limit = 300) {
    let inThrottle;
    return function executedFunction(...args) {
        if (!inThrottle) {
            func(...args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// ============================================
// GLOBAL REQUEST HELPER
// ============================================

/**
 * Make a JSON API request. Returns { response, data }.
 * Canonical implementation — do NOT redeclare in component files.
 * @param {string} url
 * @param {object} options - Fetch options
 * @returns {Promise<{response: Response, data: any}>}
 */
async function requestJson(url, options = {}) {
    if (window.ApiClient && typeof window.ApiClient.json === 'function') {
        return window.ApiClient.json(url, options);
    }
    const response = await fetch(url, {
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
        ...options
    });
    if (!response.ok) {
        const text = await response.text().catch(() => '');
        throw new Error(text || `Erro HTTP ${response.status}`);
    }
    const data = await response.json();
    return { response, data };
}

// ============================================
// API HELPERS
// ============================================

const API = {
    /**
     * Make API request with error handling
     * @param {string} url - API endpoint
     * @param {object} options - Fetch options
     * @returns {Promise} API response
     */
    async request(url, options = {}) {
        try {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                }
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('API Request failed:', error);
            Notifications.error(`Erro na requisição: ${error.message}`);
            throw error;
        }
    },

    /**
     * GET request
     */
    async get(url) {
        return this.request(url, { method: 'GET' });
    },

    /**
     * POST request
     */
    async post(url, data) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    /**
     * PUT request
     */
    async put(url, data) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    /**
     * DELETE request
     */
    async delete(url) {
        return this.request(url, { method: 'DELETE' });
    }
};

// ============================================
// LOCAL STORAGE CACHE
// ============================================

const Cache = {
    /**
     * Set cache item with expiration
     * @param {string} key - Cache key
     * @param {any} value - Value to cache
     * @param {number} ttl - Time to live in seconds (default: 300 = 5min)
     */
    set(key, value, ttl = 300) {
        const item = {
            value: value,
            expiry: Date.now() + (ttl * 1000)
        };
        localStorage.setItem(`seo_killer_${key}`, JSON.stringify(item));
    },

    /**
     * Get cache item
     * @param {string} key - Cache key
     * @returns {any|null} Cached value or null if expired/not found
     */
    get(key) {
        const itemStr = localStorage.getItem(`seo_killer_${key}`);
        if (!itemStr) return null;

        try {
            const item = JSON.parse(itemStr);
            if (Date.now() > item.expiry) {
                localStorage.removeItem(`seo_killer_${key}`);
                return null;
            }
            return item.value;
        } catch (e) {
            return null;
        }
    },

    /**
     * Remove cache item
     * @param {string} key - Cache key
     */
    remove(key) {
        localStorage.removeItem(`seo_killer_${key}`);
    },

    /**
     * Clear all SEO Killer cache
     */
    clearAll() {
        Object.keys(localStorage)
            .filter(key => key.startsWith('seo_killer_'))
            .forEach(key => localStorage.removeItem(key));
    }
};

// ============================================
// VALIDATION
// ============================================

const Validate = {
    /**
     * Validate required field
     */
    required(value, fieldName) {
        if (!value || value.toString().trim() === '') {
            Notifications.error(`O campo "${fieldName}" é obrigatório`);
            return false;
        }
        return true;
    },

    /**
     * Validate email
     */
    email(value) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!re.test(value)) {
            Notifications.error('Email inválido');
            return false;
        }
        return true;
    },

    /**
     * Validate number range
     */
    range(value, min, max, fieldName) {
        const num = parseFloat(value);
        if (isNaN(num) || num < min || num > max) {
            Notifications.error(`${fieldName} deve estar entre ${min} e ${max}`);
            return false;
        }
        return true;
    },

    /**
     * Validate ML title (max 60 chars)
     */
    mlTitle(value) {
        if (value.length > 60) {
            Notifications.warning('Título do ML deve ter no máximo 60 caracteres');
            return false;
        }
        return true;
    }
};

// ============================================
// TOOLTIPS INITIALIZATION
// ============================================

function initTooltips() {
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(
            document.querySelectorAll('[data-bs-toggle="tooltip"]')
        );
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

// Initialize tooltips on DOM ready
document.addEventListener('DOMContentLoaded', initTooltips);

// Re-initialize tooltips after dynamic content loads
const reinitTooltips = debounce(initTooltips, 500);

// Export for use in other modules
window.SEOKillerUtils = {
    Notifications,
    Loading,
    Format,
    API,
    Cache,
    Validate,
    debounce,
    throttle,
    initTooltips,
    reinitTooltips,
    requestJson
};
