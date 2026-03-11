<?php
session_start();
require_once 'ligarbd.php';

header('Content-Type: application/json; charset=utf-8');
if (!isset($con) || !$con) {
    error_log('api_clear_message.php: conexão à BD falhou');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_connection_failed']);
    exit;
}

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

$email = $_SESSION['email'];
$sql = "UPDATE utilizador SET mensagem = 0 WHERE email = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param("s", $email);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to clear message status']);
}

$stmt->close();
$con->close();
?>