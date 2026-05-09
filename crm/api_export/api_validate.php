<?php
// api_export/api_validate.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once 'api_db.php';

$type = $_GET['type'] ?? 'investor'; // investor or franchisor
$id = $_GET['id'] ?? '';

$response = ['status' => 'success', 'exists' => false, 'data' => null];

if (empty($id)) {
    $response['status'] = 'error';
    $response['message'] = 'ID is required';
    echo json_encode($response);
    exit;
}

try {
    if ($type === 'investor') {
        $stmt = $pdoApi->prepare("SELECT inv_det_id, investor_id, service_company_name FROM investor_details WHERE inv_det_id = ? OR investor_id = ? LIMIT 1");
        $stmt->execute([$id, $id]);
        $data = $stmt->fetch();
        if ($data) {
            $response['exists'] = true;
            $response['data'] = $data;
        }
    } elseif ($type === 'franchisor') {
        $stmt = $pdoApi->prepare("SELECT fran_detail_id, franchisor_id, brand_name, company_name FROM franchisor_business_details WHERE fran_detail_id = ? OR franchisor_id = ? LIMIT 1");
        $stmt->execute([$id, $id]);
        $data = $stmt->fetch();
        if ($data) {
            $response['exists'] = true;
            $response['data'] = $data;
        }
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Invalid type';
    }
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>
