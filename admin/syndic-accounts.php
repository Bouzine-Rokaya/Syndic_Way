<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
$current_user = getCurrentUser();


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $city = trim($_POST['city']);
            $company_name = trim($_POST['company_name']);
            $address = trim($_POST['address']);
            $subscription_id = intval($_POST['subscription_id']);
            
            // Check if email exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM member WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Un compte avec cet email existe déjà.");
            }
            
            $conn->beginTransaction();
            
            // Insert or get city
            $stmt = $conn->prepare("SELECT id_city FROM city WHERE city_name = ?");
            $stmt->execute([$city]);
            $city_id = $stmt->fetchColumn();
            
            if (!$city_id) {
                $stmt = $conn->prepare("INSERT INTO city (city_name) VALUES (?)");
                $stmt->execute([$city]);
                $city_id = $conn->lastInsertId();
            }
            
            // Insert residence
            $stmt = $conn->prepare("INSERT INTO residence (id_city, name, address) VALUES (?, ?, ?)");
            $stmt->execute([$city_id, $company_name, $address]);
            $residence_id = $conn->lastInsertId();
            
            // Insert member
            $default_password = password_hash('syndic123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO member (full_name, email, password, phone, role, status) VALUES (?, ?, ?, ?, 2, 'active')");
            $stmt->execute([$full_name, $email, $default_password, $phone]);
            $member_id = $conn->lastInsertId();
            
            // Create apartment
            $stmt = $conn->prepare("INSERT INTO apartment (id_residence, id_member, type, floor, number) VALUES (?, ?, 'Bureau', '1', 1)");
            $stmt->execute([$residence_id, $member_id]);
            
            // Link to admin
            $stmt = $conn->prepare("INSERT INTO admin_member_link (id_admin, id_member, date_created) VALUES (?, ?, NOW())");
            $stmt->execute([$current_user['id'], $member_id]);
            
            // Create subscription
            $stmt = $conn->prepare("SELECT price_subscription FROM subscription WHERE id_subscription = ?");
            $stmt->execute([$subscription_id]);
            $price = $stmt->fetchColumn();

            $stmt = $conn->prepare("INSERT INTO admin_member_subscription (id_admin, id_member, id_subscription, date_payment, amount) VALUES (?, ?, ?, NOW(), ?)");
            $stmt->execute([$current_user['id'], $member_id, $subscription_id, $price]);
            
            $conn->commit();
            $_SESSION['success'] = "Compte syndic créé avec succès. Mot de passe par défaut: syndic123";
            
        } elseif ($action === 'update_status') {
            $member_id = intval($_POST['member_id']);
            $new_status = $_POST['new_status'];
            
            $stmt = $conn->prepare("UPDATE member SET status = ? WHERE id_member = ? AND role = 2");
            $stmt->execute([$new_status, $member_id]);
            
            $_SESSION['success'] = "Statut mis à jour avec succès.";
            
        } elseif ($action === 'delete') {
            $member_id = intval($_POST['member_id']);
            
            $conn->beginTransaction();
            
            // Delete related records first
            $stmt = $conn->prepare("DELETE FROM admin_member_subscription WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $stmt = $conn->prepare("DELETE FROM admin_member_link WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $stmt = $conn->prepare("DELETE FROM apartment WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $stmt = $conn->prepare("DELETE FROM member WHERE id_member = ? AND role = 2");
            $stmt->execute([$member_id]);
            
            $conn->commit();
            $_SESSION['success'] = "Compte syndic supprimé avec succès.";
        }
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header('Location: syndic-accounts.php');
    exit();
}

// Get statistics
$stats = [
    'total_syndics' => 0,
    'active_syndics' => 0,
    'pending_syndics' => 0,
    'total_revenue' => 0
];

try {
    // Total syndics
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member WHERE role = 2");
    $stmt->execute();
    $stats['total_syndics'] = $stmt->fetch()['count'];
    
    // Active syndics
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member WHERE role = 2 AND status = 'active'");
    $stmt->execute();
    $stats['active_syndics'] = $stmt->fetch()['count'];
    
    // Pending syndics
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member WHERE role = 2 AND status = 'inactive'");
    $stmt->execute();
    $stats['pending_syndics'] = $stmt->fetch()['count'];
    
    // Total revenue from syndics
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM admin_member_subscription WHERE amount IS NOT NULL");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_revenue'] = $result['total'] ?? 0;

    // Get syndic accounts with subscription details
    $stmt = $conn->prepare("
        SELECT 
            m.id_member,
            m.full_name,
            m.email,
            m.phone,
            m.status,
            m.date_created,
            r.name as company_name,
            r.address,
            c.city_name,
            s.name_subscription,
            s.price_subscription,
            ams.amount as subscription_amount,
            ams.date_payment,
            COUNT(ap.id_apartment) as apartment_count
        FROM member m
        LEFT JOIN apartment ap ON ap.id_member = m.id_member
        LEFT JOIN residence r ON r.id_residence = ap.id_residence
        LEFT JOIN city c ON c.id_city = r.id_city
        LEFT JOIN admin_member_subscription ams ON ams.id_member = m.id_member
        LEFT JOIN subscription s ON s.id_subscription = ams.id_subscription
        WHERE m.role = 2
        GROUP BY m.id_member
        ORDER BY m.date_created DESC
    ");
    $stmt->execute();
    $syndic_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active subscriptions for form
    $stmt = $conn->prepare("SELECT * FROM subscription WHERE is_active = 1 ORDER BY price_subscription ASC");
    $stmt->execute();
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "Erreur lors du chargement des données.";
    $syndic_accounts = [];
    $subscriptions = [];
}

// Helper function to format time ago
function timeAgo($datetime) {
    if (!$datetime) return 'Inconnu';
    
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'À l\'instant';
    if ($time < 3600) return floor($time/60) . ' min';
    if ($time < 86400) return floor($time/3600) . ' h';
    if ($time < 2592000) return floor($time/86400) . ' j';
    if ($time < 31536000) return floor($time/2592000) . ' mois';
    
    return floor($time/31536000) . ' ans';
}

$page_title = "Comptes Syndic - Syndic Way";
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
            background: #FFCB32;
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
            color: #FFCB32;
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
            background: #FFCB32;
            width: <?php echo min(($stats['total_syndics'] / 50) * 100, 100); ?>%;
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
            padding: 4px 24px;
        }


        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            background: #FFCB32;
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

        .quick-card.total .quick-card-icon { background: #FFCB32; }
        .quick-card.active .quick-card-icon { background: #10b981; }
        .quick-card.pending .quick-card-icon { background: #f59e0b; }
        .quick-card.revenue .quick-card-icon { background: #8b5cf6; }

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
            color: #FFCB32;
        }

        /* Table Header */
         .table-header {
            display: flex;
            justify-content: end;
            align-items: center;
            margin-bottom: 16px;
        }

        .view-options {
            display: flex;
            gap: 8px;
        }


        .add-btn {
            background: #FFCB32;
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
            background: #2563eb;
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
            background: #FFCB32;
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

        .status-active {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-inactive {
            background: #f1f5f9;
            color: #64748b;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
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
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            color: #1e293b;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
        }

        .modal-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 4px;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #FFCB32;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .required {
            color: #ef4444;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding: 20px 24px;
            border-top: 1px solid #e2e8f0;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #FFCB32;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border-color: #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-sm {
            padding: 4px 8px;
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
            
            .quick-access-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php require_once __DIR__ ."/../includes/sidebar_admin.php"?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Header -->
            <?php require_once __DIR__ ."/../includes/navigation.php"?>

            <!-- Quick Access -->
            <div class="quick-access">
                <div class="quick-access-header">
                    <div class="quick-access-title">Statistiques Syndic</div>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card total">
                        <div class="quick-card-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="quick-card-title">Total Syndics</div>
                        <div class="quick-card-count"><?php echo $stats['total_syndics']; ?></div>
                        <div class="quick-card-stats">Comptes enregistrés</div>
                    </div>

                    <div class="quick-card active">
                        <div class="quick-card-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="quick-card-title">Syndics Actifs</div>
                        <div class="quick-card-count"><?php echo $stats['active_syndics']; ?></div>
                        <div class="quick-card-stats">En service</div>
                    </div>

                    <div class="quick-card pending">
                        <div class="quick-card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="quick-card-title">En Attente</div>
                        <div class="quick-card-count"><?php echo $stats['pending_syndics']; ?></div>
                        <div class="quick-card-stats">À activer</div>
                    </div>

                    <div class="quick-card revenue">
                        <div class="quick-card-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="quick-card-title">Revenus</div>
                        <div class="quick-card-count"><?php echo number_format($stats['total_revenue'], 0, ',', ' '); ?> DH</div>
                        <div class="quick-card-stats">Total abonnements</div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div class="main-panel">
                    <!-- Breadcrumb -->
                    <div class="breadcrumb">
                        <a href="dashboard.php">Accueil</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <a href="#">Gestion</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Comptes Syndic</span>
                    </div>

                    <!-- Table Header -->
                    <div class="table-header">
                        <button class="add-btn" onclick="openModal()">
                            <i class="fas fa-plus"></i>
                            Nouveau Syndic
                        </button>
                    </div>

                    <!-- Data Table -->
                    <table class="data-table">
                        <thead class="table-header-row">
                            <tr>
                                <th>Syndic</th>
                                <th>Contact</th>
                                <th>Abonnement</th>
                                <th>Statut</th>
                                <th>Créé</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($syndic_accounts)): ?>
                            <tr class="table-row">
                                <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                                    <i class="fas fa-building" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                                    <div>Aucun syndic trouvé</div>
                                    <div style="font-size: 12px; margin-top: 8px;">Créez votre premier compte syndic pour commencer</div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($syndic_accounts as $syndic): ?>
                                <tr class="table-row">
                                    <td>
                                        <div class="file-item">
                                            <div class="table-icon" style="background: #FFCB32;">
                                                <i class="fas fa-user-tie"></i>
                                            </div>
                                            <div class="file-info">
                                                <div class="file-name"><?php echo htmlspecialchars($syndic['full_name']); ?></div>
                                                <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($syndic['company_name'] ?? 'Entreprise non définie'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 14px;"><?php echo htmlspecialchars($syndic['email']); ?></div>
                                        <?php if ($syndic['phone']): ?>
                                        <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($syndic['phone']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($syndic['city_name']): ?>
                                        <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($syndic['city_name']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($syndic['name_subscription']): ?>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($syndic['name_subscription']); ?></div>
                                        <div style="font-size: 12px; color: #64748b;"><?php echo number_format($syndic['price_subscription'], 0); ?> DH/mois</div>
                                        <?php else: ?>
                                        <span style="color: #64748b;">Aucun abonnement</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $syndic['status']; ?>">
                                            <?php 
                                                $status_text = [
                                                    'active' => 'Actif',
                                                    'pending' => 'En attente',
                                                    'inactive' => 'Inactif'
                                                ];
                                                echo $status_text[$syndic['status']] ?? $syndic['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo timeAgo($syndic['date_created']); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 4px;">
                                            <?php if ($syndic['status'] === 'pending'): ?>
                                                <button class="btn btn-success btn-sm" 
                                                        onclick="updateStatus(<?php echo $syndic['id_member']; ?>, 'active')"
                                                        title="Activer">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php elseif ($syndic['status'] === 'active'): ?>
                                                <button class="btn btn-warning btn-sm" 
                                                        onclick="updateStatus(<?php echo $syndic['id_member']; ?>, 'inactive')"
                                                        title="Suspendre">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-success btn-sm" 
                                                        onclick="updateStatus(<?php echo $syndic['id_member']; ?>, 'active')"
                                                        title="Réactiver">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-danger btn-sm" 
                                                    onclick="confirmDelete(<?php echo $syndic['id_member']; ?>, '<?php echo htmlspecialchars($syndic['full_name']); ?>')"
                                                    title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Syndic Modal -->
    <div id="syndicModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-building"></i>
                    Nouveau compte syndic
                </h2>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name">Nom complet <span class="required">*</span></label>
                            <input type="text" name="full_name" id="full_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <input type="email" name="email" id="email" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Téléphone <span class="required">*</span></label>
                            <input type="tel" name="phone" id="phone" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="city">Ville <span class="required">*</span></label>
                            <input type="text" name="city" id="city" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="company_name">Nom de l'entreprise/immeuble <span class="required">*</span></label>
                        <input type="text" name="company_name" id="company_name" required>
                    </div>

                    <div class="form-group">
                        <label for="address">Adresse</label>
                        <textarea name="address" id="address" rows="3" placeholder="Adresse complète de l'entreprise/immeuble"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="subscription_id">Forfait d'abonnement <span class="required">*</span></label>
                        <select name="subscription_id" id="subscription_id" required>
                            <option value="">Choisir un forfait</option>
                            <?php foreach ($subscriptions as $subscription): ?>
                                <option value="<?php echo $subscription['id_subscription']; ?>">
                                    <?php echo htmlspecialchars($subscription['name_subscription']); ?> 
                                    - <?php echo number_format($subscription['price_subscription'], 0); ?> DH/mois
                                    (<?php echo $subscription['max_residents']; ?> résidents max)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="background: #f0f9ff; padding: 12px; border-radius: 6px; margin-top: 16px;">
                        <div style="font-size: 14px; color: #0369a1;">
                            <i class="fas fa-info-circle"></i> <strong>Informations importantes :</strong>
                        </div>
                        <ul style="margin: 8px 0 0 20px; font-size: 13px; color: #0369a1;">
                            <li>Le mot de passe par défaut sera : <strong>syndic123</strong></li>
                            <li>Le syndic devra changer son mot de passe lors de la première connexion</li>
                            <li>L'abonnement sera automatiquement activé</li>
                        </ul>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Créer le compte
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden forms for actions -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="member_id" id="statusMemberId">
        <input type="hidden" name="new_status" id="newStatus">
    </form>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="member_id" id="deleteMemberId">
    </form>

    <script>
        // Open modal
        function openModal() {
            document.getElementById('syndicModal').classList.add('show');
        }

        // Close modal
        function closeModal() {
            document.getElementById('syndicModal').classList.remove('show');
        }

        // Update status
        function updateStatus(memberId, newStatus) {
            const statusText = {
                'active': 'activer',
                'inactive': 'suspendre'
            };
            
            if (confirm(`Êtes-vous sûr de vouloir ${statusText[newStatus]} ce syndic ?`)) {
                document.getElementById('statusMemberId').value = memberId;
                document.getElementById('newStatus').value = newStatus;
                document.getElementById('statusForm').submit();
            }
        }

        // Confirm delete
        function confirmDelete(memberId, syndicName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer le compte de "${syndicName}" ?\n\nCette action supprimera définitivement :\n- Le compte syndic\n- Tous les appartements associés\n- L'abonnement\n- Toutes les données liées\n\nCette action est irréversible.`)) {
                document.getElementById('deleteMemberId').value = memberId;
                document.getElementById('deleteForm').submit();
            }
        }

        // View toggle functionality
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.table-row').forEach(row => {
                const syndicName = row.querySelector('.file-name');
                const email = row.cells[1].textContent;
                const company = row.querySelector('.file-info div:nth-child(2)');
                
                if (syndicName && email && company) {
                    const text = (syndicName.textContent + ' ' + email + ' ' + company.textContent).toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });

        // Quick card clicks
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('click', function() {
                const cardType = this.classList[1];
                console.log(`Filtering by ${cardType}`);
                // Add filtering logic here
            });
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('syndicModal');
            if (event.target === modal) {
                closeModal();
            }
        }

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

            // Animate storage bar
            const storageFill = document.querySelector('.storage-fill');
            const originalWidth = storageFill.style.width;
            storageFill.style.width = '0%';
            setTimeout(() => {
                storageFill.style.width = originalWidth;
            }, 500);

            // Animate counters
            document.querySelectorAll('.quick-card-count').forEach(counter => {
                const target = parseInt(counter.textContent.replace(/[^0-9]/g, ''));
                if (!isNaN(target)) {
                    animateCounter(counter, target);
                }
            });
        });

        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 20;
            const originalText = element.textContent;
            const suffix = originalText.replace(/[0-9]/g, '');
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                
                if (suffix.includes('DH')) {
                    element.textContent = Math.floor(current).toLocaleString() + ' DH';
                } else {
                    element.textContent = Math.floor(current);
                }
            }, 50);
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#ef4444';
                    isValid = false;
                } else {
                    field.style.borderColor = '#d1d5db';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires.');
            }
        });

        // Enhanced table interactions
        document.querySelectorAll('.table-row').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8fafc';
                this.style.transform = 'translateX(2px)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
                this.style.transform = 'translateX(0)';
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+N to open new syndic modal
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                openModal();
            }
            
            // Escape to close modal
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Real-time validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#ef4444';
                this.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
            } else {
                this.style.borderColor = '#d1d5db';
                this.style.boxShadow = '';
            }
        });

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 10) {
                value = value.replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5');
            }
            this.value = value;
        });

        // City autocomplete (basic)
        const moroccanCities = ['Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger', 'Agadir', 'Meknès', 'Oujda', 'Kenitra', 'Tétouan', 'Safi', 'Mohammedia', 'Khouribga', 'El Jadida', 'Béni Mellal', 'Nador'];
        
        document.getElementById('city').addEventListener('input', function() {
            const input = this.value.toLowerCase();
            const matches = moroccanCities.filter(city => 
                city.toLowerCase().includes(input)
            );
            
            // Simple autocomplete could be implemented here
            if (matches.length > 0 && input.length > 0) {
                this.style.backgroundColor = '#f0f9ff';
            } else {
                this.style.backgroundColor = '';
            }
        });
    </script>
</body>
</html>