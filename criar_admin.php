<?php
/**
 * Script para criar conta de administrador
 * Executa uma vez em: http://localhost/AulaBot/criar_admin.php
 * APAGA este ficheiro após usar (segurança)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/ligarbd.php';

$email = 'santiagoesteves35@gmail.com';
$nome = 'Administrador';
$password_temp = 'AdminAulaBot2025!'; // Altera após o primeiro login

$con = $con ?? $conn ?? null;
if (!$con) {
    die('Erro: Não foi possível conectar à base de dados.');
}

// Verificar se o utilizador já existe
$stmt = db_prepare($con, "SELECT id_utilizador, tipo FROM utilizador WHERE email = ?");
db_stmt_bind_param($stmt, "s", $email);
db_stmt_execute($stmt);
$res = db_stmt_get_result($stmt);
$user = db_fetch_assoc($res);
db_stmt_close($stmt);

if ($user) {
    // Utilizador existe: promover a admin
    if ($user['tipo'] === 'admin') {
        echo "<h2>✓ Conta já é administrador</h2>";
        echo "<p>O email <strong>{$email}</strong> já tem privilégios de administrador.</p>";
        echo "<p><a href='index.php'>Ir para o site</a> | <a href='admin/dashboard.php'>Painel Admin</a></p>";
        exit;
    }
    
    $stmt = db_prepare($con, "UPDATE utilizador SET tipo = 'admin' WHERE email = ?");
    db_stmt_bind_param($stmt, "s", $email);
    if (db_stmt_execute($stmt)) {
        db_stmt_close($stmt);
        echo "<h2>✓ Conta promovida a administrador</h2>";
        echo "<p>O email <strong>{$email}</strong> foi promovido a administrador.</p>";
        echo "<p>Usa a tua <strong>palavra-passe atual</strong> para fazer login.</p>";
        echo "<p><a href='conta/login.php'>Fazer login</a> | <a href='admin/dashboard.php'>Painel Admin</a></p>";
    } else {
        echo "<h2>✗ Erro</h2><p>Não foi possível promover a admin.</p>";
    }
} else {
    // Criar novo utilizador admin
    $hash = password_hash($password_temp, PASSWORD_DEFAULT);
    $tipo = 'admin';
    $temaMaterias = json_encode(['portugues', 'matematica', 'fisica', 'quimica', 'biologia', 'historia', 'geografia', 'ingles', 'francés', 'artes', 'educacao_fisica', 'cidadania'], JSON_UNESCAPED_UNICODE);
    
    $stmt = db_prepare($con, "INSERT INTO utilizador (nome, email, palavra_passe, tipo, tema_escola, data_criacao) VALUES (?, ?, ?, ?, ?, NOW())");
    db_stmt_bind_param($stmt, "sssss", $nome, $email, $hash, $tipo, $temaMaterias);
    
    if (db_stmt_execute($stmt)) {
        db_stmt_close($stmt);
        echo "<h2>✓ Conta de administrador criada</h2>";
        echo "<p><strong>Email:</strong> {$email}</p>";
        echo "<p><strong>Palavra-passe temporária:</strong> <code>{$password_temp}</code></p>";
        echo "<p style='color:#c00;'><strong>Importante:</strong> Altera a palavra-passe após o primeiro login (Perfil → Alterar palavra-passe).</p>";
        echo "<p><a href='conta/login.php'>Fazer login</a> | <a href='admin/dashboard.php'>Painel Admin</a></p>";
    } else {
        echo "<h2>✗ Erro</h2><p>Não foi possível criar a conta: " . db_stmt_error($stmt) . "</p>";
    }
}

echo "<hr><p style='color:#666;font-size:12px;'>Após usar, apaga o ficheiro <code>criar_admin.php</code> por segurança.</p>";
