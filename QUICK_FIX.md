# 🔧 LICENSE ACTIVATION ISSUE - QUICK FIX

## What Happened?

Your license activation shows "License activated successfully!" but status remains "NO LICENSE".

## Quick Fix Steps

### 1. Import License Fix SQL

**In cPanel phpMyAdmin:**

1. Select your database
2. Click **Import** tab
3. Upload file: `LICENSE_FIX.sql` (from this package)
4. Click **Go**

✅ This fixes the `app_license` table structure

### 2. Test License Server Connection

**Create file `test_curl.php` on your server:**

```php
<?php
$url = "https://www.li.cleansbuy.store/api.php?key=DEMO-LICENSE-2026&domain=" . $_SERVER['HTTP_HOST'];
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code<br>";
echo "Response: <pre>$response</pre>";
?>
```

Access: `https://yourdomain.com/test_curl.php`

- **HTTP Code 200**: ✅ Working - license server reachable
- **HTTP Code 0**: ❌ Problem - your server can't reach license server

### 3. Fix Server Connectivity (if HTTP Code 0)

**Contact your hosting provider:**
"Please enable outbound CURL connections to `https://www.li.cleansbuy.store`"

**Or temporarily bypass license check:**

Edit `config/config.php`, comment lines 212-220:

```php
/*
if (!$is_excluded && PHP_SAPI !== 'cli') {
    $license_check = validate_license();
    if (!$license_check['status']) {
        header("Location: " . BASE_URL . "/modules/settings/license-manage.php?error=" . ($license_check['message'] ?? 'invalid_license'));
        exit;
    }
}
*/
```

### 4. Re-Activate License

1. Go to: `/modules/settings/license-manage.php`
2. Enter: `DEMO-LICENSE-2026`
3. Click "Activate"
4. Refresh page
5. Status should show "ACTIVE" ✅

---

## Files in This Package

| File | Purpose |
|------|---------|
| `deployment_database.sql` | Complete database (updated) |
| `LICENSE_FIX.sql` | Quick fix for license table |
| `LICENSE_FIX_GUIDE.md` | Detailed troubleshooting |
| `QUICK_FIX.md` | This file (quick reference) |

---

## Still Not Working?

See `LICENSE_FIX_GUIDE.md` for detailed troubleshooting.

**Support**: [Facebook - Dustu Shimul](https://www.facebook.com/dustu.shimul)
