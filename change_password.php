<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();
header('Content-Type: application/json; charset=utf-8');
ob_clean();

function respondJson($success, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['sucesso' => $success, 'erro' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['email'])) {
    respondJson(false, 'A sua sessão expirou. Por favor, autentique-se novamente.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(false, 'Método HTTP não permitido.', 405);
}

require('ligarbd.php');

if (!$con) {
    respondJson(false, 'Erro ao conectar à base de dados. Tente novamente mais tarde.', 500);
}

$email = $_SESSION['email'];
$current = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
$new = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';

if (!$current || !$new) {
    respondJson(false, 'Por favor, preencha todos os campos obrigatórios.', 400);
}

if (strlen($new) < 6) {
    respondJson(false, 'A nova palavra-passe deve ter pelo menos 6 caracteres.', 400);
}

try {
    // Buscar utilizador
    $sql = "SELECT id_utilizador, palavra_passe FROM utilizador WHERE email = ? LIMIT 1";
    $stmt = db_prepare($con, $sql);
    if (!$stmt) throw new Exception('Erro ao preparar query: ' . db_error($con));
    db_stmt_bind_param($stmt, 's', $email);
    db_stmt_execute($stmt);
    $result = db_stmt_get_result($stmt);

    if (db_num_rows($result) !== 1) {
        respondJson(false, 'Utilizador não encontrado no sistema.', 404);
        db_stmt_close($stmt);
    }

    $user = db_fetch_assoc($result);
    $stored = isset($user['palavra_passe']) ? trim($user['palavra_passe']) : '';
    $userId = $user['id_utilizador'];
    db_stmt_close($stmt);

    $verified = false;
    if ($current === $stored) $verified = true;
    if (!$verified && md5($current) === $stored) $verified = true;
    if (!$verified && substr(md5($current), 0, 12) === $stored) $verified = true;
    if (!$verified && password_verify($current, $stored)) $verified = true;

    if (!$verified) {
        $debug = sprintf("change_password: verification failed for user=%s; stored_len=%d; md5=%s; md5_12=%s; password_verify=%s",
            $email,
            strlen($stored),
            md5($current),
            substr(md5($current), 0, 12),
            password_verify($current, $stored) ? 'true' : 'false'
        );
        error_log($debug);
        respondJson(false, 'A palavra-passe atual não está correta. Verifique e tente novamente.', 403);
    }

    $newHash = substr(md5($new), 0, 12);

    $updateSql = "UPDATE utilizador SET palavra_passe = ? WHERE id_utilizador = ?";
    $upd = db_prepare($con, $updateSql);
    if (!$upd) throw new Exception('Erro ao preparar update: ' . db_error($con));
    db_stmt_bind_param($upd, 'si', $newHash, $userId);

    if (db_stmt_execute($upd)) {
        db_stmt_close($upd);
        respondJson(true, 'Palavra-passe alterada com sucesso!', 200);
    } else {
        $err = db_stmt_error($upd);
        db_stmt_close($upd);
        throw new Exception('Erro ao atualizar password: ' . $err);
    }

} catch (Exception $e) {
    error_log('change_password error: ' . $e->getMessage());
    respondJson(false, 'Erro ao processar a alteração. Por favor, tente novamente.', 500);
}

db_close($con);
?>
