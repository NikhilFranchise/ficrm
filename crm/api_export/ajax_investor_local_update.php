<?php
header('Content-Type: application/json');
require_once '../db.php';
require_once '../api_client.php';

$response = ['status' => 'success', 'message' => ''];

try {
    $investor_id = $_POST['id'] ?? null;
    $updated_by = $_POST['updated_by'] ?? 'System';
    if (!$investor_id) throw new Exception("Investor ID missing");

    // 1. Take Snapshot of OLD data from Remote API
    $apiRes = fetchSingleFromApi('investor', $investor_id);
    if ($apiRes['status'] === 'success' && !empty($apiRes['data'])) {
        $old = $apiRes['data'];
        
        // Handle various name field possibilities
        $old_name = $old['name'] ?? '';
        if (empty($old_name)) {
            $fname = $old['first_name'] ?? '';
            $lname = $old['last_name'] ?? '';
            $old_name = trim($fname . ' ' . $lname);
        }
        if (empty($old_name)) {
            $old_name = $old['display_name'] ?? '';
        }

        // Save to Local Backup Table
        $stmtB = $pdoCrm->prepare("INSERT INTO investor_update_backups 
            (investor_id, old_name, old_email, old_mobile, old_min_inv, old_max_inv, old_category, updated_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtB->execute([
            $investor_id,
            $old_name,
            $old['email'] ?? '',
            $old['mobile'] ?? '',
            $old['min_investment'] ?? '',
            $old['max_investment'] ?? '',
            $old['category_interested'] ?? '',
            $updated_by
        ]);
    }

    // 2. Map and Update Local Extended Details
    $fields = [
        'title' => $_POST['title'] ?? '',
        'address' => $_POST['address'] ?? '',
        'pincode' => $_POST['pincode'] ?? '',
        'available_capital' => $_POST['available_capital'] ?? '',
        'loan_interest' => (int)($_POST['loan_interest'] ?? 0),
        'loan_range' => $_POST['loan_range'] ?? '',
        'income_range' => $_POST['income_range'] ?? '',
        'property_type_mortgage' => $_POST['property_type_mortgage'] ?? '',
        'property_size_mortgage' => $_POST['property_size_mortgage'] ?? '',
        'property_value_mortgage' => $_POST['property_value_mortgage'] ?? '',
        'loan_purpose' => $_POST['loan_purpose'] ?? '',
        'investment_date' => $_POST['investment_date'] ?? '',
        'qualification' => $_POST['qualification'] ?? '',
        'occupation' => $_POST['occupation'] ?? '',
        'looking_for' => isset($_POST['looking_for']) ? implode(', ', (array)$_POST['looking_for']) : '',
        'business_state_looking' => $_POST['business_state_looking'] ?? '',
        'business_city_looking' => $_POST['business_city_looking'] ?? '',
        'is_property_own' => (int)($_POST['is_property_own'] ?? 0),
        'prop_area_min' => $_POST['prop_area_min'] ?? '',
        'prop_area_max' => $_POST['prop_area_max'] ?? '',
        'area_type' => $_POST['area_type'] ?? ''
    ];

    $sql = "INSERT INTO crm_investor_details (
                investor_id, title, address, pincode, available_capital, 
                loan_interest, loan_range, income_range, property_type_mortgage, 
                property_size_mortgage, property_value_mortgage, loan_purpose, 
                investment_date, qualification, occupation, looking_for, 
                business_state_looking, business_city_looking, is_property_own, 
                prop_area_min, prop_area_max, area_type
            ) VALUES (
                :investor_id, :title, :address, :pincode, :available_capital, 
                :loan_interest, :loan_range, :income_range, :property_type_mortgage, 
                :property_size_mortgage, :property_value_mortgage, :loan_purpose, 
                :investment_date, :qualification, :occupation, :looking_for, 
                :business_state_looking, :business_city_looking, :is_property_own, 
                :prop_area_min, :prop_area_max, :area_type
            ) ON DUPLICATE KEY UPDATE 
                title = VALUES(title), address = VALUES(address), pincode = VALUES(pincode), 
                available_capital = VALUES(available_capital), loan_interest = VALUES(loan_interest), 
                loan_range = VALUES(loan_range), income_range = VALUES(income_range), 
                property_type_mortgage = VALUES(property_type_mortgage), 
                property_size_mortgage = VALUES(property_size_mortgage), 
                property_value_mortgage = VALUES(property_value_mortgage), 
                loan_purpose = VALUES(loan_purpose), investment_date = VALUES(investment_date), 
                qualification = VALUES(qualification), occupation = VALUES(occupation), 
                looking_for = VALUES(looking_for), business_state_looking = VALUES(business_state_looking), 
                business_city_looking = VALUES(business_city_looking), 
                is_property_own = VALUES(is_property_own), prop_area_min = VALUES(prop_area_min), 
                prop_area_max = VALUES(prop_area_max), area_type = VALUES(area_type)";

    $stmt = $pdoCrm->prepare($sql);
    $params = array_merge(['investor_id' => $investor_id], $fields);
    $stmt->execute($params);

    $response['message'] = "Local backup taken and details updated.";
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
