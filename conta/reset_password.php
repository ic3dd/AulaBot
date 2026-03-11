<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
ob_clean();

function respondJson($success, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['sucesso' => $success, 'erro' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(false, 'Método HTTP não permitido.', 405);
}

try {
    require('../ligarbd.php');

    $token = $_POST['token'] ?? '';
    $nova_password = $_POST['nova_password'] ?? '';

    if (!$token || !$nova_password) {
        respondJson(false, 'Token de verificação e palavra-passe são obrigatórios.', 400);
    }

    if (strlen($nova_password) < 6) {
        respondJson(false, 'A palavra-passe deve ter pelo menos 6 caracteres.', 400);
    }

    if (!$con) {
        respondJson(false, 'Erro ao conectar à base de dados. Tente novamente mais tarde.', 500);
    }

    $token_hash = hash('sha256', $token);
    $agora = date('Y-m-d H:i:s');

    $sql = "SELECT email FROM utilizador WHERE reset_token = ? AND reset_token_expiry > NOW()";
    $stmt = mysqli_prepare($con, $sql);
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . mysqli_error($con));
    }
    
    mysqli_stmt_bind_param($stmt, "s", $token_hash);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        mysqli_stmt_close($stmt);
        respondJson(false, 'Link de recuperação inválido ou expirado. Solicite um novo.', 400);
    }
    
    $user = mysqli_fetch_assoc($result);
    $user_email = $user['email'];
    mysqli_stmt_close($stmt);

    $password_hash = substr(md5($nova_password), 0, 12);

    $sql_update = "UPDATE utilizador SET palavra_passe = ?, reset_token = NULL, reset_token_expiry = NULL WHERE email = ?";
    $stmt_update = mysqli_prepare($con, $sql_update);
    
    if (!$stmt_update) {
        throw new Exception('Erro ao preparar query: ' . mysqli_error($con));
    }
    
    mysqli_stmt_bind_param($stmt_update, "ss", $password_hash, $user_email);
    
    if (!mysqli_stmt_execute($stmt_update)) {
        mysqli_stmt_close($stmt_update);
        throw new Exception('Erro ao atualizar palavra-passe: ' . mysqli_error($con));
    }
    
    mysqli_stmt_close($stmt_update);
    mysqli_close($con);

    respondJson(true, 'Palavra-passe redefinida com sucesso! Será redirecionado para o login.', 200);

} catch (Exception $e) {
    error_log("Erro na redefinição de palavra-passe: " . $e->getMessage());
    respondJson(false, 'Erro ao redefinir a palavra-passe. Tente novamente.', 500);
}

exit;
?>
