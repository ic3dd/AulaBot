<?php
// Migration: move images from uploads/vision referenced in mensagens.pergunta into mensagens_imagem and set mensagens.id_imagem
header('Content-Type: text/plain; charset=UTF-8');
require_once(__DIR__ . '/../ligarbd.php');

if (!isset($con) || (!$con instanceof mysqli && !$con instanceof PDO)) {
    echo "DB connection not available\n"; exit;
}

$baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'vision' . DIRECTORY_SEPARATOR;
if (!is_dir($baseDir)) {
    echo "No uploads/vision directory found.\n";
    exit;
}

// Find messages that contain an <img src=uploads/vision/...>
$res = db_query($con, "SELECT id_mensagem, pergunta FROM mensagens WHERE pergunta LIKE '%uploads/vision/%'");
if (!$res) { echo "Query failed: " . db_error($con) . "\n"; exit; }

$count = 0;
while ($row = db_fetch_assoc($res)) {
    $id = (int)$row['id_mensagem'];
    $pergunta = $row['pergunta'];
    // extract filename
    if (preg_match('#uploads/vision/([a-zA-Z0-9_\-\.]+)#', $pergunta, $m)) {
        $filename = $m[1];
        $localPath = $baseDir . $filename;
        if (file_exists($localPath) && is_readable($localPath)) {
            $imgData = file_get_contents($localPath);
            if ($imgData !== false) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $localPath);
                finfo_close($finfo);

                // ensure mensagens_imagem exists
                $createTbl = "CREATE TABLE IF NOT EXISTS mensagens_imagem (
                    id_imagem INT AUTO_INCREMENT PRIMARY KEY,
                    id_mensagem INT NOT NULL,
                    filename VARCHAR(255) DEFAULT NULL,
                    mime VARCHAR(100) DEFAULT NULL,
                    content LONGBLOB,
                    data_insercao DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                db_query($con, $createTbl);

                // insert image
                $stmtImg = db_prepare($con, "INSERT INTO mensagens_imagem (id_mensagem, filename, mime, content, data_insercao) VALUES (?, ?, ?, ?, NOW())");
                if ($stmtImg) {
                    $null = NULL;
                    db_stmt_bind_param($stmtImg, 'issb', $id, $filename, $mime, $null);
                    db_stmt_send_long_data($stmtImg, 4, $imgData);
                    db_stmt_execute($stmtImg);
                    $idImg = db_insert_id($con);
                    if ($idImg) {
                        // add id_imagem col if missing
                        if (!db_column_exists($con, 'mensagens', 'id_imagem')) {
                            @db_query($con, defined('DB_IS_POSTGRES') && DB_IS_POSTGRES
                                ? "ALTER TABLE mensagens ADD COLUMN IF NOT EXISTS id_imagem INT NULL"
                                : "ALTER TABLE mensagens ADD COLUMN id_imagem INT NULL AFTER id_chat");
                        }
                        // update mensagens
                        $upd = db_prepare($con, "UPDATE mensagens SET id_imagem = ? WHERE id_mensagem = ?");
                        if ($upd) {
                            db_stmt_bind_param($upd, 'ii', $idImg, $id);
                            db_stmt_execute($upd);
                        }
                        $count++;
                        echo "Migrated $filename for message $id -> image id $idImg\n";
                    }
                } else {
                    echo "Failed to prepare insert for $filename: " . db_error($con) . "\n";
                }
            }
        } else {
            echo "File not found: $localPath\n";
        }
    }
}

echo "Done. Migrated $count images.\n";
