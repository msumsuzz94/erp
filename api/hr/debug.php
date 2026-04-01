<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    file_put_contents(__DIR__ . '/js_debug.txt', date('Y-m-d H:i:s') . "\n" . $input . "\n\n", FILE_APPEND);
    exit;
}
echo "Debug receiver active";
