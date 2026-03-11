<?php
// Migration: move images from uploads/vision referenced in mensagens.pergunta into mensagens_imagem and set mensagens.id_imagem
header('Content-Type: text/plain; charset=UTF-8');
require_once('ligarbd.php');

if (!isset($con) || !$con instanceof mysqli) {
    echo "DB connection not available\n"; exit;
}

$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'vision' . DIRECTORY_SEPARATOR;
if (!is_dir($baseDir)) {
    echo "No uploads/vision directory found.\n";
    exit;
}

// Find messages that contain an <img src=uploads/vision/...>
$res = mysqli_query($con, "SELECT id_mensagem, pergunta FROM mensagens WHERE pergunta LIKE '%uploads/vision/%'");
if (!$res) { echo "Query failed: " . mysqli_error($con) . "\n"; exit; }

$count = 0;
while ($row = mysqli_fetch_assoc($res)) {
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
                mysqli_query($con, $createTbl);

                // insert image
                $stmtImg = mysqli_prepare($con, "INSERT INTO mensagens_imagem (id_mensagem, filename, mime, content, data_insercao) VALUES (?, ?, ?, ?, NOW())");
                if ($stmtImg) {
                    $null = NULL;
                    mysqli_stmt_bind_param($stmtImg, 'issb', $id, $filename, $mime, $null);
                    mysqli_stmt_send_long_data($stmtImg, 3, $imgData);
                    mysqli_stmt_execute($stmtImg);
                    $idImg = mysqli_insert_id($con);
                    if ($idImg) {
                        // add id_imagem col if missing
                        $colCheck = mysqli_query($con, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mensagens' AND COLUMN_NAME = 'id_imagem'");
                        if ($colCheck && mysqli_num_rows($colCheck) == 0) {
                            @mysqli_query($con, "ALTER TABLE mensagens ADD COLUMN id_imagem INT NULL AFTER id_chat");
                        }
                        // update mensagens
                        $upd = mysqli_prepare($con, "UPDATE mensagens SET id_imagem = ? WHERE id_mensagem = ?");
                        if ($upd) {
                            mysqli_stmt_bind_param($upd, 'ii', $idImg, $id);
                            mysqli_stmt_execute($upd);
                        }
                        $count++;
                        echo "Migrated $filename for message $id -> image id $idImg\n";
                    }
                } else {
                    echo "Failed to prepare insert for $filename: " . mysqli_error($con) . "\n";
                }
            }
        } else {
            echo "File not found: $localPath\n";
        }
    }
}

echo "Done. Migrated $count images.\n";
