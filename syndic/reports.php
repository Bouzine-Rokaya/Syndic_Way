<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in as syndic
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'syndic' && $_SESSION['user_role'] !== 'member')) {
    header('Location: ../public/login.php');
    exit();
}

$current_user = [
    'id' => $_SESSION['user_id'],
    'role' => $_SESSION['user_role'],
    'name' => $_SESSION['user_name'] ?? 'Syndic',
    'email' => $_SESSION['user_email'] ?? ''
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'generate_report') {
            $report_type = $_POST['report_type'];
            $date_from = $_POST['date_from'];
            $date_to = $_POST['date_to'];
            $format = $_POST['format'] ?? 'html';
            
            $_SESSION['success'] = "Rapport généré avec succès.";
            
        } elseif ($action === 'export_data') {
            $export_type = $_POST['export_type'];
            $date_range = $_POST['date_range'];
            
            $_SESSION['success'] = "Export effectué avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header('Location: reports.php');
    exit();
}

// Get date filters
$period_filter = $_GET['period'] ?? 'current_month';
$custom_from = $_GET['custom_from'] ?? '';
$custom_to = $_GET['custom_to'] ?? '';

// Calculate date range based on period
switch ($period_filter) {
    case 'current_month':
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-t');
        break;
    case 'last_month':
        $date_from = date('Y-m-01', strtotime('last month'));
        $date_to = date('Y-m-t', strtotime('last month'));
        break;
    case 'current_year':
        $date_from = date('Y-01-01');
        $date_to = date('Y-12-31');
        break;
    case 'custom':
        $date_from = $custom_from ?: date('Y-m-01');
        $date_to = $custom_to ?: date('Y-m-t');
        break;
    default:
        $date_from = date('Y-m-01');
        $date_to = date('Y-m-t');
}

try {
    // Get building info and residents
    $stmt = $conn->prepare("
        SELECT r.name, r.address, c.city_name, COUNT(DISTINCT ap.id_apartment) as total_apartments
        FROM apartment ap
        JOIN residence r ON r.id_residence = ap.id_residence
        JOIN city c ON c.id_city = r.id_city
        WHERE ap.id_member = ?
        GROUP BY r.id_residence
        LIMIT 1
    ");
    $stmt->execute([$current_user['id']]);
    $building_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($building_info) {
        $residence_id = null;
        $stmt = $conn->prepare("
            SELECT DISTINCT r.id_residence
            FROM apartment ap
            JOIN residence r ON r.id_residence = ap.id_residence
            WHERE ap.id_member = ?
            LIMIT 1
        ");
        $stmt->execute([$current_user['id']]);
        $residence_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $residence_id = $residence_data['id_residence'];

        // Get residents statistics
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_residents,
                SUM(CASE WHEN m.status = 'active' THEN 1 ELSE 0 END) as active_residents,
                COUNT(DISTINCT ap.floor) as total_floors
            FROM member m
            JOIN apartment ap ON ap.id_member = m.id_member
            WHERE m.role = 1 AND ap.id_residence = ?
        ");
        $stmt->execute([$residence_id]);
        $residents_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get payments statistics for the period
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_payments,
                COUNT(DISTINCT mp.id_payer) as paying_residents,
                SUM(1) as payment_count
            FROM member_payments mp
            JOIN member m ON mp.id_payer = m.id_member
            JOIN apartment ap ON ap.id_member = m.id_member
            WHERE mp.id_receiver = ? 
            AND ap.id_residence = ?
            AND DATE(mp.date_payment) BETWEEN ? AND ?
        ");
        $stmt->execute([$current_user['id'], $residence_id, $date_from, $date_to]);
        $payments_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get messages statistics
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_messages,
                COUNT(CASE WHEN mm.id_sender = ? THEN 1 END) as sent_messages,
                COUNT(CASE WHEN mm.id_receiver = ? THEN 1 END) as received_messages
            FROM member_messages mm
            JOIN member m ON (mm.id_sender = m.id_member OR mm.id_receiver = m.id_member)
            JOIN apartment ap ON ap.id_member = m.id_member
            WHERE (mm.id_sender = ? OR mm.id_receiver = ?)
            AND ap.id_residence = ?
            AND DATE(mm.date_message) BETWEEN ? AND ?
        ");
        $stmt->execute([$current_user['id'], $current_user['id'], $current_user['id'], $current_user['id'], $residence_id, $date_from, $date_to]);
        $messages_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get announcements statistics
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT ma.date_announcement) as total_announcements,
                COUNT(*) as total_recipients
            FROM member_announcements ma
            JOIN member m ON ma.id_receiver = m.id_member
            JOIN apartment ap ON ap.id_member = m.id_member
            WHERE ma.id_poster = ?
            AND ap.id_residence = ?
            AND DATE(ma.date_announcement) BETWEEN ? AND ?
        ");
        $stmt->execute([$current_user['id'], $residence_id, $date_from, $date_to]);
        $announcements_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate derived statistics
        $occupancy_rate = $residents_stats['total_residents'] > 0 ? 
            round(($residents_stats['active_residents'] / $residents_stats['total_residents']) * 100, 1) : 0;
        
        $payment_rate = $residents_stats['total_residents'] > 0 ? 
            round(($payments_stats['paying_residents'] / $residents_stats['total_residents']) * 100, 1) : 0;

    } else {
        // No building found
        $residents_stats = ['total_residents' => 0, 'active_residents' => 0, 'total_floors' => 0];
        $payments_stats = ['total_payments' => 0, 'paying_residents' => 0, 'payment_count' => 0];
        $messages_stats = ['total_messages' => 0, 'sent_messages' => 0, 'received_messages' => 0];
        $announcements_stats = ['total_announcements' => 0, 'total_recipients' => 0];
        $occupancy_rate = 0;
        $payment_rate = 0;
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors du chargement des données.";
    $building_info = null;
    $residents_stats = ['total_residents' => 0, 'active_residents' => 0, 'total_floors' => 0];
    $payments_stats = ['total_payments' => 0, 'paying_residents' => 0, 'payment_count' => 0];
    $messages_stats = ['total_messages' => 0, 'sent_messages' => 0, 'received_messages' => 0];
    $announcements_stats = ['total_announcements' => 0, 'total_recipients' => 0];
    $occupancy_rate = 0;
    $payment_rate = 0;
}

$page_title = "Rapports et Analyses - Syndic Way";
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

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
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

        .quick-card.residents .quick-card-icon { background: #FFCB32; }
        .quick-card.payments .quick-card-icon { background: #10b981; }
        .quick-card.messages .quick-card-icon { background: #f59e0b; }
        .quick-card.reports .quick-card-icon { background: #8b5cf6; }

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

        /* Period Selection */
        .period-selection {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
        }

        .period-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .period-tab {
            padding: 8px 16px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .period-tab.active {
            background: #FFCB32;
            color: white;
            border-color: #FFCB32;
        }

        .custom-date-range {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .date-input {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .date-input label {
            font-size: 12px;
            color: #64748b;
        }

        .date-input input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }

        .current-period {
            font-size: 14px;
            color: #64748b;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }

        /* Data Table */
        .data-table {
            width: 100%;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 20px;
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
            padding-bottom:10px;
            border-bottom: 1px solid rgb(226, 231, 235);
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

        .activity-icon.residents { background: #FFCB32; }
        .activity-icon.payments { background: #10b981; }
        .activity-icon.messages { background: #f59e0b; }
        .activity-icon.reports { background: #8b5cf6; }

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
            background: #eff6ff;
            color: #FFCB32;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
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
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
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
            background: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.2s;
        }

        .modal.show {
            display: block;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
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
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .close {
            background: none;
            border: none;
            font-size: 24px;
            color: #64748b;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .close:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .modal-body {
            padding: 24px;
        }

        /* Form Styles */
        .form-section {
            margin-bottom: 24px;
        }

        .form-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #FFCB32;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .required {
            color: #ef4444;
        }

        .modal-actions {
            padding: 20px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Report Cards */
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .report-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            border-color: #FFCB32;
        }

        .report-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 20px;
            color: white;
        }

 .report-icon.financial { background: #FFCB32; }
        .report-icon.occupancy { background: #10b981; }
        .report-icon.communication { background: #f59e0b; }
        .report-icon.monthly { background: #8b5cf6; }
        .report-icon.annual { background: #ef4444; }
        .report-icon.custom { background: #64748b; }

        .report-info h4 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .report-info p {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 12px;
        }

        .report-status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .report-status {
            background: #dcfce7;
            color: #16a34a;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
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
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .period-tabs {
                flex-wrap: wrap;
            }
            
            .custom-date-range {
                flex-direction: column;
                align-items: stretch;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php require_once __DIR__ ."/../includes/sidebar_syndic.php"?>


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
            <?php require_once __DIR__ ."/../includes/navigation_syndic.php"?>


            <!-- Quick Access -->
            <div class="quick-access">
                <div class="quick-access-header">
                    <div class="quick-access-title">Indicateurs Clés</div>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card residents">
                        <div class="quick-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="quick-card-title">Résidents</div>
                        <div class="quick-card-count"><?php echo $residents_stats['total_residents']; ?></div>
                        <div class="quick-card-stats"><?php echo $occupancy_rate; ?>% d'occupation</div>
                    </div>

                    <div class="quick-card payments">
                        <div class="quick-card-icon">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                        <div class="quick-card-title">Paiements</div>
                        <div class="quick-card-count"><?php echo $payments_stats['total_payments']; ?></div>
                        <div class="quick-card-stats"><?php echo $payment_rate; ?>% de taux</div>
                    </div>

                    <div class="quick-card messages">
                        <div class="quick-card-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="quick-card-title">Messages</div>
                        <div class="quick-card-count"><?php echo $messages_stats['total_messages']; ?></div>
                        <div class="quick-card-stats"><?php echo $messages_stats['sent_messages']; ?> envoyés</div>
                    </div>

                    <div class="quick-card reports">
                        <div class="quick-card-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="quick-card-title">Annonces</div>
                        <div class="quick-card-count"><?php echo $announcements_stats['total_announcements']; ?></div>
                        <div class="quick-card-stats"><?php echo $announcements_stats['total_recipients']; ?> destinataires</div>
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
                        <a href="#">Syndic Panel</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Rapports et Analyses</span>
                    </div>

                    <!-- Period Selection -->
                    <div class="period-selection">
                        <form method="GET" id="periodForm">
                            <div class="period-tabs">
                                <button type="button" class="period-tab <?php echo $period_filter === 'current_month' ? 'active' : ''; ?>" 
                                        onclick="setPeriod('current_month')">Mois actuel</button>
                                <button type="button" class="period-tab <?php echo $period_filter === 'last_month' ? 'active' : ''; ?>" 
                                        onclick="setPeriod('last_month')">Mois dernier</button>
                                <button type="button" class="period-tab <?php echo $period_filter === 'current_year' ? 'active' : ''; ?>" 
                                        onclick="setPeriod('current_year')">Année actuelle</button>
                                <button type="button" class="period-tab <?php echo $period_filter === 'custom' ? 'active' : ''; ?>" 
                                        onclick="setPeriod('custom')">Personnalisé</button>
                            </div>
                            
                            <div id="customDateRange" class="custom-date-range" style="<?php echo $period_filter === 'custom' ? 'display: flex;' : 'display: none;'; ?>">
                                <div class="date-input">
                                    <label for="custom_from">Du :</label>
                                    <input type="date" name="custom_from" id="custom_from" value="<?php echo $custom_from; ?>">
                                </div>
                                <div class="date-input">
                                    <label for="custom_to">Au :</label>
                                    <input type="date" name="custom_to" id="custom_to" value="<?php echo $custom_to; ?>">
                                </div>
                                <button type="submit" class="btn btn-primary">Appliquer</button>
                            </div>
                            
                            <input type="hidden" name="period" id="periodInput" value="<?php echo $period_filter; ?>">
                        </form>
                        
                        <div class="current-period">
                            <i class="fas fa-calendar-alt"></i>
                            Période analysée : <?php echo date('d/m/Y', strtotime($date_from)); ?> - <?php echo date('d/m/Y', strtotime($date_to)); ?>
                        </div>
                    </div>

                    <!-- Reports Table -->
                    <table class="data-table">
                        <thead class="table-header-row">
                            <tr>
                                <th>Type de Rapport</th>
                                <th>Données</th>
                                <th>Période</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #FFCB32;">
                                            <i class="fas fa-chart-pie"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Rapport Financier</div>
                                            <div style="font-size: 12px; color: #64748b;">Analyse des paiements et charges</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="sharing-avatars">
                                        <div class="sharing-avatar">€</div>
                                        <span class="sharing-count"><?php echo $payments_stats['total_payments']; ?> paiements</span>
                                    </div>
                                </td>
                                <td><?php echo date('M Y', strtotime($date_from)); ?></td>
                                <td>
                                    <button class="btn btn-primary" onclick="generateQuickReport('financial')">
                                        <i class="fas fa-download"></i> Générer
                                    </button>
                                </td>
                            </tr>

                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #10b981;">
                                            <i class="fas fa-home"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Rapport d'Occupation</div>
                                            <div style="font-size: 12px; color: #64748b;">État des appartements et résidents</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="sharing-avatars">
                                        <div class="sharing-avatar">R</div>
                                        <span class="sharing-count"><?php echo $residents_stats['total_residents']; ?> résidents</span>
                                    </div>
                                </td>
                                <td>Temps réel</td>
                                <td>
                                    <button class="btn btn-primary" onclick="generateQuickReport('occupancy')">
                                        <i class="fas fa-download"></i> Générer
                                    </button>
                                </td>
                            </tr>

                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #f59e0b;">
                                            <i class="fas fa-comments"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Rapport Communication</div>
                                            <div style="font-size: 12px; color: #64748b;">Messages et annonces</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="sharing-avatars">
                                        <div class="sharing-avatar">M</div>
                                        <span class="sharing-count"><?php echo $messages_stats['total_messages']; ?> messages</span>
                                    </div>
                                </td>
                                <td><?php echo date('M Y', strtotime($date_from)); ?></td>
                                <td>
                                    <button class="btn btn-primary" onclick="generateQuickReport('communication')">
                                        <i class="fas fa-download"></i> Générer
                                    </button>
                                </td>
                            </tr>

                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #8b5cf6;">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Rapport Mensuel Complet</div>
                                            <div style="font-size: 12px; color: #64748b;">Synthèse complète du mois</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="sharing-avatars">
                                        <div class="sharing-avatar">S</div>
                                        <span class="sharing-count">Synthèse complète</span>
                                    </div>
                                </td>
                                <td><?php echo date('M Y', strtotime($date_from)); ?></td>
                                <td>
                                    <button class="btn btn-primary" onclick="generateQuickReport('monthly')">
                                        <i class="fas fa-download"></i> Générer
                                    </button>
                                </td>
                            </tr>

                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #ef4444;">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Rapport Annuel</div>
                                            <div style="font-size: 12px; color: #64748b;">Bilan annuel de la copropriété</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="sharing-avatars">
                                        <div class="sharing-avatar">A</div>
                                        <span class="sharing-count">Données annuelles</span>
                                    </div>
                                </td>
                                <td><?php echo date('Y'); ?></td>
                                <td>
                                    <button class="btn btn-primary" onclick="generateQuickReport('annual')">
                                        <i class="fas fa-download"></i> Générer
                                    </button>
                                </td>
                            </tr>

                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #64748b;">
                                            <i class="fas fa-cog"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Rapport Personnalisé</div>
                                            <div style="font-size: 12px; color: #64748b;">Configuration sur mesure</div>
                                        </div>
                                    </div>
                                </td>
                                <td>Configuration</td>
                                <td>Variable</td>
                                <td>
                                    <button class="btn btn-secondary" onclick="openReportModal()">
                                        <i class="fas fa-cog"></i> Configurer
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Quick Reports Section -->
                    <div style="margin-top: 30px;">
                        <h2 style="margin-bottom: 20px; color: #1e293b; font-size: 18px;">
                            <i class="fas fa-file-alt"></i> Rapports Rapides
                        </h2>
                        <div class="reports-grid">
                            <div class="report-card" onclick="generateQuickReport('financial')">
                                <div class="report-icon financial">
                                    <i class="fas fa-chart-pie"></i>
                                </div>
                                <div class="report-info">
                                    <h4>Rapport Financier</h4>
                                    <p>Analyse des paiements et charges</p>
                                    <span class="report-status">Disponible</span>
                                </div>
                            </div>

                            <div class="report-card" onclick="generateQuickReport('occupancy')">
                                <div class="report-icon occupancy">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div class="report-info">
                                    <h4>Rapport d'Occupation</h4>
                                    <p>État des appartements et résidents</p>
                                    <span class="report-status">Disponible</span>
                                </div>
                            </div>

                            <div class="report-card" onclick="generateQuickReport('communication')">
                                <div class="report-icon communication">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <div class="report-info">
                                    <h4>Rapport Communication</h4>
                                    <p>Messages et annonces</p>
                                    <span class="report-status">Disponible</span>
                                </div>
                            </div>

                            <div class="report-card" onclick="openReportModal()">
                                <div class="report-icon custom">
                                    <i class="fas fa-cog"></i>
                                </div>
                                <div class="report-info">
                                    <h4>Rapport Personnalisé</h4>
                                    <p>Configuration sur mesure</p>
                                    <span class="report-status">Configuration</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Panel -->
                <div class="activity-panel">
                    <div class="activity-header">
                        <div class="activity-title">Historique Rapports</div>
                        <button class="close-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon reports">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Rapport financier généré</div>
                            <div class="activity-time">Il y a 2 heures</div>
                            <div class="activity-meta">
                                <div class="sharing-avatar">PDF</div>
                                <span style="font-size: 12px; color: #64748b;">2.4 MB</span>
                                <div class="tag">Terminé</div>
                            </div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon residents">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Export résidents</div>
                            <div class="activity-time">Hier</div>
                            <div class="activity-meta">
                                <div class="sharing-avatar">XLS</div>
                                <span style="font-size: 12px; color: #64748b;">1.1 MB</span>
                                <div class="tag">Terminé</div>
                            </div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon messages">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Rapport communication</div>
                            <div class="activity-time">Il y a 3 jours</div>
                            <div class="activity-meta">
                                <div class="sharing-avatar">PDF</div>
                                <span style="font-size: 12px; color: #64748b;">1.8 MB</span>
                                <div class="tag">Archivé</div>
                            </div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon payments">
                            <i class="fas fa-file-csv"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Export paiements</div>
                            <div class="activity-time">La semaine dernière</div>
                            <div class="activity-meta">
                                <div class="sharing-avatar">CSV</div>
                                <span style="font-size: 12px; color: #64748b;">512 KB</span>
                                <div class="tag">Archivé</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Generate Report Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-file-alt"></i>
                    Générer un Rapport
                </h2>
                <button class="close" onclick="closeModal('reportModal')">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="reportForm" method="POST">
                    <input type="hidden" name="action" value="generate_report">
                    
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-cog"></i>
                            Configuration du rapport
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="report_type">
                                    Type de rapport <span class="required">*</span>
                                </label>
                                <select name="report_type" id="report_type" required>
                                    <option value="">Sélectionner un type</option>
                                    <option value="financial">Rapport Financier</option>
                                    <option value="occupancy">Rapport d'Occupation</option>
                                    <option value="communication">Rapport Communication</option>
                                    <option value="monthly">Rapport Mensuel</option>
                                    <option value="annual">Rapport Annuel</option>
                                    <option value="custom">Rapport Personnalisé</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="format">
                                    Format <span class="required">*</span>
                                </label>
                                <select name="format" id="format" required>
                                    <option value="pdf">PDF</option>
                                    <option value="excel">Excel</option>
                                    <option value="html">HTML</option>
                                    <option value="csv">CSV</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_from">
                                    Date de début <span class="required">*</span>
                                </label>
                                <input type="date" name="date_from" id="date_from" 
                                       value="<?php echo $date_from; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="date_to">
                                    Date de fin <span class="required">*</span>
                                </label>
                                <input type="date" name="date_to" id="date_to" 
                                       value="<?php echo $date_to; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('reportModal')">
                            <i class="fas fa-times"></i> Annuler
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-file-alt"></i> Générer le rapport
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden form for quick reports -->
    <form id="quickReportForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="generate_report">
        <input type="hidden" name="report_type" id="quickReportType">
        <input type="hidden" name="format" value="pdf">
        <input type="hidden" name="date_from" value="<?php echo $date_from; ?>">
        <input type="hidden" name="date_to" value="<?php echo $date_to; ?>">
    </form>
<script>
        // Period selection
        function setPeriod(period) {
            document.getElementById('periodInput').value = period;
            
            // Show/hide custom date range
            const customRange = document.getElementById('customDateRange');
            if (period === 'custom') {
                customRange.style.display = 'flex';
            } else {
                customRange.style.display = 'none';
                document.getElementById('periodForm').submit();
            }
            
            // Update active tab
            document.querySelectorAll('.period-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        // Generate quick report
        function generateQuickReport(type) {
            document.getElementById('quickReportType').value = type;
            document.getElementById('quickReportForm').submit();
        }

        // Open report modal
        function openReportModal() {
            document.getElementById('reportModal').classList.add('show');
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }


        // Storage bar animation
        window.addEventListener('load', function() {
            const storageFill = document.querySelector('.storage-fill');
            storageFill.style.width = '0%';
            setTimeout(() => {
                storageFill.style.width = '75%';
            }, 500);
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('reportModal');
            if (event.target === modal) {
                closeModal('reportModal');
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

            // Animate quick cards
            document.querySelectorAll('.quick-card').forEach((card, index) => {
                const count = card.querySelector('.quick-card-count');
                if (count) {
                    const target = parseInt(count.textContent);
                    count.textContent = '0';
                    setTimeout(() => {
                        animateCounter(count, target);
                    }, index * 200);
                }
            });
        });

        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 20;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current);
            }, 50);
        }


        // Close activity panel
        document.querySelector('.close-btn').addEventListener('click', function() {
            document.querySelector('.activity-panel').style.display = 'none';
            document.querySelector('.main-panel').style.marginRight = '0';
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

        // Report card hover effects
        document.querySelectorAll('.report-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
                this.style.boxShadow = '0 12px 25px rgba(0, 0, 0, 0.15)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '';
            });
        });

        // Form validation
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            const reportType = document.getElementById('report_type').value;
            const format = document.getElementById('format').value;
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;

            if (!reportType || !format || !dateFrom || !dateTo) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires.');
                return;
            }

            if (new Date(dateFrom) > new Date(dateTo)) {
                e.preventDefault();
                alert('La date de début ne peut pas être postérieure à la date de fin.');
                return;
            }

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Génération...';
            
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 3000);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // Ctrl/Cmd + R for new report
            if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
                event.preventDefault();
                openReportModal();
            }
            
            // Escape to close modals
            if (event.key === 'Escape') {
                closeModal('reportModal');
            }
        });

        // Enhanced interactions
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('click', function() {
                // Add ripple effect
                const ripple = document.createElement('div');
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.6)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple 0.6s linear';
                ripple.style.pointerEvents = 'none';
                
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
                ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';
                
                this.style.position = 'relative';
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });

        // Add ripple animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Responsive navigation toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('mobile-open');
        }

        // Add mobile styles
        const mobileStyles = document.createElement('style');
        mobileStyles.textContent = `
            @media (max-width: 768px) {
                .sidebar {
                    transform: translateX(-100%);
                    transition: transform 0.3s;
                    position: fixed;
                    z-index: 1000;
                    height: 100vh;
                }
                
                .sidebar.mobile-open {
                    transform: translateX(0);
                }
                
                .main-content {
                    width: 100%;
                }
                
                .header {
                    padding-left: 60px;
                }
                
                .header::before {
                    content: '☰';
                    position: absolute;
                    left: 20px;
                    top: 50%;
                    transform: translateY(-50%);
                    font-size: 18px;
                    cursor: pointer;
                    color: #64748b;
                }
            }
        `;
        document.head.appendChild(mobileStyles);

        // Mobile menu toggle
        if (window.innerWidth <= 768) {
            document.querySelector('.header').addEventListener('click', function(e) {
                if (e.target === this || e.clientX <= 60) {
                    toggleSidebar();
                }
            });
        }

        // Print functionality
        function printReport() {
            window.print();
        }

        // Export functionality
        function exportAllData() {
            alert('Fonctionnalité d\'export en cours de développement...');
        }

        // Advanced analytics toggle
        function toggleAdvancedView() {
            const advancedElements = document.querySelectorAll('.advanced-analytics');
            advancedElements.forEach(el => {
                el.style.display = el.style.display === 'none' ? 'block' : 'none';
            });
        }

        // Auto-refresh data every 5 minutes
        setInterval(function() {
            // You could implement automatic data refresh here
            console.log('Auto-refresh check...');
        }, 300000); // 5 minutes

        // Performance monitoring
        function trackReportGeneration(reportType) {
            const startTime = performance.now();
            
            return function() {
                const endTime = performance.now();
                console.log(`Rapport ${reportType} généré en ${endTime - startTime} ms`);
            };
        }



        // Initialize tooltips for icons
        function initTooltips() {
            document.querySelectorAll('[data-tooltip]').forEach(element => {
                element.addEventListener('mouseenter', function() {
                    // Show tooltip
                });
                element.addEventListener('mouseleave', function() {
                    // Hide tooltip
                });
            });
        }

        initTooltips();

        console.log('Syndic Reports Dashboard loaded successfully');
    </script>