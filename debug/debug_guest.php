<?php
// Simple debug script to test guest control setup
header('Content-Type: text/plain; charset=UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Debug Guest Control ===\n\n";

echo "1. Testing DB connection...\n";
if (file_exists('ligarbd.php')) {
    require_once('ligarbd.php');
    echo "   - ligarbd.php loaded\n";
} else {
    echo "   - ERROR: ligarbd.php not found\n";
}

if (!isset($con) && isset($conn)) $con = $conn;
if (!isset($con) && isset($mysqli)) $con = $mysqli;

if (isset($con) && $con instanceof mysqli) {
    echo "   - DB connection OK\n";
    echo "   - DB: " . $con->get_charset()->charset . "\n";
} else {
    echo "   - ERROR: DB connection failed\n";
    exit(1);
}

echo "\n2. Testing guest_control.php...\n";
if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'guest_control.php')) {
    echo "   - guest_control.php found\n";
    require_once(__DIR__ . DIRECTORY_SEPARATOR . 'guest_control.php');
    echo "   - guest_control.php loaded\n";
} else {
    echo "   - ERROR: guest_control.php not found\n";
    exit(1);
}

if (!function_exists('guest_check_and_increment')) {
    echo "   - ERROR: guest_check_and_increment function not found\n";
    exit(1);
} else {
    echo "   - guest_check_and_increment found\n";
}

echo "\n3. Testing guest_status...\n";
try {
    $status = guest_status($con);
    if ($status) {
        echo "   - Status retrieved successfully\n";
        echo "   - IP: " . $status['ip'] . "\n";
        echo "   - Guest ID: " . $status['guest_id'] . "\n";
        echo "   - Remaining: " . $status['remaining'] . "/3\n";
        echo "   - Total: " . $status['total'] . "\n";
        echo "   - Blocked: " . ($status['blocked'] ? 'YES' : 'NO') . "\n";
    } else {
        echo "   - ERROR: guest_status returned null\n";
    }
} catch (Exception $e) {
    echo "   - ERROR: " . $e->getMessage() . "\n";
}

echo "\n4. Testing usage_convidado table...\n";
$result = db_query($con, "SHOW TABLES LIKE 'uso_convidado'");
if ($result && db_num_rows($result) > 0) {
    echo "   - Table exists\n";
    $columns = db_query($con, "DESCRIBE uso_convidado");
    if ($columns) {
        echo "   - Columns: ";
        while ($col = db_fetch_assoc($columns)) {
            echo $col['Field'] . " ";
        }
        echo "\n";
    }
} else {
    echo "   - Table does NOT exist\n";
}

echo "\n=== End Debug ===\n";
?>
