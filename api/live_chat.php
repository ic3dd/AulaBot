<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/live_chat_errors.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: *');

require_once(__DIR__ . '/../ligarbd.php');

$method = $_SERVER['REQUEST_METHOD'];
$input = null;
$action = '';

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
} else {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
    } else {
        $input = $_POST;
        $action = $_POST['action'] ?? '';
    }
}

function responderSucesso($dados = null, $mensagem = '')
{
    $resposta = ['success' => true];
    if ($mensagem)
        $resposta['message'] = $mensagem;
    if ($dados !== null)
        $resposta['data'] = $dados;
    echo json_encode($resposta);
    exit;
}

function responderErro($mensagem)
{
    echo json_encode(['success' => false, 'message' => $mensagem]);
    exit;
}

function obterEmailAutenticado()
{
    if (isset($_SESSION['email'])) {
        return $_SESSION['email'];
    }

    if (isset($_GET['email'])) {
        return $_GET['email'];
    }

    if (isset($_POST['email'])) {
        return $_POST['email'];
    }

    global $input;
    if (is_array($input) && isset($input['email'])) {
        return $input['email'];
    }

    return null;
}

function verificarAutenticacao()
{
    global $con;

    $email = obterEmailAutenticado();

    if (!$email) {
        responderErro('Utilizador não autenticado');
    }

    $email = trim($email);
    $stmt = db_prepare($con, "SELECT id_utilizador, nome FROM utilizador WHERE email = ? LIMIT 1");

    if (!$stmt) {
        responderErro('Erro ao verificar autenticação');
    }

    db_stmt_bind_param($stmt, "s", $email);
    db_stmt_execute($stmt);
    $result = db_stmt_get_result($stmt);

    if (db_num_rows($result) === 0) {
        responderErro('Utilizador não encontrado');
    }

    $row = db_fetch_assoc($result);
    db_stmt_close($stmt);

    $_SESSION['email'] = $email;
    $_SESSION['nome'] = $row['nome'];
    $_SESSION['id_utilizador'] = $row['id_utilizador'];

    return $row['id_utilizador'];
}

if ($method === 'GET') {
    if ($action === 'get_conversation') {
        $conversationId = $_GET['conversation_id'] ?? null;
        verificarAutenticacao();
        obterConversa($conversationId);
    } elseif ($action === 'list_conversations') {
        $utilizadorId = verificarAutenticacao();
        listarConversas($utilizadorId);
    } elseif ($action === 'check_new_messages') {
        $conversationId = $_GET['conversation_id'] ?? null;
        verificarAutenticacao();
        verificarNovasMensagens($conversationId);
    } else {
        responderErro('Ação não reconhecida');
    }
} elseif ($method === 'POST') {
    if ($action === 'send_message') {
        $utilizadorId = verificarAutenticacao();
        enviarMensagem($input, $utilizadorId);
    } elseif ($action === 'close_conversation_user') {
        $utilizadorId = verificarAutenticacao();
        fecharConversaUtilizador($input, $utilizadorId);
    } else {
        responderErro('Ação não reconhecida');
    }
}

function enviarMensagem($dados, $utilizadorId)
{
    global $con;

    $conteudo = trim($dados['conteudo'] ?? '');
    $conversationId = $dados['conversation_id'] ?? null;

    if (empty($conteudo)) {
        responderErro('Mensagem não pode estar vazia');
    }

    if (strlen($conteudo) > 1000) {
        responderErro('Mensagem excede o limite de 1000 caracteres');
    }

    $conteudo = db_real_escape_string($con, $conteudo);

    if (!$conversationId || !is_numeric($conversationId)) {
        $stmtChat = db_prepare($con, "INSERT INTO chat_ajuda (id_utilizador, criado_em) VALUES (?, NOW())");
        if (!$stmtChat) {
            responderErro('Erro ao criar conversa');
        }
        db_stmt_bind_param($stmtChat, "i", $utilizadorId);
        db_stmt_execute($stmtChat);
        $conversationId = db_insert_id($con);
        db_stmt_close($stmtChat);
    }

    $stmtMsg = db_prepare($con, "INSERT INTO mensagens_chat_ajuda (conversation_id, sender, conteudo, enviado_em) VALUES (?, 'utilizador', ?, NOW())");
    if (!$stmtMsg) {
        responderErro('Erro ao enviar mensagem');
    }

    db_stmt_bind_param($stmtMsg, "is", $conversationId, $conteudo);

    if (!db_stmt_execute($stmtMsg)) {
        responderErro('Erro ao enviar mensagem');
    }

    $messageId = db_insert_id($con);
    db_stmt_close($stmtMsg);

    responderSucesso([
        'message_id' => $messageId,
        'conversation_id' => $conversationId
    ], 'Mensagem enviada com sucesso');
}

function obterConversa($conversationId)
{
    global $con;

    if (!$conversationId || !is_numeric($conversationId)) {
        responderErro('ID de conversa inválido');
    }

    $conversationId = intval($conversationId);
    $utilizadorId = verificarAutenticacao();
    $isAdmin = isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'admin';

    $stmtConv = db_prepare($con, "SELECT id_utilizador, estado FROM chat_ajuda WHERE id = ? LIMIT 1");
    if (!$stmtConv) {
        responderErro('Erro ao verificar conversa');
    }

    db_stmt_bind_param($stmtConv, "i", $conversationId);
    db_stmt_execute($stmtConv);
    $resultConv = db_stmt_get_result($stmtConv);

    if (db_num_rows($resultConv) === 0) {
        responderErro('Conversa não encontrada');
    }

    $rowConv = db_fetch_assoc($resultConv);
    db_stmt_close($stmtConv);

    if (!$isAdmin && $rowConv['estado'] === 'fechado') {
        responderErro('Esta conversa está fechada e não pode ser acedida');
    }

    if (!$isAdmin && $rowConv['id_utilizador'] != $utilizadorId) {
        responderErro('Não tem permissão para aceder a esta conversa');
    }

    $stmt = db_prepare($con, "
        SELECT id, conversation_id, sender, conteudo, enviado_em 
        FROM mensagens_chat_ajuda 
        WHERE conversation_id = ? 
        ORDER BY enviado_em ASC
    ");

    if (!$stmt) {
        responderErro('Erro ao obter mensagens');
    }

    db_stmt_bind_param($stmt, "i", $conversationId);
    db_stmt_execute($stmt);
    $result = db_stmt_get_result($stmt);

    $mensagens = [];
    while ($row = db_fetch_assoc($result)) {
        $mensagens[] = $row;
    }

    db_stmt_close($stmt);

    responderSucesso(['mensagens' => $mensagens, 'estado' => $rowConv['estado']]);
}

function fecharConversaUtilizador($dados, $utilizadorId)
{
    global $con;

    $conversationId = $dados['conversation_id'] ?? null;

    if (!$conversationId || !is_numeric($conversationId)) {
        responderErro('ID de conversa inválido');
    }

    $conversationId = intval($conversationId);

    $stmt = db_prepare($con, "SELECT id_utilizador FROM chat_ajuda WHERE id = ? LIMIT 1");
    if (!$stmt) {
        responderErro('Erro ao verificar conversa');
    }

    db_stmt_bind_param($stmt, "i", $conversationId);
    db_stmt_execute($stmt);
    $result = db_stmt_get_result($stmt);

    if (db_num_rows($result) === 0) {
        responderErro('Conversa não encontrada');
    }

    $row = db_fetch_assoc($result);
    db_stmt_close($stmt);

    if ($row['id_utilizador'] != $utilizadorId) {
        responderErro('Não tem permissão para fechar esta conversa');
    }

    $updateStmt = db_prepare($con, "UPDATE chat_ajuda SET estado = 'fechado' WHERE id = ?");
    if (!$updateStmt) {
        responderErro('Erro ao fechar conversa');
    }

    db_stmt_bind_param($updateStmt, "i", $conversationId);

    if (!db_stmt_execute($updateStmt)) {
        responderErro('Erro ao fechar conversa: ' . db_stmt_error($updateStmt));
    }

    db_stmt_close($updateStmt);
    responderSucesso(null, 'Conversa fechada com sucesso');
}

function listarConversas($utilizadorId)
{
    global $con;

    $stmt = db_prepare($con, "
        SELECT id, criado_em 
        FROM chat_ajuda 
        WHERE id_utilizador = ? AND (estado IS NULL OR estado != 'fechado')
        ORDER BY criado_em DESC 
        LIMIT 1
    ");

    if (!$stmt) {
        responderErro('Erro ao listar conversas');
    }

    db_stmt_bind_param($stmt, "i", $utilizadorId);
    db_stmt_execute($stmt);
    $result = db_stmt_get_result($stmt);

    $conversas = [];
    while ($row = db_fetch_assoc($result)) {
        $conversas[] = $row;
    }

    db_stmt_close($stmt);

    responderSucesso(['conversas' => $conversas]);
}

function verificarNovasMensagens($conversationId)
{
    global $con;

    if (!$conversationId || !is_numeric($conversationId)) {
        responderErro('ID de conversa inválido');
    }

    $conversationId = intval($conversationId);

    $stmt = db_prepare($con, "
        SELECT MAX(m.enviado_em) as ultima_mensagem, c.estado
        FROM chat_ajuda c
        LEFT JOIN mensagens_chat_ajuda m ON c.id = m.conversation_id
        WHERE c.id = ?
        GROUP BY c.id
    ");

    if (!$stmt) {
        responderErro('Erro ao verificar mensagens');
    }

    db_stmt_bind_param($stmt, "i", $conversationId);
    db_stmt_execute($stmt);
    $result = db_stmt_get_result($stmt);
    $row = db_fetch_assoc($result);
    db_stmt_close($stmt);

    responderSucesso([
        'ultima_mensagem' => $row['ultima_mensagem'],
        'estado' => $row['estado']
    ]);
}
