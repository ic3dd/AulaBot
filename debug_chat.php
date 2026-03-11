<?php
// Script de debug para verificar problemas com chats
session_start();
require_once 'ligarbd.php';

echo "<h2>Debug - Informações de Sessão</h2>";
echo "<pre>";
echo "SESSION: " . json_encode($_SESSION, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>";

echo "<h2>Debug - Tabelas de Chats</h2>";

if ($con && mysqli_ping($con)) {
    echo "<p style='color: green;'>✅ Conexão à BD OK</p>";
    
    // Verificar se tabelas existem
    $result = mysqli_query($con, "SHOW TABLES LIKE 'chats'");
    if ($result && mysqli_num_rows($result) > 0) {
        echo "<p style='color: green;'>✅ Tabela 'chats' existe</p>";
        
        // Listar chats
        $id_util = $_SESSION['id_utilizador'] ?? $_SESSION['user_id'] ?? 0;
        $sql = "SELECT * FROM chats WHERE id_utilizador = ?";
        $stmt = mysqli_prepare($con, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id_util);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            echo "<p>Chats do utilizador ID $id_util:</p>";
            if ($result && mysqli_num_rows($result) > 0) {
                echo "<table border='1' cellpadding='5'>";
                echo "<tr><th>ID</th><th>Título</th><th>Criação</th><th>Atualização</th></tr>";
                while ($row = mysqli_fetch_assoc($result)) {
                    // Tenta ler data_criacao_chat (novo) ou data_criacao (antigo) para evitar erros
                    $dataCriacao = $row['data_criacao_chat'] ?? $row['data_criacao'] ?? 'N/A';
                    echo "<tr><td>" . $row['id_chat'] . "</td><td>" . htmlspecialchars($row['titulo']) . "</td><td>" . $dataCriacao . "</td><td>" . $row['data_atualizacao'] . "</td></tr>";
                }
                echo "</table>";
            } else {
                echo "<p style='color: orange;'>⚠️ Nenhum chat encontrado para este utilizador</p>";
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        echo "<p style='color: red;'>❌ Tabela 'chats' NÃO existe</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Conexão à BD FALHOU</p>";
}

echo "<h2>Debug - Últimas Mensagens</h2>";
if ($con && mysqli_ping($con)) {
    $result = mysqli_query($con, "SHOW TABLES LIKE 'mensagens'");
    if ($result && mysqli_num_rows($result) > 0) {
        echo "<p style='color: green;'>✅ Tabela 'mensagens' existe</p>";
        
        // Listar últimas mensagens
        $sql = "SELECT m.*, c.id_utilizador FROM mensagens m JOIN chats c ON m.id_chat = c.id_chat WHERE c.id_utilizador = ? LIMIT 10";
        $stmt = mysqli_prepare($con, $sql);
        if ($stmt) {
            $id_util = $_SESSION['id_utilizador'] ?? $_SESSION['user_id'] ?? 0;
            mysqli_stmt_bind_param($stmt, 'i', $id_util);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) > 0) {
                echo "<table border='1' cellpadding='5'>";
                echo "<tr><th>Chat ID</th><th>Pergunta</th><th>Data</th></tr>";
                while ($row = mysqli_fetch_assoc($result)) {
                    $pergunta = substr($row['pergunta'], 0, 50) . (strlen($row['pergunta']) > 50 ? '...' : '');
                    echo "<tr><td>" . $row['id_chat'] . "</td><td>" . htmlspecialchars($pergunta) . "</td><td>" . $row['data_conversa'] . "</td></tr>";
                }
                echo "</table>";
            } else {
                echo "<p style='color: orange;'>⚠️ Nenhuma mensagem encontrada</p>";
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        echo "<p style='color: red;'>❌ Tabela 'mensagens' NÃO existe</p>";
    }
}

mysqli_close($con);
?>
