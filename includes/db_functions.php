<?php
/**
 * Database Helper Functions
 * Simplified database operations
 */

/**
 * Select records from database
 * @param string $table Table name
 * @param array $conditions WHERE conditions as associative array
 * @param string $fields Fields to select (default: *)
 * @param string $order_by ORDER BY clause
 * @param int $limit LIMIT value
 * @return array Results
 */
function db_select($table, $conditions = [], $fields = '*', $order_by = '', $limit = 0) {
    global $conn;
    if ($conn === null && function_exists('getDB')) {
        $conn = getDB();
    }
    
    $sql = "SELECT $fields FROM $table";
    $params = [];
    
    if (!empty($conditions)) {
        $where = [];
        foreach ($conditions as $key => $value) {
            $where[] = "$key = ?";
            $params[] = $value;
        }
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    if (!empty($order_by)) {
        $sql .= " ORDER BY $order_by";
    }
    
    if ($limit > 0) {
        $sql .= " LIMIT $limit";
    }
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        logError("SELECT Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Select single record
 * @param string $table Table name
 * @param array $conditions WHERE conditions
 * @param string $fields Fields to select
 * @return array|null Single record or null
 */
function db_select_one($table, $conditions = [], $fields = '*') {
    $results = db_select($table, $conditions, $fields, '', 1);
    return $results[0] ?? null;
}

/**
 * Insert record into database
 * @param string $table Table name
 * @param array $data Data as associative array
 * @return int|bool Last insert ID or false
 */
function db_insert($table, $data) {
    global $conn;
    if ($conn === null && function_exists('getDB')) {
        $conn = getDB();
    }
    
    $fields = array_keys($data);
    $values = array_values($data);
    
    $field_list = implode(', ', $fields);
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    
    $sql = "INSERT INTO $table ($field_list) VALUES ($placeholders)";
    
    try {
        $stmt = $conn->prepare($sql);
        if ($stmt->execute($values)) {
            return $conn->lastInsertId();
        }
        return false;
    } catch (PDOException $e) {
        logError("INSERT Error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Update records in database
 * @param string $table Table name
 * @param array $data Data to update
 * @param array $conditions WHERE conditions
 * @return bool Success status
 */
function db_update($table, $data, $conditions) {
    global $conn;
    if ($conn === null && function_exists('getDB')) {
        $conn = getDB();
    }
    
    $set = [];
    $params = [];
    
    foreach ($data as $key => $value) {
        $set[] = "$key = ?";
        $params[] = $value;
    }
    
    $where = [];
    foreach ($conditions as $key => $value) {
        $where[] = "$key = ?";
        $params[] = $value;
    }
    
    $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $where);
    
    try {
        $stmt = $conn->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        logError("UPDATE Error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Delete records from database
 * @param string $table Table name
 * @param array $conditions WHERE conditions
 * @return bool Success status
 */
function db_delete($table, $conditions) {
    global $conn;
    if ($conn === null && function_exists('getDB')) {
        $conn = getDB();
    }
    
    $where = [];
    $params = [];
    
    foreach ($conditions as $key => $value) {
        $where[] = "$key = ?";
        $params[] = $value;
    }
    
    $sql = "DELETE FROM $table WHERE " . implode(' AND ', $where);
    
    try {
        $stmt = $conn->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        logError("DELETE Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Count records in database
 * @param string $table Table name
 * @param array $conditions WHERE conditions
 * @return int Count
 */
function db_count($table, $conditions = []) {
    global $conn;
    if ($conn === null && function_exists('getDB')) {
        $conn = getDB();
    }
    
    $sql = "SELECT COUNT(*) as count FROM $table";
    $params = [];
    
    if (!empty($conditions)) {
        $where = [];
        foreach ($conditions as $key => $value) {
            $where[] = "$key = ?";
            $params[] = $value;
        }
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int)$result['count'];
    } catch (PDOException $e) {
        logError("COUNT Error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check if record exists
 * @param string $table Table name
 * @param array $conditions WHERE conditions
 * @return bool Exists or not
 */
function db_exists($table, $conditions) {
    return db_count($table, $conditions) > 0;
}

/**
 * Get paginated results
 * @param string $table Table name
 * @param int $page Page number
 * @param int $per_page Records per page
 * @param array $conditions WHERE conditions
 * @param string $order_by ORDER BY clause
 * @return array Results with pagination info
 */
function db_paginate($table, $page = 1, $per_page = null, $conditions = [], $order_by = 'id DESC') {
    if (!$per_page) {
        $per_page = RECORDS_PER_PAGE;
    }
    
    $total_records = db_count($table, $conditions);
    $total_pages = ceil($total_records / $per_page);
    $offset = ($page - 1) * $per_page;
    
    global $conn;
    if ($conn === null && function_exists('getDB')) {
        $conn = getDB();
    }
    $sql = "SELECT * FROM $table";
    $params = [];
    
    if (!empty($conditions)) {
        $where = [];
        foreach ($conditions as $key => $value) {
            $where[] = "$key = ?";
            $params[] = $value;
        }
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    if (!empty($order_by)) {
        $sql .= " ORDER BY $order_by";
    }
    
    $sql .= " LIMIT $per_page OFFSET $offset";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();
    } catch (PDOException $e) {
        logError("PAGINATE Error: " . $e->getMessage());
        $records = [];
    }
    
    return [
        'records' => $records,
        'current_page' => $page,
        'per_page' => $per_page,
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'has_prev' => $page > 1,
        'has_next' => $page < $total_pages
    ];
}

/**
 * Execute custom query
 * @param string $sql SQL query
 * @param array $params Parameters
 * @return array Results
 */
function db_query($sql, $params = []) {
    global $conn;
    if ($conn === null && function_exists('getDB')) {
        $conn = getDB();
    }
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        logError("QUERY Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Execute custom query and return single result
 * @param string $sql SQL query
 * @param array $params Parameters
 * @return array|null Single result or null
 */
function db_query_one($sql, $params = []) {
    $results = db_query($sql, $params);
    return $results[0] ?? null;
}

/**
 * Get max value of a column
 * @param string $table Table name
 * @param string $column Column name
 * @param array $conditions WHERE conditions
 * @return mixed Max value
 */
function db_max($table, $column, $conditions = []) {
    global $conn;
    if ($conn === null && function_exists('getDB')) {
        $conn = getDB();
    }
    
    $sql = "SELECT MAX($column) as max_value FROM $table";
    $params = [];
    
    if (!empty($conditions)) {
        $where = [];
        foreach ($conditions as $key => $value) {
            $where[] = "$key = ?";
            $params[] = $value;
        }
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['max_value'];
    } catch (PDOException $e) {
        logError("MAX Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get sum of a column
 * @param string $table Table name
 * @param string $column Column name
 * @param array $conditions WHERE conditions
 * @return float Sum value
 */
function db_sum($table, $column, $conditions = []) {
    global $conn;
    if ($conn === null && function_exists('getDB')) {
        $conn = getDB();
    }
    
    $sql = "SELECT SUM($column) as sum_value FROM $table";
    $params = [];
    
    if (!empty($conditions)) {
        $where = [];
        foreach ($conditions as $key => $value) {
            $where[] = "$key = ?";
            $params[] = $value;
        }
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (float)($result['sum_value'] ?? 0);
    } catch (PDOException $e) {
        logError("SUM Error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Begin database transaction
 * @return bool Success status
 */
function db_begin_transaction() {
    global $conn;
    if ($conn === null && function_exists('getDB')) {
        $conn = getDB();
    }
    try {
        return $conn->beginTransaction();
    } catch (PDOException $e) {
        logError("BEGIN TRANSACTION Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Commit database transaction
 * @return bool Success status
 */
function db_commit() {
    global $conn;
    if ($conn === null && function_exists('getDB')) {
        $conn = getDB();
    }
    try {
        return $conn->commit();
    } catch (PDOException $e) {
        logError("COMMIT Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Rollback database transaction
 * @return bool Success status
 */
function db_rollback() {
    global $conn;
    if ($conn === null && function_exists('getDB')) {
        $conn = getDB();
    }
    try {
        return $conn->rollBack();
    } catch (PDOException $e) {
        logError("ROLLBACK Error: " . $e->getMessage());
        return false;
    }
}
