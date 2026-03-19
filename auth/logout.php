<?php
session_start();

// Apenas tenta registar o logout se um ID de utilizador estiver na sessão
$id_utilizador = $_SESSION['id_utilizador'] ?? $_SESSION['user_id'] ?? null;
if (isset($id_utilizador)) {
    require_once(__DIR__ . '/../ligarbd.php');

    // Verifica se a conexão à base de dados foi bem-sucedida
    if (isset($con) && $con) {
        // Usa um statement preparado para prevenir injeção de SQL
        $sql = "INSERT INTO registo (reg, data, id_utilizador) VALUES ('logout', NOW(), ?)";
        $stmt = db_prepare($con, $sql);
        
        if ($stmt) {
            db_stmt_bind_param($stmt, "i", $id_utilizador);
            db_stmt_execute($stmt);
            db_stmt_close($stmt);
        }
        
        db_close($con);
    }
}

// Limpa e destrói a sessão
session_unset();
session_destroy();

// Redireciona para a página de login
header('Location: index.php?logout=true');
exit;
?>
