<?php
header('Content-Type: application/json');
require_once 'api_db.php';

$type = $_GET['type'] ?? 'investor';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$offset = ($page - 1) * $limit;

$search = $_GET['search'] ?? '';

$response = [
    'status' => 'success',
    'data' => [],
    'total' => 0,
    'page' => $page,
    'limit' => $limit
];

if ($type === 'investor') {
    if (isset($_GET['id'])) {
        $stmt = $pdoApi->prepare("
            SELECT d.*, u.name as investor_name, u.mobile as investor_mobile 
            FROM investor_details d 
            LEFT JOIN user_accounts u ON d.investor_id = u.profile_str 
            WHERE d.inv_det_id = ?
        ");
        $stmt->execute([$_GET['id']]);
        $response['data'] = $stmt->fetch();
        $response['total'] = $response['data'] ? 1 : 0;
    } else {
        $where = "1=1";
        $params = [];
        if ($search) {
            $where .= " AND (d.inv_city LIKE :s1 OR d.inv_state LIKE :s2 OR d.service_company_name LIKE :s3)";
            $params[':s1'] = "%$search%";
            $params[':s2'] = "%$search%";
            $params[':s3'] = "%$search%";
        }
        
        if (isset($_GET['state']) && $_GET['state'] !== '') {
            $where .= " AND (d.inv_state = :state OR ip.looking_business_state LIKE :likestate)";
            $params[':state'] = $_GET['state'];
            $params[':likestate'] = "%" . $_GET['state'] . "%";
        }
        if (isset($_GET['city']) && $_GET['city'] !== '') {
            $where .= " AND (d.inv_city = :city OR ip.looking_business_city LIKE :likecity)";
            $params[':city'] = $_GET['city'];
            $params[':likecity'] = "%" . $_GET['city'] . "%";
        }
        if (isset($_GET['inv_min']) && $_GET['inv_min'] !== '') {
            $where .= " AND (d.investment_max >= :inv_min OR d.avlcap_max >= :inv_min)";
            $params[':inv_min'] = $_GET['inv_min'];
        }
        if (isset($_GET['inv_max']) && $_GET['inv_max'] !== '') {
            $where .= " AND (d.investment_min <= :inv_max OR d.avlcap_min <= :inv_max)";
            $params[':inv_max'] = $_GET['inv_max'];
        }
        if (isset($_GET['category']) && $_GET['category'] !== '') {
            $where .= " AND (d.fran_ind_int LIKE :category OR ip.industry_type LIKE :category)";
            $params[':category'] = "%" . $_GET['category'] . "%";
        }
        
        // Total count
        $stmt = $pdoApi->prepare("
            SELECT COUNT(*) 
            FROM investor_details d 
            LEFT JOIN fi_crm.investor_preferences ip ON d.inv_det_id = ip.investor_id 
            WHERE $where
        ");
        $stmt->execute($params);
        $response['total'] = $stmt->fetchColumn();
        
        // Fetch data
        $stmt = $pdoApi->prepare("
            SELECT d.*, u.name as investor_name, u.mobile as investor_mobile, ip.investment_range as pref_inv, ip.looking_business_state as pref_state, ip.looking_business_city as pref_city
            FROM investor_details d 
            LEFT JOIN user_accounts u ON d.investor_id = u.profile_str 
            LEFT JOIN fi_crm.investor_preferences ip ON d.inv_det_id = ip.investor_id 
            WHERE $where 
            ORDER BY d.inv_det_id DESC LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $response['data'] = $stmt->fetchAll();
    }
    
} elseif ($type === 'franchisor') {
    if (isset($_GET['id'])) {
        $stmt = $pdoApi->prepare("
            SELECT f.*, 
                   c1.catname as main_category_name, 
                   c2.catname as category_name, 
                   c3.catname as sub_category_name
            FROM franchisor_business_details f
            LEFT JOIN category_final c1 ON f.ind_main_cat = c1.catid
            LEFT JOIN category_final c2 ON f.ind_cat = c2.catid
            LEFT JOIN category_final c3 ON f.ind_sub_cat = c3.catid
            WHERE f.fran_detail_id = ?
        ");
        $stmt->execute([$_GET['id']]);
        $response['data'] = $stmt->fetch();
        $response['total'] = $response['data'] ? 1 : 0;
    } else {
        $where = "f.profile_status = 1";
        $params = [];
        if ($search) {
            $where .= " AND (f.city LIKE :s1 OR f.state LIKE :s2 OR f.company_name LIKE :s3 OR f.brand_name LIKE :s4)";
            $params[':s1'] = "%$search%";
            $params[':s2'] = "%$search%";
            $params[':s3'] = "%$search%";
            $params[':s4'] = "%$search%";
        }
        if (isset($_GET['fid']) && $_GET['fid'] !== '') {
            $where .= " AND f.franchisor_id = :fid";
            $params[':fid'] = $_GET['fid'];
        }
        if (isset($_GET['state']) && $_GET['state'] !== '') {
            $where .= " AND (f.state = :state OR fr.state LIKE :likestate)";
            $params[':state'] = $_GET['state'];
            $params[':likestate'] = "%" . $_GET['state'] . "%";
        }
        if (isset($_GET['city']) && $_GET['city'] !== '') {
            $where .= " AND (f.city = :city OR fr.preferred_cities LIKE :likecity)";
            $params[':city'] = $_GET['city'];
            $params[':likecity'] = "%" . $_GET['city'] . "%";
        }
        if (isset($_GET['inv_min']) && $_GET['inv_min'] !== '') {
            $where .= " AND (f.unit_inv_max >= :inv_min OR fr.unit_inv_max >= :inv_min)";
            $params[':inv_min'] = $_GET['inv_min'];
        }
        if (isset($_GET['inv_max']) && $_GET['inv_max'] !== '') {
            $where .= " AND (f.unit_inv_min <= :inv_max OR fr.unit_inv_min <= :inv_max)";
            $params[':inv_max'] = $_GET['inv_max'];
        }
        if (isset($_GET['category']) && $_GET['category'] !== '') {
            $where .= " AND f.ind_main_cat = :category";
            $params[':category'] = $_GET['category'];
        }
        
        // Total count
        $stmt = $pdoApi->prepare("
            SELECT COUNT(*) 
            FROM franchisor_business_details f
            LEFT JOIN fi_crm.franchisor_requirements fr ON f.fran_detail_id = fr.franchisor_id
            WHERE $where
        ");
        $stmt->execute($params);
        $response['total'] = $stmt->fetchColumn();
        
        // Fetch data
        $stmt = $pdoApi->prepare("
            SELECT f.*, fr.unit_inv_min as pref_min, fr.unit_inv_max as pref_max, fr.state as pref_state, fr.preferred_cities as pref_city 
            FROM franchisor_business_details f 
            LEFT JOIN fi_crm.franchisor_requirements fr ON f.fran_detail_id = fr.franchisor_id
            WHERE $where 
            ORDER BY f.fran_detail_id DESC LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $response['data'] = $stmt->fetchAll();
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'Invalid type';
}

echo json_encode($response);
?>
