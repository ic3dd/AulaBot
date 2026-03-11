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
    require('ligarbd.php');

    if (!$con) {
        respondJson(false, 'Erro ao conectar à base de dados. Tente novamente mais tarde.', 500);
    }

    $novo_email = trim($_POST['novo_email'] ?? '');
    $confirmar_novo_email = trim($_POST['confirmar_novo_email'] ?? '');
    $password = $_POST['password_para_email'] ?? '';
    $email_atual = $_SESSION['email'];

    if (!$novo_email || !$confirmar_novo_email || !$password) {
        respondJson(false, 'Por favor, preencha todos os campos obrigatórios.', 400);
    }

    if (!filter_var($novo_email, FILTER_VALIDATE_EMAIL)) {
        respondJson(false, 'O novo email não é válido.', 400);
    }

    if ($novo_email !== $confirmar_novo_email) {
        respondJson(false, 'Os emails não coincidem.', 400);
    }

    if ($novo_email === $email_atual) {
        respondJson(false, 'O novo email é igual ao email atual.', 400);
    }

    $sql = "SELECT palavra_passe FROM utilizador WHERE email = ?";
    $stmt = mysqli_prepare($con, $sql);

    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . mysqli_error($con));
    }

    mysqli_stmt_bind_param($stmt, "s", $email_atual);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 0) {
        mysqli_stmt_close($stmt);
        respondJson(false, 'Utilizador não encontrado no sistema.', 404);
    }

    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $password_verified = false;

    if ($password === $user['palavra_passe']) {
        $password_verified = true;
    }

    if (!$password_verified && md5($password) === $user['palavra_passe']) {
        $password_verified = true;
    }

    if (!$password_verified && substr(md5($password), 0, 12) === $user['palavra_passe']) {
        $password_verified = true;
    }

    if (!$password_verified && password_verify($password, $user['palavra_passe'])) {
        $password_verified = true;
    }

    if (!$password_verified) {
        respondJson(false, 'A palavra-passe não está correta. Verifique e tente novamente.', 403);
    }

    $checkEmail = "SELECT id_utilizador FROM utilizador WHERE email = ? AND email != ?";
    $stmtCheck = mysqli_prepare($con, $checkEmail);
    
    if (!$stmtCheck) {
        throw new Exception('Erro ao preparar query de verificação: ' . mysqli_error($con));
    }

    mysqli_stmt_bind_param($stmtCheck, "ss", $novo_email, $email_atual);
    mysqli_stmt_execute($stmtCheck);
    $resultCheck = mysqli_stmt_get_result($stmtCheck);

    if (mysqli_num_rows($resultCheck) > 0) {
        mysqli_stmt_close($stmtCheck);
        respondJson(false, 'Este email já está registado por outro utilizador.', 400);
    }

    mysqli_stmt_close($stmtCheck);

    $sql_update = "UPDATE utilizador SET email = ? WHERE email = ?";
    $stmt_update = mysqli_prepare($con, $sql_update);

    if (!$stmt_update) {
        throw new Exception('Erro ao preparar query de atualização: ' . mysqli_error($con));
    }

    mysqli_stmt_bind_param($stmt_update, "ss", $novo_email, $email_atual);

    if (!mysqli_stmt_execute($stmt_update)) {
        mysqli_stmt_close($stmt_update);
        throw new Exception('Erro ao alterar email');
    }

    mysqli_stmt_close($stmt_update);

    $_SESSION['email'] = $novo_email;

    respondJson(true, '', 200);

    mysqli_close($con);

} catch (Exception $e) {
    error_log("Erro na alteração de email: " . $e->getMessage());
    respondJson(false, 'Erro ao processar a alteração. Por favor, tente novamente.', 500);
}

exit;
?>
