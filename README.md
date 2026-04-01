# Business Management System

A comprehensive full-stack business management system built with PHP and MySQL for inventory management, point-of-sale operations, accounting, HR management, and reporting.

## 🚀 Features

- **User Management** - Role-based access control with permissions
- **Customer & Supplier Management** - Complete ledger system
- **Product Management** - With variants, serial/IMEI tracking, barcode support
- **Purchase Management** - Purchase orders, returns, supplier payments
- **Sales/POS** - Fast checkout, barcode scanning, invoice generation
- **Quotation Management** - Create and convert quotations to sales
- **Warranty & RMA** - Product warranty tracking and RMA management
- **Expense Management** - Track and categorize expenses
- **Accounts Management** - Cash and bank account management
- **HR Management** - Staff, salary, and attendance (optional)
- **Comprehensive Reports** - Sales, purchase, stock, profit/loss, and more
- **Barcode Generation** - Generate and print product barcodes
- **Multi-environment Support** - Works on XAMPP (localhost) and cPanel (production)

## 📋 Requirements

### XAMPP (Development)
- XAMPP with PHP 8.0+ and MySQL 5.7+
- Apache web server
- PDO and PDO_MySQL extensions
- GD Library for image handling
- mbstring extension

### cPanel (Production)
- cPanel hosting with PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- Same PHP extensions as above

## 🛠️ Installation

### Step 1: Download/Clone Project

```bash
# For XAMPP, place in:
C:\xampp\htdocs\business-management-system\

# Extract all files to this directory
```

### Step 2: Database Setup

1. Start XAMPP Control Panel
2. Start **Apache** and **MySQL**
3. Open phpMyAdmin: http://localhost/phpmyadmin
4. Create a new database:
   - Database name: `business_db`
   - Collation: `utf8mb4_general_ci`
5. Import the schema:
   - Click on the `business_db` database
   - Click "Import" tab
   - Choose file: `database/schema.sql`
   - Click "Go"
6. Verify all tables are created (should see 40+ tables)

### Step 3: Configuration

1. Open `config/config.php`
2. Verify/update database settings (already configured for XAMPP):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'business_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Empty for XAMPP
   ```
3. Update business information:
   ```php
   define('BUSINESS_NAME', 'Your Business Name');
   define('BUSINESS_ADDRESS', 'Your Address');
   define('BUSINESS_PHONE', '+1234567890');
   define('BUSINESS_EMAIL', 'info@yourbusiness.com');
   ```

### Step 4: Set Permissions

Ensure the `uploads/` directory and subdirectories are writable:
```bash
# Windows (XAMPP)
# Right-click uploads folder → Properties → Security → Edit → Allow Full Control

# For cPanel, set permissions to 755 or 777
```

### Step 5: Access Application

1. Open browser and navigate to:
   ```
   http://localhost/business-management-system
   ```

2. You will be redirected to login page

3. Use default credentials:
   - **Username**: `admin`
   - **Password**: `admin123`

4. **⚠️ IMPORTANT**: Change the default password immediately after first login!

## 🌐 cPanel Deployment

### Step 1: Prepare Files

1. Create a ZIP file of the entire project
2. Exclude any development files (`.git`, `node_modules`, etc.)

### Step 2: Upload to cPanel

1. Log in to your cPanel account
2. Open **File Manager**
3. Navigate to `public_html/` (or your subdomain folder)
4. Upload the ZIP file
5. Extract the ZIP file

### Step 3: Create Database

1. In cPanel, go to **MySQL® Databases**
2. Create new database:
   - Name: `username_business_db` (cPanel prefixes with your username)
3. Create MySQL user
4. Assign user to database with **ALL PRIVILEGES**
5. Note the database name, username, and password

### Step 4: Import Database

1. Open **phpMyAdmin** from cPanel
2. Select your database
3. Click **Import**
4. Choose `database/schema.sql`
5. Click **Go**

### Step 5: Update Configuration

1. In File Manager, edit `config/config.php`
2. Update database credentials:
   ```php
   define('DB_HOST', 'localhost');  // Usually localhost
   define('DB_NAME', 'username_business_db');
   define('DB_USER', 'username_dbuser');
   define('DB_PASS', 'your_password');
   ```
3. Update Base URL:
   ```php
   define('BASE_URL', 'https://yourdomain.com');
   // Or: https://yourdomain.com/subfolder
   ```
4. Set environment to production:
   ```php
   define('APP_ENV', 'production');
   define('DEBUG_MODE', false);
   ```

### Step 6: Test & Secure

1. Navigate to your domain
2. Test login functionality
3. Test a few core features
4. **Enable HTTPS** (SSL certificate)
5. **Change default admin password**
6. Set up regular backups

## 📁 Project Structure

```
business-management-system/
├── config/              # Configuration files
├── includes/            # Helper functions
├── templates/           # Reusable templates
├── assets/              # CSS, JS, images
├── uploads/             # User uploads
├── modules/             # Application modules
│   ├── auth/           # Login, logout
│   ├── dashboard/      # Main dashboard
│   ├── users/          # User management
│   ├── customers/      # Customer management
│   ├── suppliers/      # Supplier management
│   ├── products/       # Product management
│   ├── purchase/       # Purchase orders
│   ├── sales/          # POS and sales
│   ├── quotation/      # Quotations
│   ├── warranty/       # Warranty & RMA
│   ├── expense/        # Expenses
│   ├── accounts/       # Accounts
│   ├── hr/             # HR management
│   ├── reports/        # Reports
│   ├── barcode/        # Barcode
│   └── settings/       # Settings
├── api/                # AJAX endpoints
├── database/           # Database schema
└── index.php           # Entry point
```

## 🔐 Security Features

- Password hashing with bcrypt
- CSRF protection on all forms
- SQL injection prevention (PDO prepared statements)
- XSS protection (input sanitization)
- Role-based access control
- Session security
- Account lockout after failed login attempts

## 🎯 Default User Roles

| Role | Permissions |
|------|-------------|
| **Super Admin** | Full access to all modules |
| **Manager** | View all, create/edit most modules |
| **Cashier** | POS, sales, Bill Collections |
| **Sales Rep** | POS, quotations, customer management |
| **Accountant** | Accounts, expenses, financial reports |

## ⌨️ Keyboard Shortcuts

- **F2** - Quick access to POS

## 📊 Reports Available

- Business Summary
- Sales Report
- Purchase Report
- Top Customers
- Receivable/Payable Reports
- Low Stock Alert
- Product Sales Report
- Expense Report
- Account Transactions
- Daily Report
- Stock Report
- Profit/Loss Report

## 🔧 Troubleshooting

### Database Connection Failed
- Verify MySQL is running
- Check database credentials in `config/config.php`
- Ensure database exists and user has privileges

### Permission Denied Errors
- Check `uploads/` folder permissions
- Ensure web server has write access

### Blank Page / 500 Error
- Enable error reporting in `config/config.php`:
  ```php
  define('DEBUG_MODE', true);
  ```
- Check Apache error logs

### Login Not Working
- Clear browser cache and cookies
- Verify database has admin user
- Check if sessions are enabled

## 📝 Configuration File

**⚠️ IMPORTANT**: Only `config/config.php` needs to be modified when deploying:

- Database credentials
- Application URL
- Business information
- Feature flags (enable/disable modules)

## 🔄 Updating from XAMPP to cPanel

1. Export database from phpMyAdmin (XAMPP)
2. Update `config/config.php` with cPanel credentials
3. Import database to cPanel phpMyAdmin
4. Upload files to cPanel
5. Test thoroughly

## 🆘 Support

For issues or questions:
1. Check the troubleshooting section
2. Verify all configuration settings
3. Check server error logs
4. Ensure all requirements are met

## 📜 License

Copyright © 2026 Business Management System. All rights reserved.

## ✅ Post-Installation Checklist

- [ ] Database created and schema imported
- [ ] Configuration file updated
- [ ] Can access login page
- [ ] Can login with default credentials
- [ ] Dashboard loads correctly
- [ ] Changed default admin password
- [ ] Updated business information
- [ ] Tested creating a product
- [ ] Tested creating a sale
- [ ] Verified reports are working
- [ ] Set up regular backups

---

**For Production Deployment:**
- [ ] Environment set to 'production'
- [ ] Debug mode disabled
- [ ] HTTPS enabled
- [ ] Strong passwords set
- [ ] File permissions secured
- [ ] Regular backups configured

---

## 🎉 Quick Start

1. Import `database/schema.sql`
2. Login with `admin` / `admin123`
3. Go to **Settings** → Update business info
4. Go to **Products** → Add your products
5. Go to **Customers** → Add customers
6. Go to **POS** (F2) → Start selling!

Enjoy your Business Management System! 🚀
