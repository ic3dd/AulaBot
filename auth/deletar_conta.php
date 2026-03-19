<?php
session_start();


require(__DIR__ . '/../ligarbd.php');

// Verificar conexão com a BD
if (!$con) {
    error_log("Deletar conta - Erro de conexão à base de dados");
    header('Location: index.php?error=database_connection');
    exit;
}

error_log("Deletar conta - Conexão à BD OK");

$email = $_SESSION['email'];
error_log("Deletar conta - Email: " . $email);

try {
    // Iniciar transação
    db_begin_transaction($con);
    
    // Verificar se o utilizador existe
    $sql = "SELECT id_utilizador FROM utilizador WHERE email = ?";
    $stmt = db_prepare($con, $sql);
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . db_error($con));
    }
    
    db_stmt_bind_param($stmt, "s", $email);
    db_stmt_execute($stmt);
    $result = db_stmt_get_result($stmt);

    if (db_num_rows($result) === 0) {
        error_log("Deletar conta - Utilizador não encontrado na BD");
        db_rollback($con);
        db_stmt_close($stmt);
        header('Location: index.php?error=user_not_found');
        exit;
    }
    
    $user = db_fetch_assoc($result);
    $userId = $user['id_utilizador'];
    error_log("Deletar conta - ID do utilizador: " . $userId);
    db_stmt_close($stmt);

    // Eliminar mensagens de chat do utilizador
    $sql = "SELECT id FROM chat_ajuda WHERE id_utilizador = ?";
    $stmt = db_prepare($con, $sql);
    db_stmt_bind_param($stmt, "i", $userId);
    db_stmt_execute($stmt);
    $chatResult = db_stmt_get_result($stmt);
    db_stmt_close($stmt);
    
    while ($chatRow = db_fetch_assoc($chatResult)) {
        $chatId = $chatRow['id'];
        $sqlDelMsg = "DELETE FROM mensagens_chat_ajuda WHERE conversation_id = ?";
        $stmtDelMsg = db_prepare($con, $sqlDelMsg);
        db_stmt_bind_param($stmtDelMsg, "i", $chatId);
        if (!db_stmt_execute($stmtDelMsg)) {
            throw new Exception('Erro ao eliminar mensagens de chat: ' . db_stmt_error($stmtDelMsg));
        }
        db_stmt_close($stmtDelMsg);
    }
    error_log("Deletar conta - Mensagens de chat eliminadas");

    // Eliminar conversas de chat do utilizador
    $sql = "DELETE FROM chat_ajuda WHERE id_utilizador = ?";
    $stmt = db_prepare($con, $sql);
    db_stmt_bind_param($stmt, "i", $userId);
    if (!db_stmt_execute($stmt)) {
        throw new Exception('Erro ao eliminar chat_ajuda: ' . db_stmt_error($stmt));
    }
    db_stmt_close($stmt);
    error_log("Deletar conta - Conversas de chat eliminadas");

    // Eliminar anúncios vistos pelo utilizador
    $sql = "DELETE FROM anuncios_vistos WHERE id_utilizador = ?";
    $stmt = db_prepare($con, $sql);
    db_stmt_bind_param($stmt, "i", $userId);
    if (!db_stmt_execute($stmt)) {
        throw new Exception('Erro ao eliminar anuncios_vistos: ' . db_stmt_error($stmt));
    }
    db_stmt_close($stmt);
    error_log("Deletar conta - Anúncios vistos eliminados");

    // Eliminar feedback do utilizador
    $sql = "DELETE FROM feedback WHERE email = ?";
    $stmt = db_prepare($con, $sql);
    db_stmt_bind_param($stmt, "s", $email);
    if (!db_stmt_execute($stmt)) {
        throw new Exception('Erro ao eliminar feedback: ' . db_stmt_error($stmt));
    }
    db_stmt_close($stmt);
    error_log("Deletar conta - Feedback eliminado");

    // Eliminar registo do utilizador
    $sql = "DELETE FROM registo WHERE email = ?";
    $stmt = db_prepare($con, $sql);
    db_stmt_bind_param($stmt, "s", $email);
    if (!db_stmt_execute($stmt)) {
        throw new Exception('Erro ao eliminar registo: ' . db_stmt_error($stmt));
    }
    db_stmt_close($stmt);
    error_log("Deletar conta - Registo eliminado");


    // Deletar a conta do utilizador
    $sql = "DELETE FROM utilizador WHERE id_utilizador = ?";
    $stmt = db_prepare($con, $sql);
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar query de exclusão: ' . db_error($con));
    }
    
    db_stmt_bind_param($stmt, "i", $userId);
    
    if (!db_stmt_execute($stmt)) {
        throw new Exception('Erro ao executar exclusão: ' . db_stmt_error($stmt));
    }
    db_stmt_close($stmt);
    error_log("Deletar conta - Utilizador deletado com sucesso");

    // Confirmar transação
    db_commit($con);
    
    // Destruir a sessão
    $_SESSION = array();
    
    // Destruir o cookie de sessão se existir
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir a sessão
    session_destroy();
    
    error_log("Deletar conta - Sessão destruída, redirecionando...");
    
    // Redirecionar para a página principal com mensagem de sucesso
    header('Location: index.php?deleted=1');
    exit;
    
} catch (Exception $e) {
    error_log("Deletar conta - Exceção: " . $e->getMessage());
    db_rollback($con);
    header('Location: index.php?error=server_error');
}

db_close($con);
?>
