<?php
session_start();
include_once 'ligarbd.php';

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
    $checkColumn = $con->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='utilizador' AND COLUMN_NAME='$coluna'");
    
    if (!$checkColumn || $checkColumn->num_rows === 0) {
        @$con->query("ALTER TABLE utilizador ADD COLUMN $coluna $tipo");
    }
}
?>
