<?php
/**
 * Alert Components
 * Reusable alert display functions
 */

/**
 * Display alert box
 * @param string $message Alert message
 * @param string $type Alert type (success, danger, warning, info)
 * @param bool $dismissible Is dismissible
 */
function show_alert($message, $type = 'info', $dismissible = true) {
    $dismiss_class = $dismissible ? 'alert-dismissible fade show' : '';
    $dismiss_button = $dismissible ? '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' : '';
    
    echo '<div class="alert alert-' . $type . ' ' . $dismiss_class . '" role="alert">';
    echo htmlspecialchars($message);
    echo $dismiss_button;
    echo '</div>';
}

/**
 * Display success alert
 */
function show_success($message) {
    show_alert($message, 'success');
}

/**
 * Display error alert
 */
function show_error($message) {
    show_alert($message, 'danger');
}

/**
 * Display warning alert
 */
function show_warning($message) {
    show_alert($message, 'warning');
}

/**
 * Display info alert
 */
function show_info($message) {
    show_alert($message, 'info');
}
?>
