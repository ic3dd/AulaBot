<?php
// Helper to manage guest (non-authenticated) request limits
if (session_status() === PHP_SESSION_NONE) session_start();

define('GUEST_MAX_FREE', 3);
define('GUEST_RATE_LIMIT_SECONDS', 1); // minimal seconds between requests

function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function generate_uuid_v4() {
    if (function_exists('random_bytes')) {
        $data = random_bytes(16);
    } else {
        $data = openssl_random_pseudo_bytes(16);
    }
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function ensure_guest_record($con, $ip, $guestId) {
    // Ensure table exists first
    $createTable = "CREATE TABLE IF NOT EXISTS uso_convidado (
        id INT AUTO_INCREMENT PRIMARY KEY,
        endereco_ip VARCHAR(45),
        id_anonimo VARCHAR(36),
        total_pedidos INT DEFAULT 0,
        data_primeiro_pedido DATETIME,
        data_ultimo_pedido DATETIME,
        data_expiracao DATETIME,
        bloqueado TINYINT DEFAULT 0,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    @db_query($con, $createTable);
    
    $ipSafe = db_real_escape_string($con, $ip);
    $idSafe = db_real_escape_string($con, $guestId);
    $res = db_query($con, "SELECT * FROM uso_convidado WHERE endereco_ip = '$ipSafe' AND id_anonimo = '$idSafe' LIMIT 1");
    if ($res && $row = db_fetch_assoc($res)) {
        return $row;
    }
    // create new
    $now = date('Y-m-d H:i:s');
    $exp = date('Y-m-d H:i:s', time() + 7 * 24 * 3600);
    $stmt = db_prepare($con, "INSERT INTO uso_convidado (endereco_ip, id_anonimo, total_pedidos, data_primeiro_pedido, data_ultimo_pedido, data_expiracao, bloqueado, data_criacao, data_atualizacao) VALUES (?, ?, 0, ?, ?, ?, 0, ?, ?)");
    if ($stmt) {
        db_stmt_bind_param($stmt, 'sssssss', $ip, $guestId, $now, $now, $exp, $now, $now);
        db_stmt_execute($stmt);
    } else {
        // Try fallback insert
        db_query($con, "INSERT INTO uso_convidado (endereco_ip, id_anonimo, total_pedidos, data_primeiro_pedido, data_ultimo_pedido, data_expiracao, bloqueado, data_criacao, data_atualizacao) VALUES ('$ipSafe','$idSafe',0,'$now','$now','$exp',0,'$now','$now')");
    }
    $res2 = db_query($con, "SELECT * FROM uso_convidado WHERE endereco_ip = '$ipSafe' AND id_anonimo = '$idSafe' LIMIT 1");
    return $res2 ? db_fetch_assoc($res2) : null;
}

function get_or_create_guest($con) {
    $ip = get_client_ip();
    if (empty($ip)) $ip = '0.0.0.0';
    
    $guestId = $_COOKIE['guest_uuid'] ?? null;
    if (empty($guestId)) {
        $guestId = generate_uuid_v4();
        setcookie('guest_uuid', $guestId, time() + 7 * 24 * 3600, '/', '', false, true);
        $_COOKIE['guest_uuid'] = $guestId;
    }
    
    if (empty($guestId)) {
        throw new Exception('Erro ao gerar/obter guest_uuid');
    }
    
    $record = ensure_guest_record($con, $ip, $guestId);
    return ['ip' => $ip, 'guest_id' => $guestId, 'record' => $record];
}

/**
 * Check and increment guest usage for a textual request.
 * Returns array: ['ok' => bool, 'remaining' => int, 'total' => int, 'blocked' => bool, 'code' => int, 'message' => string]
 */
function guest_check_and_increment($con) {
    // Ensure table exists
    $createTable = "CREATE TABLE IF NOT EXISTS uso_convidado (
        id INT AUTO_INCREMENT PRIMARY KEY,
        endereco_ip VARCHAR(45),
        id_anonimo VARCHAR(36),
        total_pedidos INT DEFAULT 0,
        data_primeiro_pedido DATETIME,
        data_ultimo_pedido DATETIME,
        data_expiracao DATETIME,
        bloqueado TINYINT DEFAULT 0,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    @db_query($con, $createTable);
    
    $g = get_or_create_guest($con);
    $ip = $g['ip'];
    $guestId = $g['guest_id'];
    $rec = $g['record'];

    if (!$rec) return ['ok' => false, 'code' => 500, 'message' => 'Erro interno de controlo de convidados.'];

    // If expired (data_expiracao < now), reset
    $now = date('Y-m-d H:i:s');
    if (!empty($rec['data_expiracao']) && strtotime($rec['data_expiracao']) < time()) {
        db_query($con, "UPDATE uso_convidado SET total_pedidos = 0, data_primeiro_pedido = '$now', data_ultimo_pedido = '$now', data_expiracao = '" . date('Y-m-d H:i:s', time() + 7*24*3600) . "', bloqueado = 0, data_atualizacao = '$now' WHERE id = " . ((int)$rec['id'])) ;
        $rec = ensure_guest_record($con, $ip, $guestId);
    }

    // Rate limit check
    if (!empty($rec['data_ultimo_pedido'])) {
        $last = strtotime($rec['data_ultimo_pedido']);
        if (time() - $last < GUEST_RATE_LIMIT_SECONDS) {
            return ['ok' => false, 'code' => 429, 'message' => 'Demasiadas solicitações. Aguarde alguns segundos.', 'remaining' => max(0, GUEST_MAX_FREE - (int)$rec['total_pedidos']), 'total' => (int)$rec['total_pedidos']];
        }
    }

    // If blocked or exceeded
    if (!empty($rec['bloqueado']) || (int)$rec['total_pedidos'] >= GUEST_MAX_FREE) {
        // Ensure blocked flag set
        db_query($con, "UPDATE uso_convidado SET bloqueado = 1 WHERE id = " . ((int)$rec['id']));
        return ['ok' => false, 'code' => 403, 'message' => 'Limite de pedidos gratuitos atingido. Faça login para continuar.', 'remaining' => 0, 'total' => (int)$rec['total_pedidos'], 'blocked' => true];
    }

    // If second-to-last request now (i.e., total_pedidos == GUEST_MAX_FREE - 1 after increment), warn
    $newTotal = (int)$rec['total_pedidos'] + 1;
    $isLastFree = ($newTotal >= GUEST_MAX_FREE);

    $stmt = db_prepare($con, "UPDATE uso_convidado SET total_pedidos = ?, data_ultimo_pedido = ?, data_atualizacao = ? WHERE id = ?");
    if ($stmt) {
        $idInt = (int)$rec['id'];
        $nowSql = date('Y-m-d H:i:s');
        db_stmt_bind_param($stmt, 'issi', $newTotal, $nowSql, $nowSql, $idInt);
        db_stmt_execute($stmt);
    } else {
        db_query($con, "UPDATE uso_convidado SET total_pedidos = $newTotal, data_ultimo_pedido = '$now', data_atualizacao = '$now' WHERE id = " . ((int)$rec['id']));
    }

    $remaining = max(0, GUEST_MAX_FREE - $newTotal);
    $resArr = ['ok' => true, 'code' => 200, 'message' => $isLastFree ? 'Último pedido gratuito usado.' : 'Pedido processado.', 'remaining' => $remaining, 'total' => $newTotal, 'blocked' => false];
    if ($isLastFree) $resArr['warning'] = 'Estás a usar o último pedido gratuito.';
    return $resArr;
}

function guest_status($con) {
    // Ensure table exists
    $createTable = "CREATE TABLE IF NOT EXISTS uso_convidado (
        id INT AUTO_INCREMENT PRIMARY KEY,
        endereco_ip VARCHAR(45),
        id_anonimo VARCHAR(36),
        total_pedidos INT DEFAULT 0,
        data_primeiro_pedido DATETIME,
        data_ultimo_pedido DATETIME,
        data_expiracao DATETIME,
        bloqueado TINYINT DEFAULT 0,
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    @db_query($con, $createTable);
    
    $g = get_or_create_guest($con);
    $rec = $g['record'];
    if (!$rec) return null;
    $remaining = max(0, GUEST_MAX_FREE - (int)$rec['total_pedidos']);
    return ['ip' => $g['ip'], 'guest_id' => $g['guest_id'], 'remaining' => $remaining, 'total' => (int)$rec['total_pedidos'], 'blocked' => !empty($rec['bloqueado']), 'expires' => $rec['data_expiracao']];
}

?>
