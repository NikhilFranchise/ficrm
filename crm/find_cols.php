<?php
require 'api_export/api_db.php';
try {
    $stmt = $pdoApi->query("SELECT * FROM lead_management LIMIT 1");
    $row = $stmt->fetch();
    if ($row) {
        echo json_encode(array_keys($row));
    } else {
        echo "Table is empty.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
