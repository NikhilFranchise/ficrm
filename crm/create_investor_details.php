<?php
require_once 'db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS crm_investor_details (
        investor_id INT PRIMARY KEY,
        title VARCHAR(10),
        address TEXT,
        pincode VARCHAR(10),
        available_capital VARCHAR(50),
        loan_interest TINYINT(1) DEFAULT 0,
        loan_range VARCHAR(50),
        income_range VARCHAR(50),
        property_type_mortgage VARCHAR(50),
        property_size_mortgage VARCHAR(50),
        property_value_mortgage VARCHAR(50),
        loan_purpose TEXT,
        investment_date VARCHAR(50),
        qualification VARCHAR(50),
        occupation VARCHAR(50),
        looking_for TEXT,
        business_state_looking VARCHAR(50),
        business_city_looking VARCHAR(50),
        is_property_own TINYINT(1) DEFAULT 0,
        prop_area_min VARCHAR(20),
        prop_area_max VARCHAR(20),
        area_type VARCHAR(20),
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";

    $pdoCrm->exec($sql);
    echo "Table crm_investor_details created successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
