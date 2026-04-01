# Automatic Browser Cache Management System

## Overview

A comprehensive cache management system has been implemented to ensure your browser always loads the latest version of files and prevents caching issues. Everything works automatically without any manual intervention required.

## Components

### 1. Server-Side Cache Control (.htaccess)

**Location:** `/.htaccess`

**What it does:**
- **PHP Files (Dynamic Content):** NO CACHING - Every PHP page is loaded fresh every time
- **CSS &amp; JavaScript:** Medium cache (1 month) with version-based cache busting
- **Images:** Long cache (1 year) - safe to cache as they don't change often
- **Fonts:** Long cache (1 year)

**Headers Applied:**
```
PHP Files:
- Cache-Control: no-store, no-cache, must-revalidate
- Pragma: no-cache
- Expires: 0

Static Assets:
- Cache-Control: public, max-age=2592000, must-revalidate (CSS/JS)
- Cache-Control: public, max-age=31536000, immutable (Images/Fonts)
```

### 2. PHP Cache Helper Functions

**Location:** `/includes/cache_helper.php`

**Key Functions:**

#### `asset_url($asset_path)`
Automatically adds version numbers to CSS/JS files based on their modification time.

**Usage:**
```php
&lt;link href="&lt;?= asset_url('assets/css/custom.css') ?&gt;" rel="stylesheet"&gt;
```

**Output:**
```html
&lt;link href="/business-management-system/assets/css/custom.css?v=1738245890" rel="stylesheet"&gt;
```

When you modify the CSS file, the version number changes automatically, forcing browsers to reload it.

#### `set_cache_headers($type)`
Sets appropriate cache control headers for PHP pages.

**Types:**
- `'none'` - No caching (default for all dynamic pages)
- `'short'` - 5 minutes cache
- `'medium'` - 1 hour cache
- `'long'` - 1 day cache

**Auto-applied:** This is automatically called in `header.php` for all pages.

#### Other Helper Functions:
- `get_no_cache_meta_tags()` - Returns HTML meta tags for cache prevention
- `clear_opcode_cache()` - Clears PHP opcode cache
- `generate_cache_key()` - Generates unique cache keys
- `get_asset_version()` - Gets combined version string

### 3. HTML Meta Tags

**Location:** `/templates/header.php`

**Meta Tags Added:**
```html
&lt;meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"&gt;
&lt;meta http-equiv="Pragma" content="no-cache"&gt;
&lt;meta http-equiv="Expires" content="0"&gt;
```

These tell browsers explicitly NOT to cache the HTML pages.

### 4. Client-Side Cache Manager

**Location:** `/assets/js/cache-manager.js`

**Features:**

#### Automatic Cache Version Checking
- Monitors a version number (`CACHE_VERSION`)
- When version changes, automatically clears cached data
- Preserves theme preference while clearing

#### AJAX Cache Busting
- Automatically adds timestamps to all AJAX requests
- Prevents browsers from caching API responses
- Works with jQuery automatically

#### Back/Forward Cache (BFCache) Prevention
- Detects when page is loaded from browser's back/forward cache
- Automatically reloads the page if loaded from bfcache
- Ensures users always see fresh data

#### Development Helper
- Run `clearAppCache()` in browser console to manually clear cache
- Only available on localhost for security

**Global Object:**
```javascript
window.CacheManager = {
    version: '2.0.0',
    clear: clearBrowserCache,    // Manually clear cache
    check: checkCacheVersion      // Check cache version
};
```

## How It All Works Together

### When a user visits a page:

1. **Apache (.htaccess)** checks the file type:
   - PHP file → Send no-cache headers
   - CSS/JS/Image → Send appropriate cache headers

2. **PHP (header.php)** executes:
   - Loads `cache_helper.php`
   - Calls `set_cache_headers('none')` automatically
   - Adds no-cache meta tags to HTML
   - Loads CSS/JS with version parameters

3. **Browser receives:**
   ```html
   &lt;!-- No-cache meta tags --&gt;
   &lt;meta http-equiv="Cache-Control" content="no-cache..."&gt;
   
   &lt;!-- Versioned assets --&gt;
   &lt;link href=".../custom.css?v=1738245890"&gt;
   &lt;script src=".../cache-manager.js?v=1738245891"&gt;&lt;/script&gt;
   ```

4. **JavaScript (cache-manager.js)** runs:
   - Checks cache version
   - Clears old cached data if version changed
   - Sets up AJAX cache busting
   - Prevents bfcache issues

### When you modify a CSS/JS file:

1. File modification time changes
2. `asset_url()` generates new version number
3. Browser sees different URL
4. Browser downloads the new file
5. Old cached version is ignored

### When you want to force clear all cache:

**Option 1: Increment Version**
Edit `/assets/js/cache-manager.js`:
```javascript
const CACHE_VERSION = '2.0.1';  // Increment this
```

**Option 2: Manual PHP Version**
Edit `/includes/cache_helper.php`:
```php
define('ASSETS_VERSION', '2.0.1');  // Increment this
```

**Option 3: Browser Console (Development)**
```javascript
clearAppCache();  // In browser console on localhost
```

## Testing Cache Busting

### Test 1: CSS Changes
1. Edit `/assets/css/custom.css`
2. Add a simple change (e.g., change a color)
3. Save the file
4. Refresh the browser
5. ✅ Changes should appear immediately

### Test 2: JavaScript Changes
1. Edit `/assets/js/theme-manager.js`
2. Add a console.log() statement
3. Save the file
4. Refresh the browser
5. Open browser console
6. ✅ Your log statement should appear

### Test 3: AJAX Requests
1. Open browser Developer Tools → Network tab
2. Perform an action that makes an AJAX call
3. Look at the request URL
4. ✅ You should see `?_=1738245890` appended to the URL

### Test 4: Version Check
1. Open browser console
2. Type: `CacheManager.version`
3. ✅ Should show: "2.0.0"

## Troubleshooting

### Problem: Changes still not showing

**Solution 1: Hard Refresh**
- Windows/Linux: `Ctrl + F5` or `Ctrl + Shift + R`
- Mac: `Cmd + Shift + R`

**Solution 2: Clear Browser Cache Manually**
- Chrome: `Ctrl + Shift + Delete`
- Firefox: `Ctrl + Shift + Delete`
- Edge: `Ctrl + Shift + Delete`

**Solution 3: Check .htaccess is working**
1. Visit any PHP page
2. Open Developer Tools → Network tab
3. Reload page
4. Click on the main HTML request
5. Check Response Headers
6. ✅ Should see: `Cache-Control: no-store, no-cache...`

**Solution 4: Verify cache_helper.php is loaded**
1. Add this line to any page: `&lt;?php var_dump(function_exists('asset_url')); ?&gt;`
2. ✅ Should output: `bool(true)`

### Problem: Apache not applying .htaccess rules

**Check:** Is `mod_headers` and `mod_expires` enabled?

**Fix (XAMPP):**
1. Open `xampp/apache/conf/httpd.conf`
2. Find and uncomment these lines:
   ```
   LoadModule headers_module modules/mod_headers.so
   LoadModule expires_module modules/mod_expires.so
   ```
3. Restart Apache

### Problem: CSS/JS version not updating

**Possible Causes:**
1. File modification time not changing
2. `filemtime()` function not working
3. File permissions issue

**Fix:**
1. Check file was actually saved
2. Try "touching" the file: `touch /path/to/file.css`
3. Check file permissions (should be readable)

## Best Practices

### For Development
1. Keep version numbers at default
2. Use hard refresh (`Ctrl + F5`) when needed
3. Use browser DevTools with "Disable cache" enabled
4. Use `clearAppCache()` in console for quick testing

### For Production
1. Increment `CACHE_VERSION` before major deployments
2. Increment `ASSETS_VERSION` when many files change
3. Monitor cache headers in production
4. Test with incognito/private browsing mode

### For Custom Pages
If you create a custom page that should allow caching:

```php
&lt;?php
require_once 'includes/cache_helper.php';

// Allow medium caching (1 hour) instead of default
set_cache_headers('medium');

include 'templates/header.php';
?&gt;
```

### For AJAX Responses
AJAX responses are automatically cache-busted, but you can add custom headers:

```php
&lt;?php
// In your AJAX endpoint
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

echo json_encode($data);
?&gt;
```

## Summary

✅ **Automatic Features:**
- PHP pages never cached
- CSS/JS auto-versioned
- AJAX requests cache-busted
- BFCache prevented
- Old cache auto-cleared when version changes

✅ **Manual Controls:**
- Increment version numbers to force refresh
- Use `clearAppCache()` in development
- Hard refresh browser when needed

✅ **No User Action Required:**
Everything works automatically in the background to ensure users always see the latest version of your application!

## Version History

- **v2.0.0** - Initial implementation of comprehensive cache management system
  - Server-side cache control via .htaccess
  - PHP cache helper functions
  - HTML meta tags for cache prevention
  - Client-side JavaScript cache manager
  - Automatic asset versioning
