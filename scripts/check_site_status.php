<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../ligarbd.php';

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

$isAdmin = isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'admin';
$isAuthenticated = isset($_SESSION['email']) && isset($_SESSION['nome']);

echo json_encode([
    'bloqueado' => $bloqueado === 1,
    'isAdmin' => $isAdmin,
    'isAuthenticated' => $isAuthenticated
]);
?>
