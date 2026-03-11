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
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Dados recebidos em formato inválido.');
    }

    $disciplina = isset($data['disciplina']) && is_array($data['disciplina']) ? $data['disciplina'] : [];
    
    $email = trim($_SESSION['email']);

    $checkColumn = mysqli_query($con, "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='utilizador' AND COLUMN_NAME='tema_escola'");
    if ($checkColumn) {
        if (mysqli_num_rows($checkColumn) === 0) {
            mysqli_query($con, "ALTER TABLE utilizador ADD COLUMN tema_escola LONGTEXT DEFAULT NULL");
        } else {
            $row = mysqli_fetch_assoc($checkColumn);
            if(strtolower($row['DATA_TYPE']) !== 'longtext') {
                 mysqli_query($con, "ALTER TABLE utilizador MODIFY COLUMN tema_escola LONGTEXT DEFAULT NULL");
            }
        }
    }

    $disciplina_json = json_encode($disciplina, JSON_UNESCAPED_UNICODE);

    $sql_update = "UPDATE utilizador SET tema_escola = ? WHERE email = ?";
    $stmt_update = mysqli_prepare($con, $sql_update);

    if (!$stmt_update) {
        throw new Exception('Erro ao preparar query de atualização: ' . mysqli_error($con));
    }

    mysqli_stmt_bind_param($stmt_update, "ss", $disciplina_json, $email);

    if (!mysqli_stmt_execute($stmt_update)) {
        mysqli_stmt_close($stmt_update);
        throw new Exception('Erro ao atualizar disciplinas');
    }

    mysqli_stmt_close($stmt_update);

    respondJson(true, ['message' => 'Disciplinas salvas com sucesso!', 'disciplina' => $disciplina], 200);

} catch (Exception $e) {
    error_log("Erro ao salvar disciplinas: " . $e->getMessage());
    respondJson(false, ['error' => 'Erro ao guardar disciplinas. ' . $e->getMessage()], 500);
}
?>