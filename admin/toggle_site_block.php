<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);


require_once __DIR__ . '/../ligarbd.php';
require_once __DIR__ . '/../auth/auth_check.php';

function respondJson($success, $message = '', $data = [], $httpCode = 200)
{
    http_response_code($httpCode);
    $response = ['success' => $success];
    if ($message)
        $response['message'] = $message;
    $response = array_merge($response, $data);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!verificarSeAdmin()) {
    respondJson(false, 'Acesso negado. Permissões insuficientes.', [], 403);
}

$query = "SELECT site_bloqueado FROM configuracoes_site WHERE id = 1";
$result = db_query($con, $query);

if (db_num_rows($result) === 0) {
    db_query($con, "INSERT INTO configuracoes_site (site_bloqueado) VALUES (FALSE)");
    $bloqueado = false;
} else {
    $row = db_fetch_assoc($result);
    $bloqueado = (bool) $row['site_bloqueado'];
}

$novo_estado = !$bloqueado;
$query = "UPDATE configuracoes_site SET site_bloqueado = " . ($novo_estado ? "TRUE" : "FALSE") . " WHERE id = 1";
$result = db_query($con, $query);

if (!$result) {
    respondJson(false, 'Erro ao atualizar estado do site. Tente novamente.', [], 500);
}

$message = $novo_estado ? 'Site bloqueado com sucesso!' : 'Site desbloqueado com sucesso!';
respondJson(true, $message, ['bloqueado' => $novo_estado], 200);
?>