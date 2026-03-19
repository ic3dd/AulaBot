<?php
// api_check_unread.php - Versão Corrigida
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/../ligarbd.php';

$response = ['unread' => false];

if (isset($_SESSION['email']) && isset($con)) {
    $email = $_SESSION['email'];
    
    // Verifica se a tabela/coluna existe antes de tentar
    $stmt = $con->prepare("SELECT mensagem FROM utilizador WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($row && $row['mensagem'] == 1) {
            $response['unread'] = true;
        }
        $stmt->close();
    }
}

echo json_encode($response);
?>