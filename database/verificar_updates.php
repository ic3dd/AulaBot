<?php
require __DIR__ . '/../ligarbd.php';
$result = db_query($con, 'DESCRIBE updates');
while($row = db_fetch_assoc($result)) {
    echo $row['Field'] . ' -> ' . $row['Type'] . PHP_EOL;
}
?>
