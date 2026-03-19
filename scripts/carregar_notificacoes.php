<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
include_once __DIR__ . '/../ligarbd.php';

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
        if (!db_column_exists($con, 'utilizador', $coluna)) {
            db_query($con, db_sql_add_column('utilizador', $coluna, $tipo));
        }
    }

    $stmt = db_prepare($con, "SELECT notif_atualizacoes, notif_manutencao, notif_novidades, notif_seguranca, notif_performance FROM utilizador WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Erro ao preparar query: " . db_error($con));
    }
    
    db_stmt_bind_param($stmt, "s", $email);
    db_stmt_execute($stmt);
    $result = db_stmt_get_result($stmt);

    $preferencias = [
        'notif_atualizacoes' => true,
        'notif_manutencao' => true,
        'notif_novidades' => true,
        'notif_seguranca' => true,
        'notif_performance' => false
    ];

    if ($row = db_fetch_assoc($result)) {
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
    if (isset($stmt)) db_stmt_close($stmt);
    if (isset($con)) db_close($con);
}
?>
