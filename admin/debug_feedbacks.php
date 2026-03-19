<?php
require_once(__DIR__ . '/../auth/auth_check.php');
require_once(__DIR__ . '/../ligarbd.php');

echo "Verificando feedbacks...\n";
echo "Conexão: " . ($con ? "OK" : "FALHOU") . "\n";

if ($con) {
    // Testar a query de contagem
    $sqlTotal = "SELECT COUNT(*) as total FROM feedback WHERE lido = 'nao'";
    $resultTotal = db_query($con, $sqlTotal);
    
    if ($resultTotal) {
        $row = db_fetch_assoc($resultTotal);
        echo "Total de feedbacks não lidos: " . $row['total'] . "\n";
    } else {
        echo "Erro na query total: " . db_error($con) . "\n";
    }
    
    // Testar a query de seleção
    $sql = "SELECT id_feedback, nome, email, rating, gostou, melhoria, autorizacao, data_feedback, lido 
            FROM feedback 
            WHERE lido = 'nao' 
            ORDER BY data_feedback DESC 
            LIMIT 8";
    
    $result = db_query($con, $sql);
    if ($result) {
        echo "Feedbacks retornados: " . db_num_rows($result) . "\n";
        while ($row = db_fetch_assoc($result)) {
            echo "- ID: " . $row['id_feedback'] . ", Nome: " . $row['nome'] . ", Data: " . $row['data_feedback'] . "\n";
        }
    } else {
        echo "Erro na query de seleção: " . db_error($con) . "\n";
    }
} else {
    echo "Não conseguiu conectar à base de dados\n";
}
?>
