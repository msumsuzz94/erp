<?php
/**
 * Import Products from CSV
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

if (is_post() && isset($_POST['import_csv'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        redirect_with_message('products-list.php', 'Invalid CSRF token', 'error');
    }

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        redirect_with_message('products-list.php', 'Please select a valid CSV file', 'error');
    }

    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    
    // Skip header row
    $headers = fgetcsv($handle);
    
    $imported = 0;
    $updated = 0;
    $errors = 0;

    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) < 2) continue; // Skip empty rows

        $code = clean_input($data[0]);
        $name = clean_input($data[1]);
        $category_name = clean_input($data[2]);
        $brand_name = clean_input($data[3]);
        $unit_name = clean_input($data[4]);
        $purchase_price = (float)$data[5];
        $selling_price = (float)$data[6];
        $tax_rate = (float)$data[7];
        $stock = (int)$data[8];
        $reorder_level = (int)$data[9];
        $status = strtolower(clean_input($data[10] ?? 'active'));
        $description = clean_input($data[11] ?? '');

        if (empty($code) || empty($name)) {
            $errors++;
            continue;
        }

        // 1. Resolve Category
        $category_id = null;
        if (!empty($category_name)) {
            $cat = db_select_one('categories', ['name' => $category_name]);
            if ($cat) {
                $category_id = $cat['id'];
            } else {
                $category_id = db_insert('categories', ['name' => $category_name]);
            }
        }

        // 2. Resolve Brand
        $brand_id = null;
        if (!empty($brand_name)) {
            $brand = db_select_one('brands', ['name' => $brand_name]);
            if ($brand) {
                $brand_id = $brand['id'];
            } else {
                $brand_id = db_insert('brands', ['name' => $brand_name]);
            }
        }

        // 3. Resolve Unit
        $unit_id = null;
        if (!empty($unit_name)) {
            $unit = db_select_one('units', ['name' => $unit_name]);
            if (!$unit) {
                $unit = db_select_one('units', ['short_name' => $unit_name]);
            }
            if ($unit) {
                $unit_id = $unit['id'];
            } else {
                $unit_id = db_insert('units', ['name' => $unit_name, 'short_name' => $unit_name]);
            }
        }

        $product_data = [
            'name' => $name,
            'code' => $code,
            'brand_id' => $brand_id,
            'category_id' => $category_id,
            'unit_id' => $unit_id,
            'purchase_price' => $purchase_price,
            'selling_price' => $selling_price,
            'tax_rate' => $tax_rate,
            'stock_quantity' => $stock,
            'reorder_level' => $reorder_level,
            'description' => $description,
            'status' => in_array($status, ['active', 'inactive']) ? $status : 'active'
        ];

        // Check if product exists
        $existing = db_select_one('products', ['code' => $code]);
        if ($existing) {
            if (db_update('products', $product_data, ['id' => $existing['id']])) {
                $updated++;
            } else {
                $errors++;
            }
        } else {
            if (db_insert('products', $product_data)) {
                $imported++;
            } else {
                $errors++;
            }
        }
    }

    fclose($handle);

    $msg = "Import Finished: $imported added, $updated updated, $errors errors.";
    log_activity(get_current_user_id(), 'import_products', $msg);
    redirect_with_message('products-list.php', $msg, $errors > 0 ? 'warning' : 'success');
} else {
    redirect_with_message('products-list.php', 'Invalid request', 'error');
}
