<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();
header('Content-Type: application/json; charset=utf-8');
if (function_exists('ob_clean')) ob_clean();

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

require(__DIR__ . '/../ligarbd.php');

if (!$con) {
    respondJson(false, 'Erro ao conectar à base de dados. Tente novamente mais tarde.', 500);
}

$current = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
$new = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
$email = $_SESSION['email'];

if (!$current || !$new) {
    respondJson(false, 'Por favor, preencha todos os campos obrigatórios.', 400);
}

if (strlen($new) < 6) {
    respondJson(false, 'A nova palavra-passe deve ter pelo menos 6 caracteres.', 400);
}

$sql = "SELECT palavra_passe FROM utilizador WHERE email = ?";
$stmt = db_prepare($con, $sql);
if (!$stmt) {
    respondJson(false, 'Erro interno. Tente novamente.', 500);
}
db_stmt_bind_param($stmt, "s", $email);
db_stmt_execute($stmt);
$result = db_stmt_get_result($stmt);

if (db_num_rows($result) === 0) {
    db_stmt_close($stmt);
    respondJson(false, 'Utilizador não encontrado.', 404);
}

$user = db_fetch_assoc($result);
db_stmt_close($stmt);

$verified = false;
if ($current === $user['palavra_passe']) $verified = true;
if (!$verified && md5($current) === $user['palavra_passe']) $verified = true;
if (!$verified && substr(md5($current), 0, 12) === $user['palavra_passe']) $verified = true;
if (!$verified && password_verify($current, $user['palavra_passe'])) $verified = true;

if (!$verified) {
    respondJson(false, 'A palavra-passe atual não está correta.', 403);
}

$new_hash = substr(md5($new), 0, 12);
$stmt2 = db_prepare($con, "UPDATE utilizador SET palavra_passe = ? WHERE email = ?");
if (!$stmt2 || !db_stmt_bind_param($stmt2, "ss", $new_hash, $email) || !db_stmt_execute($stmt2)) {
    respondJson(false, 'Erro ao alterar palavra-passe.', 500);
}
db_stmt_close($stmt2);

respondJson(true, '', 200);
