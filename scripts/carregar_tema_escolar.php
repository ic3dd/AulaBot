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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

    $email = $_SESSION['email'];

    $sql = "SELECT tema_escola FROM utilizador WHERE email = ? LIMIT 1";
    $stmt = db_prepare($con, $sql);

    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . db_error($con));
    }

    db_stmt_bind_param($stmt, "s", $email);
    db_stmt_execute($stmt);
    $result = db_stmt_get_result($stmt);

    if (db_num_rows($result) === 0) {
        db_stmt_close($stmt);
        respondJson(false, 'Utilizador não encontrado no sistema.', 404);
    }

    $user = db_fetch_assoc($result);
    db_stmt_close($stmt);

    $tema = $user['tema_escola'];
    $tipo = null;
    
    if ($tema === 'todas') {
        $tipo = 'todas';
    } elseif ($tema && $tema !== 'todas') {
        $tipo = 'especifica';
    }

    respondJson(true, '', 200, [
        'tema_escola' => $tema,
        'tipo' => $tipo,
        'descricao' => $tema === 'todas' ? 'Todas as matérias' : ($tema ? $tema : 'Não definida')
    ]);

    db_close($con);

} catch (Exception $e) {
    error_log("Erro ao carregar tema escola: " . $e->getMessage());
    respondJson(false, 'Erro ao processar a requisição. Por favor, tente novamente.', 500);
}

exit;
?>
