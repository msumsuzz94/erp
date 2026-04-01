<?php
/**
 * Cloud Storage Functions
 * Upload backups to Google Drive, OneDrive, Dropbox, AWS S3
 */

/**
 * Upload backup to Google Drive
 * 
 * @param string $file_path Local file path
 * @param string $access_token Google OAuth access token
 * @param string $folder_id Google Drive folder ID (optional)
 * @return array Result
 */
function upload_to_google_drive($file_path, $access_token, $folder_id = null) {
    if (!file_exists($file_path)) {
        return ['success' => false, 'error' => 'File not found'];
    }
    
    $file_name = basename($file_path);
    $file_size = filesize($file_path);
    $mime_type = 'application/octet-stream';
    
    // Prepare metadata
    $metadata = [
        'name' => $file_name,
        'mimeType' => $mime_type
    ];
    
    if ($folder_id) {
        $metadata['parents'] = [$folder_id];
    }
    
    // Create boundary for multipart upload
    $boundary = uniqid();
    
    // Build multipart body
    $body = "--$boundary\r\n";
    $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
    $body .= json_encode($metadata) . "\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: $mime_type\r\n\r\n";
    $body .= file_get_contents($file_path) . "\r\n";
    $body .= "--$boundary--";
    
    // Upload to Google Drive
    $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
        "Content-Type: multipart/related; boundary=$boundary",
        "Content-Length: " . strlen($body)
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        return [
            'success' => true,
            'file_id' => $result['id'] ?? null,
            'file_name' => $file_name
        ];
    } else {
        return [
            'success' => false,
            'error' => "Upload failed with HTTP code $http_code",
            'response' => $response
        ];
    }
}

/**
 * Upload backup to OneDrive
 * 
 * @param string $file_path Local file path
 * @param string $access_token OneDrive OAuth access token
 * @param string $folder_path OneDrive folder path (e.g., '/Backups')
 * @return array Result
 */
function upload_to_onedrive($file_path, $access_token, $folder_path = '/Backups') {
    if (!file_exists($file_path)) {
        return ['success' => false, 'error' => 'File not found'];
    }
    
    $file_name = basename($file_path);
    $file_content = file_get_contents($file_path);
    
    // Upload URL
    $upload_url = "https://graph.microsoft.com/v1.0/me/drive/root:$folder_path/$file_name:/content";
    
    $ch = curl_init($upload_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
        "Content-Type: application/octet-stream"
    ]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $file_content);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code >= 200 && $http_code < 300) {
        $result = json_decode($response, true);
        return [
            'success' => true,
            'file_id' => $result['id'] ?? null,
            'file_name' => $file_name
        ];
    } else {
        return [
            'success' => false,
            'error' => "Upload failed with HTTP code $http_code",
            'response' => $response
        ];
    }
}

/**
 * Upload backup to Dropbox
 * 
 * @param string $file_path Local file path
 * @param string $access_token Dropbox access token
 * @param string $folder_path Dropbox folder path (e.g., '/Backups')
 * @return array Result
 */
function upload_to_dropbox($file_path, $access_token, $folder_path = '/Backups') {
    if (!file_exists($file_path)) {
        return ['success' => false, 'error' => 'File not found'];
    }
    
    $file_name = basename($file_path);
    $file_content = file_get_contents($file_path);
    $dropbox_path = rtrim($folder_path, '/') . '/' . $file_name;
    
    $ch = curl_init('https://content.dropboxapi.com/2/files/upload');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token",
        "Content-Type: application/octet-stream",
        "Dropbox-API-Arg: " . json_encode([
            'path' => $dropbox_path,
            'mode' => 'add',
            'autorename' => true,
            'mute' => false
        ])
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $file_content);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        return [
            'success' => true,
            'file_id' => $result['id'] ?? null,
            'file_name' => $file_name
        ];
    } else {
        return [
            'success' => false,
            'error' => "Upload failed with HTTP code $http_code",
            'response' => $response
        ];
    }
}

/**
 * Upload backup to AWS S3
 * 
 * @param string $file_path Local file path  
 * @param string $bucket S3 bucket name
 * @param string $access_key AWS access key
 * @param string $secret_key AWS secret key
 * @param string $region AWS region
 * @return array Result
 */
function upload_to_aws_s3($file_path, $bucket, $access_key, $secret_key, $region = 'us-east-1') {
    if (!file_exists($file_path)) {
        return ['success' => false, 'error' => 'File not found'];
    }
    
    // This is a simplified implementation
    // For production, use AWS SDK for PHP
    $file_name = basename($file_path);
    $file_content = file_get_contents($file_path);
    
    // Note: Full S3 implementation requires AWS SDK
    // This is a basic example - recommend using composer package: aws/aws-sdk-php
    
    return [
        'success' => false,
        'error' => 'AWS S3 requires AWS SDK. Please install: composer require aws/aws-sdk-php'
    ];
}

/**
 * Upload backup to configured cloud destinations
 * 
 * @param string $file_path Local backup file path
 * @return array Results for each destination
 */
function upload_backup_to_cloud($file_path) {
    $settings = db_select_one('backup_settings', ['id' => 1]);
    $results = [];
    
    // Google Drive
    if ($settings['google_drive_enabled'] && !empty($settings['google_drive_credentials'])) {
        $creds = json_decode($settings['google_drive_credentials'], true);
        if ($creds && isset($creds['access_token'])) {
            $result = upload_to_google_drive(
                $file_path,
                $creds['access_token'],
                $settings['google_drive_folder_id']
            );
            $results['google_drive'] = $result;
        }
    }
    
    // OneDrive
    if ($settings['onedrive_enabled'] && !empty($settings['onedrive_access_token'])) {
        $result = upload_to_onedrive(
            $file_path,
            $settings['onedrive_access_token'],
            '/Backups'
        );
        $results['onedrive'] = $result;
    }
    
    // Dropbox
    if ($settings['dropbox_enabled'] && !empty($settings['dropbox_access_token'])) {
        $result = upload_to_dropbox(
            $file_path,
            $settings['dropbox_access_token'],
            '/Backups'
        );
        $results['dropbox'] = $result;
    }
    
    // AWS S3
    if ($settings['aws_s3_enabled'] && !empty($settings['aws_s3_bucket'])) {
        $result = upload_to_aws_s3(
            $file_path,
            $settings['aws_s3_bucket'],
            $settings['aws_s3_key'],
            $settings['aws_s3_secret'],
            $settings['aws_s3_region']
        );
        $results['aws_s3'] = $result;
    }
    
    return $results;
}

/**
 * Refresh Google Drive access token
 * 
 * @param string $refresh_token Refresh token
 * @param string $client_id OAuth client ID
 * @param string $client_secret OAuth client secret
 * @return array New credentials or error
 */
function refresh_google_drive_token($refresh_token, $client_id, $client_secret) {
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'refresh_token' => $refresh_token,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'refresh_token'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        return json_decode($response, true);
    }
    
    return ['error' => 'Failed to refresh token'];
}

/**
 * Refresh OneDrive access token
 * 
 * @param string $refresh_token Refresh token
 * @param string $client_id OAuth client ID
 * @param string $client_secret OAuth client secret
 * @return array New credentials or error
 */
function refresh_onedrive_token($refresh_token, $client_id, $client_secret) {
    $ch = curl_init('https://login.microsoftonline.com/common/oauth2/v2.0/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'refresh_token' => $refresh_token,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'refresh_token',
        'scope' => 'files.readwrite offline_access'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        return json_decode($response, true);
    }
    
    return ['error' => 'Failed to refresh token'];
}
?>
