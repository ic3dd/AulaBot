<?php
/**
 * Ligação à base de dados - suporta MySQL (mysqli) e Supabase (PostgreSQL/PDO)
 * 
 * Para usar Supabase:
 * 1. Em config/config_secrets.php define:
 *    define('USE_SUPABASE', true);
 *    define('SUPABASE_DB_URL', 'postgresql://postgres.[PROJECT-REF]:[PASSWORD]@aws-0-[REGION].pooler.supabase.com:5432/postgres');
 * 2. Executa database/supabase_schema.sql no Supabase SQL Editor
 */

$con = null;
$conn = null;

if (file_exists(__DIR__ . '/config_secrets.php')) {
    require_once __DIR__ . '/config_secrets.php';
} elseif (file_exists(__DIR__ . '/../config_secrets.php')) {
    require_once __DIR__ . '/../config_secrets.php';
}

// Variáveis de ambiente (Render, etc.) - usadas quando config_secrets não existe
if (!defined('USE_SUPABASE') && getenv('USE_SUPABASE') !== false) {
    define('USE_SUPABASE', in_array(strtolower((string)getenv('USE_SUPABASE')), ['true', '1', 'yes'], true));
}
if (!defined('SUPABASE_DB_URL') && getenv('SUPABASE_DB_URL') !== false) {
    define('SUPABASE_DB_URL', getenv('SUPABASE_DB_URL'));
}

if (defined('USE_SUPABASE') && USE_SUPABASE && defined('SUPABASE_DB_URL') && SUPABASE_DB_URL) {
    try {
        $url = SUPABASE_DB_URL;
        if (preg_match('#postgres(?:ql)?://([^:]+):([^@]+)@([^:/]+)(?::(\d+))?/(.+)#', $url, $m)) {
            $user = $m[1];
            $pass = $m[2];
            $host = $m[3];
            $port = $m[4] ?? 5432;
            $dbname = preg_replace('/\?.*/', '', $m[5]);
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            $con = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } else {
            $con = new PDO($url, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        $con->exec("SET NAMES 'UTF8'");
        $conn = $con;
        define('DB_IS_POSTGRES', true);
        require_once __DIR__ . '/db_helpers.php';
    } catch (PDOException $e) {
        die("Erro ao conectar ao Supabase: " . $e->getMessage());
    }
} else {
    $servername = "localhost";
    $username = "aluno19355";
    $password = "bCXaf1CsciCwG5F";
    $dbname = "aluno19355";

    $con = new mysqli($servername, $username, $password, $dbname);

    if ($con->connect_error) {
        die("Connection failed: " . $con->connect_error);
    }

    $con->set_charset("utf8mb4");
    $conn = $con;
    define('DB_IS_POSTGRES', false);
    require_once __DIR__ . '/db_helpers.php';
}
