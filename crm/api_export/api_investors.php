<?php
// api_export/api_investors.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once 'api_db.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? '';
$ids_raw = $_GET['ids'] ?? '';

$response = ['status' => 'success', 'data' => [], 'total' => 0];

try {
    if ($action === 'list') {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';

        // Specific filters as requested by user
        $where = "lead_type = 14 AND payment_history = 1 AND lead_origin = 14";
        $params = [];
        if ($search) {
            $where .= " AND (name LIKE :s1 OR last_name LIKE :s2 OR email LIKE :s3 OR mobile LIKE :s4 OR city LIKE :s5 OR state LIKE :s6)";
            $params[':s1'] = $params[':s2'] = $params[':s3'] = $params[':s4'] = $params[':s5'] = $params[':s6'] = "%$search%";
        }

        if (isset($_GET['inv_min']) && $_GET['inv_min'] !== '') {
            $where .= " AND CAST(REPLACE(REPLACE(min_investment, ',', ''), ' ', '') AS UNSIGNED) >= :inv_min";
            $params[':inv_min'] = (int)$_GET['inv_min'];
        }
        if (isset($_GET['inv_max']) && $_GET['inv_max'] !== '') {
            $where .= " AND CAST(REPLACE(REPLACE(max_investment, ',', ''), ' ', '') AS UNSIGNED) <= :inv_max";
            $params[':inv_max'] = (int)$_GET['inv_max'];
        }
        
        $stmt = $pdoApi->prepare("SELECT COUNT(*) FROM lead_management WHERE $where");
        $stmt->execute($params);
        $response['total'] = (int)$stmt->fetchColumn();

        $sort_order = (isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC') ? 'ASC' : 'DESC';

        $stmt = $pdoApi->prepare("
            SELECT id, name as first_name, last_name, email, mobile, city, state, country, 
                   min_investment, max_investment, category_interested, created_at
            FROM lead_management 
            WHERE $where 
            ORDER BY id $sort_order LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $response['data'] = $stmt->fetchAll();
        $response['page'] = $page;
        $response['limit'] = $limit;

    } elseif ($action === 'details' && $id) {
        $stmt = $pdoApi->prepare("SELECT id, name as first_name, last_name, email, mobile, city, state, country, min_investment, max_investment, category_interested, created_at FROM lead_management WHERE id = ?");
        $stmt->execute([$id]);
        $response['data'] = $stmt->fetch();
        $response['total'] = $response['data'] ? 1 : 0;

    } elseif ($action === 'bulk' && $ids_raw) {
        $ids = array_filter(explode(',', $ids_raw));
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdoApi->prepare("SELECT id, name as first_name, last_name, email, mobile, city, state FROM lead_management WHERE id IN ($placeholders)");
            $stmt->execute(array_values($ids));
            $data = $stmt->fetchAll();
            $indexed = [];
            foreach($data as $row) { $indexed[$row['id']] = $row; }
            $response['data'] = $indexed;
            $response['total'] = count($indexed);
        }
    } elseif ($action === 'validate' && $id) {
        $stmt = $pdoApi->prepare("SELECT id, name as first_name, last_name FROM lead_management WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        if ($data) {
            $response['exists'] = true;
            $response['data'] = $data;
        } else {
            $response['exists'] = false;
        }
    }
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>
