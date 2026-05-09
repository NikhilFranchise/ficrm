<?php
require_once 'c:/xampp/htdocs/FMC/crm/api_export/api_db.php';
try {
    $stmt = $pdoApi->query("DESCRIBE lead_management");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
