<?php
require 'db.php';
try {
    $pdoCrm->exec("
        CREATE TABLE IF NOT EXISTS crm_franchisors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            franchisor_id VARCHAR(50) UNIQUE,
            company_name VARCHAR(255),
            brand_name VARCHAR(255),
            ceo_name VARCHAR(255),
            mobile VARCHAR(20),
            email VARCHAR(255),
            city VARCHAR(100),
            state VARCHAR(100),
            ind_main_cat INT,
            ind_cat INT,
            ind_sub_cat INT,
            unit_inv_min DOUBLE(16,2),
            unit_inv_max DOUBLE(16,2),
            profile_link TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "crm_franchisors table created successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
