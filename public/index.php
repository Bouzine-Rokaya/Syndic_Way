<?php
$page = $_GET['page'] ?? 'home';

switch ($page) {
    case 'home':
        include __DIR__ . '/../views/home.php';
        break;
    case 'subscriptions':
        include __DIR__ . '/../views/subscriptions.php';
        break;
    case 'purchase':
        include __DIR__ . '/../views/purchase.php';
        break;
    case 'purchase-success':
        require_once __DIR__ . '/../views/purchase-success.php';
        break;
    case 'service':
    require_once __DIR__ . '/../views/service.php';
    break;
 

    case 'contact':
    require_once __DIR__ . '/../views/contact.php';
    break;

    default:
        echo 'hello';
}
?>