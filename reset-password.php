<?php
/**
 * Emergency Password Reset Tool
 * Upload this file to your cPanel public_html folder
 * Visit: https://cleansbuy.store/reset-password.php
 * DELETE THIS FILE IMMEDIATELY AFTER USE!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include config
require_once __DIR__ . '/config/config.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $new_password = trim($_POST['password'] ?? '');
    
    if (!empty($username) && !empty($new_password)) {
        try {
            // Generate password hash on THIS server (important for compatibility)
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            global $conn;
            
            // Check if user exists first
            $check_stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
            $check_stmt->execute([$username]);
            $user_exists = $check_stmt->fetch();
            
            if ($user_exists) {
                // Update password and set status to active
                $stmt = $conn->prepare("UPDATE users SET password_hash = ?, status = 'active' WHERE username = ?");
                $result = $stmt->execute([$password_hash, $username]);
                
                if ($result) {
                    $message = "✅ Password reset successful!<br><br>";
                    $message .= "<strong>Username:</strong> " . htmlspecialchars($username) . "<br>";
                    $message .= "<strong>New Password:</strong> " . htmlspecialchars($new_password) . "<br><br>";
                    $message .= "<span style='color: red; font-weight: bold;'>⚠️ DELETE THIS FILE NOW!</span>";
                    $messageType = 'success';
                } else {
                    $message = "❌ Failed to update password. Please try again.";
                    $messageType = 'error';
                }
            } else {
                $message = "❌ User '$username' not found in database.";
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = "❌ Please fill in both username and password.";
        $messageType = 'error';
    }
}

// Get all users for reference
$users_list = [];
try {
    global $conn;
    $stmt = $conn->query("SELECT username, email, status FROM users ORDER BY id");
    $users_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silently fail if can't get users
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Tool</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        
        .warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 25px;
            color: #856404;
        }
        
        .warning strong {
            display: block;
            font-size: 18px;
            margin-bottom: 8px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }
        
        .message.error {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
        }
        
        .users-reference {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }
        
        .users-reference h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .user-item {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .user-item strong {
            color: #667eea;
        }
        
        .user-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 8px;
        }
        
        .user-status.active {
            background: #28a745;
            color: white;
        }
        
        .user-status.inactive {
            background: #dc3545;
            color: white;
        }
        
        .tip {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 12px;
            margin-top: 15px;
            font-size: 14px;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="warning">
            <strong>⚠️ SECURITY WARNING</strong>
            This is a powerful tool that can reset any user password.<br>
            <strong>DELETE THIS FILE IMMEDIATELY AFTER USE!</strong>
        </div>
        
        <h1>🔑 Password Reset Tool</h1>
        <p class="subtitle">Reset password for any user account</p>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    placeholder="Enter username (e.g., admin)" 
                    required
                    value="admin"
                >
            </div>
            
            <div class="form-group">
                <label for="password">New Password</label>
                <input 
                    type="text" 
                    id="password" 
                    name="password" 
                    placeholder="Enter new password" 
                    required
                >
                <div class="tip">
                    💡 <strong>Tip:</strong> Use a strong password with letters, numbers, and symbols.
                </div>
            </div>
            
            <button type="submit">Reset Password</button>
        </form>
        
        <?php if (!empty($users_list)): ?>
        <div class="users-reference">
            <h3>📋 Existing Users in Database</h3>
            <?php foreach ($users_list as $user): ?>
                <div class="user-item">
                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                    <span class="user-status <?php echo $user['status']; ?>">
                        <?php echo strtoupper($user['status']); ?>
                    </span>
                    <br>
                    <small><?php echo htmlspecialchars($user['email']); ?></small>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
