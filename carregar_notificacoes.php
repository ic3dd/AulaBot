<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
include_once 'ligarbd.php';

function respondJson($success, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['email'])) {
    respondJson(false, ['error' => 'A sua sessão expirou. Por favor, autentique-se novamente.'], 401);
}

try {
    if (!isset($con)) {
        throw new Exception("Erro ao conectar à base de dados. Tente novamente mais tarde.");
    }

    $email = trim($_SESSION['email']);

    $colunas = [
        'notif_atualizacoes' => 'TINYINT DEFAULT 1',
        'notif_manutencao' => 'TINYINT DEFAULT 1',
        'notif_novidades' => 'TINYINT DEFAULT 1',
        'notif_seguranca' => 'TINYINT DEFAULT 1',
        'notif_performance' => 'TINYINT DEFAULT 0'
    ];

    foreach ($colunas as $coluna => $tipo) {
        $checkColumn = $con->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='utilizador' AND COLUMN_NAME='$coluna'");
        if ($checkColumn && $checkColumn->num_rows === 0) {
            $con->query("ALTER TABLE utilizador ADD COLUMN $coluna $tipo");
        }
    }

    $stmt = $con->prepare("SELECT notif_atualizacoes, notif_manutencao, notif_novidades, notif_seguranca, notif_performance FROM utilizador WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Erro ao preparar query: " . $con->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    $preferencias = [
        'notif_atualizacoes' => true,
        'notif_manutencao' => true,
        'notif_novidades' => true,
        'notif_seguranca' => true,
        'notif_performance' => false
    ];

    if ($row = $result->fetch_assoc()) {
        $preferencias['notif_atualizacoes'] = (bool)$row['notif_atualizacoes'];
        $preferencias['notif_manutencao'] = (bool)$row['notif_manutencao'];
        $preferencias['notif_novidades'] = (bool)$row['notif_novidades'];
        $preferencias['notif_seguranca'] = (bool)$row['notif_seguranca'];
        $preferencias['notif_performance'] = (bool)$row['notif_performance'];
    }

    respondJson(true, ['preferencias' => $preferencias], 200);

} catch (Exception $e) {
    respondJson(false, ['error' => 'Erro ao carregar preferências. ' . $e->getMessage()], 500);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($con)) $con->close();
}
?>
