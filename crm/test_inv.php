<?php
session_start();
$_SESSION['role']='admin';
$_SESSION['user_id']=1;
require 'config.php';
require 'api_client.php';
$_GET['page'] = 1;
$_GET['search'] = '';
ob_start();
require 'investors.php';
file_put_contents('test_inv.html', ob_get_clean());
?>
