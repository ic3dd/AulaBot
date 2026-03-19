<?php
// =================================================================================
// LOGIN API - CORRIGIDO PARA SESSÃO GLOBAL
// =================================================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- CORREÇÃO IMPORTANTE ---
// Força o cookie de sessão a ser válido na raiz do site ('/')
// Isto tem de ser feito ANTES do session_start()
session_set_cookie_params(0, '/');
session_start();

header('Content-Type: application/json; charset=utf-8');

function getIpAddress() {
    $ip = 'UNKNOWN';
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
        $ip = $_SERVER['HTTP_FORWARDED'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // Handle multiple IPs in X-Forwarded-For, take the first one
    if (strpos($ip, ',') !== false) {
        $ip = explode(',', $ip)[0];
    }
    return trim($ip);
}

function respondJson($success, $message = '', $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    $response = ['sucesso' => $success];
    if ($message) $response['erro'] = $message;
    $response = array_merge($response, $data);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Registar handler para erros não-capturados
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    respondJson(false, "Erro PHP: $errstr (em $errfile:$errline)", [], 500);
});

// Registar handler para exceções não-capturadas
set_exception_handler(function($exception) {
    respondJson(false, 'Exceção: ' . $exception->getMessage(), [], 500);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson(false, 'Método HTTP não permitido.', [], 405);
}

// Verifica o caminho para a base de dados
if (file_exists('../ligarbd.php')) {
    require('../ligarbd.php');
}
elseif (file_exists('../../ligarbd.php')) {
    require('../../ligarbd.php');
} else {
    respondJson(false, 'Erro interno: ficheiro de base de dados não encontrado.', [], 500);
}

if (!isset($con) || !$con) {
    respondJson(false, 'Erro ao conectar à base de dados. Tente novamente mais tarde.', [], 500);
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? ($_POST['palavra_passe'] ?? ''); // Aceita ambos os nomes

if (!$email || !$password) {
    respondJson(false, 'Por favor, preencha o email e a palavra-passe.', [], 400);
}

try {
    $sql = "SELECT id_utilizador, nome, palavra_passe, tipo, bloqueado, motivo_bloqueio FROM utilizador WHERE email = ?";
    $stmt = db_prepare($con, $sql);
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . db_error($con));
    }
    
    db_stmt_bind_param($stmt, "s", $email);
    db_stmt_execute($stmt);
    $result = db_stmt_get_result($stmt);

    if (db_num_rows($result) === 1) {
        $user = db_fetch_assoc($result);

        if ($user['bloqueado'] == 1) {
            $motivo = $user['motivo_bloqueio'] ?? 'Bloqueio administrativo';
            $mensagem = 'Conta bloqueada: ' . $motivo;
            respondJson(false, $mensagem, [], 403);
        } else {
            $password_verified = false;
            
            // Verificação de password (password_hash primeiro; compatível com MD5/texto antigos)
            if (password_verify($password, $user['palavra_passe'])) {
                $password_verified = true;
            } elseif ($password === $user['palavra_passe']) {
                $password_verified = true;
            } elseif (md5($password) === $user['palavra_passe']) {
                $password_verified = true;
            } elseif (substr(md5($password), 0, 12) === $user['palavra_passe']) {
                $password_verified = true;
            }
            
            if ($password_verified) {
                // GRAVAR SESSÃO
                $_SESSION['id_utilizador'] = $user['id_utilizador'];
                $_SESSION['nome'] = $user['nome'];
                $_SESSION['email'] = $email;
                $_SESSION['tipo'] = $user['tipo'];

                // Registar Login
                try {
                    $ip_address = getIpAddress();
                    $sqlReg = "INSERT INTO registo (reg, data, id_utilizador) VALUES ('login', NOW(), ?)";
                    $stmtReg = db_prepare($con, $sqlReg);
                    if ($stmtReg) {
                        db_stmt_bind_param($stmtReg, "i", $user['id_utilizador']);
                        db_stmt_execute($stmtReg);
                        db_stmt_close($stmtReg);
                    }
                } catch (Exception $e) { /* Ignora erro de log */ }

                // Disciplinas (Lógica mantida)
                $sqlCheckDisciplinas = "SELECT tema_escola FROM utilizador WHERE id_utilizador = ?";
                $stmtCheck = db_prepare($con, $sqlCheckDisciplinas);
                db_stmt_bind_param($stmtCheck, "i", $user['id_utilizador']);
                db_stmt_execute($stmtCheck);
                $resultCheck = db_stmt_get_result($stmtCheck);
                $rowCheck = db_fetch_assoc($resultCheck);
                
                if (empty($rowCheck['tema_escola'])) {
                    $todasAsDisciplinas = ['portugues', 'matematica', 'fisica', 'quimica', 'biologia', 'historia', 'geografia', 'ingles', 'francés', 'artes', 'educacao_fisica', 'cidadania'];
                    $disciplinasJson = json_encode($todasAsDisciplinas, JSON_UNESCAPED_UNICODE);
                    
                    $sqlUpdate = "UPDATE utilizador SET tema_escola = ? WHERE id_utilizador = ?";
                    $stmtUpdate = db_prepare($con, $sqlUpdate);
                    db_stmt_bind_param($stmtUpdate, "si", $disciplinasJson, $user['id_utilizador']);
                    db_stmt_execute($stmtUpdate);
                    db_stmt_close($stmtUpdate);
                }
                db_stmt_close($stmtCheck);

                // Forçar a escrita da sessão antes de enviar o JSON
                session_write_close();

                respondJson(true, 'Login com sucesso', [
                    'redirect' => '../index.php' // Diz ao JS para onde ir
                ], 200);
            } else {
                respondJson(false, 'A palavra-passe está incorreta.', [], 401);
            }
        }
    } else {
        respondJson(false, 'Email não encontrado.', [], 404);
    }
    
    db_stmt_close($stmt);
} catch (Exception $e) {
    respondJson(false, 'Erro no servidor: ' . $e->getMessage(), [], 500);
}

if (isset($con) && $con) db_close($con);
?>