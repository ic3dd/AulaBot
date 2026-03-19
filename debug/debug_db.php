<?php
header('Content-Type: application/json; charset=UTF-8');
require_once('ligarbd.php');

$debug = [];

// Verificar conexão
if (!$con) {
    $debug['conexao'] = 'FALHA: Sem conexão ao BD';
    echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$debug['conexao'] = 'OK';

// Listar tabelas
$res_tables = db_query($con, "SHOW TABLES");
$tables = [];
if ($res_tables) {
    while ($row = db_fetch_row($res_tables)) {
        $tables[] = $row[0];
    }
}
$debug['tabelas'] = $tables;

// Esquema da tabela chats
$res_chats = db_query($con, "SHOW COLUMNS FROM chats");
$chats_cols = [];
if ($res_chats) {
    while ($row = db_fetch_assoc($res_chats)) {
        $chats_cols[] = $row;
    }
}
$debug['chats_esquema'] = $chats_cols;

// Esquema da tabela mensagens
$res_msgs = db_query($con, "SHOW COLUMNS FROM mensagens");
$msgs_cols = [];
if ($res_msgs) {
    while ($row = db_fetch_assoc($res_msgs)) {
        $msgs_cols[] = $row;
    }
}
$debug['mensagens_esquema'] = $msgs_cols;

// Tentar INSERT de teste em chats
$test_titulo = "Teste " . date('Y-m-d H:i:s');
$test_titulo_esc = db_real_escape_string($con, $test_titulo);
$ins_test = db_query($con, "INSERT INTO chats (id_utilizador, titulo, data_criacao_chat, data_atualizacao) VALUES (0, '$test_titulo_esc', NOW(), NOW())");
if ($ins_test) {
    $test_chat_id = db_insert_id($con);
    $debug['teste_insert_chats'] = ['sucesso' => true, 'id_chat' => $test_chat_id];
    
    // Tentar INSERT em mensagens com esse chat
    $test_pergunta = 'Pergunta teste';
    $test_resposta = 'Resposta teste';
    $test_pergunta_esc = db_real_escape_string($con, $test_pergunta);
    $test_resposta_esc = db_real_escape_string($con, $test_resposta);
    
    $ins_msg = db_query($con, "INSERT INTO mensagens (id_chat, pergunta, resposta, data_conversa) VALUES ($test_chat_id, '$test_pergunta_esc', '$test_resposta_esc', NOW())");
    if ($ins_msg) {
        $debug['teste_insert_mensagens'] = ['sucesso' => true, 'id_mensagem' => db_insert_id($con)];
    } else {
        $debug['teste_insert_mensagens'] = ['sucesso' => false, 'erro' => db_error($con)];
    }
} else {
    $debug['teste_insert_chats'] = ['sucesso' => false, 'erro' => db_error($con)];
}

// Listar últimos registos de chats
$res_chats_list = db_query($con, "SELECT id_chat, id_utilizador, titulo, data_criacao_chat, data_atualizacao FROM chats ORDER BY id_chat DESC LIMIT 5");
$chats_lista = [];
if ($res_chats_list) {
    while ($row = db_fetch_assoc($res_chats_list)) {
        $chats_lista[] = $row;
    }
}
$debug['ultimos_chats'] = $chats_lista;

// Listar últimos registos de mensagens
$res_msgs_list = db_query($con, "SELECT id_mensagem, id_chat, pergunta, resposta, data_conversa FROM mensagens ORDER BY id_mensagem DESC LIMIT 5");
$msgs_lista = [];
if ($res_msgs_list) {
    while ($row = db_fetch_assoc($res_msgs_list)) {
        $msgs_lista[] = $row;
    }
}
$debug['ultimas_mensagens'] = $msgs_lista;

echo json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
