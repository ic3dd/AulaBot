<?php
require_once(__DIR__ . '/../auth/auth_check.php');
require_once(__DIR__ . '/../ligarbd.php');

if ($con) {
    // Verificar todas as linhas da tabela feedback
    $sql = "SELECT * FROM feedback ORDER BY data_feedback DESC";
    $result = db_query($con, $sql);
    
    if ($result) {
        $count = db_num_rows($result);
        echo "Total de feedbacks na tabela: " . $count . "\n\n";
        
        while ($row = db_fetch_assoc($result)) {
            echo "ID: " . $row['id_feedback'] . "\n";
            echo "Nome: " . $row['nome'] . "\n";
            echo "Email: " . $row['email'] . "\n";
            echo "Rating: " . $row['rating'] . "\n";
            echo "Gostou: " . $row['gostou'] . "\n";
            echo "Melhoria: " . $row['melhoria'] . "\n";
            echo "Lido: " . $row['lido'] . "\n";
            echo "Data: " . $row['data_feedback'] . "\n";
            echo "---\n";
        }
    } else {
        echo "Erro: " . db_error($con);
    }
} else {
    echo "Falha na conexão";
}
?>
