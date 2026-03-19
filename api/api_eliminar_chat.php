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
require_once(__DIR__ . '/../ligarbd.php');

if (!isset($con) && isset($conn)) { $con = $conn; }
if (!isset($con) && isset($mysqli)) { $con = $mysqli; }

if (!isset($con) || !$con) {
    echo json_encode(['success' => false, 'error' => 'Erro na ligação à base de dados']);
    exit;
}

db_set_charset($con, "utf8mb4");

// Verificar se o utilizador está autenticado
if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'error' => 'Utilizador não autenticado']);
    exit;
}

// Ler dados da requisição
$input = json_decode(file_get_contents('php://input'), true);

$chatId = isset($input['id_chat']) && is_numeric($input['id_chat']) ? (int)$input['id_chat'] : null;

if (!$chatId || $chatId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID do chat inválido']);
    exit;
}

// Obter ID do utilizador
$emailUtilizador = db_real_escape_string($con, $_SESSION['email']);
$queryUser = "SELECT id_utilizador FROM utilizador WHERE email = '$emailUtilizador' LIMIT 1";
$resultadoUser = db_query($con, $queryUser);

if (!$resultadoUser || db_num_rows($resultadoUser) === 0) {
    echo json_encode(['success' => false, 'error' => 'Utilizador não encontrado']);
    exit;
}

$rowUser = db_fetch_assoc($resultadoUser);
$idUtilizador = (int)$rowUser['id_utilizador'];

// Verificar se o chat pertence ao utilizador
$queryVerify = "SELECT id_chat FROM chats WHERE id_chat = ? AND id_utilizador = ? LIMIT 1";
$stmtVerify = db_prepare($con, $queryVerify);
db_stmt_bind_param($stmtVerify, 'ii', $chatId, $idUtilizador);
db_stmt_execute($stmtVerify);
db_stmt_store_result($stmtVerify);

if (db_stmt_num_rows($stmtVerify) === 0) {
    db_stmt_close($stmtVerify);
    echo json_encode(['success' => false, 'error' => 'Chat não encontrado ou sem permissão']);
    exit;
}

db_stmt_close($stmtVerify);

// Iniciar transação para garantir consistência
db_begin_transaction($con);

try {
    // Primeiro, obter todas as imagens associadas às mensagens deste chat
    // para deletar ficheiros fisicamente se existirem
    $queryGetImages = "SELECT mi.id_imagem, mi.filename FROM mensagens_imagem mi 
                       INNER JOIN mensagens m ON mi.id_mensagem = m.id_mensagem 
                       WHERE m.id_chat = ?";
    $stmtGetImages = db_prepare($con, $queryGetImages);
    db_stmt_bind_param($stmtGetImages, 'i', $chatId);
    db_stmt_execute($stmtGetImages);
    $resultImages = db_stmt_get_result($stmtGetImages);
    
    // Guardar os ficheiros a deletar
    $imaGensDeletar = [];
    while ($rowImg = db_fetch_assoc($resultImages)) {
        $imaGensDeletar[] = $rowImg;
    }
    db_stmt_close($stmtGetImages);
    
    // Eliminar as imagens da BD (tabela mensagens_imagem)
    // Isto será feito automaticamente quando deletarmos as mensagens (se houver constraint)
    // Mas vamos ser explícitos:
    $queryDeleteImages = "DELETE FROM mensagens_imagem WHERE id_mensagem IN (SELECT id_mensagem FROM mensagens WHERE id_chat = ?)";
    $stmtDeleteImages = db_prepare($con, $queryDeleteImages);
    db_stmt_bind_param($stmtDeleteImages, 'i', $chatId);
    
    if (!db_stmt_execute($stmtDeleteImages)) {
        throw new Exception(db_stmt_error($stmtDeleteImages));
    }
    db_stmt_close($stmtDeleteImages);
    
    // Eliminar as mensagens do chat
    $queryDeleteMsg = "DELETE FROM mensagens WHERE id_chat = ?";
    $stmtDeleteMsg = db_prepare($con, $queryDeleteMsg);
    db_stmt_bind_param($stmtDeleteMsg, 'i', $chatId);
    
    if (!db_stmt_execute($stmtDeleteMsg)) {
        throw new Exception(db_stmt_error($stmtDeleteMsg));
    }
    db_stmt_close($stmtDeleteMsg);
    
    // Eliminar o chat
    $queryDeleteChat = "DELETE FROM chats WHERE id_chat = ?";
    $stmtDeleteChat = db_prepare($con, $queryDeleteChat);
    db_stmt_bind_param($stmtDeleteChat, 'i', $chatId);
    
    if (!db_stmt_execute($stmtDeleteChat)) {
        throw new Exception(db_stmt_error($stmtDeleteChat));
    }
    db_stmt_close($stmtDeleteChat);
    
    // Confirmar transação
    db_commit($con);
    
    // Agora, depois de tudo estar guardado, apagar os ficheiros fisicamente (se forem URLs diretos)
    // Se forem URLs em /uploads/vision/, tentamos apagá-los
    foreach ($imaGensDeletar as $img) {
        $filename = $img['filename'];
        // Se for um URL começado por http, é um ficheiro em /uploads/vision/
        if (strpos($filename, 'http') === 0) {
            // Extrair o caminho do ficheiro
            // Ex: http://localhost/uploads/vision/abc123.jpg -> /uploads/vision/abc123.jpg
            preg_match('/uploads\/vision\/[^\/]+$/', $filename, $matches);
            if (!empty($matches[0])) {
                $filePath = __DIR__ . $matches[0];
                if (file_exists($filePath)) {
                    @unlink($filePath); // Apagar silenciosamente se falhar
                }
            }
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Chat e imagens eliminados com sucesso']);
    
} catch (Exception $e) {
    // Reverter transação em caso de erro
    db_rollback($con);
    echo json_encode(['success' => false, 'error' => 'Erro ao eliminar chat: ' . $e->getMessage()]);
}

exit;
?>
