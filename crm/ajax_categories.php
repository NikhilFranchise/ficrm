<?php
require_once 'auth.php';
require_once 'api_db.php';

if (!isLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : 0;

$stmt = $pdoApi->prepare("SELECT catid, catname FROM category_final WHERE parent_id = ? ORDER BY catname ASC");
$stmt->execute([$parent_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($categories);
exit;
