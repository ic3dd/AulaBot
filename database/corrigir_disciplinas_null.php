<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    require(__DIR__ . '/../ligarbd.php');

    if (!$con) {
        echo json_encode(['sucesso' => false, 'erro' => 'Erro ao conectar à base de dados']);
        exit;
    }

    $todasAsDisciplinas = ['portugues', 'matematica', 'fisica', 'quimica', 'biologia', 'historia', 'geografia', 'ingles', 'francés', 'artes', 'educacao_fisica', 'cidadania'];
    $disciplinasJson = json_encode($todasAsDisciplinas, JSON_UNESCAPED_UNICODE);

    $sql = "UPDATE utilizador SET tema_escola = ? WHERE tema_escola IS NULL OR tema_escola = ''";
    $stmt = db_prepare($con, $sql);

    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . db_error($con));
    }

    db_stmt_bind_param($stmt, "s", $disciplinasJson);

    if (!db_stmt_execute($stmt)) {
        throw new Exception('Erro ao executar atualização: ' . db_error($con));
    }

    $rowsAffected = db_stmt_affected_rows($stmt);
    db_stmt_close($stmt);

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Base de dados corrigida com sucesso!',
        'utilizadores_atualizados' => $rowsAffected
    ]);

    db_close($con);

} catch (Exception $e) {
    error_log("Erro ao corrigir disciplinas: " . $e->getMessage());
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>
