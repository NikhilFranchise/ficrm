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
    $email = $_POST['email'] ?? '';
    $mobile = $_POST['mobile'] ?? '';
    $min_inv = $_POST['min_investment'] ?? '';
    $max_inv = $_POST['max_investment'] ?? '';
    $category = $_POST['category_interested'] ?? '';

    if (!$id) {
        throw new Exception("Investor ID is required.");
    }

    $stmt = $pdoApi->prepare("
        UPDATE lead_management 
        SET email = ?, mobile = ?, min_investment = ?, max_investment = ?, category_interested = ?
        WHERE id = ?
    ");
    $stmt->execute([$email, $mobile, $min_inv, $max_inv, $category, $id]);

    if ($stmt->rowCount() > 0) {
        $response['message'] = "Investor updated successfully.";
    } else {
        $response['message'] = "No changes made or investor not found.";
    }
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>
