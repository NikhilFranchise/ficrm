<?php
// api_export/api_bulk.php
header('Content-Type: application/json');
require_once 'api_db.php';

$type = $_GET['type'] ?? 'investor';
$ids_raw = isset($_GET['ids']) ? $_GET['ids'] : '';
$ids = explode(',', $ids_raw);
$ids = array_filter($ids, function($val) {
    return is_numeric(trim($val));
});

$response = [
    'status' => 'success',
    'data' => []
];

if (empty($ids)) {
    echo json_encode($response);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

if ($type === 'investor') {
    $stmt = $pdoApi->prepare("
        SELECT d.inv_det_id, d.investor_id, d.inv_city, d.inv_state, d.service_company_name, d.investment_max, u.name as investor_name, u.mobile as investor_mobile 
        FROM investor_details d 
        LEFT JOIN user_accounts u ON d.investor_id = u.profile_str 
        WHERE d.inv_det_id IN ($placeholders)
    ");
    $stmt->execute(array_values($ids));
    $data = $stmt->fetchAll();
    // Re-index by ID for easier lookup
    $indexed = [];
    foreach($data as $row) { $indexed[$row['inv_det_id']] = $row; }
    $response['data'] = $indexed;
} elseif ($type === 'franchisor') {
    $stmt = $pdoApi->prepare("
        SELECT f.fran_detail_id, f.franchisor_id, f.company_name, f.brand_name, f.city, f.state, f.ceo_name
        FROM franchisor_business_details f
        WHERE f.fran_detail_id IN ($placeholders)
    ");
    $stmt->execute(array_values($ids));
    $data = $stmt->fetchAll();
    // Re-index by ID
    $indexed = [];
    foreach($data as $row) { $indexed[$row['fran_detail_id']] = $row; }
    $response['data'] = $indexed;
}

echo json_encode($response);
exit;
?>
