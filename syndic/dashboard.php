<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in as syndic
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'syndic') {
    header('Location: ../public/login.php');
    exit();
}

$current_user = [
    'id' => $_SESSION['user_id'],
    'role' => $_SESSION['user_role'],
    'name' => $_SESSION['user_name'] ?? 'Syndic',
    'email' => $_SESSION['user_email'] ?? ''
];

// Initialize dashboard statistics
$stats = [
    'total_residents' => 0,
    'total_apartments' => 0,
    'pending_payments' => 0,
    'total_messages' => 0,
    'monthly_revenue' => 0,
    'pending_requests' => 0
];

$building_info = null;
$recent_activities = [];
$resident_data = [];

try {
    // FIXED: Get building information managed by this syndic
    // Option 1: If syndic is linked to residences through admin_member_link
    $stmt = $conn->prepare("
        SELECT r.*, c.city_name, COUNT(ap.id_apartment) as total_apartments
        FROM residence r
        JOIN city c ON r.id_city = c.id_city
        LEFT JOIN apartment ap ON ap.id_residence = r.id_residence
        JOIN admin_member_link aml ON aml.id_admin = ?
        WHERE r.id_residence IN (
            SELECT DISTINCT ap2.id_residence 
            FROM apartment ap2 
            JOIN admin_member_link aml2 ON ap2.id_member = aml2.id_member 
            WHERE aml2.id_admin = ?
        )
        GROUP BY r.id_residence
        LIMIT 1
    ");
    $stmt->execute([$current_user['id'], $current_user['id']]);
    $building_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // FALLBACK: If no results, get first available residence
    if (!$building_info) {
        $stmt = $conn->prepare("
            SELECT r.*, c.city_name, COUNT(ap.id_apartment) as total_apartments
            FROM residence r
            JOIN city c ON r.id_city = c.id_city
            LEFT JOIN apartment ap ON ap.id_residence = r.id_residence
            GROUP BY r.id_residence
            LIMIT 1
        ");
        $stmt->execute();
        $building_info = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($building_info) {
        $residence_id = $building_info['id_residence'];
        
        // Get total residents in this building (role = 1 means resident)
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT m.id_member) as total
            FROM member m
            JOIN apartment ap ON ap.id_member = m.id_member
            WHERE ap.id_residence = ? AND m.role = 1
        ");
        $stmt->execute([$residence_id]);
        $result = $stmt->fetch();
        $stats['total_residents'] = $result ? $result['total'] : 0;

        // Get total apartments
        $stats['total_apartments'] = $building_info['total_apartments'] ?? 0;

        // Get payments this month
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total
            FROM member_payments mp
            JOIN member m ON mp.id_payer = m.id_member
            JOIN apartment ap ON ap.id_member = m.id_member
            WHERE ap.id_residence = ? 
            AND MONTH(mp.date_payment) = MONTH(CURDATE())
            AND YEAR(mp.date_payment) = YEAR(CURDATE())
        ");
        $stmt->execute([$residence_id]);
        $result = $stmt->fetch();
        $stats['pending_payments'] = $result ? $result['total'] : 0;

        // Get messages count
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total
            FROM member_messages mm
            WHERE mm.id_receiver = ? OR mm.id_sender = ?
        ");
        $stmt->execute([$current_user['id'], $current_user['id']]);
        $result = $stmt->fetch();
        $stats['total_messages'] = $result ? $result['total'] : 0;

        // Calculate estimated monthly revenue
        $stats['monthly_revenue'] = $stats['total_residents'] * 150;

        // Get pending requests
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total
            FROM member_notifications mn
            WHERE mn.id_receiver = ?
        ");
        $stmt->execute([$current_user['id']]);
        $result = $stmt->fetch();
        $stats['pending_requests'] = $result ? $result['total'] : 0;

        // Get recent activities
        $stmt = $conn->prepare("
            (SELECT 'payment' as type, mp.date_payment as date,
                   m.full_name as user_name, 
                   CONCAT('Paiement pour ', DATE_FORMAT(mp.month_paid, '%M %Y')) as activity,
                   m.id_member
            FROM member_payments mp
            JOIN member m ON mp.id_payer = m.id_member
            JOIN apartment ap ON ap.id_member = m.id_member
            WHERE ap.id_residence = ?
            ORDER BY mp.date_payment DESC
            LIMIT 5)
            UNION ALL
            (SELECT 'message' as type, mm.date_message as date,
                   m.full_name as user_name, 'Nouveau message' as activity,
                   m.id_member
            FROM member_messages mm
            JOIN member m ON mm.id_sender = m.id_member
            WHERE mm.id_receiver = ?
            ORDER BY mm.date_message DESC
            LIMIT 5)
            ORDER BY date DESC
            LIMIT 10
        ");
        $stmt->execute([$residence_id, $current_user['id']]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process activities
        foreach ($activities as $activity) {
            $icon_class = 'create';
            $icon = 'fa-plus';
            
            switch($activity['type']) {
                case 'payment':
                    $icon_class = 'payment';
                    $icon = 'fa-credit-card';
                    break;
                case 'message':
                    $icon_class = 'update';
                    $icon = 'fa-envelope';
                    break;
            }
            
            $recent_activities[] = [
                'icon_class' => $icon_class,
                'icon' => $icon,
                'description' => $activity['activity'],
                'user_initials' => substr($activity['user_name'], 0, 2),
                'user_name' => $activity['user_name'],
                'time' => timeAgo($activity['date']),
                'tag' => ucfirst($activity['type'])
            ];
        }

        // Get residents data
        $stmt = $conn->prepare("
            SELECT 
                m.id_member,
                m.full_name,
                m.email,
                m.phone,
                m.status,
                m.date_created,
                ap.type as apartment_type,
                ap.floor,
                ap.number as apartment_number,
                COUNT(mp.id_payer) as payment_count
            FROM member m
            JOIN apartment ap ON ap.id_member = m.id_member
            LEFT JOIN member_payments mp ON mp.id_payer = m.id_member 
                AND YEAR(mp.date_payment) = YEAR(CURDATE())
            WHERE ap.id_residence = ? AND m.role = 1
            GROUP BY m.id_member, ap.id_apartment
            ORDER BY m.date_created DESC
            LIMIT 5
        ");
        $stmt->execute([$residence_id]);
        $resident_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Erreur de base de données: " . $e->getMessage();
    
    // Debug information (remove in production)
    echo "Debug: " . $e->getMessage();
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

$page_title = "Dashboard Syndic - " . ($building_info['name'] ?? 'Syndic Way');
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



        /* Building Header */
        .building-header {
            background: #FFCB32;
            color: white;
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .building-info h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .building-info p {
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 8px;
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
        .quick-card.apartments .quick-card-icon { background: #FFCB32; }
        .quick-card.payments .quick-card-icon { background: #f59e0b; }
        .quick-card.messages .quick-card-icon { background: #8b5cf6; }

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
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            justify-content : end;

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
            background:rgb(246, 192, 31);
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

        .activity-icon.create { background: #FFCB32; }
        .activity-icon.update { background: #FFCB32; }
        .activity-icon.payment { background: #f59e0b; }

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
            background: #ecfdf5;
            color: #FFCB32;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
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

            <!-- Building Header -->
            <?php if ($building_info): ?>
            <div class="building-header">
                <div class="building-info">
                    <h1><i class="fas fa-building"></i> <?php echo htmlspecialchars($building_info['name']); ?></h1>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($building_info['address']); ?>, <?php echo htmlspecialchars($building_info['city_name']); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Access -->
            <div class="quick-access">
                <div class="quick-access-header">
                    <div class="quick-access-title">Accès Rapide</div>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card residents">
                        <div class="quick-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="quick-card-title">Résidents</div>
                        <div class="quick-card-count"><?php echo $stats['total_residents']; ?></div>
                        <div class="quick-card-stats">Actifs dans l'immeuble</div>
                    </div>

                    <div class="quick-card apartments">
                        <div class="quick-card-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="quick-card-title">Appartements</div>
                        <div class="quick-card-count"><?php echo $stats['total_apartments']; ?></div>
                        <div class="quick-card-stats">Total logements</div>
                    </div>

                    <div class="quick-card payments">
                        <div class="quick-card-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="quick-card-title">Paiements</div>
                        <div class="quick-card-count"><?php echo $stats['pending_payments']; ?></div>
                        <div class="quick-card-stats">Ce mois</div>
                    </div>

                    <div class="quick-card messages">
                        <div class="quick-card-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="quick-card-title">Messages</div>
                        <div class="quick-card-count"><?php echo $stats['total_messages']; ?></div>
                        <div class="quick-card-stats">Conversations</div>
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
                        <a href="#">Gestion Résidence</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Résidents</span>
                    </div>

                    <!-- Table Header -->
                    <div class="table-header">
                        <a href="residents.php?action=create" class="add-btn">
                            <i class="fas fa-plus"></i>
                            Ajouter Résident
                        </a>
                    </div>

                    <!-- Data Table -->
                    <table class="data-table">
                        <thead class="table-header-row">
                            <tr>
                                <th>Résident</th>
                                <th>Contact</th>
                                <th>Appartement</th>
                                <th>Statut</th>
                                <th>Paiements</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($resident_data)): ?>
                            <tr class="table-row">
                                <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                                    <i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                                    <div>Aucun résident trouvé</div>
                                    <div style="font-size: 12px; margin-top: 8px;">Ajoutez vos premiers résidents pour commencer</div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($resident_data as $resident): ?>
                                <tr class="table-row">
                                    <td>
                                        <div class="file-item">
                                            <div class="table-icon" style="background: #FFCB32;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="file-info">
                                                <div class="file-name"><?php echo htmlspecialchars($resident['full_name']); ?></div>
                                                <div style="font-size: 12px; color: #64748b;">Depuis <?php echo date('M Y', strtotime($resident['date_created'])); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 14px;"><?php echo htmlspecialchars($resident['email']); ?></div>
                                        <?php if ($resident['phone']): ?>
                                            <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($resident['phone']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;">Apt <?php echo $resident['apartment_number']; ?></div>
                                        <div style="font-size: 12px; color: #64748b;">
                                            <?php echo htmlspecialchars($resident['apartment_type']); ?> - Étage <?php echo $resident['floor']; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo 'status-' . $resident['status']; ?>">
                                            <?php echo ucfirst($resident['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="text-align: center;">
                                            <span style="font-weight: 600;"><?php echo $resident['payment_count']; ?></span>
                                            <div style="font-size: 12px; color: #64748b;">cette année</div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Management summary rows -->
                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #FFCB32;">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Rapport Mensuel</div>
                                            <div style="font-size: 12px; color: #64748b;">Statistiques de gestion</div>
                                        </div>
                                    </div>
                                </td>
                                <td>Automatique</td>
                                <td><?php echo number_format($stats['monthly_revenue']); ?> DH</td>
                                <td><span class="status-badge status-active">Généré</span></td>
                                <td>Aujourd'hui</td>
                           
                            </tr>

                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #f59e0b;">
                                            <i class="fas fa-home"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Gestion Appartements</div>
                                            <div style="font-size: 12px; color: #64748b;">Configuration logements</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="sharing-avatars">
                                        <div class="sharing-avatar">SY</div>
                                    </div>
                                </td>
                                <td><?php echo $stats['total_apartments']; ?> logements</td>
                                <td><span class="status-badge status-active">Actif</span></td>
                                <td>Configuré</td>
                               
                            </tr>

                            <?php if ($stats['pending_requests'] > 0): ?>
                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #ef4444;">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Demandes en Attente</div>
                                            <div style="font-size: 12px; color: #64748b;">Nécessite une action</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="sharing-avatars">
                                        <div class="sharing-avatar" style="background: #ef4444;">!</div>
                                    </div>
                                </td>
                                <td><?php echo $stats['pending_requests']; ?> demandes</td>
                                <td><span class="status-badge status-pending">En attente</span></td>
                                <td>Urgent</td>
                             
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

                    <?php if (empty($recent_activities)): ?>
                        <div style="text-align: center; padding: 40px; color: #64748b;">
                            <i class="fas fa-clock" style="font-size: 32px; margin-bottom: 12px; opacity: 0.3;"></i>
                            <div style="font-size: 14px;">Aucune activité récente</div>
                            <div style="font-size: 12px; margin-top: 4px;">Les nouvelles activités apparaîtront ici</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $activity['icon_class']; ?>">
                                <i class="fas <?php echo $activity['icon']; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text"><?php echo htmlspecialchars($activity['description']); ?></div>
                                <div class="activity-time"><?php echo $activity['time']; ?></div>
                                <div class="activity-meta">
                                    <div class="sharing-avatar"><?php echo strtoupper($activity['user_initials']); ?></div>
                                    <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($activity['user_name']); ?></span>
                                    <div class="tag"><?php echo $activity['tag']; ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                
                // Add actual navigation logic here
                switch(cardType) {
                    case 'residents':
                        window.location.href = 'residents.php';
                        break;
                    case 'apartments':
                        window.location.href = 'apartments.php';
                        break;
                    case 'payments':
                        window.location.href = 'payments.php';
                        break;
                    case 'messages':
                        window.location.href = 'messages.php';
                        break;
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

       

        // Storage animation on load
        window.addEventListener('load', function() {
            const storageFill = document.querySelector('.storage-fill');
            const originalWidth = storageFill.style.width;
            storageFill.style.width = '0%';
            setTimeout(() => {
                storageFill.style.width = originalWidth;
            }, 500);
        });

        // Auto-refresh data every 5 minutes
        setInterval(function() {
            // You can add AJAX calls here to refresh statistics
            console.log('Auto-refresh data...');
        }, 300000);

        // Notification bell functionality
        document.querySelector('.fa-bell').addEventListener('click', function() {
            alert('Notifications: ' + <?php echo $stats['pending_requests']; ?> + ' demandes en attente');
        });

        // Welcome message for new syndics
        window.addEventListener('load', function() {
            <?php if ($stats['total_residents'] == 0): ?>
                setTimeout(() => {
                    if (confirm('Bienvenue ! Voulez-vous ajouter vos premiers résidents maintenant ?')) {
                        window.location.href = 'residents.php?action=create';
                    }
                }, 2000);
            <?php endif; ?>
        });

        // Real-time clock in building header
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
            // Update page title with current time
            document.title = `<?php echo $page_title; ?> - ${timeString}`;
        }
        
        setInterval(updateClock, 1000);
        updateClock(); // Initial call

        // Simulate real-time updates for demo
        let updateCounter = 0;
        setInterval(() => {
            updateCounter++;
            
            // Simulate new message every 2 minutes
            if (updateCounter % 24 === 0) {
                const messageCard = document.querySelector('.quick-card.messages .quick-card-count');
                if (messageCard) {
                    const currentCount = parseInt(messageCard.textContent);
                    messageCard.textContent = currentCount + 1;
                    
                    // Show notification
                    const notification = document.createElement('div');
                    notification.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: #FFCB32;
                        color: white;
                        padding: 12px 20px;
                        border-radius: 8px;
                        z-index: 1000;
                        font-size: 14px;
                        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                        animation: slideInRight 0.3s ease;
                    `;
                    notification.innerHTML = '<i class="fas fa-envelope"></i> Nouveau message reçu';
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.style.animation = 'slideOutRight 0.3s ease';
                        setTimeout(() => notification.remove(), 300);
                    }, 3000);
                }
            }
        }, 5000); // Every 5 seconds

        // Add animation styles
        const animationStyles = document.createElement('style');
        animationStyles.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .quick-card, .table-row, .activity-item {
                animation: fadeInUp 0.6s ease;
            }
            
            .quick-card:nth-child(1) { animation-delay: 0.1s; }
            .quick-card:nth-child(2) { animation-delay: 0.2s; }
            .quick-card:nth-child(3) { animation-delay: 0.3s; }
            .quick-card:nth-child(4) { animation-delay: 0.4s; }
        `;
        document.head.appendChild(animationStyles);

        // Enhanced keyboard navigation
        document.addEventListener('keydown', function(e) {
            // Alt + 1-4 for quick navigation
            if (e.altKey && e.key >= '1' && e.key <= '4') {
                e.preventDefault();
                const quickCards = document.querySelectorAll('.quick-card');
                const index = parseInt(e.key) - 1;
                if (quickCards[index]) {
                    quickCards[index].click();
                }
            }
            
            // Ctrl + K for search focus
            if (e.ctrlKey && e.key === 'k') {
                e.preventDefault();
                document.querySelector('.search-box input').focus();
            }
        });

        // Tooltip system for better UX
        document.querySelectorAll('[title]').forEach(element => {
            element.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.textContent = this.getAttribute('title');
                tooltip.style.cssText = `
                    position: absolute;
                    background: #1e293b;
                    color: white;
                    padding: 6px 10px;
                    border-radius: 4px;
                    font-size: 12px;
                    z-index: 1000;
                    pointer-events: none;
                    white-space: nowrap;
                `;
                
                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + 'px';
                tooltip.style.top = (rect.top - 35) + 'px';
                
                document.body.appendChild(tooltip);
                this._tooltip = tooltip;
            });
            
            element.addEventListener('mouseleave', function() {
                if (this._tooltip) {
                    this._tooltip.remove();
                    this._tooltip = null;
                }
            });
        });
    </script>
</body>
</html>