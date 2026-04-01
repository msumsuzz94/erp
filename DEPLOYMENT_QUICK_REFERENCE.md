# Deployment Package - Quick Reference

## 📍 File Locations

All deployment files are located in:

```
c:\xampp\htdocs\business-management-system\
```

### Generated Files

1. **deployment_database.sql**
   - Full path: `c:\xampp\htdocs\business-management-system\deployment_database.sql`
   - Purpose: Complete database export with reset license data
   - Usage: Import this file to your cPanel database via phpMyAdmin

2. **business-management-system-deploy.zip**
   - Full path: `c:\xampp\htdocs\business-management-system\business-management-system-deploy.zip`
   - Purpose: Clean application package (no test/debug files)
   - Usage: Extract and upload all files to your cPanel hosting

3. **DEPLOYMENT_INSTRUCTIONS.md**
   - Full path: `c:\xampp\htdocs\business-management-system\DEPLOYMENT_INSTRUCTIONS.md`
   - Purpose: Complete step-by-step deployment guide
   - Usage: Follow all steps for successful deployment

---

## 🚀 Quick Deployment Steps

### 1. Import Database

- cPanel → phpMyAdmin
- Select your database
- Import → Choose `deployment_database.sql`
- Click "Go"

### 2. Upload Files

- Extract `business-management-system-deploy.zip`
- Upload all files to cPanel `public_html/`
- Set permissions: folders 755, files 644

### 3. Edit Config

Edit **only** `config/config.php`:

- Lines 26-29: Database credentials
- Line 10: `APP_ENV` to `'production'`
- Line 11: `DEBUG_MODE` to `false`
- Line 47: `BASE_URL` to your domain

### 4. Activate License

- Access your domain URL
- You'll be redirected to license activation
- Enter license key: `DEMO-LICENSE-2026` (for testing)
- Click "Activate License"

### 5. Login

- Username: `admin`
- Password: `admin123`
- **Change password immediately!**

---

## 📝 Files to Edit

**Only ONE file needs editing:**

| File | Lines to Edit | What to Change |
|------|---------------|----------------|
| `config/config.php` | 26-29 | Database credentials |
| `config/config.php` | 10 | Environment to 'production' |
| `config/config.php` | 11 | Debug mode to false |
| `config/config.php` | 47 | BASE_URL to your domain |

**No other files need editing!**

---

## 🔐 License Information

- **License Server**: `https://www.li.cleansbuy.store/api.php` (already configured)
- **Demo License Key**: `DEMO-LICENSE-2026`
- **License File**: `includes/license_functions.php` (no changes needed)

The license will automatically lock to your domain upon first activation.

---

## ❓ Need Help?

See full instructions in `DEPLOYMENT_INSTRUCTIONS.md` for:

- Detailed setup steps
- Troubleshooting guide
- License server options
- Support information

---

## ✅ Deployment Checklist

- [ ] Import `deployment_database.sql` to cPanel database
- [ ] Upload all files from ZIP to cPanel
- [ ] Edit `config/config.php` with database credentials
- [ ] Edit `config/config.php` with BASE_URL
- [ ] Set APP_ENV to 'production' and DEBUG_MODE to false
- [ ] Access domain and activate license
- [ ] Login with admin/admin123
- [ ] Change default password

**You're ready to deploy!** 🎉
