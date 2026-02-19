/**
 * Advanced Theme Switcher with Multiple Presets
 */

async function requestJson(url, options = {}) {
    if (window.ApiClient) return window.ApiClient.request(url, options);
    const resp = await fetch(url, { credentials: 'include', ...options });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    return resp.json();
}

class ThemeManager {
    constructor() {
        this.themes = {
            light: { name: 'Claro', icon: 'bi-sun' },
            dark: { name: 'Escuro', icon: 'bi-moon-stars' },
            blue: { name: 'Azul', icon: 'bi-droplet' },
            purple: { name: 'Roxo', icon: 'bi-heart' },
            green: { name: 'Verde', icon: 'bi-tree' },
            'high-contrast': { name: 'Alto Contraste', icon: 'bi-eye' }
        };

        this.currentTheme = 'light';
        this.init();
    }

    init() {
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || this.detectSystemTheme();
        this.applyTheme(savedTheme, false);

        // Setup event listeners
        this.setupEventListeners();
    }

    detectSystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return 'dark';
        }
        return 'light';
    }

    setupEventListeners() {
        // Simple toggle button (if exists)
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleTheme();
            });
        }

        // Theme selector dropdown (if exists)
        const themeSelector = document.getElementById('theme-selector');
        if (themeSelector) {
            themeSelector.addEventListener('change', (e) => {
                this.applyTheme(e.target.value);
            });
        }

        // Listen for system theme changes
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (!localStorage.getItem('theme')) {
                    this.applyTheme(e.matches ? 'dark' : 'light', false);
                }
            });
        }
    }

    toggleTheme() {
        // Simple toggle between light and dark
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.applyTheme(newTheme);
    }

    async applyTheme(themeName, saveToServer = true) {
        if (!this.themes[themeName]) {
            console.warn(`Theme "${themeName}" not found, using light theme`);
            themeName = 'light';
        }

        this.currentTheme = themeName;
        document.body.setAttribute('data-theme', themeName);
        localStorage.setItem('theme', themeName);

        // Update UI elements
        this.updateThemeIcon();
        this.updateThemeSelector();

        // Save to server
        if (saveToServer) {
            await this.saveThemeToServer(themeName);
        }

        // Dispatch custom event
        window.dispatchEvent(new CustomEvent('themeChanged', {
            detail: { theme: themeName }
        }));
    }

    updateThemeIcon() {
        const themeToggle = document.getElementById('theme-toggle');
        if (!themeToggle) return;

        const icon = themeToggle.querySelector('i');
        if (icon) {
            const themeIcon = this.themes[this.currentTheme]?.icon || 'bi-sun';
            icon.className = `bi ${themeIcon}`;
        }
    }

    updateThemeSelector() {
        const themeSelector = document.getElementById('theme-selector');
        if (themeSelector) {
            themeSelector.value = this.currentTheme;
        }
    }

    async saveThemeToServer(theme) {
        try {
            await requestJson('/api/user/theme', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ theme })
            });
        } catch (error) {
            console.error('Error saving theme:', error);
        }
    }

    getAvailableThemes() {
        return this.themes;
    }

    getCurrentTheme() {
        return this.currentTheme;
    }
}

// Initialize theme manager
document.addEventListener('DOMContentLoaded', () => {
    window.themeManager = new ThemeManager();
});
