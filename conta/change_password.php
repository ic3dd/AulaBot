<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
ob_clean();

session_start();

function respondJson($success, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['sucesso' => $success, 'erro' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(false, 'Método HTTP não permitido.', 405);
}

if (!isset($_SESSION['email'])) {
    respondJson(false, 'A sua sessão expirou. Por favor, autentique-se novamente.', 401);
}

try {
    require('../ligarbd.php');

    if (!$con) {
        respondJson(false, 'Erro ao conectar à base de dados. Tente novamente mais tarde.', 500);
    }

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $email = $_SESSION['email'];

    if (!$current_password || !$new_password) {
        respondJson(false, 'Por favor, preencha todos os campos obrigatórios.', 400);
    }

    if (strlen($new_password) < 6) {
        respondJson(false, 'A palavra-passe deve ter pelo menos 6 caracteres.', 400);
    }

    $sql = "SELECT palavra_passe FROM utilizador WHERE email = ?";
    $stmt = db_prepare($con, $sql);

    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . db_error($con));
    }

    db_stmt_bind_param($stmt, "s", $email);
    db_stmt_execute($stmt);
    $result = db_stmt_get_result($stmt);

    if (db_num_rows($result) === 0) {
        db_stmt_close($stmt);
        respondJson(false, 'Utilizador não encontrado no sistema.', 404);
    }

    $user = db_fetch_assoc($result);
    db_stmt_close($stmt);

    $password_verified = false;

    if ($current_password === $user['palavra_passe']) {
        $password_verified = true;
    }

    if (!$password_verified && md5($current_password) === $user['palavra_passe']) {
        $password_verified = true;
    }

    if (!$password_verified && substr(md5($current_password), 0, 12) === $user['palavra_passe']) {
        $password_verified = true;
    }

    if (!$password_verified && password_verify($current_password, $user['palavra_passe'])) {
        $password_verified = true;
    }

    if (!$password_verified) {
        respondJson(false, 'A palavra-passe atual não está correta. Verifique e tente novamente.', 403);
    }

    $new_password_hash = substr(md5($new_password), 0, 12);

    $sql_update = "UPDATE utilizador SET palavra_passe = ? WHERE email = ?";
    $stmt_update = db_prepare($con, $sql_update);

    if (!$stmt_update) {
        throw new Exception('Erro ao preparar query de atualização: ' . db_error($con));
    }

    db_stmt_bind_param($stmt_update, "ss", $new_password_hash, $email);

    if (!db_stmt_execute($stmt_update)) {
        db_stmt_close($stmt_update);
        throw new Exception('Erro ao alterar palavra-passe');
    }

    db_stmt_close($stmt_update);

    respondJson(true, '', 200);

    db_close($con);

} catch (Exception $e) {
    error_log("Erro na alteração de palavra-passe: " . $e->getMessage());
    respondJson(false, 'Erro ao processar a alteração. Por favor, tente novamente.', 500);
}

exit;
?>
