<?php
// Iniciar sessão para acesso às variáveis de sessão
// Definir cookie path para '/' para garantir acesso global
session_set_cookie_params(0, '/');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../ligarbd.php';

function verificarAutenticacao()
{
    global $con;

    // Verificar se as variáveis de sessão essenciais existem
    if (!isset($_SESSION['email']) || !isset($_SESSION['nome'])) {
        // Utilizador não autenticado - redirecionar para login
        header('Location: ../index.php');
        exit(); // Parar execução para garantir redirecionamento
    }

    // Verificar se o site está bloqueado
    $query = "SELECT site_bloqueado FROM configuracoes_site WHERE id = 1";
    $result = db_query($con, $query);

    if ($result && db_num_rows($result) > 0) {
        $row = db_fetch_assoc($result);
        if ($row['site_bloqueado'] && !verificarSeAdmin()) {
            // Site bloqueado e usuário não é admin
            header('Location: ../site_bloqueado.php');
            exit();
        }
    }
}


function verificarSeJaAutenticado()
{
    // Verificar se as variáveis de sessão existem
    if (isset($_SESSION['email']) && isset($_SESSION['nome'])) {
        return true; // Utilizador está autenticado
    }
    return false; // Utilizador não está autenticado
}


function obterDadosUtilizador()
{
    if (verificarSeJaAutenticado()) {
        return [
            'nome' => $_SESSION['nome'],
            'email' => $_SESSION['email'],
            'tipo' => $_SESSION['tipo'] ?? 'utilizador'
        ];
    }
    return false;
}


function verificarSeAdmin()
{
    if (verificarSeJaAutenticado()) {
        return isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'admin';
    }
    return false;
}


function verificarPermissoesAdmin()
{
    // Primeiro verificar se está autenticado
    verificarAutenticacao();

    // Depois verificar se é admin
    if (!verificarSeAdmin()) {
        // Não é admin - redirecionar para página principal
        header('Location: ../index.php');
        exit();
    }
}
?>