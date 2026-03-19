<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$log_file = __DIR__ . '/test_write_debug.log';
$message = "Test write from test_write.php at " . date('Y-m-d H:i:s') . "\n";
if (file_put_contents($log_file, $message, FILE_APPEND)) {
    echo "Successfully wrote to log file: $log_file";
} else {
    echo "Failed to write to log file: $log_file";
    $error = error_get_last();
    if ($error) {
        echo "<br>Error details: " . $error['message'];
    }
}
?>
