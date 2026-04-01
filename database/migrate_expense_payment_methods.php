<?php
/**
 * Migration: Add Multi-Payment Method Support to Expenses
 * 
 * This migration adds the ability to track expenses paid from different account types
 * (cash accounts vs bank accounts including mobile banking and cards)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db_functions.php';

echo "=======================================================\n";
echo "Migration: Enhanced Payment Method Support for Expenses\n";
echo "=======================================================\n\n";

try {
    db_begin_transaction();
    
    // Step 1: Check if account_type column already exists
    echo "Step 1: Checking if account_type column exists...\n";
    $check_column = db_query("SHOW COLUMNS FROM expenses LIKE 'account_type'");
    
    if (count($check_column) > 0) {
        echo "   ✓ Column 'account_type' already exists. Skipping creation.\n\n";
    } else {
        echo "   → Adding 'account_type' column to expenses table...\n";
        db_query("ALTER TABLE `expenses` 
                  ADD COLUMN `account_type` ENUM('cash', 'bank') DEFAULT 'cash' 
                  AFTER `account_id`");
        echo "   ✓ Column added successfully.\n\n";
    }
    
    // Step 2: Set all existing records to 'cash' (since they were all cash before)
    echo "Step 2: Setting account_type='cash' for existing records...\n";
    $result = db_query("UPDATE `expenses` 
                        SET `account_type` = 'cash' 
                        WHERE `account_id` IS NOT NULL 
                        AND (`account_type` IS NULL OR `account_type` = '')");
    $count = db_query_one("SELECT COUNT(*) as count FROM expenses WHERE account_type = 'cash'");
    echo "   ✓ Updated {$count['count']} expense records.\n\n";
    
    // Step 3: Remove old foreign key constraint if it exists
    echo "Step 3: Checking and updating foreign key constraints...\n";
    $fk_check = db_query("SELECT CONSTRAINT_NAME 
                          FROM information_schema.KEY_COLUMN_USAGE 
                          WHERE TABLE_SCHEMA = DATABASE() 
                          AND TABLE_NAME = 'expenses' 
                          AND CONSTRAINT_NAME = 'fk_expenses_account'");
    
    if (count($fk_check) > 0) {
        echo "   → Removing old foreign key constraint...\n";
        db_query("ALTER TABLE `expenses` DROP FOREIGN KEY `fk_expenses_account`");
        echo "   ✓ Old constraint removed.\n\n";
    } else {
        echo "   ✓ No old constraint to remove.\n\n";
    }
    
    // Step 4: Verify data integrity
    echo "Step 4: Verifying data integrity...\n";
    
    // Check for orphaned records
    $orphaned_cash = db_query_one("SELECT COUNT(*) as count 
                                   FROM expenses e 
                                   LEFT JOIN cash_accounts ca ON e.account_id = ca.id 
                                   WHERE e.account_type = 'cash' 
                                   AND e.account_id IS NOT NULL 
                                   AND ca.id IS NULL");
    
    $orphaned_bank = db_query_one("SELECT COUNT(*) as count 
                                   FROM expenses e 
                                   LEFT JOIN bank_accounts ba ON e.account_id = ba.id 
                                   WHERE e.account_type = 'bank' 
                                   AND e.account_id IS NOT NULL 
                                   AND ba.id IS NULL");
    
    if ($orphaned_cash['count'] > 0) {
        echo "   ⚠ WARNING: {$orphaned_cash['count']} expenses reference non-existent cash accounts.\n";
    }
    
    if ($orphaned_bank['count'] > 0) {
        echo "   ⚠ WARNING: {$orphaned_bank['count']} expenses reference non-existent bank accounts.\n";
    }
    
    if ($orphaned_cash['count'] == 0 && $orphaned_bank['count'] == 0) {
        echo "   ✓ All expense records reference valid accounts.\n\n";
    } else {
        echo "\n";
    }
    
    // Step 5: Display summary
    echo "Step 5: Migration Summary\n";
    echo "=========================\n";
    
    $stats = db_query_one("SELECT 
                           COUNT(*) as total_expenses,
                           SUM(CASE WHEN account_type = 'cash' THEN 1 ELSE 0 END) as cash_expenses,
                           SUM(CASE WHEN account_type = 'bank' THEN 1 ELSE 0 END) as bank_expenses,
                           SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_expenses,
                           SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_expenses
                           FROM expenses");
    
    echo "   Total Expenses: {$stats['total_expenses']}\n";
    echo "   - Cash Account Expenses: {$stats['cash_expenses']}\n";
    echo "   - Bank Account Expenses: {$stats['bank_expenses']}\n";
    echo "   - Approved: {$stats['approved_expenses']}\n";
    echo "   - Pending: {$stats['pending_expenses']}\n\n";
    
    db_commit();
    
    echo "=======================================================\n";
    echo "✓ Migration completed successfully!\n";
    echo "=======================================================\n\n";
    
    echo "Next Steps:\n";
    echo "1. Update expense forms to support bank account selection\n";
    echo "2. Update expense approval logic to handle bank accounts\n";
    echo "3. Test the new functionality thoroughly\n\n";
    
} catch (Exception $e) {
    db_rollback();
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Database rolled back to previous state.\n\n";
    
    echo "Rollback Instructions:\n";
    echo "If you need to manually rollback this migration, run:\n";
    echo "ALTER TABLE `expenses` DROP COLUMN `account_type`;\n\n";
    exit(1);
}
