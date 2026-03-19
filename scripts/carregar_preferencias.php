<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
// MODO DEBUG CLI: quando executado via CLI, atribuimos um email de sessão para testes
if (php_sapi_name() === 'cli') {
    $_SESSION['email'] = $_SESSION['email'] ?? 'dev@example.com';
}
include_once __DIR__ . '/../ligarbd.php';

function ensure_column_exists($con, $table, $column, $definition) {
    if (db_column_exists($con, $table, $column)) return;
    $sql = db_sql_add_column($table, $column, $definition);
    if (!db_query($con, $sql)) {
        error_log("ensure_column_exists failed for $table.$column: " . db_error($con));
    }
} 

function respondJson($success, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['email'])) {
    // User is not authenticated. Send a specific response that the frontend can handle, without it being an HTTP error.
    respondJson(false, ['authenticated' => false], 200);
}

try {
    // MODO DEBUG CLI: se executado via CLI, simula sessão para testar
    if (php_sapi_name() === 'cli') {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['email'] = $_SESSION['email'] ?? 'dev@example.com';
    }

    if (empty($con) || db_connect_errno()) {
        // Fallback: BD indisponível → devolver preferências da sessão (se houver) ou padrão
        error_log('carregar_preferencias: Falha na ligação ao BD: ' . db_connect_error());
        if (session_status() === PHP_SESSION_NONE) session_start();
        $sessionPrefs = $_SESSION['preferences'] ?? null;
        if ($sessionPrefs) {
            respondJson(true, ['preferences' => [
                'tema' => $sessionPrefs['tema'],
                'cor' => $sessionPrefs['cor'],
                'fonte' => $sessionPrefs['fonte']
            ]], 200);
        }
        // Caso contrário, devolve valores padrão em vez de erro 500
        respondJson(true, ['preferences' => ['tema' => 'light', 'cor' => '#4f46e5', 'fonte' => 'medium']], 200);
    }

    $email = trim($_SESSION['email']);
    error_log("Buscando preferências para: $email");

    // Garantir colunas de forma compatível
    ensure_column_exists($con, 'utilizador', 'tema', "VARCHAR(10) DEFAULT 'claro'");
    ensure_column_exists($con, 'utilizador', 'cor', "VARCHAR(20) DEFAULT '#28a745'");
    ensure_column_exists($con, 'utilizador', 'fonte', "VARCHAR(10) DEFAULT 'medium'");

    // Buscar preferências
    $stmt = $con->prepare("SELECT tema, cor, fonte FROM utilizador WHERE email = ?");
    if (!$stmt) {
        error_log("carregar_preferencias prepare failed: " . $con->error);
        throw new Exception("Erro ao preparar query de preferências");
    }
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        $err = $stmt->error ?: $con->error;
        error_log("carregar_preferencias execute failed: " . $err);
        throw new Exception("Erro ao executar query de preferências");
    }
    $result = $stmt->get_result();
    if ($result === false) {
        error_log("carregar_preferencias get_result failed: " . $stmt->error);
        throw new Exception("Erro ao obter resultado das preferências");
    }

    if ($row = $result->fetch_assoc()) {
        $tema = $row['tema'] ?? 'claro';
        $cor = $row['cor'] ?? '#28a745';
        $fonte = $row['fonte'] ?? 'medium';
    } else {
        $tema = 'claro';
        $cor = '#28a745';
        $fonte = 'medium';
    }

    // Converter para formato frontend
    $temaFrontend = ($tema === 'escuro') ? 'dark' : 'light';

    // Se o valor da cor não for hex, corrige para o padrão
    if (!preg_match('/^#([A-Fa-f0-9]{3,6})$/', $cor)) {
        $cor = '#28a745';
    }

    // validar/normalizar fonte
    $allowedFonts = ['small','medium','large'];
    if (!in_array($fonte, $allowedFonts)) $fonte = 'medium';

    respondJson(true, [
        'preferences' => [
            'tema' => $temaFrontend,
            'cor' => $cor,
            'fonte' => $fonte
        ]
    ], 200);

} catch (Exception $e) {
    error_log("Erro em carregar_preferencias.php: " . $e->getMessage());
    respondJson(false, ['error' => 'Erro ao carregar preferências. Tente novamente.'], 500);
} finally {
    if (isset($stmt)) db_stmt_close($stmt);
    if (isset($con)) db_close($con);
}
?>
