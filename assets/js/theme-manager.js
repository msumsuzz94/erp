/**
 * Theme Manager
 * Handles light/dark mode switching with database persistence
 */

(function () {
    'use strict';

    const ThemeManager = {
        init: function () {
            this.loadTheme();
            this.bindEvents();
        },

        /**
         * Load theme from localStorage or server
         */
        loadTheme: function () {
            // Try to get theme from localStorage first for instant application
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                this.applyTheme(savedTheme);
            }
        },

        /**
         * Apply theme to document
         */
        applyTheme: function (theme) {
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.setAttribute('data-bs-theme', theme);
            localStorage.setItem('theme', theme);
            this.updateToggleIcon(theme);
        },

        /**
         * Toggle between light and dark themes
         */
        toggleTheme: function () {
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            this.applyTheme(newTheme);
            this.saveThemeToServer(newTheme);
        },

        /**
         * Save theme preference to server
         */
        saveThemeToServer: function (theme) {
            fetch(BASE_URL + '/api/users/update-theme.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ theme: theme })
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.error('Failed to save theme preference:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error saving theme:', error);
                });
        },

        /**
         * Update toggle icon based on theme
         */
        updateToggleIcon: function (theme) {
            const toggleBtn = document.getElementById('themeToggle');
            if (toggleBtn) {
                const icon = toggleBtn.querySelector('i');
                if (icon) {
                    if (theme === 'dark') {
                        icon.className = 'fas fa-sun';
                        toggleBtn.title = 'Switch to Light Mode';
                    } else {
                        icon.className = 'fas fa-moon';
                        toggleBtn.title = 'Switch to Dark Mode';
                    }
                }
            }
        },

        /**
         * Bind event listeners
         */
        bindEvents: function () {
            const toggleBtn = document.getElementById('themeToggle');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.toggleTheme();
                });
            }
        }
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ThemeManager.init());
    } else {
        ThemeManager.init();
    }
})();
