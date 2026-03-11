<?php
require_once('../auth_check.php');
require_once('../ligarbd.php');

if ($con) {
    // Verificar todas as linhas da tabela feedback
    $sql = "SELECT * FROM feedback ORDER BY data_feedback DESC";
    $result = mysqli_query($con, $sql);
    
    if ($result) {
        $count = mysqli_num_rows($result);
        echo "Total de feedbacks na tabela: " . $count . "\n\n";
        
        while ($row = mysqli_fetch_assoc($result)) {
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
        echo "Erro: " . mysqli_error($con);
    }
} else {
    echo "Falha na conexão";
}
?>
