<?php
require 'db.php';
$cols = $pdoCrm->query('DESCRIBE activity_logs')->fetchAll(PDO::FETCH_COLUMN);
print_r($cols);
?>
