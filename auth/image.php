<?php
header('Content-Type: application/octet-stream');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../ligarbd.php');

if (!isset($_GET['id_imagem']) || !is_numeric($_GET['id_imagem'])) {
    http_response_code(400);
    echo 'Invalid id_imagem';
    exit;
}

$id = (int)$_GET['id_imagem'];

try {
    $stmt = db_prepare($con, "SELECT filename, mime, content, data_insercao FROM mensagens_imagem WHERE id_imagem = ? LIMIT 1");
    db_stmt_bind_param($stmt, 'i', $id);
    db_stmt_execute($stmt);
    $result = db_stmt_get_result($stmt);
    $row = db_fetch_assoc($result);
    if ($row) {
        $filename = $row['filename'] ?? '';
        $mime = $row['mime'] ?? 'application/octet-stream';
        $content = $row['content'] ?? '';
        if (!$mime) $mime = 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: public, max-age=86400');
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
        echo $content;
        exit;
    } else {
        http_response_code(404);
        echo 'Imagem não encontrada';
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo 'Erro ao servir imagem';
    exit;
}
