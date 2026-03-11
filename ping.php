<?php
header('Content-Type: application/json; charset=utf-8');
// Simple health-check endpoint to verify PHP requests return JSON instead of an HTML 500
try {
    echo json_encode(['ok' => true, 'time' => date('c'), 'server' => php_sapi_name()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'exception', 'message' => 'Internal Server Error']);
}
?>