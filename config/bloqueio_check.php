<?php
// Verifica se o site está bloqueado e redireciona utilizadores não-admin
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/ligarbd.php';

$bloqueado = 0;
try {
    $res = db_query($con, "SELECT bloqueio FROM bloqueio ORDER BY id_bloqueio DESC LIMIT 1");
    if ($res && db_num_rows($res) > 0) {
        $row = db_fetch_assoc($res);
        $bloqueado = max($bloqueado, intval($row['bloqueio']));
    }
    $res2 = db_query($con, "SELECT site_bloqueado FROM configuracoes_site WHERE id = 1 LIMIT 1");
    if ($res2 && db_num_rows($res2) > 0) {
        $row2 = db_fetch_assoc($res2);
        $bloqueado = max($bloqueado, intval($row2['site_bloqueado']));
    }
} catch (Exception $e) {
    $bloqueado = 0;
}

if ($bloqueado === 1) {
    $isAdmin = isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'admin';
    if (!$isAdmin) {
        $current = basename($_SERVER['SCRIPT_NAME']);
        if ($current !== 'site_bloqueado.php') {
            header('Location: site_bloqueado.php');
            exit();
        }
    }
}
?>
