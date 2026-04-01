<?php

/**
 * Backup & Restore Page
 * Manual and automatic database backup management
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/backup_functions.php';

require_login();
// Skip permission check for now - allow all logged in users
// require_permission('manage_backups');

// Handle manual backup creation
if (is_post() && isset($_POST['create_backup'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $result = create_database_backup([
            'type' => 'manual',
            'compress' => isset($_POST['compress']),
            'destinations' => ['local']
        ]);

        if ($result['success']) {
            redirect_with_message(
                $_SERVER['PHP_SELF'],
                "Backup created successfully! File: {$result['backup_name']}",
                'success'
            );
        } else {
            redirect_with_message(
                $_SERVER['PHP_SELF'],
                "Backup failed: {$result['error']}",
                'error'
            );
        }
    }
}

// Handle backup deletion
if (is_post() && isset($_POST['delete_backup'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $backup_id = (int)$_POST['backup_id'];
        $backup = db_select_one('backup_history', ['id' => $backup_id]);

        if ($backup) {
            // Delete file
            if (file_exists($backup['file_path'])) {
                unlink($backup['file_path']);
            }

            // Delete record
            db_delete('backup_history', ['id' => $backup_id]);

            log_activity(get_current_user_id(), 'delete_backup', "Deleted backup: {$backup['backup_name']}");
            redirect_with_message($_SERVER['PHP_SELF'], 'Backup deleted successfully', 'success');
        }
    }
}

// Handle settings update
if (is_post() && isset($_POST['update_settings'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        $data = [
            'auto_backup_enabled' => isset($_POST['auto_backup_enabled']) ? 1 : 0,
            'backup_frequency' => clean_input($_POST['backup_frequency']),
            'backup_time' => clean_input($_POST['backup_time']),
            'retention_days' => (int)$_POST['retention_days'],
            'compress_backups' => isset($_POST['compress_backups']) ? 1 : 0,
            'google_drive_enabled' => isset($_POST['google_drive_enabled']) ? 1 : 0,
            'onedrive_enabled' => isset($_POST['onedrive_enabled']) ? 1 : 0,
            'dropbox_enabled' => isset($_POST['dropbox_enabled']) ? 1 : 0,
            'aws_s3_enabled' => isset($_POST['aws_s3_enabled']) ? 1 : 0
        ];

        // Save Google Drive credentials if provided
        if (!empty($_POST['google_drive_token'])) {
            $data['google_drive_credentials'] = json_encode([
                'access_token' => clean_input($_POST['google_drive_token'])
            ]);
        }
        if (!empty($_POST['google_drive_folder'])) {
            $data['google_drive_folder_id'] = clean_input($_POST['google_drive_folder']);
        }

        // Save OneDrive credentials if provided
        if (!empty($_POST['onedrive_token'])) {
            $data['onedrive_access_token'] = clean_input($_POST['onedrive_token']);
        }

        // Save Dropbox credentials if provided
        if (!empty($_POST['dropbox_token'])) {
            $data['dropbox_access_token'] = clean_input($_POST['dropbox_token']);
        }

        // Save AWS S3 credentials if provided
        if (!empty($_POST['aws_bucket'])) {
            $data['aws_s3_bucket'] = clean_input($_POST['aws_bucket']);
            $data['aws_s3_region'] = clean_input($_POST['aws_region']);
            $data['aws_s3_key'] = clean_input($_POST['aws_access_key']);
            if (!empty($_POST['aws_secret_key'])) {
                $data['aws_s3_secret'] = clean_input($_POST['aws_secret_key']);
            }
        }

        db_update('backup_settings', $data, ['id' => 1]);
        redirect_with_message($_SERVER['PHP_SELF'], 'Settings updated successfully', 'success');
    }
}

// Get backup settings
$settings = db_select_one('backup_settings', ['id' => 1]);
if (!$settings) {
    // Create default settings
    db_insert('backup_settings', ['id' => 1]);
    $settings = db_select_one('backup_settings', ['id' => 1]);
}

// Get backup history
$backups = db_query("
    SELECT bh.*, u.username as created_by_name
    FROM backup_history bh
    LEFT JOIN users u ON bh.created_by = u.id
    ORDER BY bh.created_at DESC
    LIMIT 50
");

// Get backup statistics
$stats = get_backup_stats();

// Get import history
$imports = db_query("
    SELECT ih.*, u.username as imported_by_name
    FROM import_history ih
    LEFT JOIN users u ON ih.imported_by = u.id
    ORDER BY ih.imported_at DESC
    LIMIT 20
");

$page_title = 'Backup & Restore';
$page_actions = '<button onclick="window.print()" class="btn btn-success no-print"><i class="fas fa-print"></i> Print Report</button>';

include __DIR__ . '/../../templates/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/backup-custom.css">
<style>
    @media print {
        .no-print {
            display: none !important;
        }
    }
</style>

<!-- Statistics Cards -->
<div class="row mb-4 no-print animate-fade-in">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card backup-stats-card shadow h-100 py-2 border-left-primary">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Backups</div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800"><?= $stats['total_backups'] ?? 0 ?></div>
                    </div>
                    <div class="col-auto">
                        <div class="icon-circle bg-primary text-white">
                            <i class="fas fa-database"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card backup-stats-card shadow h-100 py-2 border-left-success">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Size</div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            <?= format_bytes($stats['total_size'] ?? 0) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="icon-circle bg-success text-white">
                            <i class="fas fa-hdd"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card backup-stats-card shadow h-100 py-2 border-left-info">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Last Backup</div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            <?= $stats['last_backup'] ? format_date($stats['last_backup']) : 'Never' ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="icon-circle bg-info text-white">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card backup-stats-card shadow h-100 py-2 border-left-warning">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Auto Backups</div>
                        <div class="h4 mb-0 font-weight-bold text-gray-800">
                            <?= $settings['auto_backup_enabled'] ? 'Enabled' : 'Disabled' ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="icon-circle bg-warning text-white">
                            <i class="fas fa-cog"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row no-print animate-fade-in">
    <!-- Manual Backup -->
    <div class="col-md-6 mb-4">
        <div class="card backup-action-card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-download mr-1"></i> Create Manual Backup
                </h6>
            </div>
            <div class="card-body">
                <form id="manualBackupForm">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="create_backup" value="1">

                    <div class="form-group">
                        <div class="custom-control custom-switch mb-3">
                            <input type="checkbox" class="custom-control-input" id="compress" name="compress" checked>
                            <label class="custom-control-label font-weight-bold" for="compress">Compress into ZIP (Includes Database & All Media/Uploads)</label>
                        </div>
                    </div>

                    <div class="bg-light p-3 rounded border-left-info mb-4">
                        <i class="fas fa-info-circle text-info mr-2"></i>
                        <span class="small text-dark">This generates a full snapshot of your database and all uploaded files (Media), ensuring no data is lost during system updates.</span>
                    </div>

                    <button type="submit" class="btn btn-premium btn-premium-primary btn-block">
                        <span class="btn-text"><i class="fas fa-rocket mr-2"></i> Create Backup Now</span>
                        <div class="backup-loader"></div>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Backup -->
    <div class="col-md-6 mb-4">
        <div class="card backup-action-card card-success shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-upload mr-1"></i> Import/Restore Backup
                </h6>
            </div>
            <div class="card-body">
                <form id="importForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="font-weight-bold small">Select Backup File (.sql, .zip)</label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" name="backup_file" id="backupFile" accept=".sql,.zip" required>
                            <label class="custom-file-label" for="backupFile">Choose file...</label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small">Import Strategy</label>
                        <select class="form-control form-control-sm" name="import_mode" id="importMode">
                            <option value="skip_duplicates">Skip Existing Records (Safe)</option>
                            <option value="update_existing">Overwrite Existing Records</option>
                            <option value="merge_smart">Intelligent Merge</option>
                        </select>
                    </div>

                    <div class="bg-light p-3 rounded border-left-warning mb-4">
                        <i class="fas fa-exclamation-triangle text-warning mr-2"></i>
                        <span class="small text-dark"><strong>Caution:</strong> Importing may overwrite or modify your recent data. Always backup before importing.</span>
                    </div>

                    <button type="submit" class="btn btn-premium btn-premium-success btn-block">
                        <i class="fas fa-file-import mr-2"></i> Begin Import Process
                    </button>
                </form>

                <div id="importProgress" class="mt-4" style="display:none;">
                    <div class="progress rounded-pill overflow-hidden" style="height: 10px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                            role="progressbar" style="width: 100%"></div>
                    </div>
                    <p class="text-center mt-2 small text-muted">Synchronizing data, please wait...</p>
                </div>

                <div id="importResult" class="mt-3" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Automatic Backup Settings -->
<div class="card backup-action-card shadow mb-4 no-print animate-fade-in">
    <div class="card-header py-3 d-flex align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold">
            <i class="fas fa-robot mr-1"></i> Automated Backup Engine
        </h6>
        <span class="badge badge-light badge-pill"><?= $settings['auto_backup_enabled'] ? 'ACTIVE' : 'INACTIVE' ?></span>
    </div>
    <div class="card-body">

        <div class="bg-light p-3 rounded mb-4 border-left-info">
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-terminal text-primary mr-2"></i>
                <h6 class="m-0 font-weight-bold small">System Integration Guide</h6>
            </div>
            <p class="small text-muted mb-2">To automate backups, ensure your system's scheduler is configured to execute our trigger script.</p>
            <div class="input-group input-group-sm">
                <input type="text" class="form-control bg-white" readonly value="<?= realpath(__DIR__ . '/../../cron/setup-auto-backup.bat') ?>">
                <div class="input-group-append">
                    <button class="btn btn-outline-primary" type="button" onclick="copyToClipboard(this)">Copy Path</button>
                </div>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Automation Status</label>
                        <div class="custom-control custom-switch mt-1">
                            <input type="checkbox" class="custom-control-input" id="autoBackup"
                                name="auto_backup_enabled"
                                <?= $settings['auto_backup_enabled'] ? 'checked' : '' ?>>
                            <label class="custom-control-label h6 mb-0" for="autoBackup">Enable Engine</label>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Scan Frequency</label>
                        <select name="backup_frequency" class="form-control form-control-sm">
                            <option value="daily" <?= $settings['backup_frequency'] == 'daily' ? 'selected' : '' ?>>Daily Cycle</option>
                            <option value="weekly" <?= $settings['backup_frequency'] == 'weekly' ? 'selected' : '' ?>>Weekly Cycle</option>
                            <option value="monthly" <?= $settings['backup_frequency'] == 'monthly' ? 'selected' : '' ?>>Monthly Cycle</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Execution Time</label>
                        <input type="time" name="backup_time" class="form-control form-control-sm"
                            value="<?= $settings['backup_time'] ?>">
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small font-weight-bold">Retention Policy (Days)</label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="retention_days" class="form-control"
                                value="<?= $settings['retention_days'] ?>" min="1" max="365">
                            <div class="input-group-append">
                                <span class="input-group-text">Days</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group mb-4">
                <div class="custom-control custom-switch mt-1">
                    <input type="checkbox" class="custom-control-input" id="compressAuto"
                        name="compress_backups"
                        <?= $settings['compress_backups'] ? 'checked' : '' ?>>
                    <label class="custom-control-label small" for="compressAuto">Apply high compression to all automated backups</label>
                </div>
            </div>

            <hr class="my-4">

            <h6 class="text-dark mb-4 d-flex align-items-center">
                <i class="fas fa-cloud text-primary mr-2"></i> Hybrid Cloud Storage Synchronization
            </h6>

            <div class="row">
                <!-- Google Drive -->
                <div class="col-lg-6">
                    <div class="cloud-dest-card p-3 animate-fade-in">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="enableGoogleDrive"
                                    name="google_drive_enabled" value="1"
                                    <?= !empty($settings['google_drive_enabled']) ? 'checked' : '' ?>>
                                <label class="custom-control-label font-weight-bold" for="enableGoogleDrive">
                                    <i class="fab fa-google-drive text-success mr-1"></i> Google Drive
                                </label>
                            </div>
                            <?php if (!empty($settings['google_drive_credentials'])): ?>
                                <span class="badge badge-premium badge-success"><i class="fas fa-check-circle"></i> Connected</span>
                            <?php else: ?>
                                <span class="badge badge-premium badge-light text-muted border">Disconnected</span>
                            <?php endif; ?>
                        </div>
                        <p class="small text-muted mb-3">Sync snapshots to your secure Google Drive storage.</p>
                        <button type="button" class="btn btn-sm btn-light border text-primary font-weight-bold w-100" data-bs-toggle="collapse" data-bs-target="#gdriveForm">
                            <i class="fas fa-sliders-h mr-1"></i> Configuration Settings
                        </button>
                        <div class="collapse mt-3" id="gdriveForm">
                            <div class="form-group mb-2">
                                <label class="small mb-1">API Access Token</label>
                                <input type="password" name="google_drive_token" class="form-control form-control-sm" placeholder="••••••••••••••••">
                            </div>
                            <div class="form-group mb-0">
                                <label class="small mb-1">Cloud Folder ID</label>
                                <input type="text" name="google_drive_folder" class="form-control form-control-sm" value="<?= htmlspecialchars($settings['google_drive_folder_id'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- OneDrive -->
                <div class="col-lg-6">
                    <div class="cloud-dest-card p-3 animate-fade-in">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="enableOneDrive"
                                    name="onedrive_enabled" value="1"
                                    <?= !empty($settings['onedrive_enabled']) ? 'checked' : '' ?>>
                                <label class="custom-control-label font-weight-bold" for="enableOneDrive">
                                    <i class="fab fa-microsoft text-primary mr-1"></i> OneDrive
                                </label>
                            </div>
                            <?php if (!empty($settings['onedrive_access_token'])): ?>
                                <span class="badge badge-premium badge-success"><i class="fas fa-check-circle"></i> Connected</span>
                            <?php else: ?>
                                <span class="badge badge-premium badge-light text-muted border">Disconnected</span>
                            <?php endif; ?>
                        </div>
                        <p class="small text-muted mb-3">Enterprise-grade storage via Microsoft Azure Graph.</p>
                        <button type="button" class="btn btn-sm btn-light border text-primary font-weight-bold w-100" data-bs-toggle="collapse" data-bs-target="#onedriveForm">
                            <i class="fas fa-sliders-h mr-1"></i> Configuration Settings
                        </button>
                        <div class="collapse mt-3" id="onedriveForm">
                            <div class="form-group mb-0">
                                <label class="small mb-1">Microsoft Access Token</label>
                                <input type="password" name="onedrive_token" class="form-control form-control-sm" placeholder="••••••••••••••••">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dropbox -->
                <div class="col-lg-6">
                    <div class="cloud-dest-card p-3 animate-fade-in">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="enableDropbox"
                                    name="dropbox_enabled" value="1"
                                    <?= !empty($settings['dropbox_enabled']) ? 'checked' : '' ?>>
                                <label class="custom-control-label font-weight-bold" for="enableDropbox">
                                    <i class="fab fa-dropbox text-info mr-1"></i> Dropbox
                                </label>
                            </div>
                            <?php if (!empty($settings['dropbox_access_token'])): ?>
                                <span class="badge badge-premium badge-success"><i class="fas fa-check-circle"></i> Connected</span>
                            <?php else: ?>
                                <span class="badge badge-premium badge-light text-muted border">Disconnected</span>
                            <?php endif; ?>
                        </div>
                        <p class="small text-muted mb-3">Sync to personal Dropbox accounts for quick access.</p>
                        <button type="button" class="btn btn-sm btn-light border text-primary font-weight-bold w-100" data-bs-toggle="collapse" data-bs-target="#dropboxForm">
                            <i class="fas fa-sliders-h mr-1"></i> Configuration Settings
                        </button>
                        <div class="collapse mt-3" id="dropboxForm">
                            <div class="form-group mb-0">
                                <label class="small mb-1">Dropbox App API Token</label>
                                <input type="password" name="dropbox_token" class="form-control form-control-sm" placeholder="••••••••••••••••">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AWS S3 -->
                <div class="col-lg-6">
                    <div class="cloud-dest-card p-3 animate-fade-in">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="enableAwsS3"
                                    name="aws_s3_enabled" value="1"
                                    <?= !empty($settings['aws_s3_enabled']) ? 'checked' : '' ?>>
                                <label class="custom-control-label font-weight-bold" for="enableAwsS3">
                                    <i class="fab fa-aws text-warning mr-1"></i> AWS S3
                                </label>
                            </div>
                            <?php if (!empty($settings['aws_s3_bucket'])): ?>
                                <span class="badge badge-premium badge-success"><i class="fas fa-check-circle"></i> Connected</span>
                            <?php else: ?>
                                <span class="badge badge-premium badge-light text-muted border">Disconnected</span>
                            <?php endif; ?>
                        </div>
                        <p class="small text-muted mb-3">Industrial-strength immutable storage on Amazon S3.</p>
                        <button type="button" class="btn btn-sm btn-light border text-primary font-weight-bold w-100" data-bs-toggle="collapse" data-bs-target="#awsForm">
                            <i class="fas fa-sliders-h mr-1"></i> Configuration Settings
                        </button>
                        <div class="collapse mt-3" id="awsForm">
                            <div class="form-group mb-2">
                                <label class="small mb-1">Bucket & Region</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="aws_bucket" class="form-control" placeholder="Bucket" value="<?= htmlspecialchars($settings['aws_s3_bucket'] ?? '') ?>">
                                    <select name="aws_region" class="form-control">
                                        <option value="us-east-1" <?= ($settings['aws_s3_region'] ?? '') == 'us-east-1' ? 'selected' : '' ?>>US-East-1</option>
                                        <option value="ap-southeast-1" <?= ($settings['aws_s3_region'] ?? '') == 'ap-southeast-1' ? 'selected' : '' ?>>AP-SE-1</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label class="small mb-1">Access Key & Secret</label>
                                <input type="text" name="aws_access_key" class="form-control form-control-sm mb-1" placeholder="Access Key ID" value="<?= htmlspecialchars($settings['aws_s3_key'] ?? '') ?>">
                                <input type="password" name="aws_secret_key" class="form-control form-control-sm" placeholder="Secret Key">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top text-right">
                <button type="submit" name="update_settings" class="btn btn-premium btn-premium-primary">
                    <i class="fas fa-save mr-2"></i> Commit All Settings
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Backup History -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-history"></i> Backup History
        </h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="backupsTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Backup Name</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Tables</th>
                        <th>Records</th>
                        <th>Status</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td><?= format_date($backup['created_at']) ?></td>
                            <td><?= htmlspecialchars($backup['backup_name']) ?></td>
                            <td>
                                <?php
                                $type_badges = [
                                    'automatic' => 'info',
                                    'manual' => 'primary',
                                    'media' => 'success'
                                ];
                                $type_badge = $type_badges[$backup['backup_type']] ?? 'secondary';
                                ?>
                                <span class="badge badge-<?= $type_badge ?>">
                                    <?php if ($backup['backup_type'] === 'media'): ?>
                                        <i class="fas fa-photo-video mr-1"></i>
                                    <?php endif; ?>
                                    <?= ucfirst($backup['backup_type']) ?>
                                </span>
                            </td>
                            <td><?= format_bytes($backup['backup_size']) ?></td>
                            <td><?= $backup['tables_backed_up'] ?></td>
                            <td><?= number_format($backup['total_records']) ?></td>
                            <td>
                                <?php
                                $badge_class = [
                                    'success' => 'success',
                                    'failed' => 'danger',
                                    'partial' => 'warning'
                                ];
                                ?>
                                <span class="badge badge-<?= $badge_class[$backup['status']] ?>">
                                    <?= ucfirst($backup['status']) ?>
                                </span>
                            </td>
                            <td class="no-print">
                                <?php if ($backup['status'] == 'success' && file_exists($backup['file_path'])): ?>
                                    <a href="download-backup.php?id=<?= $backup['id'] ?>"
                                        class="btn btn-sm btn-primary" title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-danger"
                                    onclick="deleteBackup(<?= $backup['id'] ?>, '<?= htmlspecialchars($backup['backup_name']) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Import History -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-file-import"></i> Import History
        </h6>
        <?php if ($_SESSION['user_role_id'] == 1): ?>
            <button type="button" class="btn btn-sm btn-outline-danger shadow-sm" onclick="clearImportHistory()">
                <i class="fas fa-eraser mr-1"></i> Clear All History
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="importsTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>File</th>
                        <th>Mode</th>
                        <th>Imported</th>
                        <th>Skipped</th>
                        <th>Failed</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($imports as $import): ?>
                        <tr>
                            <td><?= format_date($import['imported_at']) ?></td>
                            <td><?= htmlspecialchars($import['import_file']) ?></td>
                            <td><?= str_replace('_', ' ', ucfirst($import['import_mode'])) ?></td>
                            <td><span class="badge badge-success"><?= $import['imported_records'] ?></span></td>
                            <td><span class="badge badge-warning"><?= $import['skipped_records'] ?></span></td>
                            <td><span class="badge badge-danger"><?= $import['failed_records'] ?></span></td>
                            <td>
                                <span class="badge badge-<?= $import['status'] == 'success' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($import['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="delete_backup" value="1">
    <input type="hidden" name="backup_id" id="deleteBackupId">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
</form>


<?php include __DIR__ . '/../../templates/footer.php'; ?>

<script>
    $(document).ready(function() {
        $('#backupsTable').DataTable({
            "pageLength": 25,
            "order": [[0, "desc"]],
            "responsive": true
        });

        $('#importsTable').DataTable({
            "pageLength": 10,
            "order": [[0, "desc"]],
            "responsive": true
        });

        // Copy path helper
        window.copyToClipboard = function(btn) {
            const input = $(btn).closest('.input-group').find('input')[0];
            input.select();
            document.execCommand('copy');
            const originalText = $(btn).text();
            $(btn).text('Copied!').addClass('btn-success').removeClass('btn-outline-primary');
            setTimeout(() => {
                $(btn).text(originalText).addClass('btn-outline-primary').removeClass('btn-success');
            }, 2000);
        };

        // Handle Manual Backup AJAX
        $('#manualBackupForm').on('submit', function(e) {
            e.preventDefault();
            const $btn = $(this).find('button');
            const $loader = $btn.find('.backup-loader');
            const $text = $btn.find('.btn-text');

            $btn.prop('disabled', true);
            $loader.show();
            $text.text('Creating Snapshot...');

            $.ajax({
                url: '<?= BASE_URL ?>/api/backup/create.php',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    $loader.hide();
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Backup Successful!',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Backup Failed',
                            text: response.error
                        });
                        $btn.prop('disabled', false);
                        $text.html('<i class="fas fa-rocket mr-2"></i> Create Backup Now');
                    }
                },
                error: function() {
                    $loader.hide();
                    Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not connect to the backup server.' });
                    $btn.prop('disabled', false);
                    $text.html('<i class="fas fa-rocket mr-2"></i> Create Backup Now');
                }
            });
        });

        // Handle import form
        $('#importForm').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            $('#importProgress').show();
            $('#importResult').hide();

            $.ajax({
                url: '<?= BASE_URL ?>/api/backup/import.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#importProgress').hide();
                    $('#importResult').html(formatImportResult(response)).show();

                    if (response.success) {
                        Swal.fire({ icon: 'success', title: 'Import Complete', text: 'System data has been synchronized.', timer: 2000 });
                        setTimeout(() => location.reload(), 2000);
                    }
                },
                error: function() {
                    $('#importProgress').hide();
                    $('#importResult').html('<div class="alert alert-danger">Import failed!</div>').show();
                }
            });
        });

        // Show selected filename
        $('.custom-file-input').on('change', function() {
            let fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').addClass("selected").html(fileName);
        });
    });

    /**
     * Secured Import History Cleanup
     * Requires Super Admin password verification
     */
    function clearImportHistory() {
        Swal.fire({
            title: 'Clear Import History?',
            text: "This will permanently remove all synchronization records. Enter your password to continue:",
            icon: 'warning',
            input: 'password',
            inputAttributes: {
                autocapitalize: 'off',
                placeholder: 'Enter your password'
            },
            showCancelButton: true,
            confirmButtonText: 'Clear Now',
            confirmButtonColor: '#e74a3b',
            showLoaderOnConfirm: true,
            preConfirm: (password) => {
                if (!password) {
                    Swal.showValidationMessage('Please enter your password');
                    return false;
                }
                return $.ajax({
                    url: '<?= BASE_URL ?>/api/backup/clear-import-history.php',
                    type: 'POST',
                    data: {
                        password: password,
                        csrf_token: '<?= generate_csrf_token() ?>'
                    }
                }).then(response => {
                    if (!response.success) {
                        throw new Error(response.error || 'Server error');
                    }
                    return response;
                }).catch(error => {
                    Swal.showValidationMessage(`Error: ${error.message}`);
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Cleared!',
                    text: result.value.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => location.reload());
            }
        });
    }

    function formatImportResult(result) {
        if (!result.success) {
            return `<div class="alert alert-danger border-0 shadow-sm">${result.error}</div>`;
        }

        const stats = result.stats;
        return `
        <div class="alert alert-success border-0 shadow-sm">
            <h6 class="font-weight-bold"><i class="fas fa-check-circle mr-1"></i> Synchronization Results</h6>
            <div class="row small mt-2">
                <div class="col-6">Imported: <strong>${stats.imported}</strong></div>
                <div class="col-6">Skipped: <strong>${stats.skipped}</strong></div>
                <div class="col-6">Updated: <strong>${stats.updated}</strong></div>
                <div class="col-6">Failed: <strong>${stats.failed}</strong></div>
            </div>
        </div>
    `;
    }

    function deleteBackup(id, name) {
        Swal.fire({
            title: 'Delete Backup?',
            text: `Are you sure you want to permanently delete "${name}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74a3b',
            confirmButtonText: 'Yes, delete it'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#deleteBackupId').val(id);
                $('#deleteForm').submit();
            }
        });
    }
</script>