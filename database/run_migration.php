<?php
/**
 * Execute Menu Permissions SQL Migration
 * This script will create the necessary tables and insert default menu items
 */

require_once __DIR__ . '/../config/config.php';

try {
  echo "Starting database migration...\n\n";

  // Disable foreign key checks temporarily
  $conn->exec("SET FOREIGN_KEY_CHECKS = 0");

  // Create menu_items table
  echo "Creating menu_items table...\n";
  $conn->exec("CREATE TABLE IF NOT EXISTS `menu_items` (
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
      KEY `parent_id` (`parent_id`),
      KEY `slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  echo "✓ menu_items table created\n\n";

  // Create role_permissions table
  echo "Creating role_permissions table...\n";
  $conn->exec("CREATE TABLE IF NOT EXISTS `role_permissions` (
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

  // Check if menu items already exist
  $count = $conn->query("SELECT COUNT(*) FROM menu_items")->fetchColumn();
  if ($count > 0) {
    echo "Menu items already exist ($count records). Skipping insertion.\n";
  } else {
    echo "Inserting default menu items...\n";

    // Insert parent menus
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

    echo "✓ Inserted parent menus\n";

    // Get parent IDs
    $users_id = $conn->query("SELECT id FROM menu_items WHERE slug = 'users'")->fetchColumn();
    $customers_id = $conn->query("SELECT id FROM menu_items WHERE slug = 'customers'")->fetchColumn();
    $suppliers_id = $conn->query("SELECT id FROM menu_items WHERE slug = 'suppliers'")->fetchColumn();
    $products_id = $conn->query("SELECT id FROM menu_items WHERE slug = 'products'")->fetchColumn();
    $purchase_id = $conn->query("SELECT id FROM menu_items WHERE slug = 'purchase'")->fetchColumn();
    $sales_id = $conn->query("SELECT id FROM menu_items WHERE slug = 'sales'")->fetchColumn();
    $quotation_id = $conn->query("SELECT id FROM menu_items WHERE slug = 'quotation'")->fetchColumn();
    $warranty_id = $conn->query("SELECT id FROM menu_items WHERE slug = 'warranty'")->fetchColumn();
    $expense_id = $conn->query("SELECT id FROM menu_items WHERE slug = 'expense'")->fetchColumn();
    $accounts_id = $conn->query("SELECT id FROM menu_items WHERE slug = 'accounts'")->fetchColumn();
    $hr_id = $conn->query("SELECT id FROM menu_items WHERE slug = 'hr'")->fetchColumn();
    $reports_id = $conn->query("SELECT id FROM menu_items WHERE slug = 'reports'")->fetchColumn();
    $barcode_id = $conn->query("SELECT id FROM menu_items WHERE slug = 'barcode'")->fetchColumn();
    $settings_id = $conn->query("SELECT id FROM menu_items WHERE slug = 'settings'")->fetchColumn();

    // Insert submenus
    $stmt = $conn->prepare("INSERT INTO `menu_items` (`name`, `slug`, `icon`, `url`, `parent_id`, `sort_order`) VALUES (?, ?, ?, ?, ?, ?)");

    $submenus = [
      ['Users', 'users.list', NULL, '/modules/users/users-list.php', $users_id, 1],
      ['Roles & Permissions', 'users.roles', NULL, '/modules/users/roles-permissions.php', $users_id, 2],
      ['Activity Log', 'users.activity', NULL, '/modules/users/activity-log.php', $users_id, 3],
      ['Add User', 'users.add', NULL, '/modules/users/user-add.php', $users_id, 4],
      ['Customer List', 'customers.list', NULL, '/modules/customers/customers-list.php', $customers_id, 1],
      ['Add Customer', 'customers.add', NULL, '/modules/customers/customer-add.php', $customers_id, 2],
      ['Customer Ledger', 'customers.ledger', NULL, '/modules/customers/customer-ledger.php', $customers_id, 3],
      ['Supplier List', 'suppliers.list', NULL, '/modules/suppliers/suppliers-list.php', $suppliers_id, 1],
      ['Add Supplier', 'suppliers.add', NULL, '/modules/suppliers/supplier-add.php', $suppliers_id, 2],
      ['Supplier Ledger', 'suppliers.ledger', NULL, '/modules/suppliers/supplier-ledger.php', $suppliers_id, 3],
      ['Product List', 'products.list', NULL, '/modules/products/products-list.php', $products_id, 1],
      ['Add Product', 'products.add', NULL, '/modules/products/product-add.php', $products_id, 2],
      ['Brands', 'products.brands', NULL, '/modules/products/brands-list.php', $products_id, 3],
      ['Categories', 'products.categories', NULL, '/modules/products/categories-list.php', $products_id, 4],
      ['Units', 'products.units', NULL, '/modules/products/units-list.php', $products_id, 5],
      ['Serial/IMEI', 'products.serial', NULL, '/modules/products/serial-imei-list.php', $products_id, 6],
      ['Stock Adjustment', 'products.stock', NULL, '/modules/products/stock-adjustment.php', $products_id, 7],
      ['Opening Stock', 'products.opening', NULL, '/modules/products/opening-stock.php', $products_id, 8],
      ['Damaged Stock', 'products.damaged', NULL, '/modules/products/damaged-stock.php', $products_id, 9],
      ['Create Purchase', 'purchase.create', NULL, '/modules/purchase/purchase-add.php', $purchase_id, 1],
      ['Purchase List', 'purchase.list', NULL, '/modules/purchase/purchases-list.php', $purchase_id, 2],
      ['Purchase Returns', 'purchase.returns', NULL, '/modules/purchase/purchase-return-list.php', $purchase_id, 3],
      ['Supplier Payment', 'purchase.payment', NULL, '/modules/purchase/supplier-payment.php', $purchase_id, 4],
      ['Due Management', 'purchase.dues', NULL, '/modules/purchase/due-management.php', $purchase_id, 5],
      ['POS', 'sales.pos', NULL, '/modules/sales/pos.php', $sales_id, 1],
      ['Sales List', 'sales.list', NULL, '/modules/sales/sales-list.php', $sales_id, 2],
      ['Sales Returns', 'sales.returns', NULL, '/modules/sales/sales-return-list.php', $sales_id, 3],
      ['Bill Collection', 'sales.payment', NULL, '/modules/sales/customer-payment.php', $sales_id, 4],
      ['Create Quotation', 'quotation.create', NULL, '/modules/quotation/quotation-add.php', $quotation_id, 1],
      ['Quotation List', 'quotation.list', NULL, '/modules/quotation/quotations-list.php', $quotation_id, 2],
      ['Serial List', 'warranty.serial', NULL, '/modules/warranty/serial-list.php', $warranty_id, 1],
      ['RMA List', 'warranty.rma', NULL, '/modules/warranty/rma-list.php', $warranty_id, 2],
      ['Service Centers', 'warranty.service', NULL, '/modules/warranty/service-center-info.php', $warranty_id, 3],
      ['Expense List', 'expense.list', NULL, '/modules/expense/expenses-list.php', $expense_id, 1],
      ['Add Expense', 'expense.add', NULL, '/modules/expense/expense-add.php', $expense_id, 2],
      ['Categories', 'expense.categories', NULL, '/modules/expense/expense-categories.php', $expense_id, 3],
      ['Cash Account', 'accounts.cash', NULL, '/modules/accounts/cash-accounts-list.php', $accounts_id, 1],
      ['Bank Accounts', 'accounts.bank', NULL, '/modules/accounts/bank-accounts.php', $accounts_id, 2],
      ['Balance Transfer', 'accounts.transfer', NULL, '/modules/accounts/balance-transfer.php', $accounts_id, 3],
      ['Daily Cash Closing', 'accounts.closing', NULL, '/modules/accounts/daily-cash-closing.php', $accounts_id, 4],
      ['Transaction History', 'accounts.transactions', NULL, '/modules/accounts/transaction-history.php', $accounts_id, 5],
      ['Party Cash', 'accounts.party', NULL, '/modules/accounts/party-cash.php', $accounts_id, 6],
      ['Staff List', 'hr.staff', NULL, '/modules/hr/staff-list.php', $hr_id, 1],
      ['Salary', 'hr.salary', NULL, '/modules/hr/salary-manage.php', $hr_id, 2],
      ['Attendance', 'hr.attendance', NULL, '/modules/hr/attendance.php', $hr_id, 3],
      ['Team Overview', 'hr.overview', NULL, '/modules/hr/team-overview.php', $hr_id, 4],
      ['Roles', 'hr.roles', NULL, '/modules/hr/staff-roles.php', $hr_id, 5],
      ['Business Summary', 'reports.summary', NULL, '/modules/reports/business-summary.php', $reports_id, 1],
      ['Daily Report', 'reports.daily', NULL, '/modules/reports/daily-report.php', $reports_id, 2],
      ['Sales Report', 'reports.sales', NULL, '/modules/reports/sales-report.php', $reports_id, 3],
      ['Product Sales Report', 'reports.productsales', NULL, '/modules/reports/product-sales-report.php', $reports_id, 4],
      ['Purchase Report', 'reports.purchase', NULL, '/modules/reports/purchase-report.php', $reports_id, 5],
      ['Receivable Report', 'reports.receivable', NULL, '/modules/reports/receivable-report.php', $reports_id, 6],
      ['Payable Report', 'reports.payable', NULL, '/modules/reports/payable-report.php', $reports_id, 7],
      ['Top Customer', 'reports.topcustomer', NULL, '/modules/reports/top-customer.php', $reports_id, 8],
      ['Stock Report', 'reports.stock', NULL, '/modules/reports/stock-report.php', $reports_id, 9],
      ['Alert Product Report', 'reports.alertproduct', NULL, '/modules/reports/alert-product-report.php', $reports_id, 10],
      ['Expense Report', 'reports.expense', NULL, '/modules/reports/expense-report.php', $reports_id, 11],
      ['Account Transaction Report', 'reports.accounttransaction', NULL, '/modules/reports/account-transaction-report.php', $reports_id, 12],
      ['Profit/Loss Report', 'reports.profit', NULL, '/modules/reports/profit-loss-report.php', $reports_id, 13],
      ['Low Stock Alert', 'reports.lowstock', NULL, '/modules/reports/low-stock-report.php', $reports_id, 14],
      ['Generate Barcode', 'barcode.generate', NULL, '/modules/barcode/barcode-generator.php', $barcode_id, 1],
      ['Print Barcode', 'barcode.print', NULL, '/modules/barcode/barcode-print.php', $barcode_id, 2],
      ['Business Settings', 'settings.business', NULL, '/modules/settings/business-settings.php', $settings_id, 1],
      ['Invoice Settings', 'settings.invoice', NULL, '/modules/settings/invoice-settings.php', $settings_id, 2],
      ['Tax Settings', 'settings.tax', NULL, '/modules/settings/tax-settings.php', $settings_id, 3],
      ['Profile', 'settings.profile', NULL, '/modules/settings/profile.php', $settings_id, 4],
      ['Change Password', 'settings.password', NULL, '/modules/settings/change-password.php', $settings_id, 5],
    ];

    foreach ($submenus as $submenu) {
      $stmt->execute($submenu);
    }

    echo "✓ Inserted submenu items\n";
  }

  // Re-enable foreign key checks
  $conn->exec("SET FOREIGN_KEY_CHECKS = 1");

  echo "\n===========================================\n";

  // Verify the tables
  $menu_count = $conn->query("SELECT COUNT(*) FROM menu_items")->fetchColumn();
  $perm_count = $conn->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();

  echo "✓ menu_items table: $menu_count records\n";
  echo "✓ role_permissions table: $perm_count records\n";
  echo "===========================================\n\n";
  echo "✓ Database migration successful!\n";

} catch (Exception $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  echo "Stack trace: " . $e->getTraceAsString() . "\n";
  exit(1);
}
?>
