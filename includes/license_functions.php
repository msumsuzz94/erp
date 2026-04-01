<?php
/**
 * Application Licensing Functions
 */

// Define the license server URL - should point to your remote license server
define('LICENSE_SERVER_URL', 'https://www.li.accuzest.com/api.php');

// Master key for owner - works offline without server connection
define('MASTER_LICENSE_KEY', 'MASTER-CITN-ERPP-2026');

/**
 * Check if the application has a valid license for the current domain
 * Priority: Master Key > Local DB > Remote Server
 */
function validate_license() {
    // Check if master key is stored in DB
    $license = db_select_one('app_license', ['id' => 1]);
    
    if ($license && $license['license_key'] === MASTER_LICENSE_KEY) {
        // Master key always valid - no server needed
        return ['status' => true, 'message' => 'license_active'];
    }
    
    // If DB has active status, allow
    if ($license && $license['status'] === 'active') {
        // Check expiry
        if (!empty($license['expiry_date']) && strtotime($license['expiry_date']) < time()) {
            db_update('app_license', ['status' => 'expired'], ['id' => 1]);
            return ['status' => false, 'message' => 'License expired. Please renew.'];
        }
        return ['status' => true, 'message' => 'license_active'];
    }
    
    // No valid license found
    return ['status' => false, 'message' => 'No active license. Please activate.'];
}

/**
 * Verify license with remote server
 */
function remote_verify_license($key) {
    // Master key - instant pass, no server call
    if ($key === MASTER_LICENSE_KEY) {
        return ['status' => true, 'message' => 'Master license validated.'];
    }

    $domain = $_SERVER['HTTP_HOST'];
    $url = LICENSE_SERVER_URL . "?key=" . urlencode($key) . "&domain=" . urlencode($domain);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ERP-License-Client/1.0');
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code == 200) {
        $result = json_decode($response, true);
        if (isset($result['status']) && $result['status'] === 'active') {
            db_update('app_license', [
                'status' => 'active',
                'last_verified_at' => date('Y-m-d H:i:s'),
                'domain' => $domain,
                'expiry_date' => $result['expiry'] ?? null
            ], ['id' => 1]);
            return ['status' => true, 'message' => 'License verification successful.'];
        } else {
            db_update('app_license', ['status' => $result['status'] ?? 'inactive'], ['id' => 1]);
            return ['status' => false, 'message' => $result['message'] ?? 'License revoked or invalid.'];
        }
    }

    // If server is down and we have a recently verified active status, allow it
    if ($http_code == 0 || $http_code >= 500 || $http_code == 404) {
        $license = db_select_one('app_license', ['id' => 1]);
        if ($license && $license['status'] === 'active') {
            return ['status' => true, 'message' => 'server_offline_cached'];
        }
    }

    return ['status' => false, 'message' => 'License server unreachable. ' . ($curl_error ? "Error: $curl_error" : 'Please check your internet connection.')];
}

/**
 * Activate a new license
 */
function activate_license($key) {
    $domain = $_SERVER['HTTP_HOST'];
    
    // Check if record exists
    $license = db_select_one('app_license', ['id' => 1]);
    
    $is_master = ($key === MASTER_LICENSE_KEY);
    
    $data = [
        'license_key' => $key,
        'status' => $is_master ? 'active' : 'inactive',
        'domain' => $domain,
        'activated_at' => date('Y-m-d H:i:s'),
        'last_verified_at' => date('Y-m-d H:i:s'),
        'expiry_date' => $is_master ? '2099-12-31' : null
    ];
    
    if (!$license) {
        $data['created_at'] = date('Y-m-d H:i:s');
        db_insert('app_license', $data);
    } else {
        db_update('app_license', $data, ['id' => 1]);
    }

    // Master key - no need to call server
    if ($is_master) {
        return ['status' => true, 'message' => 'Master license activated successfully.'];
    }

    // Perform remote verification to activate
    return remote_verify_license($key);
}

/**
 * Renew license from server
 */
function renew_license() {
    $license = db_select_one('app_license', ['id' => 1]);
    
    if (!$license || empty($license['license_key'])) {
        return ['status' => false, 'message' => 'No license key found. Please activate first.'];
    }
    
    $key = $license['license_key'];
    
    // Master key - just extend expiry
    if ($key === MASTER_LICENSE_KEY) {
        db_update('app_license', [
            'status' => 'active',
            'expiry_date' => '2099-12-31',
            'last_verified_at' => date('Y-m-d H:i:s')
        ], ['id' => 1]);
        return ['status' => true, 'message' => 'Master license renewed. Lifetime validity.'];
    }
    
    // Try server renewal
    $domain = $_SERVER['HTTP_HOST'];
    $url = LICENSE_SERVER_URL . "?action=renew&key=" . urlencode($key) . "&domain=" . urlencode($domain);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ERP-License-Client/1.0');
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code == 200) {
        $result = json_decode($response, true);
        if (isset($result['status']) && $result['status'] === 'active') {
            db_update('app_license', [
                'status' => 'active',
                'expiry_date' => $result['expiry'] ?? null,
                'last_verified_at' => date('Y-m-d H:i:s')
            ], ['id' => 1]);
            return ['status' => true, 'message' => 'License renewed successfully. New expiry: ' . ($result['expiry'] ?? 'Lifetime')];
        }
        return ['status' => false, 'message' => $result['message'] ?? 'Renewal failed.'];
    }

    return ['status' => false, 'message' => 'Could not reach license server. ' . ($curl_error ?: 'Check connection.')];
}
?>
