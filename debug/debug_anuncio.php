<?php
session_start();
require('ligarbd.php');

echo "<h2>Debug do Sistema de Anúncios</h2>";

echo "<h3>1. Sessão do Utilizador:</h3>";
echo "Email: " . ($_SESSION['email'] ?? 'NÃO DEFINIDO') . "<br>";
echo "Nome: " . ($_SESSION['nome'] ?? 'NÃO DEFINIDO') . "<br>";
echo "Autenticado: " . (isset($_SESSION['email']) ? 'SIM' : 'NÃO') . "<br>";

echo "<h3>2. Último Anúncio na BD:</h3>";
$resultadoUpdate = db_query($con, "SELECT id_update, nome, versao, descricao, data_update FROM updates ORDER BY data_update DESC LIMIT 1");
if ($resultadoUpdate && db_num_rows($resultadoUpdate) > 0) {
    $update = db_fetch_assoc($resultadoUpdate);
    echo "<pre>";
    print_r($update);
    echo "</pre>";
    
    $idUpdate = $update['id_update'];
    $emailUtilizador = db_real_escape_string($con, $_SESSION['email'] ?? '');
    
    echo "<h3>3. Verificar se utilizador já viu este anúncio:</h3>";
    $queryVerificar = "SELECT * FROM anuncios_vistos 
                      WHERE email_utilizador = '$emailUtilizador' 
                      AND id_update = $idUpdate";
    
    $resultadoVerificar = db_query($con, $queryVerificar);
    echo "Query: $queryVerificar<br>";
    echo "Resultados: " . db_num_rows($resultadoVerificar) . "<br>";
    
    if (db_num_rows($resultadoVerificar) > 0) {
        echo "<strong>Utilizador JÁ VIU este anúncio:</strong><br>";
        while ($row = db_fetch_assoc($resultadoVerificar)) {
            echo "<pre>";
            print_r($row);
            echo "</pre>";
        }
    } else {
        echo "<strong style='color: green;'>Utilizador NUNCA VIU este anúncio - DEVE APARECER!</strong><br>";
    }
    
    echo "<h3>4. Verificar últimas 24 horas:</h3>";
    $queryVerificar24h = "SELECT * FROM anuncios_vistos 
                          WHERE email_utilizador = '$emailUtilizador' 
                          AND id_update = $idUpdate 
                          AND data_visualizacao > " . db_sql_date_sub('24 HOUR') . "";
    
    $resultadoVerificar24h = db_query($con, $queryVerificar24h);
    echo "Query: $queryVerificar24h<br>";
    echo "Resultados nas últimas 24h: " . db_num_rows($resultadoVerificar24h) . "<br>";
    
    if (db_num_rows($resultadoVerificar24h) === 0) {
        echo "<strong style='color: green;'>ANÚNCIO DEVE APARECER (não visto nas últimas 24h)</strong><br>";
    } else {
        echo "<strong style='color: red;'>ANÚNCIO NÃO DEVE APARECER (já visto nas últimas 24h)</strong><br>";
    }
    
} else {
    echo "<strong style='color: red;'>NENHUM ANÚNCIO ENCONTRADO NA TABELA 'updates'</strong><br>";
    echo "Erro MySQL: " . db_error($con) . "<br>";
}

echo "<h3>5. Variável \$mostrarAnuncio no index.php:</h3>";
echo "Para testar, acesse index.php e veja se o modal aparece.<br>";

db_close($con);
?>
