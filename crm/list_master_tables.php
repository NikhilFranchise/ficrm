<?php
require 'api_export/api_db.php';
$tables = $pdoApi->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);
?>
