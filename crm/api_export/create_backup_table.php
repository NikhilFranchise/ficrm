<?php
// api_export/create_backup_table.php
require_once 'api_db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS investor_update_backups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        investor_id INT NOT NULL,
        old_name VARCHAR(255),
        old_email VARCHAR(255),
        old_mobile VARCHAR(255),
        old_min_inv VARCHAR(100),
        old_max_inv VARCHAR(100),
        old_category VARCHAR(255),
        updated_by VARCHAR(100),
        backup_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdoApi->exec($sql);
    echo "Table 'investor_update_backups' created successfully on master database.";
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
