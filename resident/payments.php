<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is a resident
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'resident') {
    header('Location: http://localhost/syndicplatform/public/login.php');
    exit();
}

// Get resident information
try {
    $stmt = $conn->prepare("
        SELECT m.*, ap.type, ap.floor, ap.number,
               r.name as building_name, r.address, c.city_name,
               r.id_residence
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        JOIN residence r ON r.id_residence = ap.id_residence
        JOIN city c ON c.id_city = r.id_city
        WHERE m.id_member = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $resident_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get subscription info and monthly charges
    $stmt = $conn->prepare("
        SELECT s.name_subscription, s.price_subscription, s.description,
               ams.amount, ams.date_payment
        FROM subscription s
        JOIN admin_member_subscription ams ON ams.id_subscription = s.id_subscription
        WHERE ams.id_member = ?
        ORDER BY ams.date_payment DESC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $subscription_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get payment history from member_payments table
    $stmt = $conn->prepare("
        SELECT mp.*, 
               payer.full_name as payer_name, 
               receiver.full_name as receiver_name,
               DATE_FORMAT(mp.month_paid, '%M %Y') as formatted_month,
               DATE_FORMAT(mp.date_payment, '%d/%m/%Y') as formatted_payment_date
        FROM member_payments mp
        JOIN member payer ON payer.id_member = mp.id_payer
        JOIN member receiver ON receiver.id_member = mp.id_receiver
        WHERE mp.id_payer = ?
        ORDER BY mp.date_payment DESC, mp.month_paid DESC
        LIMIT 20
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $payment_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get admin/receiver for payments (who to pay to)
    $stmt = $conn->prepare("
        SELECT DISTINCT a.id_admin, a.name as admin_name, a.email as admin_email,
               m_admin.id_member as admin_member_id
        FROM admin a
        JOIN admin_member_link aml ON aml.id_admin = a.id_admin
        JOIN member m ON m.id_member = aml.id_member
        JOIN apartment ap ON ap.id_member = m.id_member
        LEFT JOIN member m_admin ON m_admin.email = a.email
        WHERE ap.id_residence = ?
        LIMIT 1
    ");
    $stmt->execute([$resident_info['id_residence'] ?? 0]);
    $payment_receiver = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate monthly charges (from subscription or default)
    $monthly_charges = $subscription_info ? $subscription_info['price_subscription'] : 750;
    
    // Calculate current balance (simplified - in real app would be more complex)
    $current_balance = 0;
    
    // Get last payment date
    $last_payment_date = !empty($payment_history) ? $payment_history[0]['date_payment'] : null;
    
    // Calculate next payment due (assuming monthly payments on 1st of month)
    $next_payment_due = date('Y-m-01', strtotime('+1 month'));
    
    // Check if current month payment is made
    $current_month_paid = false;
    $current_month = date('Y-m-01');
    foreach($payment_history as $payment) {
        if($payment['month_paid'] === $current_month) {
            $current_month_paid = true;
            break;
        }
    }

} catch(PDOException $e) {
    error_log($e->getMessage());
    $resident_info = null;
    $subscription_info = null;
    $payment_history = [];
    $payment_receiver = null;
    $monthly_charges = 0;
    $current_balance = 0;
    $last_payment_date = null;
    $next_payment_due = date('Y-m-01');
    $current_month_paid = false;
}

// Handle payment action
$payment_message = '';
if (isset($_POST['make_payment'])) {
    try {
        $payment_method = $_POST['payment_method'] ?? 'bank_transfer';
        $amount = $_POST['amount'] ?? $monthly_charges;
        $month_to_pay = $_POST['month_to_pay'] ?? date('Y-m-01');
        
        // Get receiver ID (admin or another member)
        $receiver_id = $payment_receiver['admin_member_id'] ?? 1; // Default to admin if no specific receiver
        
        // Insert payment record
        $stmt = $conn->prepare("
            INSERT INTO member_payments (id_payer, id_receiver, date_payment, month_paid) 
            VALUES (?, ?, NOW(), ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $receiver_id, $month_to_pay]);
        
        // Update subscription payment record if exists
        if($subscription_info) {
            $stmt = $conn->prepare("
                UPDATE admin_member_subscription 
                SET date_payment = NOW(), amount = ? 
                WHERE id_member = ?
            ");
            $stmt->execute([$amount, $_SESSION['user_id']]);
        }
        
        $payment_message = '<div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            Paiement de ' . number_format($amount) . ' DH effectué avec succès ! Merci pour votre paiement.
        </div>';
        
        // Refresh page to show updated data
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
        exit();
        
    } catch(PDOException $e) {
        error_log($e->getMessage());
        $payment_message = '<div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i> 
            Erreur lors du traitement du paiement. Veuillez réessayer.
        </div>';
    }
}

// Success message from redirect
if(isset($_GET['success'])) {
    $payment_message = '<div class="alert alert-success">
        <i class="fas fa-check-circle"></i> 
        Paiement effectué avec succès ! Vous recevrez une confirmation par email.
    </div>';
}

$page_title = "Mes Paiements - Syndic Way";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
       * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background-color: #f8fafc;
    color: #334155;
    line-height: 1.5;
}
.container {
    display: flex;
    height: 100vh;
}
/* Sidebar */
.sidebar {
    width: 240px;
    background: white;
    border-right: 1px solid #e2e8f0;
    padding: 20px 0;
    display: flex;
    flex-direction: column;
}
.logo {
    padding: 0 20px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 20px;
}
.logo-icon {
    width: 32px;
    height: 32px;
    background: #FFCB32; /* Changed to #FFCB32 */
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}
.logo-text {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}
.nav-section {
    padding: 0 20px;
    margin-bottom: 30px;
}
.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    color: #64748b;
    text-decoration: none;
    margin-bottom: 4px;
    transition: all 0.2s;
    font-size: 14px;
}
.nav-item:hover,
.nav-item.active {
    background: #f1f5f9;
    color: #FFCB32; /* Changed to #FFCB32 */
}
.nav-item i {
    width: 16px;
    text-align: center;
}
.storage-info {
    margin-top: auto;
    padding: 0 20px;
}
.storage-bar {
    width: 100%;
    height: 4px;
    background: #e2e8f0;
    border-radius: 2px;
    overflow: hidden;
    margin: 10px 0;
}
.storage-fill {
    height: 100%;
    background: #FFCB32; /* Changed to #FFCB32 */
    width: <?php echo min(($monthly_charges / 1000) * 100, 100); ?>%;
    border-radius: 2px;
    transition: width 0.5s ease;
}
.storage-text {
    font-size: 12px;
    color: #64748b;
}
/* Main Content */
.main-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}
/* Header */
.header {
    background: white;
    border-bottom: 1px solid #e2e8f0;
    padding: 0 24px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.header-nav {
    display: flex;
    align-items: center;
    gap: 24px;
}
.header-nav a {
    color: #64748b;
    text-decoration: none;
    font-size: 14px;
    padding: 8px 12px;
    border-radius: 6px;
    transition: all 0.2s;
}
.header-nav a.active {
    color: #FFCB32; /* Changed to #FFCB32 */
    background: #FFF0C3; /* Changed to #FFF0C3 */
}
.header-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}
.search-box {
    position: relative;
}
.search-box input {
    width: 300px;
    padding: 8px 12px 8px 36px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    background: #f8fafc;
}
.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}
.header-user {
    display: flex;
    align-items: center;
    gap: 8px;
}
.user-avatar {
    width: 32px;
    height: 32px;
    background: #FFCB32; /* Changed to #FFCB32 */
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 14px;
    font-weight: 500;
}
/* Alert Messages */
.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin: 16px 24px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}
.alert-success {
    background: #dcfce7;
    color: #16a34a;
    border: 1px solid #bbf7d0;
}
.alert-error {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
/* Quick Access Section */
.quick-access {
    padding: 24px;
    background: white;
    border-bottom: 1px solid #e2e8f0;
}
.quick-access-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.quick-access-title {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}
.quick-access-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
.quick-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: all 0.2s;
    cursor: pointer;
}
.quick-card:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: translateY(-1px);
}
.quick-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    font-size: 20px;
    color: white;
}
.quick-card.charges .quick-card-icon { background: #FFCB32; } /* Changed to #FFCB32 */
.quick-card.balance .quick-card-icon { background: #10b981; }
.quick-card.payments .quick-card-icon { background: #f59e0b; }
.quick-card.status .quick-card-icon { background: #8b5cf6; }
.quick-card-title {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 4px;
}
.quick-card-stats {
    font-size: 12px;
    color: #64748b;
}
.quick-card-count {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
}
/* Content Area */
.content-area {
    flex: 1;
    display: flex;
}
.main-panel {
    flex: 1;
    padding: 24px;
}
/* Breadcrumb */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    color: #64748b;
}
.breadcrumb a {
    color: #64748b;
    text-decoration: none;
}
.breadcrumb a:hover {
    color: #FFCB32; /* Changed to #FFCB32 */
}
/* Table Header */
.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.view-options {
    display: flex;
    gap: 8px;
}
.view-btn {
    width: 32px;
    height: 32px;
    border: 1px solid #e2e8f0;
    background: white;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}
.view-btn.active {
    background: #FFCB32; /* Changed to #FFCB32 */
    color: white;
    border-color: #FFCB32; /* Changed to #FFCB32 */
}
.add-btn {
    background: #FFCB32; /* Changed to #FFCB32 */
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}
.add-btn:hover {
    background: #f59e0b;
}
/* Data Table */
.data-table {
    width: 100%;
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}
.table-header-row {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}
.table-header-row th {
    padding: 12px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 500;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.table-row {
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.2s;
}
.table-row:hover {
    background: #f8fafc;
}
.table-row td {
    padding: 16px;
    font-size: 14px;
    color: #1e293b;
}
.table-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    color: white;
    font-size: 14px;
}
.file-item {
    display: flex;
    align-items: center;
}
.file-info {
    flex: 1;
}
.file-name {
    font-weight: 500;
    color: #1e293b;
}
.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
}
.status-active {
    background: #dcfce7;
    color: #16a34a;
}
.status-pending {
    background: #fef3c7;
    color: #d97706;
}
.status-overdue {
    background: #fef2f2;
    color: #dc2626;
}
/* Activity Panel */
.activity-panel {
    width: 320px;
    background: white;
    border-left: 1px solid #e2e8f0;
    padding: 24px;
}
.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.activity-title {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}
.close-btn {
    width: 24px;
    height: 24px;
    border: none;
    background: none;
    color: #64748b;
    cursor: pointer;
}
.activity-tabs {
    display: flex;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 20px;
}
.activity-tab {
    padding: 8px 12px;
    font-size: 14px;
    color: #64748b;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: all 0.2s;
}
.activity-tab.active {
    color: #FFCB32; /* Changed to #FFCB32 */
    border-bottom-color: #FFCB32; /* Changed to #FFCB32 */
}
.activity-item {
    display: flex;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 12px;
    color: white;
}
.activity-icon.payment { background: #FFCB32; } /* Changed to #FFCB32 */
.activity-icon.reminder { background: #f59e0b; }
.activity-icon.receipt { background: #3b82f6; }
.activity-content {
    flex: 1;
}
.activity-text {
    font-size: 14px;
    color: #1e293b;
    margin-bottom: 4px;
}
.activity-time {
    font-size: 12px;
    color: #64748b;
}
.activity-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
}
.tag {
    background: #FFF0C3; /* Changed to #FFF0C3 */
    color: #FFCB32; /* Changed to #FFCB32 */
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
}
/* Payment Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}
.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    overflow-x: hidden;
}
.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h2 {
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}
.modal-body {
    padding: 24px;
}
.payment-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin: 20px 0;
}
.payment-method {
    border: 2px solid #e2e8f0;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}
.payment-method:hover {
    border-color: #FFCB32; /* Changed to #FFCB32 */
    background: rgba(255, 203, 50, 0.05); /* Lighter shade of #FFCB32 */
}
.payment-method.selected {
    border-color: #FFCB32; /* Changed to #FFCB32 */
    background: rgba(255, 203, 50, 0.1); /* Lighter shade of #FFCB32 */
}
.payment-method input[type="radio"] {
    position: absolute;
    opacity: 0;
}
.payment-method i {
    font-size: 32px;
    color: #FFCB32; /* Changed to #FFCB32 */
    margin-bottom: 12px;
}
.payment-method h4 {
    margin-bottom: 8px;
    color: #1e293b;
}
.payment-method p {
    font-size: 12px;
    color: #64748b;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #374151;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #FFCB32; /* Changed to #FFCB32 */
    box-shadow: 0 0 0 3px rgba(255, 203, 50, 0.1); /* Lighter shade of #FFCB32 */
}
.modal-actions {
    padding: 20px 24px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}
.btn {
    padding: 8px 16px;
    border-radius: 6px;
    border: 1px solid transparent;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-primary {
    background: #FFCB32; /* Changed to #FFCB32 */
    color: white;
    border-color: #FFCB32; /* Changed to #FFCB32 */
}
.btn-primary:hover {
    background: #f59e0b;
    border-color: #f59e0b;
}
.btn-secondary {
    background: #f8fafc;
    color: #374151;
    border-color: #d1d5db;
}
.btn-secondary:hover {
    background: #f1f5f9;
}
.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}
/* Responsive */
@media (max-width: 768px) {
    .container {
        flex-direction: column;
    }
    .sidebar {
        width: 100%;
        height: auto;
    }
    .content-area {
        flex-direction: column;
    }
    .activity-panel {
        width: 100%;
    }
    .quick-access-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .payment-methods {
        grid-template-columns: 1fr;
    }
}
/* Subscription Info */
.subscription-info {
    background: linear-gradient(135deg, #FFCB32, #FFF0C3); /* Changed to #FFCB32 and #FFF0C3 */
    color: white;
    padding: 20px;
    border-radius: 12px;
    margin: 24px;
}
.subscription-info h4 {
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.subscription-info p {
    margin: 4px 0;
    opacity: 0.9;
}
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <div class="logo-icon">SW</div>
                <div class="logo-text">Syndic Way</div>
            </div>

            <div class="nav-section">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-th-large"></i>
                    Tableau de Bord
                </a>
                <a href="payments.php" class="nav-item active">
                    <i class="fas fa-credit-card"></i>
                    Paiements
                </a>
                <a href="messages.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    Messages
                </a>
                <a href="announcements.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    Annonces
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-cog"></i>
                    Mon Profil
                </a>
                <a href="http://localhost/syndicplatform/public/logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </div>

            <div class="storage-info">
                <div class="storage-text">Charges mensuelles</div>
                <div class="storage-bar">
                    <div class="storage-fill"></div>
                </div>
                <div class="storage-text"><?php echo number_format($monthly_charges); ?> DH / mois</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Alert Messages -->
            <?php echo $payment_message; ?>

            <!-- Header -->
            <div class="header">
                <div class="header-nav">
                    <a href="#" class="active">Paiements</a>
                    <a href="#">Historique</a>
                    <a href="#">Factures</a>
                    <a href="#">Paramètres</a>
                </div>

                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Rechercher un paiement...">
                    </div>
                    <i class="fas fa-bell" style="color: #64748b; cursor: pointer;"></i>
                    <div class="header-user">
                        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'R', 0, 1)); ?></div>
                    </div>
                </div>
            </div>

            <!-- Subscription Info -->
            <?php if ($subscription_info): ?>
            <div class="subscription-info">
                <h4><i class="fas fa-star"></i> Votre forfait: <?php echo htmlspecialchars($subscription_info['name_subscription']); ?></h4>
                <p><?php echo htmlspecialchars($subscription_info['description']); ?></p>
                <p><strong>Montant mensuel: <?php echo number_format($subscription_info['price_subscription']); ?> DH</strong></p>
                <?php if($subscription_info['date_payment']): ?>
                    <p><small>Dernier paiement: <?php echo date('d/m/Y', strtotime($subscription_info['date_payment'])); ?></small></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Quick Access -->
            <div class="quick-access">
                <div class="quick-access-header">
                    <div class="quick-access-title">Résumé des Paiements</div>
                    <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card charges">
                        <div class="quick-card-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="quick-card-title">Charges Mensuelles</div>
                        <div class="quick-card-count"><?php echo number_format($monthly_charges); ?> DH</div>
                        <div class="quick-card-stats">Montant fixe</div>
                    </div>

                    <div class="quick-card balance">
                        <div class="quick-card-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="quick-card-title">Solde Actuel</div>
                        <div class="quick-card-count"><?php echo number_format($current_balance); ?> DH</div>
                        <div class="quick-card-stats">À jour</div>
                    </div>

                    <div class="quick-card payments">
                        <div class="quick-card-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="quick-card-title">Paiements Effectués</div>
                        <div class="quick-card-count"><?php echo count($payment_history); ?></div>
                        <div class="quick-card-stats">Au total</div>
                    </div>

                    <div class="quick-card status">
                        <div class="quick-card-icon">
                            <i class="fas fa-<?php echo $current_month_paid ? 'check-circle' : 'clock'; ?>"></i>
                        </div>
                        <div class="quick-card-title">Statut du Mois</div>
                        <div class="quick-card-count"><?php echo $current_month_paid ? 'Payé' : 'En attente'; ?></div>
                        <div class="quick-card-stats"><?php echo date('F Y'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div class="main-panel">
                    <!-- Breadcrumb -->
                    <div class="breadcrumb">
                        <a href="#">Accueil</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <a href="#">Résidents</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Mes Paiements</span>
                    </div>

                    <!-- Table Header -->
                    <div class="table-header">
                        <div class="view-options">
                            <div class="view-btn active">
                                <i class="fas fa-th"></i>
                            </div>
                            <div class="view-btn">
                                <i class="fas fa-list"></i>
                            </div>
                        </div>
                        <?php if (!$current_month_paid): ?>
                        <button class="add-btn" onclick="openPaymentModal()">
                            <i class="fas fa-plus"></i>
                            Effectuer un Paiement
                        </button>
                        <?php endif; ?>
                    </div>

                    <!-- Data Table -->
                    <table class="data-table">
                        <thead class="table-header-row">
                            <tr>
                                <th>Paiement</th>
                                <th>Bénéficiaire</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($payment_history)): ?>
                                <?php foreach ($payment_history as $payment): ?>
                                <tr class="table-row">
                                    <td>
                                        <div class="file-item">
                                            <div class="table-icon" style="background: #10b981;">
                                                <i class="fas fa-credit-card"></i>
                                            </div>
                                            <div class="file-info">
                                                <div class="file-name">
                                                    <?php 
                                                    if($payment['month_paid']) {
                                                        echo 'Charges ' . date('F Y', strtotime($payment['month_paid']));
                                                    } else {
                                                        echo 'Paiement général';
                                                    }
                                                    ?>
                                                </div>
                                                <div style="font-size: 12px; color: #64748b;">Paiement mensuel</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['receiver_name']); ?></td>
                                    <td><?php echo number_format($monthly_charges); ?> DH</td>
                                    <td>
                                        <span class="status-badge status-active">
                                            <i class="fas fa-check-circle"></i> Payé
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($payment['date_payment'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-secondary" onclick="downloadReceipt(<?php echo $payment['id_payer']; ?>)">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Current month payment if not paid -->
                            <?php if (!$current_month_paid): ?>
                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #f59e0b;">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Charges <?php echo date('F Y'); ?></div>
                                            <div style="font-size: 12px; color: #64748b;">Paiement en attente</div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($payment_receiver['admin_name'] ?? 'Administration'); ?></td>
                                <td><?php echo number_format($monthly_charges); ?> DH</td>
                                <td>
                                    <span class="status-badge status-pending">
                                        <i class="fas fa-clock"></i> En attente
                                    </span>
                                </td>
                                <td>Échéance: <?php echo date('d/m/Y'); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="openPaymentModal()">
                                        <i class="fas fa-credit-card"></i> Payer
                                    </button>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <!-- Services and maintenance -->
                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #8b5cf6;">
                                            <i class="fas fa-tools"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Services & Maintenance</div>
                                            <div style="font-size: 12px; color: #64748b;">Gestion de l'immeuble</div>
                                        </div>
                                    </div>
                                </td>
                                <td>Syndic de copropriété</td>
                                <td>Inclus</td>
                                <td>
                                    <span class="status-badge status-active">
                                        <i class="fas fa-check-circle"></i> Actif
                                    </span>
                                </td>
                                <td>Services continus</td>
                                <td>
                                    <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                                </td>
                            </tr>

                            <!-- Bank details -->
                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #ef4444;">
                                            <i class="fas fa-university"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Coordonnées Bancaires</div>
                                            <div style="font-size: 12px; color: #64748b;">Pour virements</div>
                                        </div>
                                    </div>
                                </td>
                                <td>Attijariwafa Bank</td>
                                <td>IBAN disponible</td>
                                <td>
                                    <span class="status-badge status-active">
                                        <i class="fas fa-check-circle"></i> Actif
                                    </span>
                                </td>
                                <td>Informations</td>
                                <td>
                                    <button class="btn btn-sm btn-secondary" onclick="viewBankDetails()">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Activity Panel -->
                <div class="activity-panel">
                    <div class="activity-header">
                        <div class="activity-title">Activité</div>
                        <button class="close-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="activity-tabs">
                        <div class="activity-tab active">Activité</div>
                        <div class="activity-tab">Rappels</div>
                        <div class="activity-tab">Reçus</div>
                    </div>

                    <?php if (!empty($payment_history)): ?>
                        <?php foreach (array_slice($payment_history, 0, 5) as $payment): ?>
                        <div class="activity-item">
                            <div class="activity-icon payment">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">Paiement effectué</div>
                                <div class="activity-time"><?php echo date('d/m/Y à H:i', strtotime($payment['date_payment'])); ?></div>
                                <div class="activity-meta">
                                    <div class="tag"><?php echo number_format($monthly_charges); ?> DH</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!$current_month_paid): ?>
                    <div class="activity-item">
                        <div class="activity-icon reminder">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Rappel de paiement</div>
                            <div class="activity-time">Aujourd'hui</div>
                            <div class="activity-meta">
                                <div class="tag">À payer</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="activity-item">
                        <div class="activity-icon receipt">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Reçu disponible</div>
                            <div class="activity-time">Dernier paiement</div>
                            <div class="activity-meta">
                                <div class="tag">PDF</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-credit-card"></i>
                    Effectuer un Paiement
                </h2>
                <button class="close-btn" onclick="closePaymentModal()">&times;</button>
            </div>
            
            <form method="POST" id="paymentForm">
                <div class="modal-body">
                    <input type="hidden" name="amount" value="<?php echo $monthly_charges; ?>">
                    <input type="hidden" name="month_to_pay" value="<?php echo date('Y-m-01'); ?>">
                    
                    <div class="form-group">
                        <label>Période de paiement</label>
                        <div style="padding: 12px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
                            <strong>Charges de <?php echo date('F Y'); ?></strong><br>
                            <small>Montant: <?php echo number_format($monthly_charges); ?> DH</small>
                            <?php if($payment_receiver): ?>
                                <br><small>Bénéficiaire: <?php echo htmlspecialchars($payment_receiver['admin_name']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Méthode de paiement</label>
                        <div class="payment-methods">
                            <label class="payment-method selected">
                                <input type="radio" name="payment_method" value="bank_transfer" checked>
                                <i class="fas fa-university"></i>
                                <h4>Virement bancaire</h4>
                                <p>Paiement sécurisé par virement</p>
                            </label>
                            
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="credit_card">
                                <i class="fas fa-credit-card"></i>
                                <h4>Carte bancaire</h4>
                                <p>Paiement instantané par carte</p>
                            </label>
                            
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="check">
                                <i class="fas fa-money-check"></i>
                                <h4>Chèque</h4>
                                <p>Paiement par chèque postal</p>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Informations bancaires (pour virement)</label>
                        <div style="padding: 12px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 13px;">
                            <strong>Banque:</strong> Attijariwafa Bank<br>
                            <strong>IBAN:</strong> MA64 0151 0000 0000 0012 3456<br>
                            <strong>Swift:</strong> BMCEMAMC<br>
                            <strong>Bénéficiaire:</strong> <?php echo htmlspecialchars($payment_receiver['admin_name'] ?? 'Administration'); ?>
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" name="make_payment" class="btn btn-primary">
                        <i class="fas fa-credit-card"></i> Confirmer le Paiement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bank Details Modal -->
    <div id="bankDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-university"></i>
                    Coordonnées Bancaires
                </h2>
                <button class="close-btn" onclick="closeBankDetailsModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Informations pour virement</label>
                    <div style="padding: 20px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                        <div style="margin-bottom: 12px;"><strong>Banque:</strong> Attijariwafa Bank</div>
                        <div style="margin-bottom: 12px;"><strong>IBAN:</strong> MA64 0151 0000 0000 0012 3456</div>
                        <div style="margin-bottom: 12px;"><strong>Code Swift:</strong> BMCEMAMC</div>
                        <div style="margin-bottom: 12px;"><strong>Bénéficiaire:</strong> <?php echo htmlspecialchars($payment_receiver['admin_name'] ?? 'Administration'); ?></div>
                        <div style="margin-bottom: 12px;"><strong>Référence:</strong> <?php echo htmlspecialchars($resident_info['full_name'] ?? 'Résident'); ?> - App. <?php echo $resident_info['number'] ?? 'N/A'; ?></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Instructions importantes</label>
                    <ul style="margin: 0; padding-left: 20px; color: #64748b;">
                        <li>Indiquez toujours votre nom et numéro d'appartement en référence</li>
                        <li>Conservez le reçu de virement comme justificatif</li>
                        <li>Les paiements sont traités sous 1-2 jours ouvrables</li>
                        <li>En cas de problème, contactez l'administration</li>
                    </ul>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeBankDetailsModal()">
                    <i class="fas fa-times"></i> Fermer
                </button>
                <button type="button" class="btn btn-primary" onclick="copyBankDetails()">
                    <i class="fas fa-copy"></i> Copier les Détails
                </button>
            </div>
        </div>
    </div>

    <script>
        // Payment method selection
        document.querySelectorAll('.payment-method input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.payment-method').forEach(method => {
                    method.classList.remove('selected');
                });
                this.closest('.payment-method').classList.add('selected');
            });
        });

        // Modal functions
        function openPaymentModal() {
            document.getElementById('paymentModal').classList.add('show');
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('show');
        }

        function viewBankDetails() {
            document.getElementById('bankDetailsModal').classList.add('show');
        }

        function closeBankDetailsModal() {
            document.getElementById('bankDetailsModal').classList.remove('show');
        }

        function copyBankDetails() {
            const details = `Banque: Attijariwafa Bank
IBAN: MA64 0151 0000 0000 0012 3456
Swift: BMCEMAMC
Bénéficiaire: <?php echo htmlspecialchars($payment_receiver['admin_name'] ?? 'Administration'); ?>
Référence: <?php echo htmlspecialchars($resident_info['full_name'] ?? 'Résident'); ?> - App. <?php echo $resident_info['number'] ?? 'N/A'; ?>`;

            navigator.clipboard.writeText(details).then(() => {
                alert('Coordonnées bancaires copiées dans le presse-papiers !');
            });
        }

        function downloadReceipt(paymentId) {
            alert('Téléchargement du reçu pour le paiement #' + paymentId + '\nCette fonctionnalité sera bientôt disponible.');
        }

        // View toggle functionality
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Activity tabs
        document.querySelectorAll('.activity-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.activity-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Close activity panel
        document.querySelector('.close-btn').addEventListener('click', function() {
            document.querySelector('.activity-panel').style.display = 'none';
            document.querySelector('.main-panel').style.marginRight = '0';
        });

        // Quick card clicks
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('click', function() {
                const cardType = this.classList[1];
                console.log(`Navigating to ${cardType} section`);
            });
        });

        // Confirm payment submission
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const amount = <?php echo $monthly_charges; ?>;
            const method = document.querySelector('input[name="payment_method"]:checked').value;
            
            let methodText = '';
            switch(method) {
                case 'bank_transfer': methodText = 'virement bancaire'; break;
                case 'credit_card': methodText = 'carte bancaire'; break;
                case 'check': methodText = 'chèque'; break;
                default: methodText = 'méthode sélectionnée';
            }
            
            if(!confirm(`Confirmer le paiement de ${amount} DH par ${methodText} ?`)) {
                e.preventDefault();
            }
        });

        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });

            // Storage animation on load
            const storageFill = document.querySelector('.storage-fill');
            const originalWidth = storageFill.style.width;
            storageFill.style.width = '0%';
            setTimeout(() => {
                storageFill.style.width = originalWidth;
            }, 500);
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const paymentModal = document.getElementById('paymentModal');
            const bankModal = document.getElementById('bankDetailsModal');
            
            if (event.target === paymentModal) {
                closePaymentModal();
            }
            if (event.target === bankModal) {
                closeBankDetailsModal();
            }
        }
    </script>
</body>
</html>