<?php
// Verifica se o site está bloqueado e redireciona utilizadores não-admin
// Não inclui auth_check.php para evitar chamadas duplicadas a session_start().
if (session_status() === PHP_SESSION_NONE) session_start();

// Conectar à BD
// Corrigir caminho para ligarbd.php
require_once __DIR__ . '/ligarbd.php';

$bloqueado = 0;
try {
    // Verifica estado na tabela 'bloqueio' (onde o admin toggle pode gravar)
    $res = mysqli_query($con, "SELECT bloqueio FROM bloqueio ORDER BY id_bloqueio DESC LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $bloqueado = max($bloqueado, intval($row['bloqueio']));
    }

    // Também verifica na tabela 'configuracoes_site' por compatibilidade com outras rotinas
    $res2 = mysqli_query($con, "SELECT site_bloqueado FROM configuracoes_site WHERE id = 1 LIMIT 1");
    if ($res2 && mysqli_num_rows($res2) > 0) {
        $row2 = mysqli_fetch_assoc($res2);
        $bloqueado = max($bloqueado, intval($row2['site_bloqueado']));
    }
} catch (Exception $e) {
    // Em caso de erro, assumimos desbloqueado para não causar downtime acidental
    $bloqueado = 0;
}

// Se bloqueado e não for admin, redirecionar para página de bloqueio
if ($bloqueado === 1) {
    $isAdmin = isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'admin';
    if (!$isAdmin) {
        // Evita loop de redirecionamento se já estamos na página de manutenção
        $current = basename($_SERVER['SCRIPT_NAME']);
        if ($current !== 'site_bloqueado.php') {
            header('Location: site_bloqueado.php');
            exit();
        }
    }
}

?>
