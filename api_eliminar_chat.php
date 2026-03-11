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

if (!$chatId || $chatId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID do chat inválido']);
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

// Iniciar transação para garantir consistência
mysqli_begin_transaction($con);

try {
    // Primeiro, obter todas as imagens associadas às mensagens deste chat
    // para deletar ficheiros fisicamente se existirem
    $queryGetImages = "SELECT mi.id_imagem, mi.filename FROM mensagens_imagem mi 
                       INNER JOIN mensagens m ON mi.id_mensagem = m.id_mensagem 
                       WHERE m.id_chat = ?";
    $stmtGetImages = mysqli_prepare($con, $queryGetImages);
    mysqli_stmt_bind_param($stmtGetImages, 'i', $chatId);
    mysqli_stmt_execute($stmtGetImages);
    $resultImages = mysqli_stmt_get_result($stmtGetImages);
    
    // Guardar os ficheiros a deletar
    $imaGensDeletar = [];
    while ($rowImg = mysqli_fetch_assoc($resultImages)) {
        $imaGensDeletar[] = $rowImg;
    }
    mysqli_stmt_close($stmtGetImages);
    
    // Eliminar as imagens da BD (tabela mensagens_imagem)
    // Isto será feito automaticamente quando deletarmos as mensagens (se houver constraint)
    // Mas vamos ser explícitos:
    $queryDeleteImages = "DELETE FROM mensagens_imagem WHERE id_mensagem IN (SELECT id_mensagem FROM mensagens WHERE id_chat = ?)";
    $stmtDeleteImages = mysqli_prepare($con, $queryDeleteImages);
    mysqli_stmt_bind_param($stmtDeleteImages, 'i', $chatId);
    
    if (!mysqli_stmt_execute($stmtDeleteImages)) {
        throw new Exception(mysqli_stmt_error($stmtDeleteImages));
    }
    mysqli_stmt_close($stmtDeleteImages);
    
    // Eliminar as mensagens do chat
    $queryDeleteMsg = "DELETE FROM mensagens WHERE id_chat = ?";
    $stmtDeleteMsg = mysqli_prepare($con, $queryDeleteMsg);
    mysqli_stmt_bind_param($stmtDeleteMsg, 'i', $chatId);
    
    if (!mysqli_stmt_execute($stmtDeleteMsg)) {
        throw new Exception(mysqli_stmt_error($stmtDeleteMsg));
    }
    mysqli_stmt_close($stmtDeleteMsg);
    
    // Eliminar o chat
    $queryDeleteChat = "DELETE FROM chats WHERE id_chat = ?";
    $stmtDeleteChat = mysqli_prepare($con, $queryDeleteChat);
    mysqli_stmt_bind_param($stmtDeleteChat, 'i', $chatId);
    
    if (!mysqli_stmt_execute($stmtDeleteChat)) {
        throw new Exception(mysqli_stmt_error($stmtDeleteChat));
    }
    mysqli_stmt_close($stmtDeleteChat);
    
    // Confirmar transação
    mysqli_commit($con);
    
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
    mysqli_rollback($con);
    echo json_encode(['success' => false, 'error' => 'Erro ao eliminar chat: ' . $e->getMessage()]);
}

exit;
?>
