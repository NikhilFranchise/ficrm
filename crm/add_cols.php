<?php
require 'db.php';
try {
    $pdoCrm->exec("ALTER TABLE activity_logs ADD COLUMN cat_name VARCHAR(255) NULL");
    $pdoCrm->exec("ALTER TABLE activity_logs ADD COLUMN sub_cat_name VARCHAR(255) NULL");
    $pdoCrm->exec("ALTER TABLE activity_logs ADD COLUMN sub_sub_cat_name VARCHAR(255) NULL");
    echo "Columns added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
