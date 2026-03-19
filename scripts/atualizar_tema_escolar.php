<?php
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
ob_clean();

session_start();

function respondJson($success, $message = '', $httpCode = 200, $data = null) {
    http_response_code($httpCode);
    $response = ['sucesso' => $success, 'erro' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(false, 'Método HTTP não permitido.', 405);
}

if (!isset($_SESSION['email'])) {
    respondJson(false, 'A sua sessão expirou. Por favor, autentique-se novamente.', 401);
}

try {
    require(__DIR__ . '/../ligarbd.php');

    if (!$con) {
        respondJson(false, 'Erro ao conectar à base de dados. Tente novamente mais tarde.', 500);
    }

    $tipo_materia = trim($_POST['tipo_materia'] ?? '');
    $materia = trim($_POST['materia'] ?? '');
    $email = $_SESSION['email'];

    if (!$tipo_materia) {
        respondJson(false, 'Por favor, selecione uma opção.', 400);
    }

    $todasAsDisciplinas = ['portugues', 'matematica', 'fisica', 'quimica', 'biologia', 'historia', 'geografia', 'ingles', 'francés', 'artes', 'educacao_fisica', 'cidadania'];
    $tema_escola = null;
    
    if ($tipo_materia === 'todas') {
        $tema_escola = json_encode($todasAsDisciplinas, JSON_UNESCAPED_UNICODE);
    } elseif ($tipo_materia === 'especifica') {
        if (!$materia) {
            respondJson(false, 'Por favor, selecione uma matéria.', 400);
        }
        $tema_escola = json_encode([$materia], JSON_UNESCAPED_UNICODE);
    } else {
        respondJson(false, 'Opção inválida.', 400);
    }

    $sql_update = "UPDATE utilizador SET tema_escola = ? WHERE email = ?";
    $stmt_update = db_prepare($con, $sql_update);

    if (!$stmt_update) {
        throw new Exception('Erro ao preparar query de atualização: ' . db_error($con));
    }

    db_stmt_bind_param($stmt_update, "ss", $tema_escola, $email);

    if (!db_stmt_execute($stmt_update)) {
        db_stmt_close($stmt_update);
        throw new Exception('Erro ao atualizar matéria');
    }

    db_stmt_close($stmt_update);

    $_SESSION['tema_escola'] = $tema_escola;

    $descricao = $tipo_materia === 'todas' ? 'Todas as disciplinas' : $materia;

    respondJson(true, 'Disciplinas atualizadas com sucesso!', 200, ['tema_escola' => $tema_escola, 'descricao' => $descricao]);

    db_close($con);

} catch (Exception $e) {
    error_log("Erro ao atualizar tema escola: " . $e->getMessage());
    respondJson(false, 'Erro ao processar a alteração. Por favor, tente novamente.', 500);
}

exit;
?>
