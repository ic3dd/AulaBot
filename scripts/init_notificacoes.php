<?php
session_start();
include_once __DIR__ . '/../ligarbd.php';

if (!isset($_SESSION['email']) || !isset($con)) {
    exit;
}

$colunas = [
    'notif_atualizacoes' => 'TINYINT DEFAULT 1',
    'notif_manutencao' => 'TINYINT DEFAULT 1',
    'notif_novidades' => 'TINYINT DEFAULT 1',
    'notif_seguranca' => 'TINYINT DEFAULT 1',
    'notif_performance' => 'TINYINT DEFAULT 0'
];

foreach ($colunas as $coluna => $tipo) {
    if (!db_column_exists($con, 'utilizador', $coluna)) {
        @db_query($con, db_sql_add_column('utilizador', $coluna, $tipo));
    }
}
?>
