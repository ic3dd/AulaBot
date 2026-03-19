<?php
session_start();

echo "<h2>Teste de Sessão</h2>";
echo "<h3>Dados da Sessão:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Status:</h3>";
if (isset($_SESSION['email']) && isset($_SESSION['nome'])) {
    echo "<strong style='color:green'>✓ AUTENTICADO</strong><br>";
    echo "Email: " . $_SESSION['email'] . "<br>";
    echo "Nome: " . $_SESSION['nome'] . "<br>";
} else {
    echo "<strong style='color:red'>✗ NÃO AUTENTICADO</strong><br>";
}
?>
