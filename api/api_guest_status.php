<?php
// Start session early to avoid header/ session issues
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=UTF-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);
try {
    if (file_exists('ligarbd.php')) {
        require_once(__DIR__ . '/../ligarbd.php');
    } else {
        throw new Exception('ligarbd.php não encontrado');
    }
    
    if (!isset($con) && isset($conn)) $con = $conn;
    if (!isset($con) && isset($mysqli)) $con = $mysqli;
    
    if (!isset($con) || !$con instanceof mysqli) throw new Exception('Erro BD: conexão inválida');
    db_set_charset($con, 'utf8mb4');

    if (!file_exists(__DIR__ . '/../auth/guest_control.php')) throw new Exception('Módulo de convidados ausente');
    require_once(__DIR__ . '/../auth/guest_control.php');
    if (!function_exists('guest_status')) {
        throw new Exception('Função guest_status não disponível');
    }

    // Ensure table exists
    $createTable = "CREATE TABLE IF NOT EXISTS uso_convidado (
        id INT AUTO_INCREMENT PRIMARY KEY,
        endereco_ip VARCHAR(45),
        id_anonimo VARCHAR(36),
        total_pedidos INT DEFAULT 0,
        data_primeiro_pedido DATETIME,
        data_ultimo_pedido DATETIME,
        data_expiracao DATETIME,
        bloqueado TINYINT DEFAULT 0,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    db_query($con, $createTable);

    $status = guest_status($con);
    if (!$status) throw new Exception('Não foi possível obter status do convidado');

    echo json_encode(['status' => 'success', 'data' => $status]);
} catch (Exception $e) {
    http_response_code(500);
    // Log error for diagnostics
    error_log('[api_guest_status] ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erro no servidor ao obter status do convidado.']);
}
?>
