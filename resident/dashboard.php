<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is a resident
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'resident') {
    header('Location: ../public/login.php');
    exit();
}

$current_user = [
    'id' => $_SESSION['user_id'],
    'role' => $_SESSION['user_role'],
    'name' => $_SESSION['user_name'] ?? 'Résident',
    'email' => $_SESSION['user_email'] ?? ''
];

// Get resident information and building details
try {
    // Get resident details with apartment and building info
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

    // Get syndic information for this building
    $stmt = $conn->prepare("
        SELECT m.full_name as syndic_name, m.email as syndic_email, m.phone as syndic_phone
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        WHERE ap.id_residence = ? AND m.role = 2
        LIMIT 1
    ");
    $stmt->execute([$resident_info['id_residence'] ?? 0]);
    $syndic_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get total residents in building
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_residents
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        WHERE ap.id_residence = ?
    ");
    $stmt->execute([$resident_info['id_residence'] ?? 0]);
    $total_residents = $stmt->fetch()['total_residents'] ?? 0;

    // Get apartment count in building
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_apartments
        FROM apartment
        WHERE id_residence = ?
    ");
    $stmt->execute([$resident_info['id_residence'] ?? 0]);
    $total_apartments = $stmt->fetch()['total_apartments'] ?? 0;

    // Get subscription info
    $stmt = $conn->prepare("
        SELECT s.name_subscription, s.price_subscription, s.description
        FROM subscription s
        JOIN admin_member_subscription ams ON ams.id_subscription = s.id_subscription
        WHERE ams.id_member = ?
        ORDER BY ams.date_payment DESC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $subscription_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent payments
    $stmt = $conn->prepare("
        SELECT mp.*, receiver.full_name as receiver_name
        FROM member_payments mp
        JOIN member receiver ON receiver.id_member = mp.id_receiver
        WHERE mp.id_payer = ?
        ORDER BY mp.date_payment DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent announcements
    $stmt = $conn->prepare("
        SELECT ma.*, poster.full_name as poster_name
        FROM member_announcements ma
        JOIN member poster ON poster.id_member = ma.id_poster
        WHERE ma.id_receiver = ?
        ORDER BY ma.date_announcement DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent messages
    $stmt = $conn->prepare("
        SELECT mm.*, sender.full_name as sender_name
        FROM member_messages mm
        JOIN member sender ON sender.id_member = mm.id_sender
        WHERE mm.id_receiver = ?
        ORDER BY mm.date_message DESC
        LIMIT 3
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent notifications
    $stmt = $conn->prepare("
        SELECT mn.*, sender.full_name as sender_name
        FROM member_notifications mn
        JOIN member sender ON sender.id_member = mn.id_sender
        WHERE mn.id_receiver = ?
        ORDER BY mn.date_notification DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $stats = [
        'total_payments' => count($recent_payments),
        'total_announcements' => count($recent_announcements),
        'unread_messages' => count($recent_messages),
        'unread_notifications' => count($recent_notifications),
        'monthly_charges' => $subscription_info ? $subscription_info['price_subscription'] : 750,
        'payment_status' => !empty($recent_payments) ? 'paid' : 'pending'
    ];

} catch(PDOException $e) {
    error_log($e->getMessage());
    $resident_info = null;
    $syndic_info = null;
    $subscription_info = null;
    $total_residents = 0;
    $total_apartments = 0;
    $recent_payments = [];
    $recent_announcements = [];
    $recent_messages = [];
    $recent_notifications = [];
    $stats = [
        'total_payments' => 0,
        'total_announcements' => 0,
        'unread_messages' => 0,
        'unread_notifications' => 0,
        'monthly_charges' => 0,
        'payment_status' => 'unknown'
    ];
}

$page_title = "Espace Résident - Syndic Way";
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
    position: relative;
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
.notifications-badge {
    position: absolute;
    right: 8px;
    background: #ef4444;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 10px;
    font-weight: 500;
}
.resident-info {
    margin-top: auto;
    padding: 16px 20px;
    background: #f8fafc;
    border-radius: 8px;
    margin: 20px;
}
.resident-avatar {
    width: 40px;
    height: 40px;
    background: #FFCB32; /* Changed to #FFCB32 */
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    margin-bottom: 8px;
}
.resident-details {
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
    text-decoration: none;
    color: inherit;
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
.quick-card.payments .quick-card-icon { background: #FFCB32; } /* Changed to #FFCB32 */
.quick-card.messages .quick-card-icon { background: #3b82f6; }
.quick-card.announcements .quick-card-icon { background: #f59e0b; }
.quick-card.profile .quick-card-icon { background: #8b5cf6; }
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
.sharing-avatars {
    display: flex;
    align-items: center;
    gap: 4px;
}
.sharing-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #FFCB32; /* Changed to #FFCB32 */
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 10px;
    font-weight: 500;
    border: 2px solid white;
}
.sharing-count {
    font-size: 12px;
    color: #64748b;
    margin-left: 8px;
}
.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
}
.status-paid {
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
.activity-icon.announcement { background: #f59e0b; }
.activity-icon.message { background: #3b82f6; }
.activity-icon.notification { background: #8b5cf6; }
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
.empty-state {
    text-align: center;
    padding: 40px;
    color: #64748b;
}
.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.3;
}
/* Responsive */
@media (max-width: 1024px) {
    .content-area {
        flex-direction: column;
    }
    .activity-panel {
        width: 100%;
    }
}
@media (max-width: 768px) {
    .container {
        flex-direction: column;
    }
    .sidebar {
        width: 100%;
        height: auto;
    }
    .quick-access-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .search-box input {
        width: 200px;
    }
}
/* Enhanced animations */
.table-row {
    transition: all 0.2s ease;
}
.table-row:hover {
    transform: translateX(2px);
}
.quick-card {
    transition: all 0.3s ease;
}
.quick-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <div class="logo-icon">SR</div>
                <div class="logo-text">Syndic Resident</div>
            </div>

            <div class="nav-section">
                <a href="#" class="nav-item active">
                    <i class="fas fa-th-large"></i>
                    Tableau de Bord
                </a>
                <a href="payments.php" class="nav-item">
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
                <a href="../public/login.php?logout=1" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </div>

            <div class="resident-info">
                <div class="resident-avatar"><?php echo strtoupper(substr($current_user['name'], 0, 1)); ?></div>
                <div style="font-weight: 600; margin-bottom: 4px;"><?php echo htmlspecialchars($current_user['name']); ?></div>
                <?php if ($resident_info): ?>
                    <div class="resident-details">Apt. <?php echo $resident_info['number']; ?> - Étage <?php echo $resident_info['floor']; ?></div>
                    <div class="resident-details"><?php echo htmlspecialchars($resident_info['building_name'] ?? 'Mon Immeuble'); ?></div>
                <?php endif; ?>
                <div class="resident-details" style="margin-top: 8px;">
                    <?php echo $total_residents; ?> résidents • <?php echo $total_apartments; ?> appartements
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-nav">
                    <a href="#" class="active">Dashboard</a>
                    <a href="payments.php">Paiements</a>
                    <a href="messages.php">Messages</a>
                    <a href="announcements.php">Annonces</a>
                </div>

                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Rechercher...">
                    </div>
                    <i class="fas fa-bell" style="color: #64748b; cursor: pointer;"></i>
                    <div class="header-user">
                        <div class="user-avatar"><?php echo strtoupper(substr($current_user['name'], 0, 1)); ?></div>
                    </div>
                </div>
            </div>

            <!-- Quick Access -->
            <div class="quick-access">
                <div class="quick-access-header">
                    <div class="quick-access-title">Accès Rapide</div>
                    <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                </div>

                <div class="quick-access-grid">
                    <a href="payments.php" class="quick-card payments">
                        <div class="quick-card-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="quick-card-title">Mes Paiements</div>
                        <div class="quick-card-count"><?php echo number_format($stats['monthly_charges']); ?> DH</div>
                        <div class="quick-card-stats">Charges mensuelles</div>
                    </a>

                    <a href="messages.php" class="quick-card messages">
                        <div class="quick-card-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="quick-card-title">Messages</div>
                        <div class="quick-card-count"><?php echo $stats['unread_messages']; ?></div>
                        <div class="quick-card-stats">Messages non lus</div>
                    </a>

                    <a href="announcements.php" class="quick-card announcements">
                        <div class="quick-card-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="quick-card-title">Annonces</div>
                        <div class="quick-card-count"><?php echo $stats['total_announcements']; ?></div>
                        <div class="quick-card-stats">Nouvelles annonces</div>
                    </a>

                    <a href="profile.php" class="quick-card profile">
                        <div class="quick-card-icon">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <div class="quick-card-title">Mon Profil</div>
                        <div class="quick-card-count">✓</div>
                        <div class="quick-card-stats">Gérer mon compte</div>
                    </a>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div class="main-panel">
                    <!-- Breadcrumb -->
                    <div class="breadcrumb">
                        <a href="#">Accueil</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <a href="#">Espace Résident</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Mon Tableau de Bord</span>
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
                        <a href="payments.php?action=pay" class="add-btn">
                            <i class="fas fa-credit-card"></i>
                            Effectuer un Paiement
                        </a>
                    </div>

                    <!-- Resident Activities Table -->
                    <table class="data-table">
                        <thead class="table-header-row">
                            <tr>
                                <th>Type</th>
                                <th>Informations</th>
                                <th>Statut</th>
                                <th>Date/Période</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Apartment Info Row -->
                            <?php if ($resident_info): ?>
                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #10b981;">
                                            <i class="fas fa-home"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Mon Appartement</div>
                                            <div style="font-size: 12px; color: #64748b;">Informations du logement</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 14px; font-weight: 600;">
                                        Appartement <?php echo htmlspecialchars($resident_info['number']); ?> - Étage <?php echo $resident_info['floor']; ?>
                                    </div>
                                    <div style="font-size: 12px; color: #64748b;">
                                        <?php echo htmlspecialchars($resident_info['building_name']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #64748b;">
                                        <?php echo htmlspecialchars($resident_info['address']); ?>, <?php echo htmlspecialchars($resident_info['city_name']); ?>
                                    </div>
                                </td>
                                <td><span class="status-badge status-paid">Résident</span></td>
                                <td>Depuis <?php echo date('M Y', strtotime($resident_info['date_created'])); ?></td>
                                <td>
                                    <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <!-- Payment Status Row -->
                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #10b981;">
                                            <i class="fas fa-credit-card"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Charges Mensuelles</div>
                                            <div style="font-size: 12px; color: #64748b;">Paiement récurrent</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 14px; font-weight: 600;">
                                        <?php echo number_format($stats['monthly_charges']); ?> DH
                                    </div>
                                    <div style="font-size: 12px; color: #64748b;">
                                        Charges pour <?php echo date('F Y'); ?>
                                    </div>
                                    <?php if (!empty($recent_payments)): ?>
                                        <div style="font-size: 12px; color: #64748b;">
                                            Dernier paiement: <?php echo date('d/m/Y', strtotime($recent_payments[0]['date_payment'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $stats['payment_status']; ?>">
                                        <?php 
                                            $status_text = [
                                                'paid' => 'Payé',
                                                'pending' => 'En attente',
                                                'overdue' => 'En retard'
                                            ];
                                            echo $status_text[$stats['payment_status']] ?? 'Inconnu';
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo date('F Y'); ?></td>
                                <td>
                                    <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                                </td>
                            </tr>

                            <!-- Subscription Info Row -->
                            <?php if ($subscription_info): ?>
                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #8b5cf6;">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Abonnement Actuel</div>
                                            <div style="font-size: 12px; color: #64748b;">Plan de service</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 14px; font-weight: 600;">
                                        <?php echo htmlspecialchars($subscription_info['name_subscription']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #64748b;">
                                        <?php echo htmlspecialchars($subscription_info['description']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #64748b;">
                                        <?php echo number_format($subscription_info['price_subscription']); ?> DH/mois
                                    </div>
                                </td>
                                <td><span class="status-badge status-paid">Actif</span></td>
                                <td>Permanent</td>
                                <td>
                                    <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <!-- Syndic Contact Row -->
                            <?php if ($syndic_info): ?>
                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #f59e0b;">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Contact Syndic</div>
                                            <div style="font-size: 12px; color: #64748b;">Gestionnaire de l'immeuble</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 14px; font-weight: 600;">
                                        <?php echo htmlspecialchars($syndic_info['syndic_name']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #64748b;">
                                        <?php echo htmlspecialchars($syndic_info['syndic_email']); ?>
                                    </div>
                                    <?php if ($syndic_info['syndic_phone']): ?>
                                        <div style="font-size: 12px; color: #64748b;">
                                            <?php echo htmlspecialchars($syndic_info['syndic_phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-badge status-paid">Disponible</span></td>
                                <td>Contact direct</td>
                                <td>
                                    <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <!-- Recent Payments -->
                            <?php if (!empty($recent_payments)): ?>
                                <?php foreach (array_slice($recent_payments, 0, 3) as $payment): ?>
                                <tr class="table-row">
                                    <td>
                                        <div class="file-item">
                                            <div class="table-icon" style="background: #10b981;">
                                                <i class="fas fa-receipt"></i>
                                            </div>
                                            <div class="file-info">
                                                <div class="file-name">Paiement Effectué</div>
                                                <div style="font-size: 12px; color: #64748b;">Transaction validée</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 14px;">
                                            <?php echo $payment['month_paid'] ? 'Charges ' . date('M Y', strtotime($payment['month_paid'])) : 'Paiement effectué'; ?>
                                        </div>
                                        <?php if ($payment['receiver_name']): ?>
                                            <div style="font-size: 12px; color: #64748b;">
                                                Destinataire: <?php echo htmlspecialchars($payment['receiver_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status-badge status-paid">Effectué</span></td>
                                    <td><?php echo date('d/m/Y', strtotime($payment['date_payment'])); ?></td>
                                    <td>
                                        <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Building Information Row -->
                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #3b82f6;">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Informations Immeuble</div>
                                            <div style="font-size: 12px; color: #64748b;">Détails de la résidence</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="sharing-avatars">
                                        <?php 
                                        $resident_avatars = min(4, $total_residents);
                                        for ($i = 0; $i < $resident_avatars; $i++): 
                                        ?>
                                            <div class="sharing-avatar"><?php echo chr(65 + $i); ?></div>
                                        <?php endfor; ?>
                                        <?php if ($total_residents > 4): ?>
                                            <span class="sharing-count">+<?php echo $total_residents - 4; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 12px;">
                                        <?php echo $total_residents; ?> résidents<br>
                                        <?php echo $total_apartments; ?> appartements
                                    </div>
                                </td>
                                <td>Communauté active</td>
                                <td>
                                    <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                                </td>
                            </tr>

                            <!-- If no data available -->
                            <?php if (empty($recent_payments) && !$resident_info && !$syndic_info): ?>
                            <tr class="table-row">
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fas fa-home"></i>
                                        <div>Aucune information disponible</div>
                                        <div style="font-size: 12px; margin-top: 8px;">Vos informations de résidence apparaîtront ici</div>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
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
                        <div class="activity-tab">Messages</div>
                        <div class="activity-tab">Annonces</div>
                    </div>

                    <!-- Recent Activities -->
                    <?php if (!empty($recent_payments) || !empty($recent_announcements) || !empty($recent_messages) || !empty($recent_notifications)): ?>
                        
                        <!-- Recent Payments -->
                        <?php foreach (array_slice($recent_payments, 0, 2) as $payment): ?>
                        <div class="activity-item">
                            <div class="activity-icon payment">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">Paiement effectué</div>
                                <div class="activity-time"><?php echo date('d/m/Y à H:i', strtotime($payment['date_payment'])); ?></div>
                                <div class="activity-meta">
                                    <div class="sharing-avatar">P</div>
                                    <span style="font-size: 12px; color: #64748b;">
                                        <?php echo $payment['month_paid'] ? date('M Y', strtotime($payment['month_paid'])) : 'Paiement'; ?>
                                    </span>
                                    <div class="tag">Effectué</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Recent Announcements -->
                        <?php foreach (array_slice($recent_announcements, 0, 2) as $announcement): ?>
                        <div class="activity-item">
                            <div class="activity-icon announcement">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">Nouvelle annonce reçue</div>
                                <div class="activity-time"><?php echo date('d/m/Y à H:i', strtotime($announcement['date_announcement'])); ?></div>
                                <div class="activity-meta">
                                    <div class="sharing-avatar"><?php echo strtoupper(substr($announcement['poster_name'], 0, 1)); ?></div>
                                    <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($announcement['poster_name']); ?></span>
                                    <div class="tag">Annonce</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Recent Messages -->
                        <?php foreach (array_slice($recent_messages, 0, 2) as $message): ?>
                        <div class="activity-item">
                            <div class="activity-icon message">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">Nouveau message reçu</div>
                                <div class="activity-time"><?php echo date('d/m/Y à H:i', strtotime($message['date_message'])); ?></div>
                                <div class="activity-meta">
                                    <div class="sharing-avatar"><?php echo strtoupper(substr($message['sender_name'], 0, 1)); ?></div>
                                    <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($message['sender_name']); ?></span>
                                    <div class="tag">Message</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Recent Notifications -->
                        <?php foreach (array_slice($recent_notifications, 0, 1) as $notification): ?>
                        <div class="activity-item">
                            <div class="activity-icon notification">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">Nouvelle notification</div>
                                <div class="activity-time"><?php echo date('d/m/Y à H:i', strtotime($notification['date_notification'])); ?></div>
                                <div class="activity-meta">
                                    <div class="sharing-avatar"><?php echo strtoupper(substr($notification['sender_name'], 0, 1)); ?></div>
                                    <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($notification['sender_name']); ?></span>
                                    <div class="tag">Notification</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Welcome message for new residents -->
                        <?php if ($resident_info && (time() - strtotime($resident_info['date_created'])) < 7 * 24 * 60 * 60): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: #10b981;">
                                <i class="fas fa-home"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">Bienvenue dans votre nouvel appartement !</div>
                                <div class="activity-time">Inscrit le <?php echo date('d/m/Y', strtotime($resident_info['date_created'])); ?></div>
                                <div class="activity-meta">
                                    <div class="sharing-avatar">🏠</div>
                                    <span style="font-size: 12px; color: #64748b;">Syndic Way</span>
                                    <div class="tag">Nouveau</div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-alt"></i>
                            <div>Aucune activité récente</div>
                            <div style="font-size: 12px; margin-top: 8px;">
                                Vos paiements, messages et annonces apparaîtront ici
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Quick Actions in Activity Panel -->
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e2e8f0;">
                        <div style="font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #1e293b;">
                            Actions Rapides
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <a href="payments.php?action=pay" style="display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 6px; background: #f8fafc; text-decoration: none; color: #64748b; font-size: 12px; transition: all 0.2s;">
                                <i class="fas fa-credit-card" style="color: #10b981;"></i>
                                Effectuer un paiement
                            </a>
                            <a href="messages.php?action=new" style="display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 6px; background: #f8fafc; text-decoration: none; color: #64748b; font-size: 12px; transition: all 0.2s;">
                                <i class="fas fa-envelope" style="color: #3b82f6;"></i>
                                Envoyer un message
                            </a>
                            <a href="profile.php" style="display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 6px; background: #f8fafc; text-decoration: none; color: #64748b; font-size: 12px; transition: all 0.2s;">
                                <i class="fas fa-user-edit" style="color: #8b5cf6;"></i>
                                Modifier mon profil
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // View toggle functionality
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Activity tabs functionality
        document.querySelectorAll('.activity-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.activity-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (!this.href.includes('login.php') && !this.href.endsWith('.php')) {
                    e.preventDefault();
                    document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });

        // Close activity panel
        document.querySelector('.close-btn').addEventListener('click', function() {
            document.querySelector('.activity-panel').style.display = 'none';
            document.querySelector('.main-panel').style.marginRight = '0';
        });

        // Quick card clicks
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (!this.href) {
                    e.preventDefault();
                    console.log('Quick action clicked');
                }
            });
        });

        // Table row hover effects
        document.querySelectorAll('.table-row').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8fafc';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });

        // Search functionality
        document.querySelector('.search-box input').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.table-row').forEach(row => {
                const fileName = row.querySelector('.file-name');
                if (fileName) {
                    const text = fileName.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });

        // Notification bell functionality
        document.querySelector('.fa-bell').addEventListener('click', function() {
            const totalNotifications = <?php echo $stats['unread_messages'] + $stats['unread_notifications'] + $stats['total_announcements']; ?>;
            if (totalNotifications > 0) {
                alert(`Vous avez ${totalNotifications} notification(s) non lue(s)`);
            } else {
                alert('Aucune nouvelle notification');
            }
        });

        // Keyboard shortcuts for residents
        document.addEventListener('keydown', function(event) {
            // Alt + P for payments
            if (event.altKey && event.key === 'p') {
                event.preventDefault();
                window.location.href = 'payments.php';
            }
            
            // Alt + M for messages
            if (event.altKey && event.key === 'm') {
                event.preventDefault();
                window.location.href = 'messages.php';
            }
            
            // Alt + A for announcements
            if (event.altKey && event.key === 'a') {
                event.preventDefault();
                window.location.href = 'announcements.php';
            }
            
            // Alt + N for notifications
            if (event.altKey && event.key === 'n') {
                event.preventDefault();
                window.location.href = 'notifications.php';
            }
        });

        // Enhanced interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Animate notification badges
            document.querySelectorAll('.notifications-badge').forEach(badge => {
                badge.style.animation = 'pulse 2s infinite';
            });

            // Add pulse animation CSS
            const style = document.createElement('style');
            style.textContent = `
                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.1); }
                    100% { transform: scale(1); }
                }
            `;
            document.head.appendChild(style);

            // Payment status checker
            const paymentStatus = '<?php echo $stats['payment_status']; ?>';
            if (paymentStatus === 'pending' || paymentStatus === 'overdue') {
                const paymentCard = document.querySelector('.quick-card.payments');
                if (paymentCard) {
                    paymentCard.style.borderColor = '#f59e0b';
                    paymentCard.style.background = '#fef3c7';
                }
            }

            // Enhanced hover effects for activity panel quick actions
            document.querySelectorAll('.activity-panel a').forEach(link => {
                link.addEventListener('mouseenter', function() {
                    this.style.background = '#e2e8f0';
                    this.style.transform = 'translateX(4px)';
                });
                
                link.addEventListener('mouseleave', function() {
                    this.style.background = '#f8fafc';
                    this.style.transform = 'translateX(0)';
                });
            });
        });

        // Auto-refresh activity panel every 60 seconds
        setInterval(function() {
            console.log('Auto-refresh activity...');
            // You can add AJAX calls here to refresh activity data
        }, 60000);

        // Enhanced card interactions
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px) scale(1.02)';
                this.style.boxShadow = '0 12px 24px rgba(0,0,0,0.15)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
                this.style.boxShadow = '';
            });
        });
    </script>
</body>
</html>