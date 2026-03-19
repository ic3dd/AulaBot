<?php
// Debug endpoint: lista mensagens recentes (apenas para debug local)
header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) session_start();

// Proteção simples: exigir token via query param ?token=xxx
// Define um token seguro aqui ou, preferível, no ficheiro `config_secrets.php`
$DEBUG_TOKEN = defined('DEBUG_ENDPOINT_TOKEN') ? DEBUG_ENDPOINT_TOKEN : (file_exists(__DIR__ . '/config_secrets.php') ? (function() { require __DIR__ . '/config_secrets.php'; return defined('DEBUG_ENDPOINT_TOKEN') ? DEBUG_ENDPOINT_TOKEN : null; })() : null);
// Caso não exista um token, rejeita para evitar exposição acidental
if (empty($DEBUG_TOKEN)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Debug endpoint desativado (token não configurado).']);
    exit;
}

$provided = $_GET['token'] ?? null;
if (!hash_equals((string)$DEBUG_TOKEN, (string)$provided)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Token inválido.']);
    exit;
}

try {
    if (file_exists('ligarbd.php')) require_once('ligarbd.php');
    if (!isset($con) && isset($conn)) $con = $conn;
    if (!isset($con) && isset($mysqli)) $con = $mysqli;
    if (!isset($con) || !$con instanceof mysqli) throw new Exception('Falha na ligação à BD.');
    db_set_charset($con, 'utf8mb4');

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $limit = max(1, min(100, $limit));

    $resp = [];
    $q = "SELECT id_mensagem AS id, id_chat, pergunta, resposta, data_conversa FROM mensagens ORDER BY data_conversa DESC LIMIT $limit";
    $r = db_query($con, $q);
    if ($r) {
        $rows = [];
        while ($row = db_fetch_assoc($r)) {
            $rows[] = $row;
        }
        $resp['mensagens'] = $rows;
    } else {
        $resp['mensagens'] = [];
        $resp['mensagens_error'] = db_error($con);
    }

    $q2 = "SELECT id_chat, id_utilizador, titulo, data_criacao_chat, data_atualizacao FROM chats ORDER BY data_atualizacao DESC LIMIT 50";
    $r2 = db_query($con, $q2);
    if ($r2) {
        $rows2 = [];
        while ($row = db_fetch_assoc($r2)) $rows2[] = $row;
        $resp['chats'] = $rows2;
    } else {
        $resp['chats'] = [];
        $resp['chats_error'] = db_error($con);
    }

    echo json_encode(['status' => 'success', 'debug' => $resp], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
