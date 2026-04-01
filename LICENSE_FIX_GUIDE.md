# License Activation Fix - For cPanel Deployment

## Problem

After deploying to cPanel, the license activation page shows "License activated successfully!" but the status still shows "NO LICENSE".

## Root Cause

This happens when:

1. The `app_license` table is empty or has corrupt data
2. The remote license server is unreachable from your cPanel server
3. CURL requests are blocked by your hosting provider

## Solution

### Option 1: Run SQL Fix (Recommended)

**Steps:**

1. Go to **cPanel** → **phpMyAdmin**
2. Select your database
3. Click **Import** tab
4. Upload `LICENSE_FIX.sql` file
5. Click **Go** to execute

This will:

- Recreate the `app_license` table with correct structure
- Clear any corrupt data
- Insert a fresh row ready for activation
- Create `server_licenses` table (if you want to run your own license server)

### Option 2: Manual SQL Commands

If you can't upload the SQL file, run these commands in phpMyAdmin → SQL tab:

```sql
-- Clear and reset app_license table
TRUNCATE TABLE `app_license`;

-- Insert fresh row
INSERT INTO `app_license` (`id`, `license_key`, `status`, `domain`)
VALUES (1, '', 'inactive', NULL);```

### Option 3: Check License Server Connectivity

The license system connects to: `https://www.li.cleansbuy.store/api.php`

**Test if your server can reach it:**

1. Create a file called `test_license_curl.php` in your cPanel public_html
2. Add this code:

```php
<?php
$url = "https://www.li.cleansbuy.store/api.php?key=DEMO-LICENSE-2026&domain=" . $_SERVER['HTTP_HOST'];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only

$response = curl_exec($ch);
$error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h2>License Server Test</h2>";
echo "<p><strong>HTTP Code:</strong> " . $http_code . "</p>";
if ($error) {
    echo "<p style='color:red;'><strong>CURL Error:</strong> " . $error . "</p>";
} else {
    echo "<p style='color:green;'><strong>Success!</strong> Server is reachable</p>";
}
echo "<p><strong>Response:</strong></p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";
?>
```

1. Access `https://yourdomain.com/test_license_curl.php` in your browser
2. Check the results:
   - **HTTP Code 200**: License server is reachable ✅
   - **HTTP Code 0 or CURL Error**: Your server can't reach the license server ❌

**If server can't reach license server:**

- Contact your hosting provider to allow outbound CURL connections
- Ask them to whitelist `https://www.li.cleansbuy.store`
- Check if your server has a firewall blocking outbound connections

### Option 4: Bypass License Check (Temporary Development Only)

**⚠️ ONLY FOR TESTING - NOT FOR PRODUCTION**

Edit `config/config.php` and comment out the license enforcement section (lines 212-220):

```php
// COMMENT OUT THIS SECTION TEMPORARILY
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

Save and upload. The app will now work without license validation.

**Remember to remove these comments and re-enable license checking for production!**

---

## After Fix: Re-activate License

1. Go to your domain
2. Access `/modules/settings/license-manage.php`
3. Enter license key: `DEMO-LICENSE-2026`
4. Click "Activate & Verify Software"
5. Wait for success message
6. Refresh the page
7. Status should now show "ACTIVE" ✅

---

## Still Having Issues?

**Check these:**

1. ✅ Database credentials in `config/config.php` are correct
2. ✅ `app_license` table exists in database
3. ✅ Your server can make outbound CURL requests
4. ✅ License server `https://www.li.cleansbuy.store/api.php` is accessible
5. ✅ No firewall blocking connections

**Get detailed error info:**

Set `DEBUG_MODE` to `true` in `config/config.php` temporarily:

```php
define('DEBUG_MODE', true); // Enable for debugging
```

This will show detailed error messages.

---

## Files Included

| File | Purpose |
|------|---------|
| `LICENSE_FIX.sql` | SQL script to fix database tables |
| `LICENSE_FIX_GUIDE.md` | This file - complete troubleshooting guide |

---

## Contact Support

If none of these solutions work, contact support with:

- Your domain name
- Screenshot of the license page
- Results from the `test_license_curl.php` test
- Any error messages from browser console or PHP error logs

**Support**: [Web - CITNBD](https://citnbd.com)
