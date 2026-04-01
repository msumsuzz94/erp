/**
 * Browser Cache Management Script with 5-Second Auto-Clear
 * Implements client-side cache busting and automatic clearing every 5 seconds
 */

(function () {
    'use strict';

    // Cache version - increment this to force clear all cached data
    const CACHE_VERSION = '2.0.0';
    const CACHE_KEY = 'app_cache_version';

    /**
     * Check and update cache version
     * Clears localStorage if version has changed
     */
    function checkCacheVersion() {
        const storedVersion = localStorage.getItem(CACHE_KEY);

        if (storedVersion !== CACHE_VERSION) {
            console.log('Cache version updated, clearing cached data...');
            clearBrowserCache();
            localStorage.setItem(CACHE_KEY, CACHE_VERSION);
        }
    }

    /**
     * Clear browser cache data
     */
    function clearBrowserCache() {
        // Clear localStorage (except theme preference)
        const theme = localStorage.getItem('theme');
        localStorage.clear();
        if (theme) {
            localStorage.setItem('theme', theme);
        }

        // Clear sessionStorage
        sessionStorage.clear();

        console.log('✅ Browser cache cleared successfully');
    }

    /**
     * Disable browser back/forward cache (bfcache)
     */
    function disableBFCache() {
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                console.log('Page loaded from bfcache, reloading...');
                window.location.reload();
            }
        });
    }

    /**
     * Prevent caching of AJAX requests
     */
    function setupAjaxCacheBusting() {
        if (typeof $ !== 'undefined' && $.ajaxSetup) {
            $.ajaxSetup({
                cache: false,
                beforeSend: function (xhr, settings) {
                    // Add timestamp to prevent caching
                    if (settings.url.indexOf('?') === -1) {
                        settings.url += '?_=' + new Date().getTime();
                    } else {
                        settings.url += '&_=' + new Date().getTime();
                    }
                }
            });
        }
    }

    /**
     * Add manual cache clear button (for development)
     */
    function addClearCacheButton() {
        // Only in development mode
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            window.clearAppCache = function () {
                clearBrowserCache();
                alert('Cache cleared! Page will reload.');
                window.location.reload(true);
            };

            console.log('💡 Tip: Run clearAppCache() in console to clear cache manually');
        }
    }

    /**
     * Initialize cache management on page load
     */
    function init() {
        checkCacheVersion();
        disableBFCache();
        setupAjaxCacheBusting();
        addClearCacheButton();

        console.log('%c🚀 Cache Management Initialized', 'background: #4CAF50; color: white; padding: 5px 10px; border-radius: 3px;');
        console.log('Version: ' + CACHE_VERSION);

        // AUTO-CLEAR CACHE BASED ON SETTINGS
        const intervalMinutes = window.CACHE_INTERVAL || 0;

        if (intervalMinutes > 0) {
            const intervalMs = intervalMinutes * 60 * 1000;
            console.log(`%c⚡ AUTO-CLEAR ENABLED: Cache will clear every ${intervalMinutes} minute(s)`, 'background: #FF9800; color: white; padding: 5px 10px; border-radius: 3px;');

            setInterval(function () {
                console.log(`%c🔄 Auto-clearing cache (${intervalMinutes}-minute interval)...`, 'background: #2196F3; color: white; padding: 3px 8px; border-radius: 3px;');
                clearBrowserCache();

                // Force reload CSS by updating version parameter
                const links = document.querySelectorAll('link[rel="stylesheet"]');
                links.forEach(function (link) {
                    const href = link.getAttribute('href');
                    if (href && href.indexOf('?v=') !== -1) {
                        const newHref = href.split('?')[0] + '?v=' + new Date().getTime();
                        link.href = newHref;
                        console.log('↻ CSS refreshed: ' + href.split('/').pop());
                    }
                });
            }, intervalMs);
        } else {
            console.log('%c⚡ AUTO-CLEAR DISABLED', 'background: #757575; color: white; padding: 5px 10px; border-radius: 3px;');
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose cache management to window object
    window.CacheManager = {
        version: CACHE_VERSION,
        clear: clearBrowserCache,
        check: checkCacheVersion
    };

})();
