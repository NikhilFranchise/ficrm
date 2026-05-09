<?php
require 'db.php';
$tables = $pdoCrm->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);
?>
