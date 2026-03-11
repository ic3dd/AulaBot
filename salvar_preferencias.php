<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
// MODO DEBUG CLI: quando executado via CLI, atribuimos um email de sessão para testes
if (php_sapi_name() === 'cli') {
    $_SESSION['email'] = $_SESSION['email'] ?? 'dev@example.com';
}
include_once 'ligarbd.php';

// Garantir coluna de forma compatível com MySQL antigo (sem 'IF NOT EXISTS')
function ensure_column_exists($con, $table, $column, $definition) {
    $tableSafe = mysqli_real_escape_string($con, $table);
    $columnSafe = mysqli_real_escape_string($con, $column);
    $res = mysqli_query($con, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tableSafe' AND COLUMN_NAME = '$columnSafe' LIMIT 1");
    if ($res && mysqli_num_rows($res) === 0) {
        $sql = "ALTER TABLE `$tableSafe` ADD COLUMN `$columnSafe` $definition";
        if (!mysqli_query($con, $sql)) {
            error_log("ensure_column_exists failed for $table.$column: " . mysqli_error($con));
        }
    }
} 

function respondJson($success, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['email'])) {
    respondJson(false, ['error' => 'A sua sessão expirou. Por favor, autentique-se novamente.'], 401);
}



try {
    // MODO DEBUG CLI: se executado via CLI, simula sessão e input para testar
    if (php_sapi_name() === 'cli') {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['email'] = $_SESSION['email'] ?? 'dev@example.com';
        $raw = '{"tema":"light","cor":"#4f46e5","fonte":"large"}';
        $data = json_decode($raw, true);
    } else {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
    }

    if (json_last_error() !== JSON_ERROR_NONE) {
        respondJson(false, ['error' => 'Dados recebidos em formato inválido.'], 400);
    }

    $tema = $data['tema'] ?? null;
    $cor = $data['cor'] ?? null;
    $fonte = $data['fonte'] ?? null;

    // validar fonte esperada
    $allowedFonts = ['small','medium','large'];
    if (!$tema || !$cor || !$fonte || !in_array($fonte, $allowedFonts)) {
        respondJson(false, ['error' => 'Por favor, preencha todos os campos obrigatórios com valores válidos.'], 400);
    }

    $temaDB = ($tema === 'dark') ? 'escuro' : 'claro';
    $email = trim($_SESSION['email']);

    // Verificar ligação ao BD e usar fallback em sessão se estiver indisponível
    if (empty($con) || mysqli_connect_errno()) {
        error_log('salvar_preferencias: Falha na ligação ao BD: ' . mysqli_connect_error());
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['preferences'] = [
            'tema' => $temaDB,
            'cor' => $cor,
            'fonte' => $fonte
        ];
        respondJson(true, [
            'message' => 'Preferências guardadas em sessão (fallback - BD indisponível).',
            'tema' => $temaDB,
            'cor' => $cor,
            'fonte' => $fonte
        ], 200);
    }

    // Garantir colunas de forma compatível
    ensure_column_exists($con, 'utilizador', 'tema', "VARCHAR(10) DEFAULT 'claro'");
    ensure_column_exists($con, 'utilizador', 'cor', "VARCHAR(10) DEFAULT '#28a745'");
    ensure_column_exists($con, 'utilizador', 'fonte', "VARCHAR(10) DEFAULT 'medium'");

    // Atualizar (ou inserir se não existir)
    $stmt = $con->prepare("
        UPDATE utilizador 
        SET tema = ?, cor = ?, fonte = ? 
        WHERE email = ?
    ");
    if (!$stmt) {
        throw new Exception("Erro ao preparar query: " . $con->error);
    }

    $stmt->bind_param("ssss", $temaDB, $cor, $fonte, $email);
    if (!$stmt->execute()) {
        $err = $stmt->error ?: $con->error;
        error_log("salvar_preferencias execute failed: " . $err);
        throw new Exception("Erro ao executar atualização de preferências");
    }

    if ($stmt->affected_rows === 0) {
        $check = $con->prepare("SELECT COUNT(*) FROM utilizador WHERE email = ?");
        if (!$check) {
            error_log("salvar_preferencias check prepare failed: " . $con->error);
            throw new Exception("Erro interno ao verificar utilizador");
        }
        $check->bind_param("s", $email);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count === 0) {
            throw new Exception("Utilizador não encontrado no sistema.");
        }
    }

    $stmt->close();
    $con->close();

    respondJson(true, [
        'message' => 'Preferências guardadas com sucesso!',
        'tema' => $temaDB,
        'cor' => $cor,
        'fonte' => $fonte
    ], 200);

} catch (Exception $e) {
    error_log("Erro em salvar_preferencias.php: " . $e->getMessage());
    respondJson(false, ['error' => 'Erro ao guardar preferências. Tente novamente.'], 500);
}
?>
