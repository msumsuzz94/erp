<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Check if User can Import/Export CSV
if (!is_admin() && !has_role(get_current_user_id(), 'Manager') && !has_role(get_current_user_id(), 'Admin')) {
    redirect_with_message('units-list.php', 'Permission denied', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        redirect_with_message('units-list.php', 'Invalid CSRF token', 'error');
    }

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];

        if (($handle = fopen($file, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ",");

            $success_count = 0;
            $error_count = 0;

            try {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) < 1) continue;

                    $name = clean_input($data[0] ?? '');
                    if (empty($name)) continue;

                    $short_name = clean_input($data[1] ?? '');

                    $existing = db_select_one('units', ['name' => $name]);

                    if ($existing) {
                        db_update('units', [
                            'short_name' => $short_name
                        ], ['id' => $existing['id']]);
                        $success_count++;
                    } else {
                        $unit_data = [
                            'name' => $name,
                            'short_name' => $short_name,
                            'created_at' => date('Y-m-d H:i:s')
                        ];

                        $unit_id = db_insert('units', $unit_data);

                        if ($unit_id) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }

                log_activity(get_current_user_id(), 'import_units', "Imported $success_count units via CSV");
                redirect_with_message('units-list.php', "Successfully imported $success_count units. ($error_count errors)", 'success');
            } catch (Exception $e) {
                redirect_with_message('units-list.php', 'Import failed: ' . $e->getMessage(), 'error');
            }

            fclose($handle);
        } else {
            redirect_with_message('units-list.php', 'Failed to read CSV file', 'error');
        }
    } else {
        redirect_with_message('units-list.php', 'No file uploaded or upload error', 'error');
    }
} else {
    redirect_with_message('units-list.php', 'Invalid request', 'error');
}
