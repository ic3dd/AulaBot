<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: application/json; charset=utf-8');

function respondJson($success, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['sucesso' => $success, 'erro' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(false, 'Método HTTP não permitido.', 405);
}

if (!isset($_SESSION['email'])) {
    respondJson(false, 'A sua sessão expirou. Por favor, autentique-se novamente.', 401);
}

require('ligarbd.php');

if (!$con) {
    respondJson(false, 'Erro ao conectar à base de dados. Tente novamente mais tarde.', 500);
}

$idUpdate = isset($_POST['id_update']) ? (int)$_POST['id_update'] : 0;

if ($idUpdate <= 0) {
    respondJson(false, 'Identificador do anúncio inválido.', 400);
}

$emailUtilizador = mysqli_real_escape_string($con, $_SESSION['email']);

$queryId = "SELECT id_utilizador FROM utilizador WHERE email = '$emailUtilizador' LIMIT 1";
$resultadoId = mysqli_query($con, $queryId);

if (!$resultadoId || mysqli_num_rows($resultadoId) === 0) {
    respondJson(false, 'Utilizador não encontrado no sistema.', 404);
}

$row = mysqli_fetch_assoc($resultadoId);
$idUtilizador = (int)$row['id_utilizador'];

$query = "INSERT INTO anuncios_vistos (id_utilizador, id_update, data_visualizacao) 
          VALUES ($idUtilizador, $idUpdate, NOW())
          ON DUPLICATE KEY UPDATE data_visualizacao = NOW()";

$resultado = mysqli_query($con, $query);

if ($resultado) {
    respondJson(true, '', 200);
} else {
    respondJson(false, 'Erro ao registar visualização do anúncio. Tente novamente.', 500);
}

mysqli_close($con);
?>
