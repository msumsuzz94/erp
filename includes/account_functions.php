<?php
/**
 * Account Helper Functions
 * 
 * Logic for calculating user cash balances from multiple sources:
 * 1. Party Cash (Assigned/Returned)
 * 2. Sales (Cash collected)
 * 3. Purchase Payments (Cash paid)
 * 4. Expenses (Cash paid)
 */

/**
 * Get party cash summary for a specific user or all users
 * 
 * @param int|null $user_id Specific user ID or null for all users
 * @return array
 */
function get_party_cash_summary($user_id = null) {
    global $conn;
    
    // Base WHERE clause
    $user_where = $user_id ? "WHERE u.id = ?" : "";
    $pc_where = $user_id ? "WHERE pc.user_id = ?" : "";
    $params = $user_id ? [$user_id] : [];
    
    // 1. Party Cash records (Group by user_id and person_name)
    $sql_party = "SELECT pc.user_id, pc.person_name,
        COALESCE(SUM(CASE WHEN pc.transaction_type = 'assign' THEN pc.amount ELSE 0 END), 0) as assigned_cash,
        COALESCE(SUM(CASE WHEN pc.transaction_type = 'return' THEN pc.amount ELSE 0 END), 0) as returned_cash
        FROM party_cash pc
        $pc_where
        GROUP BY pc.user_id, pc.person_name";
        
    // 2. Sales (Cash In)
    $sql_sales = "SELECT u.id as user_id, 
        COALESCE(SUM(s.paid_amount), 0) as sales_cash
        FROM users u
        LEFT JOIN sales s ON u.id = s.created_by 
            AND s.status = 'completed' 
            AND s.payment_method = 'cash'
        $user_where
        GROUP BY u.id";
        
    // 3. Purchase Payments (Cash Out)
    $sql_payments = "SELECT u.id as user_id, 
        COALESCE(SUM(pp.amount), 0) as payment_cash
        FROM users u
        LEFT JOIN purchase_payments pp ON u.id = pp.created_by 
            AND pp.payment_method = 'cash'
        $user_where
        GROUP BY u.id";
        
    // 4. Expenses (Cash Out)
    $sql_expenses = "SELECT u.id as user_id, 
        COALESCE(SUM(e.amount), 0) as expense_cash
        FROM users u
        LEFT JOIN expenses e ON u.id = e.created_by 
            AND e.payment_method = 'cash'
        $user_where
        GROUP BY u.id";
        
    // Execute queries
    $party_data = db_query($sql_party, $params);
    $sales_data = db_query($sql_sales, $params);
    $payments_data = db_query($sql_payments, $params);
    try {
        $expenses_data = db_query($sql_expenses, $params);
    } catch (Exception $e) {
        $expenses_data = [];
    }
    
    $merged = [];
    
    // Build user dictionary
    $all_users = db_query("SELECT id, username, email FROM users WHERE status='active' " . ($user_id ? "AND id=$user_id" : ""));
    foreach ($all_users as $u) {
        $merged['u_'.$u['id']] = [
            'id' => $u['id'],
            'username' => $u['username'],
            'email' => $u['email'],
            'is_user' => true,
            'assigned_cash' => 0, 'returned_cash' => 0, 'sales_cash' => 0, 'payment_cash' => 0, 'expense_cash' => 0
        ];
    }
    
    // Map Party data
    foreach ($party_data as $row) {
        if ($row['user_id']) {
            $key = 'u_'.$row['user_id'];
            if (!isset($merged[$key])) continue; // Ignore inactive users
            $merged[$key]['assigned_cash'] += (float)$row['assigned_cash'];
            $merged[$key]['returned_cash'] += (float)$row['returned_cash'];
        } else if (!empty($row['person_name'])) {
            $key = 'ext_' . md5($row['person_name']);
            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'id' => $key, // use hash as a string id for JS matching
                    'username' => $row['person_name'],
                    'email' => '',
                    'is_user' => false,
                    'assigned_cash' => 0, 'returned_cash' => 0, 'sales_cash' => 0, 'payment_cash' => 0, 'expense_cash' => 0
                ];
            }
            $merged[$key]['assigned_cash'] += (float)$row['assigned_cash'];
            $merged[$key]['returned_cash'] += (float)$row['returned_cash'];
        }
    }
    
    // Map Sales, Payments, Expenses
    $map_fn = function($data, $field) use (&$merged) {
        foreach ($data as $row) {
            $key = 'u_'.$row['user_id'];
            if (isset($merged[$key])) {
                $merged[$key][$field] += (float)$row[$field];
            }
        }
    };
    $map_fn($sales_data, 'sales_cash');
    $map_fn($payments_data, 'payment_cash');
    $map_fn($expenses_data, 'expense_cash');
    
    // Build final result
    $final_result = [];
    foreach ($merged as $stats) {
        $total_in = $stats['assigned_cash'] + $stats['sales_cash'];
        $total_out = $stats['returned_cash'] + $stats['payment_cash'] + $stats['expense_cash'];
        $balance = $total_in - $total_out;
        
        // Only return entities with actual activity
        if ($total_in > 0 || $total_out > 0) {
            $stats['total_in'] = $total_in;
            $stats['total_out'] = $total_out;
            $stats['balance'] = $balance;
            $final_result[] = $stats;
        }
    }
    
    // If getting single user, return just that data
    if ($user_id) {
        return !empty($final_result) ? $final_result[0] : null;
    }
    
    return $final_result;
}
