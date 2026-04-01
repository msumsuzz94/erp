<?php

/**
 * Media Management Module
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permissions.php';

require_login();
require_permission('media');

$page_title = 'Media Management';
$page_actions = '';
$additional_css = '
<style>
.media-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    height: 100%;
}
.media-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}
.media-preview-container {
    height: 180px;
    background: var(--body-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: calc(0.35rem - 1px) calc(0.35rem - 1px) 0 0;
}
.media-preview-container img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
.media-info {
    font-size: 0.8rem;
    color: var(--text-secondary);
}
.media-name {
    font-size: 0.9rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 5px;
}

/* List View Styles */
.media-container.view-list .row {
    flex-direction: column;
}
.media-container.view-list .col-xl-2 {
    width: 100%;
    max-width: 100%;
    flex: 0 0 100%;
}
.media-container.view-list .media-card {
    display: flex;
    flex-direction: row;
    height: auto;
    align-items: center;
    padding: 10px;
}
.media-container.view-list .media-preview-container {
    height: 60px;
    width: 80px;
    border-radius: 5px;
    flex-shrink: 0;
    margin-right: 15px;
}
.media-container.view-list .card-body {
    display: flex;
    flex: 1;
    align-items: center;
    justify-content: space-between;
    padding: 0;
}
.media-container.view-list .media-info {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 0;
}
.media-container.view-list .media-name {
    margin-bottom: 0;
    min-width: 250px;
}
.media-container.view-list .btn-delete {
    width: auto;
    padding: 0.25rem 0.75rem;
}

/* Dark Mode Search Input Placeholder Fix */
[data-theme="dark"] #mediaSearch::placeholder {
    color: #9baec8 !important; /* Visible muted color for dark mode */
    opacity: 1 !important;
}
[data-theme="dark"] #mediaSearch {
    color: var(--text-primary) !important;
    background-color: var(--input-bg) !important;
}
[data-theme="dark"] .input-group-text.bg-light {
    background-color: var(--input-bg) !important;
    border-color: var(--border-color) !important;
}
</style>
';

// Handle Delete Request via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'delete_media') {
    header('Content-Type: application/json');
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $filename = basename($_POST['filename'] ?? '');
        $folder = basename($_POST['folder'] ?? '');

        // Reconstruct path securely without taking full path from client
        $file_path = "uploads/{$folder}/{$filename}";
        $absolute_path = BASE_PATH . '/' . $file_path;

        // Basic security check to ensure it's in the uploads folder and no traversal
        if (!empty($filename) && !empty($folder) && strpos($file_path, '..') === false && file_exists($absolute_path)) {
            if (unlink($absolute_path)) {
                echo json_encode(['status' => 'success', 'message' => 'File deleted successfully.']);
                exit;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete file.']);
                exit;
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid file or file not found.']);
            exit;
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token.']);
        exit;
    }
}

// Function to scan directory for images
function scan_uploads_dir($dir, &$results = array(), $depth = 0)
{
    if ($depth > 5) return $results; // Prevent infinite recursion

    // Normalize directory path to use forward slashes (Linux/cPanel compatible)
    $dir = rtrim(str_replace('\\', '/', $dir), '/');

    if (!is_dir($dir)) return $results;

    $files = @scandir($dir);
    if ($files === false) return $results;

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    foreach ($files as $value) {
        if ($value === '.' || $value === '..') continue;

        $path = $dir . '/' . $value;

        if (is_dir($path)) {
            scan_uploads_dir($path, $results, $depth + 1);
        } else {
            $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed_extensions)) {
                $results[] = $path;
            }
        }
    }
    return $results;
}

$uploads_path = BASE_PATH . '/uploads';
$all_images = [];
if (file_exists($uploads_path)) {
    scan_uploads_dir($uploads_path, $all_images);
}



include __DIR__ . '/../../templates/header.php';
?>



<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <h6 class="m-0 font-weight-bold text-primary">Media Library - Uploads</h6>
            <span class="badge badge-info" id="mediaCount"><?= count($all_images) ?> Images</span>
        </div>

        <div class="d-flex align-items-center gap-2 w-100" style="max-width: 500px;">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="mediaSearch" class="form-control border-start-0 ps-0" placeholder="Search files...">
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary active" id="btnGridView" title="Grid View">
                    <i class="fas fa-th"></i>
                </button>
                <button type="button" class="btn btn-outline-primary" id="btnListView" title="List View">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($all_images)): ?>
            <div class="text-center py-5">
                <i class="fas fa-images fa-4x text-muted mb-3"></i>
                <p class="text-muted">No images found in the uploads directory.</p>
            </div>
        <?php else: ?>
            <div class="media-container view-grid" id="mediaContainer">
                <div class="row">
                    <?php foreach ($all_images as $image_path):
                        // Normalize all paths to forward slashes for cross-platform compatibility
                        $normalized_base = rtrim(str_replace('\\', '/', BASE_PATH), '/');
                        $normalized_image = str_replace('\\', '/', $image_path);
                        $relative_path = str_replace($normalized_base . '/', '', $normalized_image);
                        $web_url = BASE_URL . '/' . $relative_path;

                        $filesize = @filesize($image_path) ?: 0;
                        $file_info = pathinfo($image_path);
                        $dimensions = @getimagesize($image_path);
                        $dim_str = $dimensions ? "{$dimensions[0]}x{$dimensions[1]}" : 'Unknown';
                    ?>
                        <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-4 media-item" data-filename="<?= htmlspecialchars(basename($image_path)) ?>">
                            <div class="card media-card">
                                <div class="media-preview-container">
                                    <a href="<?= $web_url ?>" target="_blank">
                                        <img src="<?= $web_url ?>" alt="<?= htmlspecialchars($file_info['basename']) ?>" loading="lazy">
                                    </a>
                                </div>
                                <div class="card-body p-2">
                                    <div class="media-name" title="<?= htmlspecialchars($file_info['basename']) ?>">
                                        <?= htmlspecialchars($file_info['basename']) ?>
                                    </div>
                                    <div class="media-info mb-2 text-muted">
                                        <div><i class="fas fa-folder-open fa-fw"></i> <?= htmlspecialchars(dirname($relative_path)) ?></div>
                                        <div><i class="fas fa-hdd fa-fw"></i> <?= format_bytes($filesize) ?></div>
                                        <div><i class="fas fa-expand fa-fw"></i> <?= $dim_str ?></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-resize"
                                            data-filename="<?= htmlspecialchars($file_info['basename']) ?>"
                                            data-folder="<?= htmlspecialchars(basename(dirname($relative_path))) ?>"
                                            data-width="<?= $dimensions ? $dimensions[0] : '' ?>"
                                            data-height="<?= $dimensions ? $dimensions[1] : '' ?>">
                                            <i class="fas fa-compress-arrows-alt"></i> Resize
                                        </button>

                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-media"
                                            data-filename="<?= htmlspecialchars($file_info['basename']) ?>"
                                            data-folder="<?= htmlspecialchars(basename(dirname($relative_path))) ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </div>
    </div>

    <!-- Resize Modal -->
    <div class="modal fade" id="resizeModal" tabindex="-1" aria-labelledby="resizeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resizeModalLabel">Resize Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="resizeForm">
                    <div class="modal-body">
                        <p>Image: <strong id="resizeFileName"></strong></p>
                        <input type="hidden" id="resizeFilename" name="filename">
                        <input type="hidden" id="resizeFolder" name="folder">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="resizeWidth">Width (px)</label>
                                    <input type="number" class="form-control" id="resizeWidth" name="width" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="resizeHeight">Height (px)</label>
                                    <input type="number" class="form-control" id="resizeHeight" name="height" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="maintainRatio" checked>
                            <label class="form-check-label" for="maintainRatio">
                                Maintain Aspect Ratio
                            </label>
                        </div>

                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-exclamation-triangle"></i> This action will overwrite the original image file.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="btnResizeSubmit">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php
    ob_start();
    ?>
    <script>
        $(document).ready(function() {
            let originalWidth = 0;
            let originalHeight = 0;
            let aspectRatio = 1;
            let isEditingWidth = false;
            let isEditingHeight = false;

            // Search functionality
            $('#mediaSearch').on('keyup', function() {
                const searchTerm = $(this).val().toLowerCase();
                let visibleCount = 0;

                $('.media-item').each(function() {
                    const filename = $(this).data('filename').toLowerCase();
                    if (filename.includes(searchTerm)) {
                        $(this).show();
                        visibleCount++;
                    } else {
                        $(this).hide();
                    }
                });

                $('#mediaCount').text(visibleCount + ' Images');
            });

            // View Toggle functionality
            $('#btnGridView').click(function() {
                $('#mediaContainer').removeClass('view-list').addClass('view-grid');
                $(this).addClass('active');
                $('#btnListView').removeClass('active');
                // Save preference
                localStorage.setItem('mediaViewPref', 'grid');
            });

            $('#btnListView').click(function() {
                $('#mediaContainer').removeClass('view-grid').addClass('view-list');
                $(this).addClass('active');
                $('#btnGridView').removeClass('active');
                // Save preference
                localStorage.setItem('mediaViewPref', 'list');
            });

            // Load saved preference
            const savedView = localStorage.getItem('mediaViewPref');
            if (savedView === 'list') {
                $('#btnListView').click();
            }

            $('.btn-resize').click(function() {
                const filename = $(this).data('filename');
                const folder = $(this).data('folder');
                originalWidth = parseInt($(this).data('width')) || 0;
                originalHeight = parseInt($(this).data('height')) || 0;

                if (originalWidth && originalHeight) {
                    aspectRatio = originalWidth / originalHeight;
                } else {
                    aspectRatio = 1;
                }

                $('#resizeFileName').text(filename);
                $('#resizeFilename').val(filename);
                $('#resizeFolder').val(folder);
                $('#resizeWidth').val(originalWidth);
                $('#resizeHeight').val(originalHeight);

                var myModal = new bootstrap.Modal(document.getElementById('resizeModal'));
                myModal.show();
            });

            $('#resizeWidth').on('input', function() {
                if ($('#maintainRatio').is(':checked') && !isEditingHeight) {
                    isEditingWidth = true;
                    const newWidth = parseInt($(this).val()) || 0;
                    if (newWidth > 0 && aspectRatio > 0) {
                        $('#resizeHeight').val(Math.round(newWidth / aspectRatio));
                    }
                    isEditingWidth = false;
                }
            });

            $('#resizeHeight').on('input', function() {
                if ($('#maintainRatio').is(':checked') && !isEditingWidth) {
                    isEditingHeight = true;
                    const newHeight = parseInt($(this).val()) || 0;
                    if (newHeight > 0 && aspectRatio > 0) {
                        $('#resizeWidth').val(Math.round(newHeight * aspectRatio));
                    }
                    isEditingHeight = false;
                }
            });

            $('#resizeForm').submit(function(e) {
                e.preventDefault();

                const filename = $('#resizeFilename').val();
                const folder = $('#resizeFolder').val();
                const width = $('#resizeWidth').val();
                const height = $('#resizeHeight').val();

                $('#btnResizeSubmit').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

                $.ajax({
                    url: '<?= BASE_URL ?>/modules/media_manager/resize-action.php',
                    type: 'POST',
                    data: {
                        csrf_token: '<?= generate_csrf_token() ?>',
                        filename: filename,
                        folder: folder,
                        width: width,
                        height: height
                    },
                    success: function(response) {
                        try {
                            const res = typeof response === 'string' ? JSON.parse(response) : response;
                            if (res.status === 'success') {
                                location.reload();
                            } else {
                                showAlert(res.message || 'Error resizing image', 'error');
                                $('#btnResizeSubmit').prop('disabled', false).html('<i class="fas fa-save"></i> Save Changes');
                            }
                        } catch (e) {
                            showAlert('Failed to process response', 'error');
                            $('#btnResizeSubmit').prop('disabled', false).html('<i class="fas fa-save"></i> Save Changes');
                        }
                    },
                    error: function() {
                        showAlert('Server error occurred', 'error');
                        $('#btnResizeSubmit').prop('disabled', false).html('<i class="fas fa-save"></i> Save Changes');
                    }
                });
            });

            // Handle sweet alert confirmation for media deletion via AJAX
            $('.btn-delete-media').click(function(e) {
                e.preventDefault();
                const btn = $(this);
                const filename = btn.data('filename');
                const folder = btn.data('folder');

                confirmDelete('Are you sure you want to permanently delete this image? This might break links where it is used.').then((result) => {
                    if (result.isConfirmed) {
                        // Show loading state
                        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);

                        $.ajax({
                            url: '<?= BASE_URL ?>/modules/media_manager/index.php',
                            type: 'POST',
                            data: {
                                action: 'delete_media',
                                csrf_token: '<?= generate_csrf_token() ?>',
                                filename: filename,
                                folder: folder
                            },
                            success: function(response) {
                                try {
                                    const res = typeof response === 'string' ? JSON.parse(response) : response;
                                    if (res.status === 'success') {
                                        // Remove the item from DOM instead of reloading entirely
                                        btn.closest('.media-item').fadeOut(300, function() {
                                            $(this).remove();
                                            // Update count
                                            let count = $('.media-item').length;
                                            $('#mediaCount').text(count + ' Images');
                                            if (count === 0) location.reload();
                                        });
                                        showAlert(res.message, 'success');
                                    } else {
                                        showAlert(res.message || 'Error deleting image', 'error');
                                        btn.html('<i class="fas fa-trash"></i>').prop('disabled', false);
                                    }
                                } catch (e) {
                                    showAlert('Failed to process response', 'error');
                                    btn.html('<i class="fas fa-trash"></i>').prop('disabled', false);
                                }
                            },
                            error: function() {
                                showAlert('Server error occurred', 'error');
                                btn.html('<i class="fas fa-trash"></i>').prop('disabled', false);
                            }
                        });
                    }
                });
            });
        });
    </script>
    <?php
    $additional_js = ob_get_clean();
    include __DIR__ . '/../../templates/footer.php';
    ?>