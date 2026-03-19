<?php
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');

require_once(__DIR__ . '/../ligarbd.php');

function respondJson($success, $message = '', $httpCode = 200) {
    if (ob_get_length()) ob_clean();
    http_response_code($httpCode);
    echo json_encode(['sucesso' => $success, 'erro' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// Compatível com mysqli e PDO (Supabase)
$conn = $con ?? $conn ?? null;
if (!$conn) {
    respondJson(false, 'Erro ao conectar à base de dados. Tente novamente mais tarde.', 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(false, 'Método HTTP não permitido.', 405);
}

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$rating = $_POST['rating'] ?? '';
$gostou = trim($_POST['gostou'] ?? '');
$melhoria = trim($_POST['melhoria'] ?? '');
$autorizacao = trim($_POST['autorizacao'] ?? '');

if (empty($nome) || empty($email) || empty($rating)) {
    respondJson(false, 'Por favor, preencha todos os campos obrigatórios.', 400);
}

$data_feedback = date('Y-m-d H:i:s');

$sql = "INSERT INTO feedback (nome, email, rating, gostou, melhoria, autorizacao, lido, data_feedback)
        VALUES (?, ?, ?, ?, ?, ?, 'nao', ?)";

$stmt = db_prepare($conn, $sql);
if (!$stmt) {
    respondJson(false, 'Erro ao processar o feedback. Tente novamente.', 500);
}

db_stmt_bind_param($stmt, 'sssssss', $nome, $email, $rating, $gostou, $melhoria, $autorizacao, $data_feedback);

if (db_stmt_execute($stmt)) {
    respondJson(true, 'Feedback enviado com sucesso! Obrigado pela sua opinião.', 201);
} else {
    respondJson(false, 'Erro ao guardar o feedback. Tente novamente.', 500);
}

db_stmt_close($stmt);

ob_end_flush();
exit;
?>
