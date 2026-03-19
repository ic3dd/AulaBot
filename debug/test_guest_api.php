<?php
// Simple test endpoint for guest status
header('Content-Type: application/json; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Test 1: Load DB
    require_once('ligarbd.php');
    
    if (!isset($con) && isset($conn)) $con = $conn;
    if (!isset($con) && isset($mysqli)) $con = $mysqli;
    
    if (!isset($con) || !$con instanceof mysqli) {
        throw new Exception('BD connection invalid');
    }
    
    // Test 2: Load guest control
    require_once('guest_control.php');
    
    if (!function_exists('guest_status')) {
        throw new Exception('guest_status not found');
    }
    
    // Test 3: Create table if needed
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
    
    // Test 4: Get guest status
    $status = guest_status($con);
    
    if (!$status) {
        throw new Exception('Failed to get guest status');
    }
    
    echo json_encode([
        'status' => 'ok',
        'test' => 'success',
        'data' => [
            'guest_ip' => $status['ip'],
            'guest_id' => substr($status['guest_id'], 0, 8) . '...',
            'remaining' => $status['remaining'] . '/3',
            'total_used' => $status['total'],
            'blocked' => $status['blocked'],
            'expires' => $status['expires']
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'test' => 'failed'
    ]);
}
?>
