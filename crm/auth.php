<?php
// crm/auth.php
session_start();
require_once 'db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function checkRole($allowedRoles) {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    if (!in_array($_SESSION['role'], (array)$allowedRoles)) {
        echo "Access Denied. You do not have permission to view this page.";
        exit;
    }
}
?>
