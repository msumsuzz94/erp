<?php
/**
 * Application Constants
 * Define constant values used throughout the application
 */

// User status
define('USER_STATUS_ACTIVE', 'active');
define('USER_STATUS_INACTIVE', 'inactive');
define('USER_STATUS_SUSPENDED', 'suspended');

// Transaction status
define('STATUS_PENDING', 'pending');
define('STATUS_COMPLETED', 'completed');
define('STATUS_CANCELLED', 'cancelled');
define('STATUS_DRAFT', 'draft');

// Payment status
define('PAYMENT_PAID', 'paid');
define('PAYMENT_PARTIAL', 'partial');
define('PAYMENT_UNPAID', 'unpaid');

// Payment methods
define('PAYMENT_CASH', 'cash');
define('PAYMENT_BANK', 'bank');
define('PAYMENT_CREDIT', 'credit');
define('PAYMENT_CARD', 'card');
define('PAYMENT_MOBILE', 'mobile_money');

// Transaction types
define('TRANSACTION_DEBIT', 'debit');
define('TRANSACTION_CREDIT', 'credit');

// Stock adjustment types
define('ADJUSTMENT_ADD', 'add');
define('ADJUSTMENT_SUBTRACT', 'subtract');
define('ADJUSTMENT_OPENING', 'opening_stock');

// RMA Status
define('RMA_PENDING', 'pending');
define('RMA_SENT', 'sent');
define('RMA_SOLVED', 'solved');
define('RMA_DELIVERED', 'delivered');

// Quotation status
define('QUOTATION_PENDING', 'pending');
define('QUOTATION_ACCEPTED', 'accepted');
define('QUOTATION_REJECTED', 'rejected');
define('QUOTATION_CONVERTED', 'converted');

// Serial status
define('SERIAL_IN_STOCK', 'in_stock');
define('SERIAL_SOLD', 'sold');
define('SERIAL_RETURNED', 'returned');
define('SERIAL_DEFECTIVE', 'defective');

// Attendance status
define('ATTENDANCE_PRESENT', 'present');
define('ATTENDANCE_ABSENT', 'absent');
define('ATTENDANCE_LEAVE', 'leave');
define('ATTENDANCE_HALF_DAY', 'half_day');

// Expense approval
define('EXPENSE_PENDING', 'pending');
define('EXPENSE_APPROVED', 'approved');
define('EXPENSE_REJECTED', 'rejected');

// Account types
define('ACCOUNT_CASH', 'cash');
define('ACCOUNT_BANK', 'bank');

// Permissions
define('PERM_VIEW', 'view');
define('PERM_CREATE', 'create');
define('PERM_EDIT', 'edit');
define('PERM_DELETE', 'delete');

// Alert types
define('ALERT_SUCCESS', 'success');
define('ALERT_ERROR', 'danger');
define('ALERT_WARNING', 'warning');
define('ALERT_INFO', 'info');

// Report types
define('REPORT_PDF', 'pdf');
define('REPORT_EXCEL', 'excel');
define('REPORT_PRINT', 'print');
