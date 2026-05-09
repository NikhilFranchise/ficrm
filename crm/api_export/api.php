<?php
// api_export/api.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once 'api_db.php';

// This file handles List, Single Details, Bulk Lookups, and Categories
$action = $_GET['action'] ?? 'list';
$type = $_GET['type'] ?? 'investor';
$id = $_GET['id'] ?? (isset($_GET['inv_det_id']) ? $_GET['inv_det_id'] : (isset($_GET['fran_detail_id']) ? $_GET['fran_detail_id'] : ''));
$ids_raw = $_GET['ids'] ?? '';

$response = ['status' => 'success', 'data' => [], 'total' => 0];

try {
    if ($action === 'list') {
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';

        if ($type === 'investor') {
            $where = "1=1";
            $params = [];
            if ($search) {
                $where .= " AND (d.inv_city LIKE :s1 OR d.inv_state LIKE :s2 OR d.service_company_name LIKE :s3 OR d.investor_id LIKE :s4)";
                $params[':s1'] = $params[':s2'] = $params[':s3'] = $params[':s4'] = "%$search%";
            }
            if (isset($_GET['state']) && $_GET['state'] !== '') {
                $where .= " AND d.inv_state LIKE :state";
                $params[':state'] = "%" . $_GET['state'] . "%";
            }
            if (isset($_GET['city']) && $_GET['city'] !== '') {
                $where .= " AND d.inv_city LIKE :city";
                $params[':city'] = "%" . $_GET['city'] . "%";
            }
            if (isset($_GET['inv_min']) && $_GET['inv_min'] !== '') {
                $where .= " AND d.investment_max >= :inv_min";
                $params[':inv_min'] = $_GET['inv_min'];
            }
            if (isset($_GET['inv_max']) && $_GET['inv_max'] !== '') {
                $where .= " AND d.investment_min <= :inv_max";
                $params[':inv_max'] = $_GET['inv_max'];
            }

            $stmt = $pdoApi->prepare("SELECT COUNT(*) FROM investor_details d WHERE $where");
            $stmt->execute($params);
            $response['total'] = (int) $stmt->fetchColumn();

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
            if (isset($_GET['fid']) && $_GET['fid'] !== '') {
                $where .= " AND f.franchisor_id LIKE :fid";
                $params[':fid'] = "%" . $_GET['fid'] . "%";
            }
            if (isset($_GET['state']) && $_GET['state'] !== '') {
                $where .= " AND f.state LIKE :state";
                $params[':state'] = "%" . $_GET['state'] . "%";
            }
            if (isset($_GET['city']) && $_GET['city'] !== '') {
                $where .= " AND f.city LIKE :city";
                $params[':city'] = "%" . $_GET['city'] . "%";
            }
            if (isset($_GET['inv_min']) && $_GET['inv_min'] !== '') {
                $where .= " AND f.unit_inv_max >= :inv_min";
                $params[':inv_min'] = $_GET['inv_min'];
            }
            if (isset($_GET['inv_max']) && $_GET['inv_max'] !== '') {
                $where .= " AND f.unit_inv_min <= :inv_max";
                $params[':inv_max'] = $_GET['inv_max'];
            }

            $stmt = $pdoApi->prepare("SELECT COUNT(*) FROM franchisor_business_details f WHERE $where");
            $stmt->execute($params);
            $response['total'] = (int) $stmt->fetchColumn();

            $stmt = $pdoApi->prepare("SELECT f.* FROM franchisor_business_details f WHERE $where ORDER BY f.fran_detail_id DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $response['data'] = $stmt->fetchAll();
        }
        $response['page'] = $page;
        $response['limit'] = $limit;

    } elseif ($action === 'validate') {
        if (empty($id)) {
            $response['status'] = 'error';
            $response['message'] = 'ID is required';
        } else {
            if ($type === 'investor') {
                $stmt = $pdoApi->prepare("SELECT inv_det_id, investor_id, service_company_name FROM investor_details WHERE inv_det_id = ? OR investor_id = ? LIMIT 1");
                $stmt->execute([$id, $id]);
                $data = $stmt->fetch();
                if ($data) {
                    $response['exists'] = true;
                    $response['data'] = $data;
                } else {
                    $response['exists'] = false;
                }
            } elseif ($type === 'franchisor') {
                // For Franchisors, we now use the local CRM table as requested
                require_once '../db.php';
                $stmt = $pdoCrm->prepare("SELECT id, franchisor_id, brand_name, company_name FROM crm_franchisors WHERE id = ? OR franchisor_id = ? LIMIT 1");
                $stmt->execute([$id, $id]);
                $data = $stmt->fetch();
                if ($data) {
                    $response['exists'] = true;
                    $response['data'] = $data;
                } else {
                    $response['exists'] = false;
                }
            }
        }
    } elseif ($action === 'details' || (isset($_GET['id']) && !isset($_GET['action'])) || (isset($_GET['ids']) && !isset($_GET['action']))) {
        // Handle Single ID or Bulk IDs
        if ($id) {
            if ($type === 'investor') {
                $stmt = $pdoApi->prepare("
                    SELECT d.*, u.name as investor_name, u.mobile as investor_mobile 
                    FROM investor_details d 
                    LEFT JOIN user_accounts u ON d.investor_id = u.profile_str 
                    WHERE d.inv_det_id = ? OR d.investor_id = ?
                ");
                $stmt->execute([$id, $id]);
                $response['data'] = $stmt->fetch();
                $response['total'] = $response['data'] ? 1 : 0;
            } else {
                $stmt = $pdoApi->prepare("
                    SELECT f.*, c1.catname as main_category_name, c2.catname as category_name, c3.catname as sub_category_name
                    FROM franchisor_business_details f
                    LEFT JOIN category_final c1 ON f.ind_main_cat = c1.catid
                    LEFT JOIN category_final c2 ON f.ind_cat = c2.catid
                    LEFT JOIN category_final c3 ON f.ind_sub_cat = c3.catid
                    WHERE f.fran_detail_id = ? OR f.franchisor_id = ?
                ");
                $stmt->execute([$id, $id]);
                $response['data'] = $stmt->fetch();
                $response['total'] = $response['data'] ? 1 : 0;
            }
        } elseif ($ids_raw) {
            $ids = array_filter(explode(',', $ids_raw));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                if ($type === 'investor') {
                    $stmt = $pdoApi->prepare("
                        SELECT d.inv_det_id, d.investor_id, d.inv_city, d.inv_state, d.service_company_name, d.investment_max, u.name as investor_name, u.mobile as investor_mobile 
                        FROM investor_details d 
                        LEFT JOIN user_accounts u ON d.investor_id = u.profile_str 
                        WHERE d.inv_det_id IN ($placeholders) OR d.investor_id IN ($placeholders)
                    ");
                } else {
                    $stmt = $pdoApi->prepare("
                        SELECT f.fran_detail_id, f.franchisor_id, f.company_name, f.brand_name, f.city, f.state, f.ceo_name
                        FROM franchisor_business_details f
                        WHERE f.fran_detail_id IN ($placeholders) OR f.franchisor_id IN ($placeholders)
                    ");
                }
                // We need to pass the IDs twice if we use two IN clauses, but let's just use numeric check
                // for simplicity if possible. Or just use one column.
                // Actually, let's just repeat the array.
                $all_params = array_merge(array_values($ids), array_values($ids));
                $stmt->execute($all_params);
                $data = $stmt->fetchAll();
                $indexed = [];
                foreach ($data as $row) {
                    $key = ($type === 'investor' ? $row['inv_det_id'] : $row['fran_detail_id']);
                    $indexed[$key] = $row;
                }
                $response['data'] = $indexed;
                $response['total'] = count($indexed);
            }
        }
    } elseif ($action === 'categories') {
        require_once '../db.php';
        $parent_id = isset($_GET['parent_id']) ? (int) $_GET['parent_id'] : 0;
        $stmt = $pdoCrm->prepare("SELECT catid, catname FROM category_final WHERE parent_id = ? ORDER BY catname ASC");
        $stmt->execute([$parent_id]);
        $response['data'] = $stmt->fetchAll();
        $response['total'] = count($response['data']);
    }
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>