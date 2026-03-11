<?php
// test_session.php
// Serve para verificar se o ID do utilizador está mesmo na sessão

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Usa as mesmas definições do teu login
session_set_cookie_params(0, '/');
session_start();

header('Content-Type: application/json');

$response = [
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'status_login' => 'Desconhecido'
];

if (isset($_SESSION['id_utilizador']) && $_SESSION['id_utilizador'] > 0) {
    $response['status_login'] = 'SUCESSO: ID encontrado (' . $_SESSION['id_utilizador'] . ')';
    $response['conclusao'] = 'O api_chat.php DEVE funcionar agora.';
} else {
    $response['status_login'] = 'ERRO: ID não encontrado ou é 0.';
    $response['conclusao'] = 'O problema está no login, não no chat.';
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>