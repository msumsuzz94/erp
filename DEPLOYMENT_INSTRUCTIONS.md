# Business Management System - Deployment Instructions

## 📦 Package Contents

Your deployment package includes:

1. **deployment_database.sql** - Complete database with reset license data
2. **business-management-system-deploy.zip** - All application files
3. **DEPLOYMENT_INSTRUCTIONS.md** - This file

---

## 🚀 Step-by-Step Deployment Guide

### Step 1: Database Setup

#### 1.1 Create Database in cPanel

1. Log into your cPanel account
2. Go to **MySQL Databases**
3. Create a new database (e.g., `youruser_business`)
4. Create a new database user with a strong password
5. Add the user to the database with **ALL PRIVILEGES**
6. **Note down**: Database name, username, and password

#### 1.2 Import SQL File

1. Go to **phpMyAdmin** in cPanel
2. Select your newly created database from the left sidebar
3. Click the **Import** tab
4. Click **Choose File** and select `deployment_database.sql`
5. Click **Go** to import
6. Wait for the success message

> ✅ **Expected Result**: You should see 50+ tables imported successfully

---

### Step 2: Upload Application Files

#### 2.1 Extract ZIP File

1. Download `business-management-system-deploy.zip` to your computer
2. Extract the ZIP file to a folder

#### 2.2 Upload to cPanel

1. In cPanel, go to **File Manager**
2. Navigate to `public_html` (for main domain) or `public_html/subfolder` (for subdomain/subfolder)
3. Upload all extracted files and folders
4. Ensure the following directories are uploaded:
   - `modules/`
   - `assets/`
   - `includes/`
   - `config/`
   - `api/`
   - `license-server/`
   - `templates/`
   - `uploads/`
   - Root files: `index.php`, `.htaccess`

#### 2.3 Set Permissions

1. Set folder permissions to **755**:
   - `uploads/`
   - `uploads/products/`
   - `uploads/invoices/`
   - `uploads/documents/`
2. Set file permissions to **644** for `.php` files

---

### Step 3: Configure Application

#### 3.1 Edit config/config.php

This is the **ONLY file you need to edit**. Open `config/config.php` and update the following:

**Lines 26-29: Database Credentials**

```php
// CHANGE THESE:
define('DB_HOST', 'localhost');           // Usually stays 'localhost'
define('DB_NAME', 'youruser_business');   // Your database name from Step 1
define('DB_USER', 'youruser_dbuser');     // Your database username
define('DB_PASS', 'your_strong_password'); // Your database password
```

**Line 10: Environment**

```php
define('APP_ENV', 'production'); // CHANGE from 'development' to 'production'
```

**Line 11: Debug Mode**

```php
define('DEBUG_MODE', false); // CHANGE from true to false
```

**Line 47: Base URL**

```php
// CHANGE THIS to your actual domain:
define('BASE_URL', 'https://yourdomain.com');
// OR if in subfolder:
define('BASE_URL', 'https://yourdomain.com/subfolder');
```

Save the file and upload it back to the server.

---

### Step 4: Verify Installation

1. Open your browser and go to your domain (e.g., `https://yourdomain.com`)
2. You should be redirected to the **License Activation** page
3. **If you see database connection errors:**
   - Double-check your `config/config.php` database credentials
   - Verify the database was imported successfully in phpMyAdmin

---

### Step 5: License Activation

#### 5.1 Get Your License Key

Contact your license provider to obtain your license key. The system comes with a demo license key for testing:

- **Demo License Key**: `DEMO-LICENSE-2026` (for testing only)

#### 5.2 Activate License

1. You will be on the License Management page at `/modules/settings/license-manage.php`
2. Enter your license key in the input field
3. Click **Activate License**
4. The system will:
   - Connect to the license server at `https://www.li.cleansbuy.store/api.php`
   - Verify your license key
   - Lock the license to your domain
   - Activate the application

> ✅ **Success**: You'll see "License activated successfully" and be redirected to the login page

---

### Step 6: First Login

**Default Superadmin Credentials:**

- **Username**: `admin`
- **Password**: `admin123`

> ⚠️ **IMPORTANT**: Change the default password immediately after first login!

**To Change Password:**

1. After logging in, click your profile icon (top-right)
2. Go to **Change Password**
3. Set a strong new password

---

## 📋 Files That Need Editing (Summary)

| File | What to Edit | Lines |
|------|-------------|-------|
| `config/config.php` | Database credentials | 26-29 |
| `config/config.php` | Environment to 'production' | 10 |
| `config/config.php` | Debug mode to false | 11 |
| `config/config.php` | BASE_URL to your domain | 47 |

**That's it!** No other files need editing.

---

## 🔐 License Server Information

### How Licensing Works

Your application validates its license with a **remote license server** on every page load:

- **License Server URL**: `https://www.li.cleansbuy.store/api.php`
- **License File**: `includes/license_functions.php` (Line 8)
- **No changes needed** - Already configured correctly

### License Behavior

1. **Domain Locking**: When you activate a license, it locks to your domain
2. **Cannot Transfer**: The same license cannot be used on a different domain
3. **Instant Revocation**: If your license is revoked on the server, the app stops immediately
4. **Offline Grace**: If the license server is temporarily down, the app continues working (based on last successful verification)

### License Management

The `license-server/` directory is included in your package. This allows you to:

- **Option A**: Use the existing remote license server (recommended, no setup needed)
- **Option B**: Run your own license server on a separate domain/subdomain

**For Option B (Advanced Users Only):**

1. Upload `license-server/` to a separate domain (e.g., `license.yourdomain.com`)
2. Create a separate database for licenses
3. Import `license-server/license_server_db.sql`
4. Edit `license-server/db.php` with license database credentials
5. Update `includes/license_functions.php` line 8 to point to your license server URL
6. Manage licenses through `https://license.yourdomain.com/index.php`

**Most users should stick with Option A** (existing remote server).

---

## 🐛 Troubleshooting

### Issue: Database Connection Error

**Symptoms**: "Could not connect to database" error

**Solutions**:

1. Verify `config/config.php` has correct database credentials
2. Ensure database was imported successfully in phpMyAdmin
3. Check that database user has ALL PRIVILEGES on the database
4. Try changing `DB_HOST` from `localhost` to `127.0.0.1`

---

### Issue: License Activation Fails

**Symptoms**: "License server unreachable" or "Invalid license key"

**Solutions**:

1. Verify your license key is correct
2. Ensure your server can make outbound CURL requests (check with hosting provider)
3. Check that `https://www.li.cleansbuy.store/api.php` is accessible from your server
4. Verify SSL certificates are up to date on your server
5. Contact license provider for key validation

---

### Issue: Page Not Found / 404 Errors

**Symptoms**: URLs like `/modules/sales/sales-list.php` show 404

**Solutions**:

1. Ensure `.htaccess` file is uploaded and active
2. Verify Apache `mod_rewrite` is enabled (ask hosting provider)
3. Check file permissions (files: 644, folders: 755)

---

### Issue: Blank White Page

**Symptoms**: Application shows blank page

**Solutions**:

1. Set `DEBUG_MODE` to `true` in `config/config.php` temporarily to see errors
2. Check PHP error logs in cPanel
3. Verify PHP version is 7.4 or higher
4. Ensure all required PHP extensions are installed:
   - mysqli
   - curl
   - gd
   - mbstring
   - json

---

## 📧 Support

If you encounter issues during deployment:

1. **Check error logs**: cPanel > Error Logs
2. **Enable debug mode**: Set `DEBUG_MODE` to `true` in `config/config.php`
3. **Contact support**: Provide error messages and steps you've completed

---

## ✅ Deployment Checklist

- [ ] Database created in cPanel
- [ ] Database user created with ALL PRIVILEGES
- [ ] SQL file imported successfully
- [ ] All application files uploaded
- [ ] **config/config.php** edited with database credentials
- [ ] **config/config.php** BASE_URL updated to actual domain
- [ ] **config/config.php** APP_ENV set to 'production'
- [ ] **config/config.php** DEBUG_MODE set to false
- [ ] File permissions set (755 for folders, 644 for files)
- [ ] Application accessible in browser
- [ ] License activated successfully
- [ ] Logged in with default credentials
- [ ] Default password changed

---

## 🎉 You're All Set

Your Business Management System is now deployed and ready to use. Enjoy managing your business with a powerful, licensed application!

**License Key for Testing**: `DEMO-LICENSE-2026`

Remember to contact your license provider for your production license key.
