<?php
// api_export/api_investor_update.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once 'api_db.php';

$response = ['status' => 'success', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['status'] = 'error';
    $response['message'] = 'Only POST requests allowed.';
    echo json_encode($response);
    exit;
}

try {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $min_inv = $_POST['min_investment'] ?? '';
    $max_inv = $_POST['max_investment'] ?? '';
    $category = $_POST['category_interested'] ?? '';
    $title = $_POST['title'] ?? '';
    $address = $_POST['address'] ?? '';
    $pincode = $_POST['pincode'] ?? '';
    $qualification = $_POST['qualification'] ?? '';
    $occupation = $_POST['occupation'] ?? '';

    if (!$id) {
        throw new Exception("Investor ID is required.");
    }

    // Perform Actual Update on Remote lead_management table
    $stmt = $pdoApi->prepare("
        UPDATE lead_management 
        SET name = ?, last_name = ?, email = ?, min_investment = ?, max_investment = ?, 
            category_interested = ?, title = ?, address = ?, pincode = ?, 
            qualification = ?, occupation = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $last_name, $email, $min_inv, $max_inv, $category, $title, $address, $pincode, $qualification, $occupation, $id]);

    $response['message'] = "Investor master record updated successfully.";
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>
