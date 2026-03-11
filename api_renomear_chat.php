<?php

// Configurações iniciais
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

// Iniciar Sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ligar à Base de Dados
require_once('ligarbd.php');

if (!isset($con) && isset($conn)) { $con = $conn; }
if (!isset($con) && isset($mysqli)) { $con = $mysqli; }

if (!isset($con) || !$con) {
    echo json_encode(['success' => false, 'error' => 'Erro na ligação à base de dados']);
    exit;
}

mysqli_set_charset($con, "utf8mb4");

// Verificar se o utilizador está autenticado
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'error' => 'Utilizador não autenticado']);
    exit;
}

// Ler dados da requisição
$input = json_decode(file_get_contents('php://input'), true);

$chatId = isset($input['id_chat']) && is_numeric($input['id_chat']) ? (int)$input['id_chat'] : null;
$novoNome = isset($input['novo_nome']) ? trim($input['novo_nome']) : '';

if (!$chatId || $chatId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID do chat inválido']);
    exit;
}

if (empty($novoNome)) {
    echo json_encode(['success' => false, 'error' => 'Nome do chat não pode estar vazio']);
    exit;
}

if (strlen($novoNome) > 255) {
    echo json_encode(['success' => false, 'error' => 'Nome do chat muito longo (máximo 255 caracteres)']);
    exit;
}

// Obter ID do utilizador
$emailUtilizador = mysqli_real_escape_string($con, $_SESSION['email']);
$queryUser = "SELECT id_utilizador FROM utilizador WHERE email = '$emailUtilizador' LIMIT 1";
$resultadoUser = mysqli_query($con, $queryUser);

if (!$resultadoUser || mysqli_num_rows($resultadoUser) === 0) {
    echo json_encode(['success' => false, 'error' => 'Utilizador não encontrado']);
    exit;
}

$rowUser = mysqli_fetch_assoc($resultadoUser);
$idUtilizador = (int)$rowUser['id_utilizador'];

// Verificar se o chat pertence ao utilizador
$queryVerify = "SELECT id_chat FROM chats WHERE id_chat = ? AND id_utilizador = ? LIMIT 1";
$stmtVerify = mysqli_prepare($con, $queryVerify);
mysqli_stmt_bind_param($stmtVerify, 'ii', $chatId, $idUtilizador);
mysqli_stmt_execute($stmtVerify);
mysqli_stmt_store_result($stmtVerify);

if (mysqli_stmt_num_rows($stmtVerify) === 0) {
    mysqli_stmt_close($stmtVerify);
    echo json_encode(['success' => false, 'error' => 'Chat não encontrado ou sem permissão']);
    exit;
}

mysqli_stmt_close($stmtVerify);

// Atualizar o nome do chat
$novoNomeEsc = mysqli_real_escape_string($con, $novoNome);
$queryUpdate = "UPDATE chats SET titulo = '$novoNomeEsc', data_atualizacao = NOW() WHERE id_chat = $chatId";

if (mysqli_query($con, $queryUpdate)) {
    echo json_encode(['success' => true, 'message' => 'Chat renomeado com sucesso']);
} else {
    echo json_encode(['success' => false, 'error' => mysqli_error($con)]);
}

exit;
?>
