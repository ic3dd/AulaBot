<?php
// api_check_message.php
ini_set('display_errors', 0); // Ocultar erros para não partir o JSON
header('Content-Type: application/json; charset=utf-8');

session_start();

// Se não houver email na sessão, sai logo
if (!isset($_SESSION['email'])) {
    echo json_encode(['mensagem' => 0]);
    exit;
}

// Tenta incluir a ligação
require_once 'ligarbd.php';

// Se a ligação falhou ou não existe, devolve 0 em vez de Erro 500
if (!isset($con) || $con->connect_error) {
    echo json_encode(['mensagem' => 0]); 
    exit;
}

$email = $_SESSION['email'];
$stmt = $con->prepare("SELECT mensagem FROM utilizador WHERE email = ?");

if ($stmt) {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['mensagem' => $row['mensagem']]);
    } else {
        echo json_encode(['mensagem' => 0]);
    }
    $stmt->close();
} else {
    echo json_encode(['mensagem' => 0]);
}
?>