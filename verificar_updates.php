<?php
require 'ligarbd.php';
$result = mysqli_query($con, 'DESCRIBE updates');
while($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . ' -> ' . $row['Type'] . PHP_EOL;
}
?>
