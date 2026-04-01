<?php
// Final migration script - Create tables and insert menu items
require_once __DIR__ . '/../config/config.php';

echo "<h2>Menu Permissions Database Migration</h2>";
echo "<pre>";

try {
    echo "Connected to database: " . DB_NAME . "\n\n";

    // Drop existing tables if they exist (for clean migration)
    echo "Dropping existing tables (if any)...\n";
    $conn->exec("DROP TABLE IF EXISTS `role_permissions`");
    $conn->exec("DROP TABLE IF EXISTS `menu_items`");
    echo "✓ Cleanup completed\n\n";

    // Create menu_items table (removed duplicate slug index)
    echo "Creating menu_items table...\n";
    $conn->exec("CREATE TABLE `menu_items` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `name` VARCHAR(100) NOT NULL,
      `slug` VARCHAR(50) NOT NULL UNIQUE,
      `icon` VARCHAR(50) DEFAULT NULL,
      `url` VARCHAR(255) DEFAULT NULL,
      `parent_id` INT(11) DEFAULT NULL,
      `sort_order` INT(11) DEFAULT 0,
      `is_active` TINYINT(1) DEFAULT 1,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `parent_id` (`parent_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ menu_items table created\n\n";

    // Create role_permissions table
    echo "Creating role_permissions table...\n";
    $conn->exec("CREATE TABLE `role_permissions` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `role_id` INT(11) NOT NULL,
      `menu_item_id` INT(11) NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_role_menu` (`role_id`, `menu_item_id`),
      KEY `role_id` (`role_id`),
      KEY `menu_item_id` (`menu_item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ role_permissions table created\n\n";

    // Insert parent menus
    echo "Inserting parent menus...\n";
    $conn->exec("INSERT INTO `menu_items` (`name`, `slug`, `icon`, `url`, `parent_id`, `sort_order`) VALUES
    ('Dashboard', 'dashboard', 'fas fa-tachometer-alt', '/modules/dashboard/index.php', NULL, 1),
    ('Users & Access', 'users', 'fas fa-users', NULL, NULL, 2),
    ('Customers', 'customers', 'fas fa-user-tie', NULL, NULL, 3),
    ('Suppliers', 'suppliers', 'fas fa-truck', NULL, NULL, 4),
    ('Products', 'products', 'fas fa-box', NULL, NULL, 5),
    ('Purchase', 'purchase', 'fas fa-shopping-cart', NULL, NULL, 6),
    ('Sales / POS', 'sales', 'fas fa-cash-register', NULL, NULL, 7),
    ('Quotation', 'quotation', 'fas fa-file-invoice', NULL, NULL, 8),
    ('Warranty & RMA', 'warranty', 'fas fa-tools', NULL, NULL, 9),
    ('Expense', 'expense', 'fas fa-money-bill-wave', NULL, NULL, 10),
    ('Accounts', 'accounts', 'fas fa-university', NULL, NULL, 11),
    ('HR Management', 'hr', 'fas fa-user-friends', NULL, NULL, 12),
    ('Reports', 'reports', 'fas fa-chart-bar', NULL, NULL, 13),
    ('Barcode', 'barcode', 'fas fa-barcode', NULL, NULL, 14),
    ('Settings', 'settings', 'fas fa-cog', NULL, NULL, 15)");
    echo "✓ Inserted 15 parent menus\n\n";

    // Get parent IDs
    echo "Fetching parent menu IDs...\n";
    $parents = [];
    $slugs = ['users', 'customers', 'suppliers', 'products', 'purchase', 'sales', 'quotation', 'warranty', 'expense', 'accounts', 'hr', 'reports', 'barcode', 'settings'];
    foreach ($slugs as $slug) {
        $parents[$slug] = $conn->query("SELECT id FROM menu_items WHERE slug = '$slug'")->fetchColumn();
    }
    echo "✓ Retrieved parent IDs\n\n";

    // Insert submenus
    echo "Inserting submenu items...\n";
    $stmt = $conn->prepare("INSERT INTO `menu_items` (`name`, `slug`, `icon`, `url`, `parent_id`, `sort_order`) VALUES (?, ?, ?, ?, ?, ?)");

    $submenus = [
        ['Users', 'users.list', NULL, '/modules/users/users-list.php', $parents['users'], 1],
        ['Roles & Permissions', 'users.roles', NULL, '/modules/users/roles-permissions.php', $parents['users'], 2],
        ['Activity Log', 'users.activity', NULL, '/modules/users/activity-log.php', $parents['users'], 3],
        ['Add User', 'users.add', NULL, '/modules/users/user-add.php', $parents['users'], 4],
        ['Customer List', 'customers.list', NULL, '/modules/customers/customers-list.php', $parents['customers'], 1],
        ['Add Customer', 'customers.add', NULL, '/modules/customers/customer-add.php', $parents['customers'], 2],
        ['Customer Ledger', 'customers.ledger', NULL, '/modules/customers/customer-ledger.php', $parents['customers'], 3],
        ['Supplier List', 'suppliers.list', NULL, '/modules/suppliers/suppliers-list.php', $parents['suppliers'], 1],
        ['Add Supplier', 'suppliers.add', NULL, '/modules/suppliers/supplier-add.php', $parents['suppliers'], 2],
        ['Supplier Ledger', 'suppliers.ledger', NULL, '/modules/suppliers/supplier-ledger.php', $parents['suppliers'], 3],
        ['Product List', 'products.list', NULL, '/modules/products/products-list.php', $parents['products'], 1],
        ['Add Product', 'products.add', NULL, '/modules/products/product-add.php', $parents['products'], 2],
        ['Brands', 'products.brands', NULL, '/modules/products/brands-list.php', $parents['products'], 3],
        ['Categories', 'products.categories', NULL, '/modules/products/categories-list.php', $parents['products'], 4],
        ['Units', 'products.units', NULL, '/modules/products/units-list.php', $parents['products'], 5],
        ['Serial/IMEI', 'products.serial', NULL, '/modules/products/serial-imei-list.php', $parents['products'], 6],
        ['Stock Adjustment', 'products.stock', NULL, '/modules/products/stock-adjustment.php', $parents['products'], 7],
        ['Opening Stock', 'products.opening', NULL, '/modules/products/opening-stock.php', $parents['products'], 8],
        ['Damaged Stock', 'products.damaged', NULL, '/modules/products/damaged-stock.php', $parents['products'], 9],
        ['Create Purchase', 'purchase.create', NULL, '/modules/purchase/purchase-add.php', $parents['purchase'], 1],
        ['Purchase List', 'purchase.list', NULL, '/modules/purchase/purchases-list.php', $parents['purchase'], 2],
        ['Purchase Returns', 'purchase.returns', NULL, '/modules/purchase/purchase-return-list.php', $parents['purchase'], 3],
        ['Supplier Payment', 'purchase.payment', NULL, '/modules/purchase/supplier-payment.php', $parents['purchase'], 4],
        ['Due Management', 'purchase.dues', NULL, '/modules/purchase/due-management.php', $parents['purchase'], 5],
        ['POS', 'sales.pos', NULL, '/modules/sales/pos.php', $parents['sales'], 1],
        ['Sales List', 'sales.list', NULL, '/modules/sales/sales-list.php', $parents['sales'], 2],
        ['Sales Returns', 'sales.returns', NULL, '/modules/sales/sales-return-list.php', $parents['sales'], 3],
        ['Bill Collection', 'sales.payment', NULL, '/modules/sales/customer-payment.php', $parents['sales'], 4],
        ['Create Quotation', 'quotation.create', NULL, '/modules/quotation/quotation-add.php', $parents['quotation'], 1],
        ['Quotation List', 'quotation.list', NULL, '/modules/quotation/quotations-list.php', $parents['quotation'], 2],
        ['Serial List', 'warranty.serial', NULL, '/modules/warranty/serial-list.php', $parents['warranty'], 1],
        ['RMA List', 'warranty.rma', NULL, '/modules/warranty/rma-list.php', $parents['warranty'], 2],
        ['Service Centers', 'warranty.service', NULL, '/modules/warranty/service-center-info.php', $parents['warranty'], 3],
        ['Expense List', 'expense.list', NULL, '/modules/expense/expenses-list.php', $parents['expense'], 1],
        ['Add Expense', 'expense.add', NULL, '/modules/expense/expense-add.php', $parents['expense'], 2],
        ['Categories', 'expense.categories', NULL, '/modules/expense/expense-categories.php', $parents['expense'], 3],
        ['Cash Account', 'accounts.cash', NULL, '/modules/accounts/cash-accounts-list.php', $parents['accounts'], 1],
        ['Bank Accounts', 'accounts.bank', NULL, '/modules/accounts/bank-accounts.php', $parents['accounts'], 2],
        ['Balance Transfer', 'accounts.transfer', NULL, '/modules/accounts/balance-transfer.php', $parents['accounts'], 3],
        ['Daily Cash Closing', 'accounts.closing', NULL, '/modules/accounts/daily-cash-closing.php', $parents['accounts'], 4],
        ['Transaction History', 'accounts.transactions', NULL, '/modules/accounts/transaction-history.php', $parents['accounts'], 5],
        ['Party Cash', 'accounts.party', NULL, '/modules/accounts/party-cash.php', $parents['accounts'], 6],
        ['Staff List', 'hr.staff', NULL, '/modules/hr/staff-list.php', $parents['hr'], 1],
        ['Salary', 'hr.salary', NULL, '/modules/hr/salary-manage.php', $parents['hr'], 2],
        ['Attendance', 'hr.attendance', NULL, '/modules/hr/attendance.php', $parents['hr'], 3],
        ['Team Overview', 'hr.overview', NULL, '/modules/hr/team-overview.php', $parents['hr'], 4],
        ['Roles', 'hr.roles', NULL, '/modules/hr/staff-roles.php', $parents['hr'], 5],
        ['Business Summary', 'reports.summary', NULL, '/modules/reports/business-summary.php', $parents['reports'], 1],
        ['Daily Report', 'reports.daily', NULL, '/modules/reports/daily-report.php', $parents['reports'], 2],
        ['Sales Report', 'reports.sales', NULL, '/modules/reports/sales-report.php', $parents['reports'], 3],
        ['Product Sales Report', 'reports.productsales', NULL, '/modules/reports/product-sales-report.php', $parents['reports'], 4],
        ['Purchase Report', 'reports.purchase', NULL, '/modules/reports/purchase-report.php', $parents['reports'], 5],
        ['Receivable Report', 'reports.receivable', NULL, '/modules/reports/receivable-report.php', $parents['reports'], 6],
        ['Payable Report', 'reports.payable', NULL, '/modules/reports/payable-report.php', $parents['reports'], 7],
        ['Top Customer', 'reports.topcustomer', NULL, '/modules/reports/top-customer.php', $parents['reports'], 8],
        ['Stock Report', 'reports.stock', NULL, '/modules/reports/stock-report.php', $parents['reports'], 9],
        ['Alert Product Report', 'reports.alertproduct', NULL, '/modules/reports/alert-product-report.php', $parents['reports'], 10],
        ['Expense Report', 'reports.expense', NULL, '/modules/reports/expense-report.php', $parents['reports'], 11],
        ['Account Transaction Report', 'reports.accounttransaction', NULL, '/modules/reports/account-transaction-report.php', $parents['reports'], 12],
        ['Profit/Loss Report', 'reports.profit', NULL, '/modules/reports/profit-loss-report.php', $parents['reports'], 13],
        ['Low Stock Alert', 'reports.lowstock', NULL, '/modules/reports/low-stock-report.php', $parents['reports'], 14],
        ['Generate Barcode', 'barcode.generate', NULL, '/modules/barcode/barcode-generator.php', $parents['barcode'], 1],
        ['Print Barcode', 'barcode.print', NULL, '/modules/barcode/barcode-print.php', $parents['barcode'], 2],
        ['Business Settings', 'settings.business', NULL, '/modules/settings/business-settings.php', $parents['settings'], 1],
        ['Invoice Settings', 'settings.invoice', NULL, '/modules/settings/invoice-settings.php', $parents['settings'], 2],
        ['Tax Settings', 'settings.tax', NULL, '/modules/settings/tax-settings.php', $parents['settings'], 3],
        ['Profile', 'settings.profile', NULL, '/modules/settings/profile.php', $parents['settings'], 4],
        ['Change Password', 'settings.password', NULL, '/modules/settings/change-password.php', $parents['settings'], 5],
    ];

    foreach ($submenus as $submenu) {
        $stmt->execute($submenu);
    }
    echo "✓ Inserted " . count($submenus) . " submenu items\n\n";

    // Final verification
    $menu_count = $conn->query("SELECT COUNT(*) FROM menu_items")->fetchColumn();
    $perm_count = $conn->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();

    echo "===========================================\n";
    echo "✓ MIGRATION COMPLETED SUCCESSFULLY!\n";
    echo "===========================================\n";
    echo "menu_items table: $menu_count records\n";
    echo "role_permissions table: $perm_count records\n";
    echo "===========================================\n";

} catch (Exception $e) {
    echo "\n\n❌ ERROR: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>
