<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../conta_errors.log');

header('Content-Type: application/json; charset=utf-8');
ob_start();

function respondJson($success, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['sucesso' => $success, 'erro' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function getIpAddress() {
    $ip = 'UNKNOWN';
    if (isset($_SERVER['HTTP_CLIENT_IP'])) $ip = $_SERVER['HTTP_CLIENT_IP'];
    elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    elseif (isset($_SERVER['HTTP_X_FORWARDED'])) $ip = $_SERVER['HTTP_X_FORWARDED'];
    elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) $ip = $_SERVER['HTTP_FORWARDED_FOR'];
    elseif (isset($_SERVER['HTTP_FORWARDED'])) $ip = $_SERVER['HTTP_FORWARDED'];
    elseif (isset($_SERVER['REMOTE_ADDR'])) $ip = $_SERVER['REMOTE_ADDR'];
    
    if (strpos($ip, ',') !== false) $ip = explode(',', $ip)[0];
    return trim($ip);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(false, 'Método HTTP não permitido.', 405);
}

try {
    if (!file_exists('../ligarbd.php')) {
        throw new Exception('Arquivo ligarbd.php não encontrado.');
    }
    
    require_once('../ligarbd.php');
    $conn = $con ?? null;
    
    if (!$conn) {
        throw new Exception("Erro na conexão: " . db_connect_error());
    }

    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ($_POST['palavra_passe'] ?? '');

    if (!$nome || !$email || !$password) {
        respondJson(false, 'Por favor, preencha todos os campos obrigatórios.', 400);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respondJson(false, 'O email fornecido não é válido.', 400);
    }
    
    // Disciplinas padrão
    $todasAsDisciplinas = ['portugues', 'matematica', 'fisica', 'quimica', 'biologia', 'historia', 'geografia', 'ingles', 'francés', 'artes', 'educacao_fisica', 'cidadania'];
    $temaMaterias = json_encode($todasAsDisciplinas, JSON_UNESCAPED_UNICODE);

    // 1. Verificar se email existe
    $sql = "SELECT COUNT(*) as total FROM utilizador WHERE email = ?";
    $stmt = db_prepare($conn, $sql);
    db_stmt_bind_param($stmt, "s", $email);
    db_stmt_execute($stmt);
    $result = db_stmt_get_result($stmt);
    $row = db_fetch_assoc($result);
    
    if ($row && $row['total'] > 0) {
        respondJson(false, 'Já existe uma conta registada com este email.', 409);
    }
    db_stmt_close($stmt);

    // Preparar dados para inserção
    $hash = substr(md5($password), 0, 12); // Hash compatível com o teu login.php
    $tipo = 'utilizador';
    $ip_address = getIpAddress(); // Usado apenas para log interno

    // 2. Inserir na BD (REMIOVIDO O CAMPO IP)
    // Nota: Verifica se a coluna 'tema_escola' existe na tua tabela. 
    $sql = "INSERT INTO utilizador (nome, email, palavra_passe, tipo, tema_escola, data_criacao) VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = db_prepare($conn, $sql);
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . db_error($conn));
    }
    
    // "sssss" = 5 strings (nome, email, hash, tipo, temaMaterias)
    db_stmt_bind_param($stmt, "sssss", $nome, $email, $hash, $tipo, $temaMaterias);

    if (!db_stmt_execute($stmt)) {
        throw new Exception('Erro ao executar inserção: ' . db_stmt_error($stmt));
    }
    
    error_log("Conta criada: $email (IP: $ip_address)");
    db_stmt_close($stmt);
    db_close($conn);
    
    respondJson(true, 'Conta criada com sucesso!', 201);

} catch (Exception $e) {
    error_log("Erro criarconta: " . $e->getMessage());
    respondJson(false, 'Erro interno: ' . $e->getMessage(), 500);
} catch (Error $e) {
    error_log("Erro fatal criarconta: " . $e->getMessage());
    respondJson(false, 'Erro interno do servidor.', 500);
}
?>