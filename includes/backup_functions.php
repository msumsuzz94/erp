<?php

/**
 * Backup Functions
 * Core backup and restore functionality
 */

// Tables that should NEVER be included in backup/restore
// to protect site-specific data like license, settings, users and menu structure
define('PROTECTED_TABLES', [
    'app_license',
    'menu_items',
    'role_permissions',
    'business_settings',
    'backup_settings',
    'attendance_settings',
    'sms_settings',
    'channel_settings', // Protect multi-channel API credentials from being overwritten
    'users' // Protect user accounts and passwords from being overwritten
]);

/**
 * Create a complete database backup
 * 
 * @param array $options Backup options
 * @return array Result with success status and file info
 */
function create_database_backup($options = [])
{
    global $conn;
    /** @var PDO $conn */

    $defaults = [
        'tables' => [], // Empty array means all tables
        'compress' => true,
        'type' => 'manual',
        'destinations' => ['local']
    ];

    $options = array_merge($defaults, $options);

    try {
        // Get list of tables to backup
        $tables = $options['tables'];
        if (empty($tables)) {
            $tables = get_all_tables();
        }

        // Generate backup filename
        $timestamp = date('Ymd_His');
        $backup_name = "backup_{$timestamp}_{$options['type']}.sql";
        $backup_path = get_backup_directory() . $backup_name;

        // Create backup directory if not exists
        if (!file_exists(dirname($backup_path))) {
            mkdir(dirname($backup_path), 0755, true);
        }

        // Generate SQL content directly to file to prevent memory exhaustion
        generate_sql_backup($tables, $backup_path);

        $file_size = filesize($backup_path);

        // Compress if requested
        if ($options['compress']) {
            $zip_path = compress_backup($backup_path);
            if ($zip_path) {
                unlink($backup_path); // Delete original
                $backup_path = $zip_path;
                $backup_name = basename($zip_path);
                $file_size = filesize($zip_path);
            }
        }

        // Count total records
        $total_records = 0;
        foreach ($tables as $table) {
            $total_records += db_count($table);
        }

        // Save to backup history
        db_insert('backup_history', [
            'backup_name' => $backup_name,
            'backup_type' => $options['type'],
            'backup_size' => $file_size,
            'file_path' => $backup_path,
            'destinations' => json_encode($options['destinations']),
            'status' => 'success',
            'tables_backed_up' => count($tables),
            'total_records' => $total_records,
            'created_by' => get_current_user_id()
        ]);

        // Update last backup time
        db_update(
            'backup_settings',
            ['last_backup_at' => date('Y-m-d H:i:s')],
            ['id' => 1]
        );

        log_activity(get_current_user_id(), 'create_backup', "Created database backup: $backup_name");

        return [
            'success' => true,
            'backup_name' => $backup_name,
            'file_path' => $backup_path,
            'file_size' => $file_size,
            'tables' => count($tables),
            'records' => $total_records
        ];
    } catch (Exception $e) {
        // Log error
        db_insert('backup_history', [
            'backup_name' => $backup_name ?? 'unknown',
            'backup_type' => $options['type'],
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'created_by' => get_current_user_id()
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get all database tables
 * 
 * @return array List of table names
 */
function get_all_tables()
{
    global $conn;
    /** @var PDO $conn */

    $stmt = $conn->query("SHOW TABLES");
    $tables = [];

    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        // Skip protected tables (e.g., app_license)
        if (in_array($row[0], PROTECTED_TABLES)) {
            continue;
        }
        $tables[] = $row[0];
    }

    return $tables;
}

/**
 * Generate SQL backup content and write to file
 * 
 * @param array $tables Tables to backup
 * @param string $backup_path Path to write the SQL file to
 */
function generate_sql_backup($tables, $backup_path)
{
    global $conn;
    /** @var PDO $conn */

    $handle = fopen($backup_path, 'w');
    if (!$handle) {
        throw new Exception("Unable to open backup file for writing: $backup_path");
    }

    $header = "-- Database Backup\n";
    $header .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $header .= "-- Database: " . DB_NAME . "\n";
    $header .= "-- Tables: " . count($tables) . "\n\n";

    $header .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $header .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $header .= "SET time_zone = \"+00:00\";\n";
    $header .= "SET NAMES 'utf8mb4';\n";
    $header .= "SET CHARACTER SET utf8mb4;\n\n";
    
    fwrite($handle, $header);

    foreach ($tables as $table) {
        $sql = "\n-- Table: $table\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";

        // Get CREATE TABLE statement
        $stmt = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $sql .= $row[1] . ";\n\n";
        
        fwrite($handle, $sql);

        // Get table data
        $stmt = $conn->query("SELECT * FROM `$table`");
        
        // Use streaming instead of fetchAll to prevent memory exhaustion on cPanel
        $is_first = true;
        $cols_str = "";

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Write column headers only for the first row
            if ($is_first) {
                $columns = array_keys($row);
                $cols_str = "`" . implode("`, `", $columns) . "`";
                $is_first = false;
            }

            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = "NULL";
                } else {
                    $values[] = $conn->quote((string)$value);
                }
            }
            fwrite($handle, "INSERT INTO `$table` ($cols_str) VALUES (" . implode(', ', $values) . ");\n");
        }
        fwrite($handle, "\n");
    }

    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);
}

/**
 * Compress backup file to ZIP (Full Backup: Database + Uploads)
 * 
 * @param string $file_path Path to SQL file
 * @return string|false Path to ZIP file or false on failure
 */
function compress_backup($file_path)
{
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $zip = new ZipArchive();
    $zip_path = str_replace('.sql', '.zip', $file_path);

    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        // 1. Add the SQL database file
        $zip->addFile($file_path, basename($file_path));

        // 2. Add the uploads/ directory recursively (Full Backup)
        $uploads_dir = BASE_PATH . '/uploads';
        if (is_dir($uploads_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if (!$file->isDir()) {
                    $real_path = $file->getRealPath();
                    $relative_path = 'uploads/' . substr($real_path, strlen(realpath($uploads_dir)) + 1);
                    // Normalize path separators for ZIP
                    $relative_path = str_replace('\\', '/', $relative_path);
                    $zip->addFile($real_path, $relative_path);
                }
            }
        }

        $zip->close();
        return $zip_path;
    }

    return false;
}

/**
 * Get backup directory path
 * 
 * @return string Backup directory path
 */
function get_backup_directory()
{
    $settings = db_select_one('backup_settings', ['id' => 1]);
    $backup_path = $settings['local_backup_path'] ?? '/backups/database/';

    // Make absolute path
    if ($backup_path[0] === '/') {
        return BASE_PATH . $backup_path;
    }

    return $backup_path;
}

/**
 * Import database backup with smart duplicate detection
 * 
 * @param string $file_path Path to backup file
 * @param string $mode Import mode: skip_duplicates, update_existing, merge_smart
 * @return array Import result with statistics
 */
function import_database_backup($file_path, $mode = 'skip_duplicates')
{
    global $conn;
    /** @var PDO $conn */

    $start_time = time();
    $stats = [
        'total_records' => 0,
        'imported' => 0,
        'skipped' => 0,
        'updated' => 0,
        'failed' => 0,
        'tables' => []
    ];

    try {
        // Decompress if ZIP
        if (pathinfo($file_path, PATHINFO_EXTENSION) === 'zip') {
            if (!class_exists('ZipArchive')) {
                throw new Exception("Cannot extract .zip backup because ZipArchive PHP extension is not installed on this server. Please use .sql files instead.");
            }
            $file_path = extract_backup_zip($file_path);
        }

        // Read SQL file
        $sql_content = file_get_contents($file_path);

        if (empty($sql_content)) {
            throw new Exception("Backup file is empty");
        }

        // Parse and execute SQL with smart import
        $result = execute_sql_with_duplicate_check($sql_content, $mode);
        $stats = array_merge($stats, $result);

        // Save import history
        $duration = time() - $start_time;
        db_insert('import_history', [
            'import_file' => basename($file_path),
            'file_size' => filesize($file_path),
            'total_records' => $stats['total_records'],
            'imported_records' => $stats['imported'],
            'skipped_records' => $stats['skipped'],
            'updated_records' => $stats['updated'],
            'failed_records' => $stats['failed'],
            'status' => $stats['failed'] > 0 ? 'partial' : 'success',
            'import_mode' => $mode,
            'import_details' => json_encode($stats['tables']),
            'imported_by' => get_current_user_id(),
            'duration_seconds' => $duration
        ]);

        log_activity(
            get_current_user_id(),
            'import_backup',
            "Imported backup: " . basename($file_path) . " ({$stats['imported']} imported, {$stats['skipped']} skipped)"
        );

        return [
            'success' => true,
            'stats' => $stats
        ];
    } catch (Exception $e) {
        db_insert('import_history', [
            'import_file' => basename($file_path ?? 'unknown'),
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'imported_by' => get_current_user_id()
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Execute SQL with duplicate checking (Schema-Safe Restore)
 * 
 * This function preserves existing table schemas. If a table already exists,
 * DROP TABLE and CREATE TABLE statements are skipped so that new columns
 * added by site updates are never lost. Only data (INSERT) is processed.
 * 
 * @param string $sql_content SQL content
 * @param string $mode Import mode
 * @return array Statistics
 */
function execute_sql_with_duplicate_check($sql_content, $mode)
{
    global $conn;
    /** @var PDO $conn */

    $stats = [
        'total_records' => 0,
        'imported' => 0,
        'skipped' => 0,
        'updated' => 0,
        'failed' => 0,
        'tables' => []
    ];

    // Get list of tables that CURRENTLY exist in the database
    $existing_tables = [];
    try {
        $stmt = $conn->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $existing_tables[] = $row[0];
        }
    } catch (Exception $e) {
        // If we can't get tables, proceed with caution
    }

    // Split SQL into statements
    $statements = parse_sql_statements($sql_content);

    $current_table = null;
    $insert_pattern = '/INSERT INTO `([^`]+)` VALUES \((.+)\);/i';
    $drop_pattern = '/DROP TABLE IF EXISTS `([^`]+)`/i';
    $create_pattern = '/CREATE TABLE.*?`([^`]+)`/i';

    // Disable FK checks during import
    try { $conn->exec("SET FOREIGN_KEY_CHECKS=0"); } catch (Exception $e) {}

    foreach ($statements as $statement) {
        $statement = trim($statement);

        if (empty($statement)) continue;

        // === PROTECT TABLES ===
        // 1. PROTECTED_TABLES: Never touch (license, menu, users, settings)
        if (preg_match($drop_pattern, $statement, $dm)) {
            if (in_array($dm[1], PROTECTED_TABLES)) {
                continue; // Never drop protected tables
            }
            // 2. EXISTING TABLES: Skip DROP to preserve schema (new columns from updates)
            if (in_array($dm[1], $existing_tables)) {
                continue; // Don't drop — preserve new columns/schema
            }
        }
        if (preg_match($create_pattern, $statement, $cm)) {
            if (in_array($cm[1], PROTECTED_TABLES)) {
                continue; // Never recreate protected tables
            }
            // Skip CREATE for existing tables — schema already has new columns
            if (in_array($cm[1], $existing_tables)) {
                continue; // Don't recreate — preserve new columns/schema
            }
        }

        // Check if it's an INSERT statement
        if (preg_match($insert_pattern, $statement, $matches)) {
            $table = $matches[1];
            $values_str = $matches[2];

            // Skip INSERT for protected tables
            if (in_array($table, PROTECTED_TABLES)) {
                $stats['skipped']++;
                continue;
            }

            if (!isset($stats['tables'][$table])) {
                $stats['tables'][$table] = [
                    'total' => 0,
                    'imported' => 0,
                    'skipped' => 0,
                    'updated' => 0
                ];
            }

            $stats['total_records']++;
            $stats['tables'][$table]['total']++;

            // Parse values
            $values = parse_insert_values($values_str);

            // Check for duplicate
            $exists = check_record_exists($table, $values);

            if ($exists && $mode === 'skip_duplicates') {
                $stats['skipped']++;
                $stats['tables'][$table]['skipped']++;
                continue;
            } elseif ($exists && $mode === 'update_existing') {
                try {
                    // Use REPLACE INTO for update mode
                    $replace_stmt = str_replace('INSERT INTO', 'REPLACE INTO', $statement);
                    $conn->exec($replace_stmt);
                    $stats['updated']++;
                    $stats['tables'][$table]['updated']++;
                } catch (Exception $e) {
                    $stats['failed']++;
                }
                continue;
            }

            // Execute INSERT
            try {
                $conn->exec($statement);
                $stats['imported']++;
                $stats['tables'][$table]['imported']++;
            } catch (Exception $e) {
                // Likely duplicate - count as skipped
                $stats['skipped']++;
                $stats['tables'][$table]['skipped']++;
            }
        } else {
            // Execute non-INSERT statements (SET, CREATE TABLE for NEW tables, etc.)
            try {
                $conn->exec($statement);
            } catch (Exception $e) {
                // Ignore errors (table might exist, etc.)
            }
        }
    }

    // Re-enable FK checks
    try { $conn->exec("SET FOREIGN_KEY_CHECKS=1"); } catch (Exception $e) {}

    return $stats;
}

/**
 * Parse SQL statements from content
 * 
 * @param string $sql SQL content
 * @return array Array of statements
 */
function parse_sql_statements($sql)
{
    // Remove comments
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $sql = preg_replace('/^\/\*.*\*\/;?$/m', '', $sql);

    // Split by semicolons, but try to avoid splitting inside quotes
    // This is still a basic approach but slightly better
    $statements = preg_split('/;(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', $sql);

    return array_filter($statements, function ($s) {
        return !empty(trim($s));
    });
}

/**
 * Parse INSERT values string
 * 
 * @param string $values_str Values string
 * @return array Parsed values
 */
function parse_insert_values($values_str)
{
    // This is a simplified parser
    // In production, you'd want a more robust SQL parser
    $values = [];
    $parts = explode(',', $values_str);

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === 'NULL') {
            $values[] = null;
        } else {
            $values[] = trim($part, "'\"");
        }
    }

    return $values;
}

/**
 * Check if record exists in table
 * 
 * @param string $table Table name
 * @param array $values Record values
 * @return bool True if exists
 */
function check_record_exists($table, $values)
{
    // Get primary key or first value as identifier
    // In real implementation, we'd get actual primary key column
    if (isset($values[0]) && !empty($values[0])) {
        return db_exists($table, ['id' => $values[0]]);
    }

    return false;
}

/**
 * Extract ZIP backup file (Full Backup: Database + Uploads)
 * Restores uploads/ folder to project root if found, then returns the SQL path.
 * 
 * @param string $zip_path Path to ZIP file
 * @return string Path to extracted SQL file
 */
function extract_backup_zip($zip_path)
{
    if (!class_exists('ZipArchive')) {
        throw new Exception("ZipArchive PHP extension is not installed.");
    }

    $zip = new ZipArchive();

    if ($zip->open($zip_path) === TRUE) {
        $extract_to = dirname($zip_path) . '/extracted_' . time();
        if (!file_exists($extract_to)) {
            mkdir($extract_to, 0755, true);
        }

        $zip->extractTo($extract_to);

        // --- Restore uploads/ folder if it exists inside the ZIP ---
        $extracted_uploads = $extract_to . '/uploads';
        if (is_dir($extracted_uploads)) {
            $target_uploads = BASE_PATH . '/uploads';
            // Recursively copy extracted uploads to the project uploads folder
            recursive_copy($extracted_uploads, $target_uploads);
        }

        // --- Find the SQL file ---
        $sql_file = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (pathinfo($name, PATHINFO_EXTENSION) === 'sql') {
                $sql_file = $name;
                break;
            }
        }
        $zip->close();

        if (!$sql_file) {
            throw new Exception("No .sql file found inside the backup ZIP.");
        }

        return $extract_to . '/' . $sql_file;
    }

    throw new Exception("Failed to extract ZIP file");
}

/**
 * Recursively copy files from source to destination directory
 * Overwrites existing files to ensure a full restore.
 * 
 * @param string $src Source directory
 * @param string $dst Destination directory
 */
function recursive_copy($src, $dst)
{
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }

    $dir = opendir($src);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;

        $src_path = $src . '/' . $file;
        $dst_path = $dst . '/' . $file;

        if (is_dir($src_path)) {
            recursive_copy($src_path, $dst_path);
        } else {
            copy($src_path, $dst_path);
        }
    }
    closedir($dir);
}

/**
 * Delete old backups based on retention policy
 * 
 * @return int Number of backups deleted
 */
function cleanup_old_backups()
{
    $settings = db_select_one('backup_settings', ['id' => 1]);
    $retention_days = $settings['retention_days'] ?? 30;

    $cutoff_date = date('Y-m-d H:i:s', strtotime("-$retention_days days"));

    $old_backups = db_query("
        SELECT * FROM backup_history 
        WHERE created_at < ? AND backup_type = 'automatic'
    ", [$cutoff_date]);

    $deleted = 0;
    foreach ($old_backups as $backup) {
        if (file_exists($backup['file_path'])) {
            unlink($backup['file_path']);
        }
        db_delete('backup_history', ['id' => $backup['id']]);
        $deleted++;
    }

    return $deleted;
}

/**
 * Get backup statistics
 * 
 * @return array Backup statistics
 */
function get_backup_stats()
{
    $stats = db_query_one("
        SELECT 
            COUNT(*) as total_backups,
            SUM(backup_size) as total_size,
            MAX(created_at) as last_backup,
            SUM(CASE WHEN backup_type = 'automatic' THEN 1 ELSE 0 END) as auto_backups,
            SUM(CASE WHEN backup_type = 'manual' THEN 1 ELSE 0 END) as manual_backups
        FROM backup_history
        WHERE status = 'success'
    ");

    return $stats;
}
