<?php
require 'api_export/api_db.php';
$cols = $pdoApi->query('DESCRIBE lead_management')->fetchAll(PDO::FETCH_ASSOC);
print_r($cols);
?>
