<?php
require_once 'c:/xampp/htdocs/FMC/crm/db.php';
try {
    $stmt = $pdoCrm->query("DESCRIBE lead_management");
    echo "COLUMNS IN lead_management:\n";
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
