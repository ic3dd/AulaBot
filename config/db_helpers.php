<?php
/**
 * Camada de compatibilidade: mysqli ↔ PDO (PostgreSQL/Supabase)
 * Permite que o código funcione com MySQL (mysqli) ou Supabase (PDO/PostgreSQL)
 */

if (!function_exists('db_query')) {
function db_query($con, $sql) {
    if ($con instanceof mysqli) {
        return mysqli_query($con, $sql);
    }
    if ($con instanceof PDO) {
        try {
            return $con->query($sql);
        } catch (PDOException $e) {
            error_log('db_query: ' . $e->getMessage());
            return false;
        }
    }
    return false;
}
}

if (!function_exists('db_fetch_assoc')) {
function db_fetch_assoc($result) {
    if ($result instanceof mysqli_result) {
        return mysqli_fetch_assoc($result);
    }
    if ($result instanceof PDOStatement) {
        return $result->fetch(PDO::FETCH_ASSOC);
    }
    return false;
}
}

if (!function_exists('db_num_rows')) {
function db_num_rows($result) {
    if ($result instanceof mysqli_result) {
        return mysqli_num_rows($result);
    }
    if ($result instanceof PDOStatement) {
        return $result->rowCount();
    }
    return 0;
}
}

if (!function_exists('db_prepare')) {
function db_prepare($con, $sql) {
    if ($con instanceof mysqli) {
        return mysqli_prepare($con, $sql);
    }
    if ($con instanceof PDO) {
        try {
            return $con->prepare($sql);
        } catch (PDOException $e) {
            error_log('db_prepare: ' . $e->getMessage());
            return false;
        }
    }
    return false;
}
}

if (!function_exists('db_stmt_bind_param')) {
function db_stmt_bind_param($stmt, $types, &...$params) {
    if ($stmt instanceof mysqli_stmt) {
        return mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    if ($stmt instanceof PDOStatement) {
        $i = 1;
        foreach ($params as &$p) {
            $t = PDO::PARAM_STR;
            if ($i <= strlen($types)) {
                $c = $types[$i - 1];
                if ($c === 'i') $t = PDO::PARAM_INT;
                elseif ($c === 'd') $t = PDO::PARAM_STR;
                elseif ($c === 'b') $t = PDO::PARAM_LOB;
            }
            $stmt->bindValue($i++, $p, $t);
        }
        return true;
    }
    return false;
}
}

if (!function_exists('db_stmt_execute')) {
function db_stmt_execute($stmt) {
    if ($stmt instanceof mysqli_stmt) {
        return mysqli_stmt_execute($stmt);
    }
    if ($stmt instanceof PDOStatement) {
        return $stmt->execute();
    }
    return false;
}
}

if (!function_exists('db_stmt_get_result')) {
function db_stmt_get_result($stmt) {
    if ($stmt instanceof mysqli_stmt) {
        return mysqli_stmt_get_result($stmt);
    }
    if ($stmt instanceof PDOStatement) {
        return $stmt;
    }
    return false;
}
}

if (!function_exists('db_stmt_close')) {
function db_stmt_close($stmt) {
    if ($stmt instanceof mysqli_stmt) {
        return mysqli_stmt_close($stmt);
    }
    if ($stmt instanceof PDOStatement) {
        return true;
    }
    return false;
}
}

if (!function_exists('db_real_escape_string')) {
function db_real_escape_string($con, $str) {
    if ($con instanceof mysqli) {
        return mysqli_real_escape_string($con, $str);
    }
    if ($con instanceof PDO) {
        return str_replace("'", "''", $str);
    }
    return addslashes($str);
}
}

if (!function_exists('db_insert_id')) {
function db_insert_id($con) {
    if ($con instanceof mysqli) {
        return mysqli_insert_id($con);
    }
    if ($con instanceof PDO) {
        return $con->lastInsertId();
    }
    return 0;
}
}

if (!function_exists('db_error')) {
function db_error($con) {
    if ($con instanceof mysqli) {
        return mysqli_error($con);
    }
    if ($con instanceof PDO) {
        $info = $con->errorInfo();
        return $info[2] ?? '';
    }
    return '';
}
}

if (!function_exists('db_stmt_error')) {
function db_stmt_error($stmt) {
    if ($stmt instanceof mysqli_stmt) {
        return mysqli_stmt_error($stmt);
    }
    if ($stmt instanceof PDOStatement) {
        $info = $stmt->errorInfo();
        return $info[2] ?? '';
    }
    return '';
}
}

if (!function_exists('db_close')) {
function db_close($con) {
    if ($con instanceof mysqli) {
        return mysqli_close($con);
    }
    $con = null;
    return true;
}
}

if (!function_exists('db_ping')) {
function db_ping($con) {
    if ($con instanceof mysqli) {
        return mysqli_ping($con);
    }
    if ($con instanceof PDO) {
        try {
            $con->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    return false;
}
}

if (!function_exists('db_stmt_affected_rows')) {
function db_stmt_affected_rows($stmt) {
    if ($stmt instanceof mysqli_stmt) {
        return mysqli_stmt_affected_rows($stmt);
    }
    if ($stmt instanceof PDOStatement) {
        return $stmt->rowCount();
    }
    return 0;
}
}

if (!function_exists('db_begin_transaction')) {
function db_begin_transaction($con) {
    if ($con instanceof mysqli) {
        return mysqli_begin_transaction($con);
    }
    if ($con instanceof PDO) {
        return $con->beginTransaction();
    }
    return false;
}
}

if (!function_exists('db_commit')) {
function db_commit($con) {
    if ($con instanceof mysqli) {
        return mysqli_commit($con);
    }
    if ($con instanceof PDO) {
        return $con->commit();
    }
    return false;
}
}

if (!function_exists('db_rollback')) {
function db_rollback($con) {
    if ($con instanceof mysqli) {
        return mysqli_rollback($con);
    }
    if ($con instanceof PDO) {
        return $con->rollBack();
    }
    return false;
}
}

if (!function_exists('db_autocommit')) {
function db_autocommit($con, $mode) {
    if ($con instanceof mysqli) {
        return mysqli_autocommit($con, $mode);
    }
    return true;
}
}

if (!function_exists('db_set_charset')) {
function db_set_charset($con, $charset) {
    if ($con instanceof mysqli) {
        return mysqli_set_charset($con, $charset);
    }
    return true;
}
}

if (!function_exists('db_fetch_row')) {
function db_fetch_row($result) {
    if ($result instanceof mysqli_result) {
        return mysqli_fetch_row($result);
    }
    if ($result instanceof PDOStatement) {
        return $result->fetch(PDO::FETCH_NUM);
    }
    return false;
}
}

if (!function_exists('db_connect_error')) {
function db_connect_error() {
    return function_exists('mysqli_connect_error') ? mysqli_connect_error() : '';
}
}

if (!function_exists('db_sql_date')) {
function db_sql_date() {
    return defined('DB_IS_POSTGRES') && DB_IS_POSTGRES ? 'CURRENT_DATE' : 'CURDATE()';
}
}

if (!function_exists('db_sql_date_col')) {
function db_sql_date_col($column) {
    $c = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    return defined('DB_IS_POSTGRES') && DB_IS_POSTGRES ? "($c::date)" : "DATE($c)";
}
}

if (!function_exists('db_column_exists')) {
function db_column_exists($con, $table, $column) {
    $tableSafe = db_real_escape_string($con, $table);
    $columnSafe = db_real_escape_string($con, $column);
    if (defined('DB_IS_POSTGRES') && DB_IS_POSTGRES) {
        $sql = "SELECT column_name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '$tableSafe' AND column_name = '$columnSafe' LIMIT 1";
    } else {
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tableSafe' AND COLUMN_NAME = '$columnSafe' LIMIT 1";
    }
    $res = db_query($con, $sql);
    return $res && db_num_rows($res) > 0;
}
}

if (!function_exists('db_sql_add_column')) {
function db_sql_add_column($table, $column, $definition) {
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $columnSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if (defined('DB_IS_POSTGRES') && DB_IS_POSTGRES) {
        $def = str_ireplace(['TINYINT', 'LONGTEXT'], ['SMALLINT', 'TEXT'], $definition);
        return "ALTER TABLE $tableSafe ADD COLUMN IF NOT EXISTS $columnSafe $def";
    }
    return "ALTER TABLE `$tableSafe` ADD COLUMN `$columnSafe` $definition";
}
}

if (!function_exists('db_sql_date_sub')) {
function db_sql_date_sub($interval) {
    $pg = str_replace([' DAY', ' HOUR'], [' days', ' hours'], $interval);
    return defined('DB_IS_POSTGRES') && DB_IS_POSTGRES 
        ? "NOW() - INTERVAL '$pg'" 
        : "DATE_SUB(NOW(), INTERVAL $interval)";
}
}

if (!function_exists('db_sql_date_add')) {
function db_sql_date_add($interval) {
    $pg = str_replace([' DAY', ' HOUR'], [' days', ' hours'], $interval);
    return defined('DB_IS_POSTGRES') && DB_IS_POSTGRES 
        ? "NOW() + INTERVAL '$pg'" 
        : "DATE_ADD(NOW(), INTERVAL $interval)";
}
}

if (!function_exists('db_connect_errno')) {
function db_connect_errno() {
    return function_exists('mysqli_connect_errno') ? mysqli_connect_errno() : 0;
}
}

if (!function_exists('db_stmt_send_long_data')) {
function db_stmt_send_long_data($stmt, $param, $data) {
    if ($stmt instanceof mysqli_stmt) {
        return mysqli_stmt_send_long_data($stmt, $param, $data);
    }
    if ($stmt instanceof PDOStatement) {
        return $stmt->bindValue($param, $data, PDO::PARAM_LOB);
    }
    return false;
}
}

if (!function_exists('db_stmt_store_result')) {
function db_stmt_store_result($stmt) {
    if ($stmt instanceof mysqli_stmt) {
        return mysqli_stmt_store_result($stmt);
    }
    return true;
}
}

if (!function_exists('db_stmt_num_rows')) {
function db_stmt_num_rows($stmt) {
    if ($stmt instanceof mysqli_stmt) {
        return mysqli_stmt_num_rows($stmt);
    }
    if ($stmt instanceof PDOStatement) {
        return $stmt->rowCount();
    }
    return 0;
}
}
