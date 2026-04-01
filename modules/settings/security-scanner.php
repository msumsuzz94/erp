<?php

/**
 * Security Scanner
 * Scan files for malware, check integrity, and manage suspicious files
 * Only accessible by Super Admin (role_id = 1)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();

// Only Super Admin can access
if (!is_admin()) {
    redirect_with_message(BASE_URL . '/modules/dashboard/index.php', 'Access denied. Only administrators can access the Security Scanner.', 'error');
    exit;
}

$scan_results = [];
$recent_files = [];
$permission_issues = [];
$action_message = '';
$action_type = '';

// ============================================
// MALWARE SIGNATURES (Only real virus/malware patterns)
// ============================================
$suspicious_patterns = [
    'eval\s*\(\s*base64_decode' => ['level' => 'critical', 'desc' => 'eval(base64_decode()) - Obfuscated backdoor'],
    'eval\s*\(\s*gzinflate' => ['level' => 'critical', 'desc' => 'eval(gzinflate()) - Compressed backdoor'],
    'eval\s*\(\s*str_rot13' => ['level' => 'critical', 'desc' => 'eval(str_rot13()) - ROT13 encoded backdoor'],
    'eval\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)' => ['level' => 'critical', 'desc' => 'eval($_INPUT) - Direct user input execution (web shell)'],
    'assert\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)' => ['level' => 'critical', 'desc' => 'assert($_INPUT) - User input assertion (backdoor)'],
    'preg_replace\s*\(.*\/[a-z]*e[a-z]*[\'"]\s*,' => ['level' => 'critical', 'desc' => 'preg_replace /e modifier - Code execution'],
    '\$_(?:GET|POST|REQUEST|COOKIE)\s*\[.*\]\s*\(' => ['level' => 'critical', 'desc' => 'Variable function call from user input (web shell)'],
    'shell_exec\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)' => ['level' => 'critical', 'desc' => 'shell_exec with user input - Remote command execution'],
    'system\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)' => ['level' => 'critical', 'desc' => 'system() with user input - Remote command execution'],
    'passthru\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)' => ['level' => 'critical', 'desc' => 'passthru() with user input - Command execution'],
    '\bexec\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)' => ['level' => 'critical', 'desc' => 'exec() with user input - Command execution'],
    'file_put_contents\s*\(.*\$_(GET|POST|REQUEST)' => ['level' => 'critical', 'desc' => 'File write with user input - File injection'],
    '\\\\x[0-9a-fA-F]{2}.*\\\\x[0-9a-fA-F]{2}.*\\\\x[0-9a-fA-F]{2}.*\\\\x[0-9a-fA-F]{2}' => ['level' => 'high', 'desc' => 'Heavy hex obfuscation detected'],
    'chr\s*\(\s*\d+\s*\)\s*\.\s*chr\s*\(\s*\d+\s*\)\s*\.\s*chr' => ['level' => 'high', 'desc' => 'Character code chain (obfuscated malware)'],
    'gzinflate\s*\(\s*base64_decode' => ['level' => 'critical', 'desc' => 'gzinflate(base64_decode()) - Compressed obfuscated code'],
    'base64_decode\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)' => ['level' => 'critical', 'desc' => 'base64_decode from user input - Encoded payload'],
    'create_function\s*\(.*\$_(GET|POST|REQUEST|COOKIE)' => ['level' => 'critical', 'desc' => 'Dynamic function from user input - Code injection'],
    '@?\$\w+\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)' => ['level' => 'critical', 'desc' => 'Variable function call with user input - Backdoor'],
];

// Known safe project directories (skip scanning)
$safe_directories = [
    '/config/', '/includes/', '/templates/', '/assets/',
    '/modules/', '/api/', '/uploads/', '/vendor/',
    '/node_modules/', '/quarantine/', '/.git/',
];

// ============================================
// HANDLE ACTIONS
// ============================================
if (is_post() && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'quarantine' && !empty($_POST['file_path'])) {
        $file_path = realpath($_POST['file_path']);
        $base_path = realpath(BASE_PATH);
        
        // Security: Only allow files within the project directory
        if ($file_path && strpos($file_path, $base_path) === 0 && file_exists($file_path)) {
            $quarantine_dir = BASE_PATH . '/quarantine/';
            if (!is_dir($quarantine_dir)) {
                mkdir($quarantine_dir, 0755, true);
                // Protect quarantine directory
                file_put_contents($quarantine_dir . '.htaccess', "Deny from all\n");
                file_put_contents($quarantine_dir . 'index.php', '<?php die("Access denied");');
            }
            
            $quarantine_name = date('Y-m-d_His') . '_' . basename($file_path) . '.quarantined';
            if (rename($file_path, $quarantine_dir . $quarantine_name)) {
                $action_message = 'File quarantined successfully: ' . basename($file_path);
                $action_type = 'success';
                log_activity(get_current_user_id(), 'security_quarantine', 'Quarantined file: ' . $file_path);
            } else {
                $action_message = 'Failed to quarantine file.';
                $action_type = 'error';
            }
        } else {
            $action_message = 'Invalid file path.';
            $action_type = 'error';
        }
    }
    
    if ($action === 'delete' && !empty($_POST['file_path'])) {
        $file_path = realpath($_POST['file_path']);
        $base_path = realpath(BASE_PATH);
        
        if ($file_path && strpos($file_path, $base_path) === 0 && file_exists($file_path)) {
            // Don't allow deleting core files
            $protected_files = ['config.php', 'database.php', 'constants.php', 'auth.php', 'functions.php', 'db_functions.php'];
            if (!in_array(basename($file_path), $protected_files)) {
                if (unlink($file_path)) {
                    $action_message = 'File deleted successfully: ' . basename($file_path);
                    $action_type = 'success';
                    log_activity(get_current_user_id(), 'security_delete', 'Deleted suspicious file: ' . $file_path);
                } else {
                    $action_message = 'Failed to delete file.';
                    $action_type = 'error';
                }
            } else {
                $action_message = 'Cannot delete protected core file.';
                $action_type = 'error';
            }
        } else {
            $action_message = 'Invalid file path.';
            $action_type = 'error';
        }
    }
    
    if ($action === 'restore' && !empty($_POST['file_path'])) {
        $quarantine_dir = BASE_PATH . '/quarantine/';
        $file_name = basename($_POST['file_path']);
        $quarantine_path = $quarantine_dir . $file_name;
        
        if (file_exists($quarantine_path)) {
            // Restore to original location (remove .quarantined extension and date prefix)
            $original_name = preg_replace('/^\d{4}-\d{2}-\d{2}_\d{6}_/', '', $file_name);
            $original_name = preg_replace('/\.quarantined$/', '', $original_name);
            
            // Try to restore to root directory
            $restore_path = BASE_PATH . '/' . $original_name;
            if (rename($quarantine_path, $restore_path)) {
                $action_message = 'File restored: ' . $original_name;
                $action_type = 'success';
                log_activity(get_current_user_id(), 'security_restore', 'Restored file: ' . $original_name);
            } else {
                $action_message = 'Failed to restore file.';
                $action_type = 'error';
            }
        }
    }
}

// ============================================
// PERFORM SCAN (GET request or after action)
// ============================================
$scan_mode = $_GET['scan'] ?? '';
$hours_filter = (int)($_GET['hours'] ?? 72);

if ($scan_mode === 'full' || $scan_mode === 'quick') {
    $scan_dirs = [BASE_PATH];
    $exclude_dirs = [
        BASE_PATH . '/vendor',
        BASE_PATH . '/node_modules',
        BASE_PATH . '/quarantine',
        BASE_PATH . '/.git',
    ];
    
    $scanned_count = 0;
    $max_files = ($scan_mode === 'quick') ? 500 : 5000;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(BASE_PATH, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($scanned_count >= $max_files) break;
        
        // Skip excluded directories
        $skip = false;
        foreach ($exclude_dirs as $exclude) {
            if (strpos($file->getPathname(), $exclude) === 0) {
                $skip = true;
                break;
            }
        }
        if ($skip) continue;
        
        if ($file->isFile() && $file->getExtension() === 'php') {
            $scanned_count++;
            $rel_path = str_replace(str_replace('\\', '/', BASE_PATH), '', str_replace('\\', '/', $file->getPathname()));
            
            // Skip all known safe project directories
            $is_safe_dir = false;
            foreach ($safe_directories as $safe) {
                if (strpos($rel_path, $safe) !== false) {
                    $is_safe_dir = true;
                    break;
                }
            }
            if ($is_safe_dir) continue;
            
            $content = @file_get_contents($file->getPathname());
            if ($content === false) continue;
            
            $file_threats = [];
            foreach ($suspicious_patterns as $pattern => $info) {
                if (preg_match('#' . $pattern . '#i', $content)) {
                    $file_threats[] = $info;
                }
            }
            
            if (!empty($file_threats)) {
                $scan_results[] = [
                    'file' => $file->getPathname(),
                    'relative' => $rel_path,
                    'size' => $file->getSize(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                    'threats' => $file_threats,
                    'max_level' => get_max_threat_level($file_threats),
                ];
            }
        }
    }
    
    // Sort by threat level (critical first)
    usort($scan_results, function($a, $b) {
        $levels = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        return ($levels[$b['max_level']] ?? 0) - ($levels[$a['max_level']] ?? 0);
    });
}

// ============================================
// RECENTLY MODIFIED FILES
// ============================================
if ($scan_mode === 'recent' || $scan_mode === 'full') {
    $cutoff_time = time() - ($hours_filter * 3600);
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(BASE_PATH, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getMTime() > $cutoff_time) {
            $rel_path = str_replace(str_replace('\\', '/', BASE_PATH), '', str_replace('\\', '/', $file->getPathname()));
            
            // Skip quarantine, vendor, node_modules, .git
            if (preg_match('#/(quarantine|vendor|node_modules|\.git)/#', $rel_path)) continue;
            
            $recent_files[] = [
                'file' => $file->getPathname(),
                'relative' => $rel_path,
                'size' => $file->getSize(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                'extension' => $file->getExtension(),
            ];
        }
    }
    
    // Sort by most recent first
    usort($recent_files, function($a, $b) {
        return strcmp($b['modified'], $a['modified']);
    });
    
    // Limit to 100
    $recent_files = array_slice($recent_files, 0, 100);
}

// ============================================
// QUARANTINED FILES
// ============================================
$quarantined_files = [];
$quarantine_dir = BASE_PATH . '/quarantine/';
if (is_dir($quarantine_dir)) {
    $q_files = glob($quarantine_dir . '*.quarantined');
    foreach ($q_files as $qf) {
        $quarantined_files[] = [
            'file' => $qf,
            'name' => basename($qf),
            'size' => filesize($qf),
            'date' => date('Y-m-d H:i:s', filemtime($qf)),
        ];
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================
function get_max_threat_level($threats) {
    $levels = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
    $max = 0;
    $max_name = 'low';
    foreach ($threats as $t) {
        if (($levels[$t['level']] ?? 0) > $max) {
            $max = $levels[$t['level']] ?? 0;
            $max_name = $t['level'];
        }
    }
    return $max_name;
}

function threat_badge($level) {
    $colors = [
        'critical' => 'danger',
        'high' => 'warning',
        'medium' => 'info',
        'low' => 'secondary',
    ];
    return '<span class="badge bg-' . ($colors[$level] ?? 'secondary') . '">' . strtoupper($level) . '</span>';
}

$page_title = 'Security Scanner';
include __DIR__ . '/../../templates/header.php';
?>

<style>
.security-card { border-left: 4px solid; }
.security-card.border-danger { border-left-color: #dc3545 !important; }
.security-card.border-warning { border-left-color: #ffc107 !important; }
.security-card.border-success { border-left-color: #28a745 !important; }
.security-card.border-info { border-left-color: #17a2b8 !important; }
.scan-result-item { border-bottom: 1px solid rgba(0,0,0,0.1); padding: 12px 0; }
.scan-result-item:last-child { border-bottom: none; }
.threat-list { font-size: 0.85rem; margin-top: 5px; }
.threat-list li { margin-bottom: 3px; }
.stat-card { text-align: center; padding: 20px; border-radius: 8px; }
.stat-card h2 { font-size: 2.5rem; font-weight: bold; margin-bottom: 5px; }
.pulse-green { animation: pulseGreen 2s infinite; }
@keyframes pulseGreen {
    0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(40, 167, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
}
</style>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-shield-alt text-primary"></i> Security Scanner</h4>
        <small class="text-muted">Scan your files for malware, viruses, and suspicious code</small>
    </div>
    <div>
        <a href="?scan=quick" class="btn btn-primary"><i class="fas fa-bolt"></i> Quick Scan</a>
        <a href="?scan=full" class="btn btn-danger"><i class="fas fa-search"></i> Full Scan</a>
        <a href="?scan=recent&hours=48" class="btn btn-info"><i class="fas fa-clock"></i> Recent Files</a>
    </div>
</div>

<?php if ($action_message): ?>
    <div class="alert alert-<?= $action_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
        <i class="fas fa-<?= $action_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
        <?= htmlspecialchars($action_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Stats Row -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card security-card border-<?= empty($scan_results) ? 'success' : 'danger' ?>">
            <div class="card-body stat-card">
                <h2 class="text-<?= empty($scan_results) && $scan_mode ? 'success' : 'muted' ?>">
                    <?= count($scan_results) ?>
                </h2>
                <p class="mb-0">Threats Found</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card security-card border-info">
            <div class="card-body stat-card">
                <h2 class="text-info"><?= count($recent_files) ?></h2>
                <p class="mb-0">Recent Changes</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card security-card border-warning">
            <div class="card-body stat-card">
                <h2 class="text-warning"><?= count($quarantined_files) ?></h2>
                <p class="mb-0">Quarantined</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card security-card border-success <?= (empty($scan_results) && $scan_mode) ? 'pulse-green' : '' ?>">
            <div class="card-body stat-card">
                <h2 class="text-success">
                    <i class="fas fa-<?= (empty($scan_results) && $scan_mode) ? 'check-circle' : 'question-circle' ?>"></i>
                </h2>
                <p class="mb-0"><?= $scan_mode ? (empty($scan_results) ? 'System Clean' : 'Issues Found') : 'Not Scanned' ?></p>
            </div>
        </div>
    </div>
</div>

<?php if ($scan_mode && !empty($scan_results)): ?>
<!-- Scan Results -->
<div class="card shadow mb-4">
    <div class="card-header py-3 bg-danger text-white">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-exclamation-triangle"></i> Scan Results — <?= count($scan_results) ?> Suspicious File(s) Found
        </h6>
    </div>
    <div class="card-body">
        <?php foreach ($scan_results as $result): ?>
            <div class="scan-result-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong><i class="fas fa-file-code"></i> <?= htmlspecialchars($result['relative']) ?></strong>
                        <span class="ms-2"><?= threat_badge($result['max_level']) ?></span>
                        <br>
                        <small class="text-muted">
                            Size: <?= number_format($result['size'] / 1024, 1) ?> KB | 
                            Modified: <?= $result['modified'] ?>
                        </small>
                        <ul class="threat-list">
                            <?php foreach ($result['threats'] as $threat): ?>
                                <li><?= threat_badge($threat['level']) ?> <?= htmlspecialchars($threat['desc']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="btn-group">
                        <form method="POST" class="d-inline" onsubmit="return confirm('Quarantine this file? It will be moved to quarantine folder.')">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="action" value="quarantine">
                            <input type="hidden" name="file_path" value="<?= htmlspecialchars($result['file']) ?>">
                            <button type="submit" class="btn btn-sm btn-warning" title="Move to Quarantine">
                                <i class="fas fa-lock"></i>
                            </button>
                        </form>
                        <form method="POST" class="d-inline" onsubmit="return confirm('DELETE this file permanently? This cannot be undone!')">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="file_path" value="<?= htmlspecialchars($result['file']) ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Delete File">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php elseif ($scan_mode && empty($scan_results) && $scan_mode !== 'recent'): ?>
<div class="card shadow mb-4">
    <div class="card-body text-center py-5">
        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
        <h4 class="mt-3 text-success">No Threats Detected!</h4>
        <p class="text-muted">Your system appears clean. No suspicious patterns found.</p>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($recent_files)): ?>
<!-- Recently Modified Files -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-info">
            <i class="fas fa-clock"></i> Recently Modified Files (Last <?= $hours_filter ?> hours)
            <span class="badge bg-info ms-2"><?= count($recent_files) ?></span>
        </h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <a href="?scan=recent&hours=24" class="btn btn-sm btn-outline-primary <?= $hours_filter == 24 ? 'active' : '' ?>">24h</a>
            <a href="?scan=recent&hours=48" class="btn btn-sm btn-outline-primary <?= $hours_filter == 48 ? 'active' : '' ?>">48h</a>
            <a href="?scan=recent&hours=72" class="btn btn-sm btn-outline-primary <?= $hours_filter == 72 ? 'active' : '' ?>">72h</a>
            <a href="?scan=recent&hours=168" class="btn btn-sm btn-outline-primary <?= $hours_filter == 168 ? 'active' : '' ?>">7 days</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Size</th>
                        <th>Type</th>
                        <th>Modified</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_files as $rf): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($rf['relative']) ?></code></td>
                            <td><?= number_format($rf['size'] / 1024, 1) ?> KB</td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($rf['extension']) ?></span></td>
                            <td><?= $rf['modified'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($quarantined_files)): ?>
<!-- Quarantined Files -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-warning">
            <i class="fas fa-lock"></i> Quarantined Files
            <span class="badge bg-warning text-dark ms-2"><?= count($quarantined_files) ?></span>
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Size</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quarantined_files as $qf): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($qf['name']) ?></code></td>
                            <td><?= number_format($qf['size'] / 1024, 1) ?> KB</td>
                            <td><?= $qf['date'] ?></td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Restore this file?')">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="file_path" value="<?= htmlspecialchars($qf['name']) ?>">
                                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-undo"></i> Restore</button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('DELETE permanently?')">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="file_path" value="<?= htmlspecialchars($qf['file']) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Security Tips -->
<div class="row">
    <div class="col-md-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-success">
                    <i class="fas fa-lightbulb"></i> Security Tips
                </h6>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>নিয়মিত <strong>Full Scan</strong> চালান (সপ্তাহে অন্তত ১ বার)</li>
                    <li><strong>Recently Modified</strong> ফাইলগুলো চেক করুন</li>
                    <li>cPanel-এর পাসওয়ার্ড শক্তিশালী রাখুন</li>
                    <li>অপরিচিত FTP ইউজার থাকলে রিমুভ করুন</li>
                    <li>PHP version আপডেট রাখুন</li>
                    <li><code>config.php</code>-এ <code>APP_ENV</code> = <code>'production'</code> সেট করুন</li>
                    <li>নিয়মিত ডেটাবেস ও ফাইল ব্যাকআপ নিন</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if (!$scan_mode): ?>
<!-- Welcome / How to Use -->
<div class="card shadow mb-4">
    <div class="card-body text-center py-5">
        <i class="fas fa-shield-alt text-primary" style="font-size: 4rem;"></i>
        <h4 class="mt-3">Security Scanner</h4>
        <p class="text-muted">
            আপনার সিস্টেমে ম্যালওয়্যার, ভাইরাস বা সন্দেহজনক কোড আছে কিনা চেক করুন।
        </p>
        <div class="mt-3">
            <a href="?scan=quick" class="btn btn-lg btn-primary me-2">
                <i class="fas fa-bolt"></i> Quick Scan
                <small class="d-block">দ্রুত স্ক্যান (প্রথম ৫০০ ফাইল)</small>
            </a>
            <a href="?scan=full" class="btn btn-lg btn-danger me-2">
                <i class="fas fa-search"></i> Full Scan
                <small class="d-block">সম্পূর্ণ স্ক্যান (সব ফাইল)</small>
            </a>
            <a href="?scan=recent&hours=48" class="btn btn-lg btn-info">
                <i class="fas fa-clock"></i> Recent Files
                <small class="d-block">সম্প্রতি পরিবর্তিত ফাইল</small>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
