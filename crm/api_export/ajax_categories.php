<?php
// api_export/ajax_categories.php
// This file now uses the LOCAL CRM database table 'category_final'
require_once '../db.php';

$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;

try {
    // Fetch from local CRM database
    $stmt = $pdoCrm->prepare("SELECT catid, catname FROM category_final WHERE parent_id = ? ORDER BY catname ASC");
    $stmt->execute([$parent_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    header("Access-Control-Allow-Origin: *");
    echo json_encode($categories);
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
?>