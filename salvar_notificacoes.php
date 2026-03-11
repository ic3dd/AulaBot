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

if (!isset($con)) {
    respondJson(false, ['error' => 'Erro ao conectar à base de dados. Tente novamente mais tarde.'], 500);
}

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Dados recebidos em formato inválido.');
    }

    $notif_atualizacoes = isset($data['notif_atualizacoes']) ? (int)$data['notif_atualizacoes'] : 1;
    $notif_manutencao = isset($data['notif_manutencao']) ? (int)$data['notif_manutencao'] : 1;
    $notif_novidades = isset($data['notif_novidades']) ? (int)$data['notif_novidades'] : 1;
    $notif_seguranca = isset($data['notif_seguranca']) ? (int)$data['notif_seguranca'] : 1;
    $notif_performance = isset($data['notif_performance']) ? (int)$data['notif_performance'] : 0;

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

    $stmt = $con->prepare("
        UPDATE utilizador 
        SET notif_atualizacoes = ?, notif_manutencao = ?, notif_novidades = ?, notif_seguranca = ?, notif_performance = ?
        WHERE email = ?
    ");
    if (!$stmt) {
        throw new Exception("Erro ao preparar query: " . $con->error);
    }

    $stmt->bind_param("iiiiis", $notif_atualizacoes, $notif_manutencao, $notif_novidades, $notif_seguranca, $notif_performance, $email);
    if (!$stmt->execute()) {
        throw new Exception("Erro ao executar query: " . $stmt->error);
    }

    $stmt->close();
    $con->close();

    respondJson(true, ['message' => 'Preferências de notificações guardadas com sucesso!'], 200);

} catch (Exception $e) {
    respondJson(false, ['error' => 'Erro ao guardar preferências. ' . $e->getMessage()], 500);
}
?>
