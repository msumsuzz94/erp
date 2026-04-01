<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Check if User can Import/Export CSV
$user_id = get_current_user_id();
if (!is_admin() && !has_role($user_id, 'Manager') && !has_role($user_id, 'Admin')) {
    redirect_with_message('lead-list.php', 'Permission denied', 'error');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        redirect_with_message('lead-list.php', 'Invalid CSRF token', 'error');
    }

    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];

        if (($handle = fopen($file, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ",");

            $success_count = 0;
            $error_count = 0;

            try {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) < 3) continue;

                    $org_name = clean_input($data[0] ?? '');
                    if (empty($org_name)) continue;

                    $contact = clean_input($data[1] ?? '');
                    $mobile = clean_input($data[2] ?? '');
                    $email = clean_input($data[3] ?? '');
                    $address = clean_input($data[4] ?? '');
                    $category_name = clean_input($data[5] ?? '');
                    $status = strtolower(clean_input($data[6] ?? 'new'));
                    $remarks = clean_input($data[7] ?? '');
                    $gps_lat = clean_input($data[8] ?? '');
                    $gps_lng = clean_input($data[9] ?? '');

                    if (!in_array($status, ['new', 'contacted', 'converted', 'closed'])) {
                        $status = 'new';
                    }

                    $category_id = null;
                    if (!empty($category_name)) {
                        $cat_record = db_select_one('lead_categories', ['name' => $category_name]);
                        if ($cat_record) {
                            $category_id = $cat_record['id'];
                        } else {
                            $category_id = db_insert('lead_categories', [
                                'name' => $category_name,
                                'status' => 'active'
                            ]);
                        }
                    }

                    $existing = db_select_one('leads', ['mobile' => $mobile]);

                    if ($existing) {
                        db_update('leads', [
                            'organization_name' => $org_name,
                            'contact_person' => $contact,
                            'email' => $email,
                            'address' => $address,
                            'status' => $status,
                            'remarks' => $remarks,
                            'category_id' => $category_id,
                            'gps_latitude' => $gps_lat,
                            'gps_longitude' => $gps_lng
                        ], ['id' => $existing['id']]);
                        $success_count++;
                    } else {
                        $lead_data = [
                            'organization_name' => $org_name,
                            'contact_person' => $contact,
                            'mobile' => $mobile,
                            'email' => $email,
                            'address' => $address,
                            'status' => $status,
                            'remarks' => $remarks,
                            'category_id' => $category_id,
                            'gps_latitude' => $gps_lat,
                            'gps_longitude' => $gps_lng,
                            'collected_by' => $user_id,
                            'created_at' => date('Y-m-d H:i:s')
                        ];

                        $lead_id = db_insert('leads', $lead_data);

                        if ($lead_id) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }

                log_activity($user_id, 'import_leads', "Imported $success_count leads via CSV");
                redirect_with_message('lead-list.php', "Successfully imported $success_count leads. ($error_count errors)", 'success');
            } catch (Exception $e) {
                redirect_with_message('lead-list.php', 'Import failed: ' . $e->getMessage(), 'error');
            }

            fclose($handle);
        } else {
            redirect_with_message('lead-list.php', 'Failed to read CSV file', 'error');
        }
    } else {
        redirect_with_message('lead-list.php', 'No file uploaded or upload error', 'error');
    }
} else {
    redirect_with_message('lead-list.php', 'Invalid request', 'error');
}
