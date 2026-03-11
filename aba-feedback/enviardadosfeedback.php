<?php
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');

require_once('../ligarbd.php');

function respondJson($success, $message = '', $httpCode = 200) {
    if (ob_get_length()) ob_clean();
    http_response_code($httpCode);
    echo json_encode(['sucesso' => $success, 'erro' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($con->connect_error) {
    respondJson(false, 'Erro ao conectar à base de dados. Tente novamente mais tarde.', 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(false, 'Método HTTP não permitido.', 405);
}

$nome = $_POST['nome'] ?? '';
$email = $_POST['email'] ?? '';
$rating = $_POST['rating'] ?? '';
$gostou = $_POST['gostou'] ?? '';
$melhoria = $_POST['melhoria'] ?? '';
$autorizacao = $_POST['autorizacao'] ?? '';

if (empty($nome) || empty($email) || empty($rating)) {
    respondJson(false, 'Por favor, preencha todos os campos obrigatórios.', 400);
}

$data_feedback = date('Y-m-d H:i:s');

$sql = "INSERT INTO feedback (nome, email, rating, gostou, melhoria, autorizacao, lido, data_feedback)
        VALUES (?, ?, ?, ?, ?, ?, 'nao', ?)";

$stmt = $con->prepare($sql);
if (!$stmt) {
    respondJson(false, 'Erro ao processar o feedback. Tente novamente.', 500);
}

$stmt->bind_param("sssssss", $nome, $email, $rating, $gostou, $melhoria, $autorizacao, $data_feedback);

if ($stmt->execute()) {
    respondJson('Feedback enviado com sucesso! Obrigado pela sua opinião.', '', 201);
} else {
    respondJson(false, 'Erro ao guardar o feedback. Tente novamente.', 500);
}

$stmt->close();
$con->close();

ob_end_flush();
exit;
?>
