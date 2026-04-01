<?php
/**
 * Redirect for pos-create.php
 * Resolves 404 error and points to the main POS interface
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header("Location: " . BASE_URL . "/modules/sales/pos.php");
exit;
