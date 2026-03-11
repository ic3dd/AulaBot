<?php
// Arquivo de teste para verificar se a autenticação está funcionando
session_start();

echo "<h2>Teste de Autenticação</h2>";

if (isset($_SESSION['email']) && isset($_SESSION['nome'])) {
    echo "<p style='color: green;'>✅ Utilizador autenticado!</p>";
    echo "<p><strong>Nome:</strong> " . htmlspecialchars($_SESSION['nome']) . "</p>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($_SESSION['email']) . "</p>";
    echo "<p><strong>Tipo:</strong> " . htmlspecialchars($_SESSION['tipo']) . "</p>";
    echo "<p><a href='logout.php?id=" . urlencode($_SESSION['id_utilizador'] ?? '') . "'>Fazer Logout</a></p>";
} else {
    echo "<p style='color: red;'>❌ Utilizador NÃO autenticado!</p>";
    echo "<p><a href='index.php'>Ir para Login</a></p>";
}

echo "<hr>";
echo "<h3>Links para testar:</h3>";
echo "<ul>";
echo "<li><a href='index.php'>Página Principal</a></li>";
echo "<li><a href='aba-ajuda/ajuda.php'>Ajuda</a></li>";
echo "<li><a href='aba-feedback/feedback.php'>Feedback</a></li>";
echo "</ul>";
?>
