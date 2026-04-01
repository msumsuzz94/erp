<?php
/**
 * Database Connection Handler
 * Uses PDO for secure database operations
 */

// Prevent direct access
if (!defined('DB_HOST')) {
    die('Direct access not permitted');
}

// Database connection variable
$conn = null;

try {
    // Create DSN (Data Source Name)
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    // PDO options for security and performance
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false, // Set to true for connection pooling
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET // Enforce charset for cPanel
    ];
    
    // Create PDO instance
    $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Log successful connection in development mode
    if (DEBUG_MODE && APP_ENV === 'development') {
        // Connection successful
    }
    
} catch (PDOException $e) {
    // Log error
    $error_message = "Database connection failed: " . $e->getMessage();
    
    $show_error = defined('DEBUG_MODE') && DEBUG_MODE;
    if ($show_error) {
        die($error_message);
    } else {
        // Don't expose detailed errors in production
        die("Unable to connect to database. Please contact administrator.");
    }
}

/**
 * Get database connection
 * @return PDO
 */
function getDB() {
    global $conn;
    return $conn;
}

/**
 * Close database connection
 */
function closeDB() {
    global $conn;
    $conn = null;
}

/**
 * Execute a prepared statement and return results
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return array Results
 */
function dbQuery($sql, $params = []) {
    global $conn;
    /** @var PDO $conn */
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        logError("Query Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Execute a prepared statement (INSERT, UPDATE, DELETE)
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return bool Success status
 */
function dbExecute($sql, $params = []) {
    global $conn;
    /** @var PDO $conn */
    if ($conn === null && function_exists('getDB')) {
        $conn = getDB();
    }
    try {
        $stmt = $conn->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        logError("Execute Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get last inserted ID
 * @return string Last insert ID
 */
function dbLastInsertId() {
    global $conn;
    /** @var PDO $conn */
    return $conn->lastInsertId();
}

/**
 * Begin transaction
 */
function dbBeginTransaction() {
    global $conn;
    /** @var PDO $conn */
    $conn->beginTransaction();
}

/**
 * Commit transaction
 */
function dbCommit() {
    global $conn;
    /** @var PDO $conn */
    $conn->commit();
}

/**
 * Rollback transaction
 */
function dbRollback() {
    global $conn;
    /** @var PDO $conn */
    $conn->rollBack();
}

/**
 * Log database errors
 * @param string $message Error message
 */
function logError($message) {
    $is_debug = defined('DEBUG_MODE') && DEBUG_MODE;
    if ($is_debug) {
        error_log($message);
    }
    // In production, log to file
    // file_put_contents(BASE_PATH . '/logs/errors.log', date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
}
