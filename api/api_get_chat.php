<?php
header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../ligarbd.php');

function respond($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_GET['id_chat']) || !is_numeric($_GET['id_chat'])) {
    respond(false, ['error' => 'id_chat inválido'], 400);
}

$id_chat = (int)$_GET['id_chat'];

// obter id_utilizador da sessão (se existir)
$idUtilizador = 0;
if (isset($_SESSION['email']) && !empty($_SESSION['email'])) {
    $emailEsc = db_real_escape_string($con, $_SESSION['email']);
    $res = db_query($con, "SELECT id_utilizador FROM utilizador WHERE email = '$emailEsc' LIMIT 1");
    if ($res && db_num_rows($res) > 0) {
        $row = db_fetch_assoc($res);
        $idUtilizador = (int)$row['id_utilizador'];
    }
}

// verificar permissões do chat
$q = "SELECT id_chat, id_utilizador, titulo FROM chats WHERE id_chat = $id_chat LIMIT 1";
$r = db_query($con, $q);
if (!$r || db_num_rows($r) === 0) {
    respond(false, ['error' => 'Chat não encontrado'], 404);
}
$chat = db_fetch_assoc($r);
$owner = (int)$chat['id_utilizador'];

// Permitir acesso apenas se o utilizador é o dono OU o chat é anónimo (id_utilizador = 0) e o utilizador é anónimo
if ($owner !== 0 && $owner !== $idUtilizador) {
    respond(false, ['error' => 'Sem permissão para aceder a este chat'], 403);
}

// buscar mensagens (inclui referência a imagem se existir)
$mensagens = [];
$qm = "SELECT m.id_mensagem, m.pergunta, m.resposta, m.data_conversa, m.id_imagem FROM mensagens m WHERE id_chat = $id_chat ORDER BY m.data_conversa ASC";
$rm = db_query($con, $qm);
if ($rm) {
    while ($row = db_fetch_assoc($rm)) {
        $imageId = isset($row['id_imagem']) ? (int)$row['id_imagem'] : 0;
        $imageUrl = null;
        
        // Se tem id_imagem, buscar a URL que foi guardada
        if ($imageId > 0) {
            $qimg = "SELECT filename FROM mensagens_imagem WHERE id_imagem = $imageId LIMIT 1";
            $rimg = db_query($con, $qimg);
            if ($rimg && $row_img = db_fetch_assoc($rimg)) {
                // A URL está guardada na coluna filename
                $imageUrl = $row_img['filename'];
            }
        }
        
        $mensagens[] = [
            'id_mensagem' => (int)$row['id_mensagem'],
            'pergunta' => $row['pergunta'],
            'resposta' => $row['resposta'],
            'data' => $row['data_conversa'],
            'id_imagem' => $imageId,
            'image_url' => $imageUrl
        ];
    }
}

respond(true, ['chat' => ['id_chat' => $id_chat, 'titulo' => $chat['titulo']], 'mensagens' => $mensagens]);
