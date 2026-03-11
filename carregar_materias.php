<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
include_once 'ligarbd.php';

function respondJson($success, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['email'])) {
    respondJson(false, ['error' => 'A sua sessão expirou. Por favor, autentique-se novamente.'], 401);
}

if (!isset($con)) {
    respondJson(false, ['error' => 'Erro ao conectar à base de dados. Tente novamente mais tarde.'], 500);
}

try {
    $email = trim($_SESSION['email']);
    
    $checkColumn = mysqli_query($con, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='utilizador' AND COLUMN_NAME='tema_escola'");
    if ($checkColumn && mysqli_num_rows($checkColumn) === 0) {
        mysqli_query($con, "ALTER TABLE utilizador ADD COLUMN tema_escola LONGTEXT DEFAULT NULL");
    }
    
    $queryDisciplina = "SELECT tema_escola FROM utilizador WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($con, $queryDisciplina);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $resultadoDisciplina = mysqli_stmt_get_result($stmt);
    
    $disciplina = [];
    
    if ($resultadoDisciplina && mysqli_num_rows($resultadoDisciplina) > 0) {
        $row = mysqli_fetch_assoc($resultadoDisciplina);
        if (!empty($row['tema_escola'])) {
            $decoded_disciplina = json_decode($row['tema_escola'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_disciplina)) {
                $disciplina = $decoded_disciplina;
            }
        }
    }
    
    mysqli_stmt_close($stmt);
    respondJson(true, ['disciplina' => $disciplina]);
    
} catch (Exception $e) {
    error_log("Erro ao carregar disciplina: " . $e->getMessage());
    respondJson(false, ['error' => 'Erro ao carregar disciplina. ' . $e->getMessage()], 500);
}
?>
