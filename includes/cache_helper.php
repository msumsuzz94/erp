<?php
/**
 * Cache Helper Functions
 * Provides automatic cache busting for CSS/JS files and cache control
 */

// Prevent direct access
if (!defined('BASE_URL')) {
    // If BASE_URL not defined, we can still use it for some functions
    // This allows the helper to be flexible
}

/**
 * Generate asset URL with auto version based on file modification time
 * This ensures browsers reload assets when they change
 * 
 * @param string $asset_path Relative path to asset from BASE_URL
 * @return string Full URL with version parameter
 */
function asset_url($asset_path) {
    // Remove leading slash if present
    $asset_path = ltrim($asset_path, '/');
    
    // Construct full file path
    $full_path = $_SERVER['DOCUMENT_ROOT'] . '/business-management-system/' . $asset_path;
    
    // Get file modification time as version
    if (file_exists($full_path)) {
        $version = filemtime($full_path);
    } else {
        // Fallback to current timestamp if file doesn't exist
        $version = time();
    }
    
    // Return URL with version parameter
    $base = defined('BASE_URL') ? BASE_URL : '/business-management-system';
    return $base . '/' . $asset_path . '?v=' . $version;
}

/**
 * Set cache control headers for PHP pages
 * Call this at the beginning of dynamic pages
 * 
 * @param string $type Type of cache control (none, short, medium, long)
 */
function set_cache_headers($type = 'none') {
    // Don't send headers if already sent
    if (headers_sent()) {
        return;
    }
    
    switch ($type) {
        case 'none':
            // No caching - for dynamic pages
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
            header("Expires: 0");
            break;
            
        case 'short':
            // 5 minutes - for semi-dynamic content
            header("Cache-Control: public, max-age=300");
            header("Expires: " . gmdate("D, d M Y H:i:s", time() + 300) . " GMT");
            break;
            
        case 'medium':
            // 1 hour - for less frequently changing content
            header("Cache-Control: public, max-age=3600");
            header("Expires: " . gmdate("D, d M Y H:i:s", time() + 3600) . " GMT");
            break;
            
        case 'long':
            // 1 day - for static content
            header("Cache-Control: public, max-age=86400");
            header("Expires: " . gmdate("D, d M Y H:i:s", time() + 86400) . " GMT");
            break;
    }
    
    // Always add these security headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
}

/**
 * Clear browser cache meta tags
 * Returns HTML meta tags to prevent browser caching
 * 
 * @return string HTML meta tags
 */
function get_no_cache_meta_tags() {
    return '
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    ';
}

/**
 * Generate ETag for content
 * Used for conditional requests
 * 
 * @param mixed $content Content to generate ETag for
 * @return string ETag value
 */
function generate_etag($content) {
    return md5($content);
}

/**
 * Check if browser cache is valid using ETag
 * 
 * @param string $etag ETag to compare
 * @return bool True if cache is valid
 */
function is_cache_valid($etag) {
    return isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag;
}

/**
 * Clear PHP opcode cache (if available)
 * Useful after file updates
 */
function clear_opcode_cache() {
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    if (function_exists('apcu_clear_cache')) {
        @call_user_func('apcu_clear_cache');
    }
}

/**
 * Generate a cache key for storing data
 * 
 * @param string $identifier Unique identifier
 * @param array $params Additional parameters
 * @return string Cache key
 */
function generate_cache_key($identifier, $params = []) {
    $key_parts = array_merge([$identifier], $params);
    return md5(serialize($key_parts));
}

/**
 * Auto-version number for manual cache busting
 * Increment this when you want to force refresh all assets
 */
if (!defined('ASSETS_VERSION')) {
    define('ASSETS_VERSION', '2.0.0');
}

/**
 * Get full asset version string
 * Combines manual version with file modification time
 * 
 * @param string $asset_path Asset path
 * @return string Version string
 */
function get_asset_version($asset_path) {
    $full_path = $_SERVER['DOCUMENT_ROOT'] . '/business-management-system/' . ltrim($asset_path, '/');
    
    if (file_exists($full_path)) {
        return ASSETS_VERSION . '.' . filemtime($full_path);
    }
    
    return ASSETS_VERSION . '.' . time();
}
