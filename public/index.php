<?php
$page = $_GET['page'] ?? 'home';

switch($page) {
    case 'home':
        include __DIR__ . '/../views/subscriptions.php';
        break;
    case 'subscriptions':
        include __DIR__ . '/../views/purchase.php';
        break;
    case 'purchase':
        require_once __DIR__ . '/../views/purchase.php';
       
        break;
    case 'purchase-success':
        require_once __DIR__ . '/../views/purchase-success.php';
      
        break;
    default:
        echo 'hello';
}
?>