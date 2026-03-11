<?php
$servername = "localhost";
$username = "aluno19355";
$password = "bCXaf1CsciCwG5F";
$dbname = "aluno19355";

// Ligar à base de dados
$con = new mysqli($servername, $username, $password, $dbname);

// Verificar erros
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

$con->set_charset("utf8mb4");

// Compatibilidade
$conn = $con;
?>