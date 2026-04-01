<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Check if User can Import/Export CSV
if (!is_admin() && !has_role(get_current_user_id(), 'Manager') && !has_role(get_current_user_id(), 'Admin')) {
    redirect_with_message('customers-list.php', 'Permission denied', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        redirect_with_message('customers-list.php', 'Invalid CSRF token', 'error');
    }

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];

        if (($handle = fopen($file, "r")) !== FALSE) {
            // Get headers
            $headers = fgetcsv($handle, 1000, ",");

            $success_count = 0;
            $error_count = 0;

            db_begin_transaction();

            try {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) < 1) continue;

                    $name = clean_input($data[0] ?? '');
                    if (empty($name)) continue;

                    $phone = clean_input($data[1] ?? '');
                    $email = clean_input($data[2] ?? '');
                    $address = clean_input($data[3] ?? '');
                    $opening_balance = (float)($data[4] ?? 0);

                    // Check if customer exists by phone (if provided) else by email
                    $existing = null;
                    if (!empty($phone)) {
                        $existing = db_select_one('customers', ['phone' => $phone]);
                    } elseif (!empty($email)) {
                        $existing = db_select_one('customers', ['email' => $email]);
                    }

                    if ($existing) {
                        // Update existing (don't override balance or ledger if exists)
                        db_update('customers', [
                            'name' => $name,
                            'email' => $email,
                            'address' => $address
                        ], ['id' => $existing['id']]);
                        $success_count++;
                    } else {
                        // Insert new
                        $customer_data = [
                            'name' => $name,
                            'phone' => $phone,
                            'email' => $email,
                            'address' => $address,
                            'customer_group' => 'Buyer',
                            'credit_limit' => 0,
                            'opening_balance' => $opening_balance,
                            'current_balance' => $opening_balance,
                            'status' => 'active',
                            'created_at' => date('Y-m-d H:i:s')
                        ];

                        $customer_id = db_insert('customers', $customer_data);

                        if ($customer_id) {
                            // Initial Ledger Entry
                            if ($opening_balance != 0) {
                                db_insert('customer_ledger', [
                                    'customer_id' => $customer_id,
                                    'transaction_type' => 'opening_balance',
                                    'debit' => $opening_balance > 0 ? $opening_balance : 0,
                                    'credit' => $opening_balance < 0 ? abs($opening_balance) : 0,
                                    'balance' => $opening_balance,
                                    'description' => 'Opening Balance (Import)',
                                    'date' => date('Y-m-d'),
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                            }
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }

                db_commit();
                log_activity(get_current_user_id(), 'import_customers', "Imported $success_count customers via CSV");
                redirect_with_message('customers-list.php', "Successfully imported $success_count customers. ($error_count errors)", 'success');
            } catch (Exception $e) {
                db_rollback();
                redirect_with_message('customers-list.php', 'Import failed: ' . $e->getMessage(), 'error');
            }

            fclose($handle);
        } else {
            redirect_with_message('customers-list.php', 'Failed to read CSV file', 'error');
        }
    } else {
        redirect_with_message('customers-list.php', 'No file uploaded or upload error', 'error');
    }
} else {
    redirect_with_message('customers-list.php', 'Invalid request', 'error');
}
