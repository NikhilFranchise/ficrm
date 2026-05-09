<?php
// api_export/api_v2.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once 'api_db.php';

$action = $_GET['action'] ?? 'list';
$type = $_GET['type'] ?? 'investor';
$id = $_GET['id'] ?? '';
$ids_raw = $_GET['ids'] ?? '';

$response = ['status' => 'success', 'data' => [], 'total' => 0];

try {
    if ($action === 'list') {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';

        if ($type === 'investor') {
            $where = "1=1";
            $params = [];
            if ($search) {
                $where .= " AND (d.inv_city LIKE :s1 OR d.inv_state LIKE :s2 OR d.service_company_name LIKE :s3)";
                $params[':s1'] = $params[':s2'] = $params[':s3'] = "%$search%";
            }
            if (isset($_GET['state']) && $_GET['state'] !== '') {
                $where .= " AND d.inv_state = :state";
                $params[':state'] = $_GET['state'];
            }
            if (isset($_GET['city']) && $_GET['city'] !== '') {
                $where .= " AND d.inv_city = :city";
                $params[':city'] = $_GET['city'];
            }
            
            $stmt = $pdoApi->prepare("SELECT COUNT(*) FROM investor_details d WHERE $where");
            $stmt->execute($params);
            $response['total'] = $stmt->fetchColumn();

            $stmt = $pdoApi->prepare("
                SELECT d.*, u.name as investor_name, u.mobile as investor_mobile
                FROM investor_details d 
                LEFT JOIN user_accounts u ON d.investor_id = u.profile_str 
                WHERE $where 
                ORDER BY d.inv_det_id DESC LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $response['data'] = $stmt->fetchAll();
        } else {
            $where = "f.profile_status = 1";
            $params = [];
            if ($search) {
                $where .= " AND (f.city LIKE :s1 OR f.state LIKE :s2 OR f.company_name LIKE :s3 OR f.brand_name LIKE :s4)";
                $params[':s1'] = $params[':s2'] = $params[':s3'] = $params[':s4'] = "%$search%";
            }
            
            $stmt = $pdoApi->prepare("SELECT COUNT(*) FROM franchisor_business_details f WHERE $where");
            $stmt->execute($params);
            $response['total'] = $stmt->fetchColumn();

            $stmt = $pdoApi->prepare("SELECT f.* FROM franchisor_business_details f WHERE $where ORDER BY f.fran_detail_id DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $response['data'] = $stmt->fetchAll();
        }
    } elseif ($action === 'details') {
        if ($id) {
            if ($type === 'investor') {
                $stmt = $pdoApi->prepare("
                    SELECT d.*, u.name as investor_name, u.mobile as investor_mobile 
                    FROM investor_details d 
                    LEFT JOIN user_accounts u ON d.investor_id = u.profile_str 
                    WHERE d.inv_det_id = ?
                ");
                $stmt->execute([$id]);
                $response['data'] = $stmt->fetch();
            } else {
                $stmt = $pdoApi->prepare("
                    SELECT f.*, c1.catname as main_category_name, c2.catname as category_name, c3.catname as sub_category_name
                    FROM franchisor_business_details f
                    LEFT JOIN category_final c1 ON f.ind_main_cat = c1.catid
                    LEFT JOIN category_final c2 ON f.ind_cat = c2.catid
                    LEFT JOIN category_final c3 ON f.ind_sub_cat = c3.catid
                    WHERE f.fran_detail_id = ?
                ");
                $stmt->execute([$id]);
                $response['data'] = $stmt->fetch();
            }
        } elseif ($ids_raw) {
            $ids = array_filter(explode(',', $ids_raw), 'is_numeric');
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                if ($type === 'investor') {
                    $stmt = $pdoApi->prepare("
                        SELECT d.inv_det_id, d.investor_id, d.inv_city, d.inv_state, d.service_company_name, d.investment_max, u.name as investor_name, u.mobile as investor_mobile 
                        FROM investor_details d 
                        LEFT JOIN user_accounts u ON d.investor_id = u.profile_str 
                        WHERE d.inv_det_id IN ($placeholders)
                    ");
                } else {
                    $stmt = $pdoApi->prepare("
                        SELECT f.fran_detail_id, f.franchisor_id, f.company_name, f.brand_name, f.city, f.state, f.ceo_name
                        FROM franchisor_business_details f
                        WHERE f.fran_detail_id IN ($placeholders)
                    ");
                }
                $stmt->execute(array_values($ids));
                $data = $stmt->fetchAll();
                $indexed = [];
                $key = ($type === 'investor' ? 'inv_det_id' : 'fran_detail_id');
                foreach($data as $row) { $indexed[$row[$key]] = $row; }
                $response['data'] = $indexed;
            }
        }
    } elseif ($action === 'categories') {
        $parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;
        $stmt = $pdoApi->prepare("SELECT catid, catname FROM category_final WHERE parent_id = ? ORDER BY catname ASC");
        $stmt->execute([$parent_id]);
        $response['data'] = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>
