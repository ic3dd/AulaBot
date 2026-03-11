<?php
// Iniciar buffering para evitar qualquer output fora do JSON
ob_start();

// Configurações de erro (logar, mas não mostrar no output)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Send PHP error log to a file inside admin folder for easier debugging
ini_set('error_log', __DIR__ . '/admin_php_errors.log');

// Iniciar sessão para aceder aos dados do utilizador
// Definir cookie path para garantir consistência
session_set_cookie_params(0, '/');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir header JSON
header('Content-Type: application/json');

// Log the raw request for debugging (safe: will not log passwords in full below)
$debugLogFile = __DIR__ . '/admin_debug.log';
$rawBody = @file_get_contents('php://input');
@file_put_contents($debugLogFile, '[' . date('Y-m-d H:i:s') . '] REQUEST ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . ' BODY: ' . substr($rawBody ?? '', 0, 2000) . ' POST_KEYS: ' . json_encode(array_keys($_POST)) . PHP_EOL, FILE_APPEND | LOCK_EX);

// Ensure fatal errors are logged and the response is valid JSON instead of HTML
register_shutdown_function(function () use ($debugLogFile) {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        @file_put_contents($debugLogFile, '[' . date('Y-m-d H:i:s') . '] FATAL ' . print_r($err, true) . PHP_EOL, FILE_APPEND | LOCK_EX);
        // Try to return a safe JSON error
        if (ob_get_level())
            ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erro interno no servidor']);
    }
});

// Incluir ficheiros necessários
require_once('../auth_check.php');
require_once('../ligarbd.php');

// Verificar se o utilizador é administrador (SEM REDIRECT AUTOMÁTICO)
// Se não for admin, retornamos JSON 403 em vez de HTML
if (!verificarSeAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores podem aceder a esta API.']);
    exit;
}

/**
 * Obter método da requisição
 */
$method = $_SERVER['REQUEST_METHOD'];

// Determinar ação baseada no método
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
} else {
    // Para POST, verificar tanto JSON como form-data
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
    } else {
        $action = $_POST['action'] ?? '';
    }
}

/**
 * Processar requisições GET
 */
if ($method === 'GET') {
    switch ($action) {
        case 'stats':
            obterEstatisticas();
            break;
        case 'get_bloqueio':
            obterBloqueio();
            break;
        case 'export_users':
            exportarUtilizadores();
            break;
        case 'get_help_conversations':
            obterConversasAjuda();
            break;
        default:
            responderErro('Ação não reconhecida');
    }
}

/**
 * Processar requisições POST
 */ elseif ($method === 'POST') {
    // Obter dados baseado no content type
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (strpos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if ($raw && $input === null) {
            // JSON inválido
            escreverDebug('Invalid JSON received: ' . substr($raw, 0, 2000));
            responderErro('JSON inválido na requisição');
        }
    } else {
        $input = $_POST;
    }

    $action = $input['action'] ?? '';

    try {
        switch ($action) {
            case 'add_user':
                adicionarUtilizador($input);
                break;
            case 'delete_user':
                eliminarUtilizador($input['user_id'] ?? null);
                break;
            case 'update_user':
                atualizarUtilizador($input);
                break;
            case 'marcar_feedback_lido':
                marcarFeedbackLido($input['feedback_id'] ?? null);
                break;
            case 'set_bloqueio':
                setBloqueio($input['value'] ?? null);
                break;
            case 'create_update':
                criarAtualizacao($input);
                break;
            case 'send_admin_reply':
                enviarRespostaAdmin($input);
                break;
            case 'close_conversation':
                fecharConversa($input);
                break;
            case 'bloquear_user':
                bloquearUtilizador($input['user_id'] ?? null, $input['motivo'] ?? '');
                break;
            case 'desbloquear_user':
                desbloquearUtilizador($input['user_id'] ?? null);
                break;
            default:
                responderErro('Ação não reconhecida');
        }
    } catch (Throwable $t) {
        // Log and return JSON error
        escreverDebug('Unhandled exception: ' . $t->getMessage() . "\n" . $t->getTraceAsString());
        responderErro('Erro interno no servidor');
    }
}

/**
 * Atualizar dados de um utilizador
 */
function atualizarUtilizador($dados)
{
    global $con;
    try {
        // Log input for debugging (mask sensitive if any)
        if (function_exists('escreverDebug') && function_exists('maskSensitive')) {
            escreverDebug('atualizarUtilizador called with: ' . print_r(maskSensitive($dados), true));
        }
        if (empty($dados['id_utilizador']) || empty($dados['nome']) || empty($dados['email']) || empty($dados['tipo'])) {
            escreverDebug('atualizarUtilizador error: missing required fields');
            responderErro('Todos os campos são obrigatórios');
        }
        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            escreverDebug('atualizarUtilizador error: invalid email ' . ($dados['email'] ?? ''));
            responderErro('Email inválido');
        }
        $id = intval($dados['id_utilizador']);
        $nome = mysqli_real_escape_string($con, $dados['nome']);
        $email = mysqli_real_escape_string($con, $dados['email']);
        $tipo = in_array($dados['tipo'], ['utilizador', 'admin']) ? $dados['tipo'] : 'utilizador';

        // Verificar se email já existe em outro utilizador
        $stmt = mysqli_prepare($con, "SELECT id_utilizador FROM utilizador WHERE email = ? AND id_utilizador != ?");
        if (!$stmt) {
            escreverDebug('atualizarUtilizador prepare SELECT error: ' . mysqli_error($con));
            responderErro('Erro ao preparar query: ' . mysqli_error($con));
        }
        mysqli_stmt_bind_param($stmt, "si", $email, $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) > 0) {
            escreverDebug('atualizarUtilizador: email already used by other user');
            responderErro('Já existe outro utilizador com esse email');
        }

        // Atualizar dados
        $stmt = mysqli_prepare($con, "UPDATE utilizador SET nome = ?, email = ?, tipo = ? WHERE id_utilizador = ?");
        if (!$stmt) {
            escreverDebug('atualizarUtilizador prepare UPDATE error: ' . mysqli_error($con));
            responderErro('Erro ao preparar query: ' . mysqli_error($con));
        }
        mysqli_stmt_bind_param($stmt, "sssi", $nome, $email, $tipo, $id);
        $exec = mysqli_stmt_execute($stmt);
        if ($exec) {
            $affected = mysqli_stmt_affected_rows($stmt);
            escreverDebug('atualizarUtilizador execute UPDATE success, affected rows: ' . $affected);
            if ($affected >= 0) {
                responderSucesso(null, 'Dados do utilizador atualizados com sucesso');
            } else {
                responderErro('Nenhuma alteração foi efetuada');
            }
        } else {
            $stmtErr = mysqli_stmt_error($stmt);
            escreverDebug('atualizarUtilizador execute UPDATE error: ' . $stmtErr);
            responderErro('Erro ao atualizar utilizador: ' . $stmtErr);
        }
    } catch (Exception $e) {
        responderErro('Erro ao atualizar utilizador: ' . $e->getMessage());
    }
}

/**
 * Responder com sucesso
 */
function responderSucesso($dados = null, $mensagem = '')
{
    // Garantir que não existe output extra
    if (ob_get_level()) {
        ob_clean();
    }
    $resposta = ['success' => true];

    if ($mensagem) {
        $resposta['message'] = $mensagem;
    }

    if ($dados !== null) {
        $resposta['data'] = $dados;
    }

    echo json_encode($resposta);
    exit;
}

/**
 * Responder com erro
 */
function responderErro($mensagem)
{
    // Garantir que não existe output extra
    if (ob_get_level()) {
        ob_clean();
    }
    echo json_encode([
        'success' => false,
        'message' => $mensagem
    ]);
    exit;
}

/**
 * Escrever debug simples para ficheiro (apenas para desenvolvimento)
 */
function escreverDebug($texto)
{
    $logFile = __DIR__ . '/admin_debug.log';
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $texto . PHP_EOL;
    // Tentar escrever, supress errors para não quebrar resposta JSON
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

function maskSensitive($dados)
{
    if (!is_array($dados))
        return $dados;
    $copy = $dados;
    if (isset($copy['password']))
        $copy['password'] = '***';
    if (isset($copy['palavra_passe']))
        $copy['palavra_passe'] = '***';
    return $copy;
}

/**
 * Obter estatísticas do sistema
 */
function obterEstatisticas()
{
    global $con;

    try {
        $stats = [];

        // Total de utilizadores
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM utilizador");
        if ($result) {
            $stats['total_utilizadores'] = mysqli_fetch_assoc($result)['total'];
        } else {
            $stats['total_utilizadores'] = 0;
        }

        // Total de administradores
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM utilizador WHERE tipo = 'admin'");
        if ($result) {
            $stats['total_admins'] = mysqli_fetch_assoc($result)['total'];
        } else {
            $stats['total_admins'] = 0;
        }

        // Utilizadores registados hoje
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM utilizador WHERE DATE(data_criacao) = CURDATE()");
        if ($result) {
            $stats['novos_hoje'] = mysqli_fetch_assoc($result)['total'];
        } else {
            $stats['novos_hoje'] = 0;
        }

        // Utilizadores registados esta semana
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM utilizador WHERE data_criacao >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        if ($result) {
            $stats['novos_semana'] = mysqli_fetch_assoc($result)['total'];
        } else {
            $stats['novos_semana'] = 0;
        }

        responderSucesso($stats, 'Estatísticas obtidas com sucesso');

    } catch (Exception $e) {
        responderErro('Erro ao obter estatísticas: ' . $e->getMessage());
    }
}

/**
 * Obter estado de bloqueio do site (0 = desbloqueado, 1 = bloqueado)
 */
function obterBloqueio()
{
    global $con;
    try {
        $result = mysqli_query($con, "SELECT id_bloqueio, bloqueio FROM bloqueio ORDER BY id_bloqueio DESC LIMIT 1");
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            responderSucesso(['id' => intval($row['id_bloqueio']), 'bloqueio' => intval($row['bloqueio'])], 'Estado de bloqueio obtido');
        } else {
            // Sem registos assume desbloqueado
            responderSucesso(['id' => null, 'bloqueio' => 0], 'Estado de bloqueio obtido (padrão)');
        }
    } catch (Exception $e) {
        responderErro('Erro ao obter estado de bloqueio: ' . $e->getMessage());
    }
}

/**
 * Definir estado de bloqueio do site. Recebe value (0/1).
 */
function setBloqueio($value)
{
    global $con;
    try {
        if (!isset($value))
            responderErro('Valor de bloqueio não especificado');
        $val = intval($value) ? 1 : 0;

        $result = mysqli_query($con, "SELECT id_bloqueio FROM bloqueio ORDER BY id_bloqueio DESC LIMIT 1");
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $id = intval($row['id_bloqueio']);
            $stmt = mysqli_prepare($con, "UPDATE bloqueio SET bloqueio = ? WHERE id_bloqueio = ?");
            if (!$stmt)
                responderErro('Erro ao preparar atualização de bloqueio: ' . mysqli_error($con));
            mysqli_stmt_bind_param($stmt, "ii", $val, $id);
            if (!mysqli_stmt_execute($stmt))
                responderErro('Erro ao atualizar bloqueio: ' . mysqli_stmt_error($stmt));
        } else {
            $stmt = mysqli_prepare($con, "INSERT INTO bloqueio (bloqueio) VALUES (?)");
            if (!$stmt)
                responderErro('Erro ao preparar inserção de bloqueio: ' . mysqli_error($con));
            mysqli_stmt_bind_param($stmt, "i", $val);
            if (!mysqli_stmt_execute($stmt))
                responderErro('Erro ao inserir bloqueio: ' . mysqli_stmt_error($stmt));
            $id = mysqli_insert_id($con);
        }

        responderSucesso(['id' => $id, 'bloqueio' => $val], $val ? 'Site bloqueado' : 'Site desbloqueado');
    } catch (Exception $e) {
        responderErro('Erro ao definir bloqueio: ' . $e->getMessage());
    }
}

function criarAtualizacao($dados)
{
    global $con;
    if (!$con) {
        responderErro('Ligação à base de dados indisponível');
    }

    $checkColumn = mysqli_query($con, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='updates' AND COLUMN_NAME='tema'");
    if ($checkColumn && mysqli_num_rows($checkColumn) === 0) {
        mysqli_query($con, "ALTER TABLE updates ADD COLUMN tema VARCHAR(50) DEFAULT 'atualizacoes'");
    }

    $checkVersaoType = mysqli_query($con, "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='updates' AND COLUMN_NAME='versao'");
    if ($checkVersaoType && mysqli_num_rows($checkVersaoType) > 0) {
        $versaoInfo = mysqli_fetch_assoc($checkVersaoType);
        $columnType = strtoupper($versaoInfo['COLUMN_TYPE'] ?? '');
        if (strpos($columnType, 'FLOAT') === 0 || strpos($columnType, 'INT') === 0) {
            mysqli_query($con, "ALTER TABLE updates MODIFY versao VARCHAR(100)");
        }
    }

    $nome = isset($dados['nome']) ? trim($dados['nome']) : '';
    $versao = isset($dados['versao']) ? trim($dados['versao']) : '';
    $descricao = isset($dados['descricao']) ? trim($dados['descricao']) : '';
    $tema = isset($dados['tema']) ? trim($dados['tema']) : 'atualizacoes';

    if ($nome === '' || $versao === '' || $descricao === '') {
        responderErro('Todos os campos são obrigatórios');
    }

    if (mb_strlen($descricao) > 1000) {
        responderErro('Descrição excede o limite de 1000 caracteres');
    }

    $temaValido = in_array($tema, ['atualizacoes', 'manutencao', 'novidades', 'seguranca', 'performance']) ? $tema : 'atualizacoes';

    $stmt = mysqli_prepare($con, "INSERT INTO updates (nome, versao, descricao, tema, data_update) VALUES (?, ?, ?, ?, NOW())");
    if (!$stmt) {
        responderErro('Erro ao preparar query: ' . mysqli_error($con));
    }

    mysqli_stmt_bind_param($stmt, "ssss", $nome, $versao, $descricao, $temaValido);

    if (!mysqli_stmt_execute($stmt)) {
        responderErro('Erro ao criar anúncio: ' . mysqli_stmt_error($stmt));
    }

    $id = mysqli_insert_id($con);
    responderSucesso(['id_update' => $id], 'Atualização publicada com sucesso');
}

/**
 * Adicionar novo utilizador
 */

function adicionarUtilizador($dados)
{
    global $con;

    try {
        // Limpar qualquer output anterior
        if (ob_get_level()) {
            ob_clean();
        }

        // ⚠️ Verifica se $con existe
        if (!$con) {
            escreverDebug('adicionarUtilizador error: database connection not available');
            responderErro('Erro na ligação à base de dados.');
        }

        // Verificar se a conexão à base de dados está ativa
        if (mysqli_ping($con) === false) {
            escreverDebug('adicionarUtilizador error: database connection lost');
            responderErro('Erro de conexão à base de dados');
        }

        // Log payload para debug (mascarar password)
        if (function_exists('escreverDebug') && function_exists('maskSensitive')) {
            escreverDebug('adicionarUtilizador chamado com: ' . print_r(maskSensitive($dados), true));
        }

        // Validar campos obrigatórios
        if (empty($dados['nome']) || empty($dados['email']) || empty($dados['password'])) {
            responderErro('Todos os campos são obrigatórios');
        }

        // Validar email
        if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            responderErro('Email inválido');
        }

        // Verificar se email já existe
        $email = mysqli_real_escape_string($con, $dados['email']);
        $stmt = mysqli_prepare($con, "SELECT id_utilizador FROM utilizador WHERE email = ?");
        if (!$stmt) {
            responderErro('Erro interno ao verificar email');
        }
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) > 0) {
            responderErro('Já existe uma conta com esse email');
        }

        // Hash da password
        $hash = password_hash($dados['password'], PASSWORD_DEFAULT);

        // Verificar se o hash foi gerado com sucesso
        if ($hash === false) {
            responderErro('Erro ao processar a password');
        }

        // Verificar tamanho do hash (deve ser até 255 caracteres)
        if (strlen($hash) > 255) {
            escreverDebug('adicionarUtilizador warning: password hash length: ' . strlen($hash));
            responderErro('Erro interno: hash da password demasiado longo');
        }

        // Tipo do utilizador
        $tipo = in_array($dados['tipo'] ?? '', ['utilizador', 'admin']) ? $dados['tipo'] : 'utilizador';

        // Nome escapado
        $nome = mysqli_real_escape_string($con, $dados['nome']);

        // Inserir utilizador
        $stmt = mysqli_prepare($con, "INSERT INTO utilizador (nome, email, palavra_passe, tipo, data_criacao) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) {
            $error = mysqli_error($con);
            escreverDebug('adicionarUtilizador error: failed to prepare INSERT statement: ' . $error);
            responderErro('Erro interno ao criar utilizador: ' . $error);
        }

        mysqli_stmt_bind_param($stmt, "ssss", $nome, $email, $hash, $tipo);

        if (mysqli_stmt_execute($stmt)) {
            $insertId = mysqli_insert_id($con);
            escreverDebug('adicionarUtilizador success: user created with ID ' . $insertId);
            responderSucesso(['user_id' => $insertId], 'Utilizador criado com sucesso');
        } else {
            $stmtError = mysqli_stmt_error($stmt);
            escreverDebug('adicionarUtilizador error: failed to execute INSERT: ' . $stmtError);

            // Verificar se é erro de tamanho de dados
            if (strpos($stmtError, 'Data too long') !== false) {
                responderErro('Erro: Dados excedem o tamanho permitido. Contacte o administrador.');
            } else {
                responderErro('Erro ao criar utilizador: ' . $stmtError);
            }
        }

    } catch (Exception $e) {
        responderErro('Erro ao adicionar utilizador: ' . $e->getMessage());
    }
}


/**
 * Marcar feedback como lido
 */
function marcarFeedbackLido($feedbackId)
{
    global $con;

    try {
        if (!$feedbackId || !is_numeric($feedbackId)) {
            escreverDebug('marcarFeedbackLido error: invalid feedbackId');
            responderErro('ID do feedback inválido');
        }

        // Desativar auto-commit para controle manual de transação
        mysqli_autocommit($con, false);

        // 1. Primeiro verificar e atualizar o feedback atual
        $stmt = mysqli_prepare($con, "UPDATE feedback SET lido = 'sim' WHERE id_feedback = ? AND lido = 'nao'");
        if (!$stmt) {
            throw new Exception('Falha ao preparar query de atualização: ' . mysqli_error($con));
        }

        mysqli_stmt_bind_param($stmt, "i", $feedbackId);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Erro ao marcar feedback como lido: ' . mysqli_stmt_error($stmt));
        }

        // Verificar se algum registro foi atualizado
        if (mysqli_stmt_affected_rows($stmt) === 0) {
            throw new Exception('Feedback não encontrado ou já está marcado como lido');
        }

        // Commit imediato da atualização
        if (!mysqli_commit($con)) {
            throw new Exception('Erro ao confirmar atualização do feedback');
        }

        // Resetar conexão para nova transação
        mysqli_autocommit($con, true);

        // 2. Agora buscar o próximo feedback não lido
        $stmt = mysqli_prepare($con, "
            SELECT f.id_feedback, f.nome, f.email, f.rating, f.gostou, f.melhoria, f.autorizacao, f.data_feedback, f.lido 
            FROM feedback f
            WHERE f.lido = 'nao'
            ORDER BY f.data_feedback DESC, f.id_feedback DESC
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception('Falha ao preparar query de busca: ' . mysqli_error($con));
        }

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Erro ao buscar próximo feedback: ' . mysqli_stmt_error($stmt));
        }

        $result = mysqli_stmt_get_result($stmt);
        $proximoFeedback = mysqli_fetch_assoc($result);

        // Log para debug
        escreverDebug('marcarFeedbackLido: Feedback ' . $feedbackId . ' marcado como lido com sucesso.');
        if ($proximoFeedback) {
            escreverDebug('marcarFeedbackLido: Próximo feedback ID: ' . $proximoFeedback['id_feedback']);
        } else {
            escreverDebug('marcarFeedbackLido: Não há mais feedbacks não lidos.');
        }

        // Responder com sucesso
        responderSucesso(
            ['proximo_feedback' => $proximoFeedback],
            'Feedback marcado como lido com sucesso'
        );

    } catch (Exception $e) {
        // Se houver erro, fazer rollback
        mysqli_rollback($con);
        mysqli_autocommit($con, true);

        escreverDebug('marcarFeedbackLido ERROR: ' . $e->getMessage());
        responderErro('Erro ao marcar feedback como lido: ' . $e->getMessage());
    }
}


/**
 * Eliminar um utilizador
 */
function eliminarUtilizador($userId)
{
    global $con;

    // Limpar qualquer output anterior
    if (ob_get_level()) {
        ob_clean();
    }

    try {
        escreverDebug('eliminarUtilizador called with userId: ' . var_export($userId, true));

        if (!$userId || !is_numeric($userId)) {
            escreverDebug('eliminarUtilizador error: invalid userId');
            responderErro('ID do utilizador inválido');
        }

        // Verificar se a conexão à base de dados está ativa
        if (!$con || mysqli_ping($con) === false) {
            escreverDebug('eliminarUtilizador error: database connection lost');
            responderErro('Erro de conexão à base de dados');
        }

        // Iniciar transação
        mysqli_begin_transaction($con);

        // Verificar se o utilizador existe (usar id_utilizador corretamente)
        $stmt = mysqli_prepare($con, "SELECT id_utilizador, email FROM utilizador WHERE id_utilizador = ?");
        if (!$stmt) {
            mysqli_rollback($con);
            responderErro('Falha ao preparar query: ' . mysqli_error($con));
        }

        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (!$result) {
            mysqli_rollback($con);
            escreverDebug('eliminarUtilizador error: failed to get SELECT result: ' . mysqli_error($con));
            responderErro('Erro interno ao procurar utilizador');
        }

        if (mysqli_num_rows($result) === 0) {
            mysqli_rollback($con);
            escreverDebug('eliminarUtilizador: utilizador não encontrado para id ' . $userId);
            responderErro('Utilizador não encontrado');
        }

        $user = mysqli_fetch_assoc($result);
        $userEmail = $user['email'];

        // Normalizar chave de sessão: alguns lugares usam 'id_utilizador' outros 'user_id'
        $sessionUserId = null;
        if (isset($_SESSION['id_utilizador'])) {
            $sessionUserId = $_SESSION['id_utilizador'];
        } elseif (isset($_SESSION['user_id'])) {
            $sessionUserId = $_SESSION['user_id'];
        }

        // Verificar se é o próprio administrador (não pode eliminar-se a si mesmo)
        escreverDebug('eliminarUtilizador: fetched user id_utilizador=' . $user['id_utilizador'] . '; sessionUserId=' . var_export($sessionUserId, true));
        if ($sessionUserId !== null && intval($user['id_utilizador']) === intval($sessionUserId)) {
            mysqli_rollback($con);
            escreverDebug('eliminarUtilizador aborted: attempt to delete self');
            responderErro('Não pode eliminar a sua própria conta');
        }

        // Eliminar mensagens de chat do utilizador
        $sql = "SELECT id FROM chat_ajuda WHERE id_utilizador = ?";
        $stmtChat = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmtChat, "i", $userId);
        mysqli_stmt_execute($stmtChat);
        $chatResult = mysqli_stmt_get_result($stmtChat);
        mysqli_stmt_close($stmtChat);

        while ($chatRow = mysqli_fetch_assoc($chatResult)) {
            $chatId = $chatRow['id'];
            $sqlDelMsg = "DELETE FROM mensagens_chat_ajuda WHERE conversation_id = ?";
            $stmtDelMsg = mysqli_prepare($con, $sqlDelMsg);
            mysqli_stmt_bind_param($stmtDelMsg, "i", $chatId);
            if (!mysqli_stmt_execute($stmtDelMsg)) {
                mysqli_rollback($con);
                throw new Exception('Erro ao eliminar mensagens de chat: ' . mysqli_stmt_error($stmtDelMsg));
            }
            mysqli_stmt_close($stmtDelMsg);
        }
        escreverDebug('eliminarUtilizador: Mensagens de chat eliminadas para id ' . $userId);

        // Eliminar conversas de chat do utilizador
        $sql = "DELETE FROM chat_ajuda WHERE id_utilizador = ?";
        $stmtChatDel = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmtChatDel, "i", $userId);
        if (!mysqli_stmt_execute($stmtChatDel)) {
            mysqli_rollback($con);
            throw new Exception('Erro ao eliminar chat_ajuda: ' . mysqli_stmt_error($stmtChatDel));
        }
        mysqli_stmt_close($stmtChatDel);
        escreverDebug('eliminarUtilizador: Conversas de chat eliminadas para id ' . $userId);

        // Eliminar anúncios vistos pelo utilizador
        $sql = "DELETE FROM anuncios_vistos WHERE id_utilizador = ?";
        $stmtAnunc = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmtAnunc, "i", $userId);
        if (!mysqli_stmt_execute($stmtAnunc)) {
            mysqli_rollback($con);
            throw new Exception('Erro ao eliminar anuncios_vistos: ' . mysqli_stmt_error($stmtAnunc));
        }
        mysqli_stmt_close($stmtAnunc);
        escreverDebug('eliminarUtilizador: Anúncios vistos eliminados para id ' . $userId);

        // Eliminar feedback do utilizador
        $sql = "DELETE FROM feedback WHERE email = ?";
        $stmtFeed = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmtFeed, "s", $userEmail);
        if (!mysqli_stmt_execute($stmtFeed)) {
            mysqli_rollback($con);
            throw new Exception('Erro ao eliminar feedback: ' . mysqli_stmt_error($stmtFeed));
        }
        mysqli_stmt_close($stmtFeed);
        escreverDebug('eliminarUtilizador: Feedback eliminado para email ' . $userEmail);

        // Eliminar utilizador com prepared statement
        $delStmt = mysqli_prepare($con, "DELETE FROM utilizador WHERE id_utilizador = ?");
        if (!$delStmt) {
            mysqli_rollback($con);
            responderErro('Falha ao preparar query de eliminação: ' . mysqli_error($con));
        }

        mysqli_stmt_bind_param($delStmt, "i", $userId);

        $execResult = mysqli_stmt_execute($delStmt);
        if ($execResult) {
            $affectedRows = mysqli_stmt_affected_rows($delStmt);
            escreverDebug('eliminarUtilizador: DELETE executed successfully for id ' . $userId . ', affected rows: ' . $affectedRows);

            if ($affectedRows > 0) {
                // Confirmar transação
                mysqli_commit($con);
                responderSucesso(null, 'Utilizador e todos os seus dados eliminados com sucesso');
            } else {
                mysqli_rollback($con);
                escreverDebug('eliminarUtilizador: No rows affected, user may not exist');
                responderErro('Utilizador não encontrado ou já foi eliminado');
            }
        } else {
            mysqli_rollback($con);
            $stmtErr = mysqli_stmt_error($delStmt);
            escreverDebug('eliminarUtilizador execute DELETE error: ' . $stmtErr);
            responderErro('Erro ao eliminar utilizador: ' . $stmtErr);
        }

    } catch (Exception $e) {
        mysqli_rollback($con);
        escreverDebug('eliminarUtilizador Exception: ' . $e->getMessage());
        responderErro('Erro ao eliminar utilizador: ' . $e->getMessage());
    }
}


function bloquearUtilizador($userId, $motivo = '')
{
    global $con;

    if (ob_get_level()) {
        ob_clean();
    }

    try {
        escreverDebug('bloquearUtilizador called with userId: ' . var_export($userId, true));

        if (!$userId || !is_numeric($userId)) {
            escreverDebug('bloquearUtilizador error: invalid userId');
            responderErro('ID do utilizador inválido');
        }

        if (!$con || mysqli_ping($con) === false) {
            escreverDebug('bloquearUtilizador error: database connection lost');
            responderErro('Erro de conexão à base de dados');
        }

        // Verificar se o utilizador existe
        $stmt = mysqli_prepare($con, "SELECT id_utilizador, tipo FROM utilizador WHERE id_utilizador = ?");
        if (!$stmt) {
            responderErro('Falha ao preparar query: ' . mysqli_error($con));
        }

        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) === 0) {
            escreverDebug('bloquearUtilizador: utilizador não encontrado para id ' . $userId);
            responderErro('Utilizador não encontrado');
        }

        $user = mysqli_fetch_assoc($result);

        // Normalizar chave de sessão
        $sessionUserId = null;
        if (isset($_SESSION['id_utilizador'])) {
            $sessionUserId = $_SESSION['id_utilizador'];
        } elseif (isset($_SESSION['user_id'])) {
            $sessionUserId = $_SESSION['user_id'];
        }

        // Verificar se é o próprio admin
        if ($sessionUserId !== null && intval($user['id_utilizador']) === intval($sessionUserId)) {
            escreverDebug('bloquearUtilizador aborted: attempt to block self');
            responderErro('Não pode bloquear a sua própria conta');
        }

        // Verificar se é um admin
        if ($user['tipo'] === 'admin') {
            escreverDebug('bloquearUtilizador aborted: attempt to block admin');
            responderErro('Não pode bloquear uma conta de administrador');
        }

        // Bloquear utilizador
        $motivo = trim($motivo) ? substr(trim($motivo), 0, 255) : 'Bloqueado pelo administrador';
        $stmt = mysqli_prepare($con, "UPDATE utilizador SET bloqueado = 1, motivo_bloqueio = ? WHERE id_utilizador = ?");
        if (!$stmt) {
            responderErro('Falha ao preparar query: ' . mysqli_error($con));
        }

        mysqli_stmt_bind_param($stmt, "si", $motivo, $userId);

        if (!mysqli_stmt_execute($stmt)) {
            $stmtErr = mysqli_stmt_error($stmt);
            escreverDebug('bloquearUtilizador execute error: ' . $stmtErr);
            responderErro('Erro ao bloquear utilizador: ' . $stmtErr);
        }

        $affectedRows = mysqli_stmt_affected_rows($stmt);
        escreverDebug('bloquearUtilizador: User blocked successfully, affected rows: ' . $affectedRows);
        responderSucesso(null, 'Utilizador bloqueado com sucesso');

    } catch (Exception $e) {
        escreverDebug('bloquearUtilizador Exception: ' . $e->getMessage());
        responderErro('Erro ao bloquear utilizador: ' . $e->getMessage());
    }
}


function desbloquearUtilizador($userId)
{
    global $con;

    if (ob_get_level()) {
        ob_clean();
    }

    try {
        escreverDebug('desbloquearUtilizador called with userId: ' . var_export($userId, true));

        if (!$userId || !is_numeric($userId)) {
            escreverDebug('desbloquearUtilizador error: invalid userId');
            responderErro('ID do utilizador inválido');
        }

        if (!$con || mysqli_ping($con) === false) {
            escreverDebug('desbloquearUtilizador error: database connection lost');
            responderErro('Erro de conexão à base de dados');
        }

        // Verificar se o utilizador existe
        $stmt = mysqli_prepare($con, "SELECT id_utilizador FROM utilizador WHERE id_utilizador = ?");
        if (!$stmt) {
            responderErro('Falha ao preparar query: ' . mysqli_error($con));
        }

        mysqli_stmt_bind_param($stmt, "i", $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) === 0) {
            escreverDebug('desbloquearUtilizador: utilizador não encontrado para id ' . $userId);
            responderErro('Utilizador não encontrado');
        }

        // Desbloquear utilizador
        $stmt = mysqli_prepare($con, "UPDATE utilizador SET bloqueado = 0, motivo_bloqueio = NULL WHERE id_utilizador = ?");
        if (!$stmt) {
            responderErro('Falha ao preparar query: ' . mysqli_error($con));
        }

        mysqli_stmt_bind_param($stmt, "i", $userId);

        if (!mysqli_stmt_execute($stmt)) {
            $stmtErr = mysqli_stmt_error($stmt);
            escreverDebug('desbloquearUtilizador execute error: ' . $stmtErr);
            responderErro('Erro ao desbloquear utilizador: ' . $stmtErr);
        }

        $affectedRows = mysqli_stmt_affected_rows($stmt);
        escreverDebug('desbloquearUtilizador: User unblocked successfully, affected rows: ' . $affectedRows);
        responderSucesso(null, 'Utilizador desbloqueado com sucesso');

    } catch (Exception $e) {
        escreverDebug('desbloquearUtilizador Exception: ' . $e->getMessage());
        responderErro('Erro ao desbloquear utilizador: ' . $e->getMessage());
    }
}


function fecharConversa($dados)
{
    global $con;

    $conversationId = $dados['conversation_id'] ?? null;

    if (!$conversationId || !is_numeric($conversationId)) {
        responderErro('ID de conversa inválido');
    }

    try {
        $conversationId = intval($conversationId);

        $stmt = mysqli_prepare($con, "UPDATE chat_ajuda SET estado = 'fechado' WHERE id = ?");
        if (!$stmt) {
            responderErro('Erro ao fechar conversa: ' . mysqli_error($con));
        }

        mysqli_stmt_bind_param($stmt, "i", $conversationId);

        if (!mysqli_stmt_execute($stmt)) {
            responderErro('Erro ao fechar conversa: ' . mysqli_stmt_error($stmt));
        }

        $affectedRows = mysqli_stmt_affected_rows($stmt);

        if ($affectedRows > 0) {
            escreverDebug('Conversa ' . $conversationId . ' fechada pelo admin');
            responderSucesso(null, 'Conversa fechada com sucesso');
        } else {
            responderErro('Conversa não encontrada');
        }

    } catch (Exception $e) {
        responderErro('Erro ao fechar conversa: ' . $e->getMessage());
    }
}

function obterConversasAjuda()
{
    global $con;

    try {
        $stmt = mysqli_prepare($con, "
            SELECT 
                ca.id,
                ca.criado_em,
                u.nome as nome_utilizador, -- Adicionado o nome do utilizador
                COUNT(mca.id) as total_mensagens,
                MAX(mca.enviado_em) as ultima_mensagem,
                SUM(CASE WHEN mca.sender = 'utilizador' THEN 1 ELSE 0 END) as mensagens_utilizador,
                SUM(CASE WHEN mca.sender = 'admin' THEN 1 ELSE 0 END) as mensagens_admin
            FROM chat_ajuda ca
            LEFT JOIN utilizador u ON ca.id_utilizador = u.id_utilizador -- JOIN com a tabela de utilizadores
            LEFT JOIN mensagens_chat_ajuda mca ON ca.id = mca.conversation_id
            WHERE ca.estado IS NULL OR ca.estado != 'fechado'
            GROUP BY ca.id, u.nome -- Agrupar também pelo nome do utilizador
            ORDER BY ca.criado_em DESC
        ");

        if (!$stmt) {
            responderErro('Erro ao obter conversas: ' . mysqli_error($con));
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $conversas = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $conversas[] = $row;
        }

        responderSucesso(['conversas' => $conversas], 'Conversas obtidas com sucesso');

    } catch (Exception $e) {
        responderErro('Erro ao obter conversas: ' . $e->getMessage());
    }
}

function enviarRespostaAdmin($dados)
{
    global $con;

    $conversationId = $dados['conversation_id'] ?? null;
    $conteudo = trim($dados['conteudo'] ?? '');

    if (!$conversationId || !is_numeric($conversationId)) {
        responderErro('ID de conversa inválido');
    }

    if (empty($conteudo)) {
        responderErro('Resposta não pode estar vazia');
    }

    if (strlen($conteudo) > 1000) {
        responderErro('Resposta excede o limite de 1000 caracteres');
    }

    try {
        mysqli_begin_transaction($con);

        $conversationId = intval($conversationId);

        $stmtCheck = mysqli_prepare($con, "SELECT estado, id_utilizador FROM chat_ajuda WHERE id = ? FOR UPDATE");
        if (!$stmtCheck) {
            throw new Exception('Erro ao preparar verificação de conversa: ' . mysqli_error($con));
        }

        mysqli_stmt_bind_param($stmtCheck, "i", $conversationId);
        mysqli_stmt_execute($stmtCheck);
        $resultCheck = mysqli_stmt_get_result($stmtCheck);

        if (mysqli_num_rows($resultCheck) === 0) {
            throw new Exception('Conversa não encontrada');
        }

        $chatInfo = mysqli_fetch_assoc($resultCheck);
        mysqli_stmt_close($stmtCheck);

        if ($chatInfo['estado'] === 'fechado') {
            throw new Exception('Esta conversa está fechada e não pode receber novas mensagens');
        }

        $conteudo = mysqli_real_escape_string($con, $conteudo);

        $stmt = mysqli_prepare($con, "INSERT INTO mensagens_chat_ajuda (conversation_id, sender, conteudo, enviado_em) VALUES (?, 'admin', ?, NOW())");
        if (!$stmt) {
            throw new Exception('Erro ao preparar para enviar resposta: ' . mysqli_error($con));
        }

        mysqli_stmt_bind_param($stmt, "is", $conversationId, $conteudo);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Erro ao executar envio de resposta: ' . mysqli_stmt_error($stmt));
        }

        $messageId = mysqli_insert_id($con);

        if (isset($chatInfo['id_utilizador']) && (int) $chatInfo['id_utilizador'] > 0) {
            $userId = (int) $chatInfo['id_utilizador'];
            escreverDebug("Tentando atualizar 'mensagem' para o id_utilizador: $userId.");

            // Verificar se o utilizador realmente existe
            $stmtUserCheck = mysqli_prepare($con, "SELECT id_utilizador FROM utilizador WHERE id_utilizador = ?");
            mysqli_stmt_bind_param($stmtUserCheck, "i", $userId);
            mysqli_stmt_execute($stmtUserCheck);
            $userResult = mysqli_stmt_get_result($stmtUserCheck);

            if (mysqli_num_rows($userResult) > 0) {
                // O utilizador existe, prosseguir com a atualização
                $stmtUpdate = mysqli_prepare($con, "UPDATE utilizador SET mensagem = 1 WHERE id_utilizador = ?");
                if (!$stmtUpdate) {
                    throw new Exception('Erro ao preparar atualização do utilizador: ' . mysqli_error($con));
                }
                mysqli_stmt_bind_param($stmtUpdate, "i", $userId);

                if (!mysqli_stmt_execute($stmtUpdate)) {
                    throw new Exception('Erro ao executar atualização do utilizador: ' . mysqli_stmt_error($stmtUpdate));
                }
                $affected_rows = mysqli_stmt_affected_rows($stmtUpdate);
                escreverDebug("UPDATE de utilizador executado para ID $userId. Linhas afetadas: $affected_rows");
                if ($affected_rows == 0) {
                    escreverDebug("Aviso: Nenhuma linha foi atualizada para o utilizador ID $userId. O valor 'mensagem' talvez já fosse 1.");
                }
            } else {
                // O utilizador não foi encontrado na tabela 'utilizador'
                escreverDebug("Aviso: id_utilizador $userId encontrado na conversa, mas não na tabela 'utilizador'. 'mensagem' não foi atualizado.");
            }
        } else {
            $uid = $chatInfo['id_utilizador'] ?? 'NULL';
            escreverDebug("Nenhum id_utilizador válido associado à conversa $conversationId (valor: $uid). 'mensagem' não foi atualizado.");
        }

        mysqli_commit($con);

        escreverDebug('Resposta do admin enviada com sucesso: ID ' . $messageId . ' na conversa ' . $conversationId);

        responderSucesso([
            'message_id' => $messageId,
            'conversation_id' => $conversationId
        ], 'Resposta enviada com sucesso');

    } catch (Exception $e) {
        mysqli_rollback($con);
        escreverDebug("Erro na transação ao enviar resposta de admin: " . $e->getMessage());
        responderErro('Erro ao enviar resposta: ' . $e->getMessage());
    }
}

/**
 * Exportar utilizadores para CSV
 */
function exportarUtilizadores()
{
    global $con;

    // Limpar output buffer para download de ficheiro
    if (ob_get_level())
        ob_clean();

    // Header para download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=utilizadores.csv');

    // Criar ficheiro de saida
    $output = fopen('php://output', 'w');

    // BOM para Excel reconhecer UTF-8
    fputs($output, "\xEF\xBB\xBF");

    // Cabeçalhos CSV
    fputcsv($output, ['ID', 'Nome', 'Email', 'Tipo', 'Data Registo', 'Bloqueado']);

    // Dados
    $query = "SELECT id_utilizador, nome, email, tipo, data_criacao, bloqueado FROM utilizador ORDER BY data_criacao DESC";
    $result = mysqli_query($con, $query);

    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['id_utilizador'],
            $row['nome'],
            $row['email'],
            $row['tipo'],
            $row['data_criacao'],
            $row['bloqueado'] ? 'Sim' : 'Não'
        ]);
    }

    fclose($output);
    exit;
}

// Fechar ligação à base de dados
if (isset($con) && $con) {
    mysqli_close($con);
}

?>