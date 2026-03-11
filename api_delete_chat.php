<?php
header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once('ligarbd.php');

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, ['error' => 'Apenas POST é permitido'], 405);
}

// Obter dados do corpo da requisição
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id_chat']) || !is_numeric($input['id_chat'])) {
    respond(false, ['error' => 'id_chat inválido'], 400);
}

$id_chat = (int)$input['id_chat'];

// Obter id_utilizador da sessão
$idUtilizador = 0;
if (isset($_SESSION['email']) && !empty($_SESSION['email'])) {
    $emailEsc = mysqli_real_escape_string($con, $_SESSION['email']);
    $res = mysqli_query($con, "SELECT id_utilizador FROM utilizador WHERE email = '$emailEsc' LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $idUtilizador = (int)$row['id_utilizador'];
    }
}

// Verificar permissões do chat
$q = "SELECT id_chat, id_utilizador FROM chats WHERE id_chat = $id_chat LIMIT 1";
$r = mysqli_query($con, $q);
if (!$r || mysqli_num_rows($r) === 0) {
    respond(false, ['error' => 'Chat não encontrado'], 404);
}
$chat = mysqli_fetch_assoc($r);
$owner = (int)$chat['id_utilizador'];

// Permitir apenas se o utilizador é o dono
if ($owner !== $idUtilizador) {
    respond(false, ['error' => 'Sem permissão para eliminar este chat'], 403);
}

// Deletar todas as mensagens do chat
$deleteMessages = "DELETE FROM mensagens WHERE id_chat = $id_chat";
if (!mysqli_query($con, $deleteMessages)) {
    respond(false, ['error' => 'Erro ao eliminar mensagens: ' . mysqli_error($con)], 500);
}

// Deletar o chat
$deleteChat = "DELETE FROM chats WHERE id_chat = $id_chat";
if (!mysqli_query($con, $deleteChat)) {
    respond(false, ['error' => 'Erro ao eliminar chat: ' . mysqli_error($con)], 500);
}

respond(true, ['message' => 'Chat eliminado com sucesso']);
?>
