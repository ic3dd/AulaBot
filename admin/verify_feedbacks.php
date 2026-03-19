<?php
// Script de diagnóstico para verificar o estado dos feedbacks na base de dados
// Útil para debug quando os feedbacks não aparecem na dashboard
require_once(__DIR__ . '/../ligarbd.php');

echo "<h1>Teste de Feedbacks</h1>";

if ($con) {
    echo "<p>✅ Conexão estabelecida</p>";
    
    // Test 1: Count all feedbacks
    // Conta total de registos na tabela feedback
    $sqlCount = "SELECT COUNT(*) as total FROM feedback";
    $resultCount = db_query($con, $sqlCount);
    $rowCount = db_fetch_assoc($resultCount);
    echo "<p>Total de feedbacks na BD: " . $rowCount['total'] . "</p>";
    
    // Test 2: Count unread feedbacks
    // Conta apenas os feedbacks marcados como não lidos
    $sqlUnread = "SELECT COUNT(*) as total FROM feedback WHERE lido = 'nao'";
    $resultUnread = db_query($con, $sqlUnread);
    $rowUnread = db_fetch_assoc($resultUnread);
    echo "<p>Feedbacks não lidos: " . $rowUnread['total'] . "</p>";
    
    // Test 3: Show last 5 feedbacks
    // Lista os detalhes dos últimos 5 feedbacks para inspeção visual
    echo "<h2>Últimos 5 feedbacks:</h2>";
    $sql = "SELECT id_feedback, nome, email, rating, data_feedback, lido FROM feedback ORDER BY id_feedback DESC LIMIT 5";
    $result = db_query($con, $sql);
    
    if (db_num_rows($result) > 0) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Email</th><th>Rating</th><th>Data</th><th>Lido</th></tr>";
        while ($row = db_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>" . $row['id_feedback'] . "</td>";
            echo "<td>" . $row['nome'] . "</td>";
            echo "<td>" . $row['email'] . "</td>";
            echo "<td>" . $row['rating'] . "</td>";
            echo "<td>" . $row['data_feedback'] . "</td>";
            echo "<td>" . $row['lido'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhum feedback encontrado</p>";
    }
} else {
    echo "<p>❌ Falha na conexão à base de dados</p>";
}
?>
