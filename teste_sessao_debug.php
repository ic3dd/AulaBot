<?php
session_start();

$resultado = [
    'timestamp' => date('Y-m-d H:i:s'),
    'session_id' => session_id(),
    'session_exists' => session_status(),
    '_SESSION' => $_SESSION,
    '_COOKIE' => $_COOKIE,
    'headers' => getallheaders()
];

header('Content-Type: application/json');
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
