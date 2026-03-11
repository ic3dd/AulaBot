<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

header('Content-Type: application/json; charset=utf-8');
ob_clean();

function respondJson($success, $message = '', $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    $response = ['sucesso' => $success];
    if ($message) $response['erro'] = $message;
    $response = array_merge($response, $data);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['tipo']) || $_SESSION['tipo'] !== 'admin') {
    respondJson(false, 'Acesso negado. Permissões insuficientes.', [], 403);
}

require_once('../ligarbd.php');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $sql = "SELECT id_utilizador, email, nome, bloqueado, motivo_bloqueio, data_bloqueio FROM utilizador ORDER BY nome ASC";
        $resultado = mysqli_query($con, $sql);
        
        if (!$resultado) {
            throw new Exception('Erro ao buscar utilizadores.');
        }
        
        $utilizadores = [];
        while ($linha = mysqli_fetch_assoc($resultado)) {
            $utilizadores[] = $linha;
        }
        
        respondJson(true, '', ['utilizadores' => $utilizadores], 200);
    } catch (Exception $e) {
        respondJson(false, 'Erro ao carregar utilizadores. Tente novamente.', [], 500);
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id_utilizador = $_POST['id_utilizador'] ?? '';
    $motivo = $_POST['motivo'] ?? '';
    
    if (!$id_utilizador || !in_array($acao, ['bloquear', 'desbloquear'])) {
        respondJson(false, 'Parâmetros inválidos. Verifique os dados enviados.', [], 400);
    }
    
    try {
        if ($acao === 'bloquear') {
            $sql = "UPDATE utilizador SET bloqueado = 1, motivo_bloqueio = ?, data_bloqueio = NOW() WHERE id_utilizador = ?";
            $stmt = mysqli_prepare($con, $sql);
            mysqli_stmt_bind_param($stmt, "si", $motivo, $id_utilizador);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Erro ao bloquear utilizador.');
            }
            
            mysqli_stmt_close($stmt);
            respondJson(true, 'Utilizador bloqueado com sucesso!', [], 200);
        } 
        else if ($acao === 'desbloquear') {
            $sql = "UPDATE utilizador SET bloqueado = 0, motivo_bloqueio = NULL, data_bloqueio = NULL WHERE id_utilizador = ?";
            $stmt = mysqli_prepare($con, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id_utilizador);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Erro ao desbloquear utilizador.');
            }
            
            mysqli_stmt_close($stmt);
            respondJson(true, 'Utilizador desbloqueado com sucesso!', [], 200);
        }
    } catch (Exception $e) {
        respondJson(false, 'Erro ao processar ação. Tente novamente.', [], 500);
    }
}

mysqli_close($con);
exit;
?>
