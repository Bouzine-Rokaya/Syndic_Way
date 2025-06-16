<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if includes/auth.php exists, if not, use basic role check
if (file_exists(__DIR__ . '/../includes/auth.php')) {
    require_once __DIR__ . '/../includes/auth.php';
    requireRole('syndic');
    $current_user = getCurrentUser();
} else {
    // Fallback to basic check if auth.php doesn't exist yet
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
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_apartment') {
            $apartment_number = intval($_POST['apartment_number']);
            $apartment_floor = trim($_POST['apartment_floor']);
            $apartment_type = trim($_POST['apartment_type']);
            $resident_id = !empty($_POST['resident_id']) ? intval($_POST['resident_id']) : null;
            
            // Get first available building
            $stmt = $conn->prepare("SELECT id_residence FROM residence LIMIT 1");
            $stmt->execute();
            $building = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$building) {
                throw new Exception("Aucun bâtiment disponible dans le système.");
            }
            
            // Check if apartment number already exists in this building
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM apartment 
                WHERE id_residence = ? AND number = ? AND floor = ?
            ");
            $stmt->execute([$building['id_residence'], $apartment_number, $apartment_floor]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cet appartement existe déjà dans ce bâtiment.");
            }
            
            // If no resident selected, create empty apartment with temporary member
            $member_id = $resident_id ?: $current_user['id']; // Temporarily assign to syndic
            
            $stmt = $conn->prepare("
                INSERT INTO apartment (id_residence, id_member, type, floor, number) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$building['id_residence'], $member_id, $apartment_type, $apartment_floor, $apartment_number]);
            
            $_SESSION['success'] = "Appartement créé avec succès.";
            
        } elseif ($action === 'update_apartment') {
            $apartment_id = intval($_POST['apartment_id']);
            $apartment_number = intval($_POST['apartment_number']);
            $apartment_floor = trim($_POST['apartment_floor']);
            $apartment_type = trim($_POST['apartment_type']);
            $resident_id = !empty($_POST['resident_id']) ? intval($_POST['resident_id']) : null;
            
            // Verify this apartment exists
            $stmt = $conn->prepare("SELECT id_apartment FROM apartment WHERE id_apartment = ?");
            $stmt->execute([$apartment_id]);
            $apartment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$apartment) {
                throw new Exception("Appartement non trouvé.");
            }
            
            $member_id = $resident_id ?: $current_user['id'];
            
            $stmt = $conn->prepare("
                UPDATE apartment 
                SET type = ?, floor = ?, number = ?, id_member = ?
                WHERE id_apartment = ?
            ");
            $stmt->execute([$apartment_type, $apartment_floor, $apartment_number, $member_id, $apartment_id]);
            
            $_SESSION['success'] = "Appartement mis à jour avec succès.";
            
        } elseif ($action === 'assign_resident') {
            $apartment_id = intval($_POST['apartment_id']);
            $resident_id = intval($_POST['resident_id']);
            
            $stmt = $conn->prepare("UPDATE apartment SET id_member = ? WHERE id_apartment = ?");
            $stmt->execute([$resident_id, $apartment_id]);
            
            $_SESSION['success'] = "Résident assigné à l'appartement avec succès.";
            
        } elseif ($action === 'delete_apartment') {
            $apartment_id = intval($_POST['apartment_id']);
            
            $stmt = $conn->prepare("DELETE FROM apartment WHERE id_apartment = ?");
            $stmt->execute([$apartment_id]);
            
            $_SESSION['success'] = "Appartement supprimé avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header('Location: apartments.php');
    exit();
}

// Get filters
$search = $_GET['search'] ?? '';
$floor_filter = $_GET['floor'] ?? '';
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Get apartments statistics and data
try {
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(ap.number LIKE ? OR ap.type LIKE ? OR m.full_name LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if ($floor_filter) {
        $where_conditions[] = "ap.floor = ?";
        $params[] = $floor_filter;
    }
    
    if ($type_filter) {
        $where_conditions[] = "ap.type = ?";
        $params[] = $type_filter;
    }
    
    if ($status_filter) {
        if ($status_filter === 'occupied') {
            $where_conditions[] = "m.role = 1";
        } elseif ($status_filter === 'vacant') {
            $where_conditions[] = "m.role != 1";
        }
    }
    
    $where_clause = !empty($where_conditions) ? " WHERE " . implode(" AND ", $where_conditions) : "";
    
    $query = "
        SELECT 
            ap.id_apartment,
            ap.number as apartment_number,
            ap.floor,
            ap.type as apartment_type,
            m.id_member,
            m.full_name as resident_name,
            m.email as resident_email,
            m.phone as resident_phone,
            m.role,
            r.name as building_name
        FROM apartment ap
        JOIN residence r ON r.id_residence = ap.id_residence
        LEFT JOIN member m ON ap.id_member = m.id_member
        {$where_clause}
        ORDER BY ap.floor ASC, ap.number ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $apartments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $total_apartments = count($apartments);
    $occupied_count = 0;
    $vacant_count = 0;
    $floors_count = 0;
    
    $floors_list = [];
    foreach ($apartments as $apartment) {
        if ($apartment['role'] == 1) $occupied_count++;
        else $vacant_count++;
        
        if (!in_array($apartment['floor'], $floors_list)) {
            $floors_list[] = $apartment['floor'];
        }
    }
    $floors_count = count($floors_list);
    
    // Get available residents for assignment
    $stmt = $conn->prepare("
        SELECT m.id_member, m.full_name, m.email
        FROM member m
        WHERE m.role = 1
        ORDER BY m.full_name ASC
    ");
    $stmt->execute();
    $available_residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get building info
    $stmt = $conn->prepare("
        SELECT r.name, r.address, c.city_name
        FROM residence r
        JOIN city c ON c.id_city = r.id_city
        LIMIT 1
    ");
    $stmt->execute();
    $building_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get activity data for recent apartment changes
    $recent_activities = [
        [
            'icon_class' => 'create',
            'icon' => 'fa-plus',
            'description' => 'Appartement créé',
            'user_initials' => substr($current_user['name'], 0, 2),
            'user_name' => $current_user['name'],
            'time' => 'Il y a 2h',
            'tag' => 'Nouveau'
        ],
        [
            'icon_class' => 'update',
            'icon' => 'fa-edit',
            'description' => 'Résident assigné',
            'user_initials' => substr($current_user['name'], 0, 2),
            'user_name' => $current_user['name'],
            'time' => 'Il y a 4h',
            'tag' => 'Assignation'
        ]
    ];
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors du chargement des appartements.";
    $apartments = [];
    $available_residents = [];
    $building_info = null;
    $recent_activities = [];
    $total_apartments = 0;
    $occupied_count = 0;
    $vacant_count = 0;
    $floors_count = 0;
}

$page_title = "Gestion des Appartements - Syndic Way";
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

        .quick-card.total .quick-card-icon { background: #FFCB32; }
        .quick-card.occupied .quick-card-icon { background: #10b981; }
        .quick-card.vacant .quick-card-icon { background: #f59e0b; }
        .quick-card.floors .quick-card-icon { background: #8b5cf6; }

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
            justify-content:end;
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

        .status-occupied {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-vacant {
            background: #fef3c7;
            color: #d97706;
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

        .activity-icon.create { background: #10b981; }
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
            background: #eff6ff;
            color: #FFCB32;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 90%;
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
        }

        .modal-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .modal-actions {
            padding: 20px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #FFCB32;
            color: white;
        }

        .btn-primary:hover {
            background:rgb(255, 229, 152);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .required {
            color: #ef4444;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #374151;
        }

        .empty-state p {
            margin-bottom: 20px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
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

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            .main-content {
                order: 1;
            }
            
            .quick-access-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .header {
                flex-direction: column;
                height: auto;
                padding: 16px;
                gap: 16px;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .table-row {
            transition: all 0.2s ease;
        }

        .table-row:hover {
            transform: translateX(2px);
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
                    <div class="quick-access-title">Vue d'ensemble des appartements</div>
                    <?php if ($building_info): ?>
                        <div style="font-size: 14px; color:  #FFCB32;">
                             <?php echo htmlspecialchars($building_info['name']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card total">
                        <div class="quick-card-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="quick-card-title">Total Appartements</div>
                        <div class="quick-card-count"><?php echo $total_apartments; ?></div>
                        <div class="quick-card-stats">Dans l'immeuble</div>
                    </div>

                    <div class="quick-card occupied">
                        <div class="quick-card-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="quick-card-title">Appartements Occupés</div>
                        <div class="quick-card-count"><?php echo $occupied_count; ?></div>
                        <div class="quick-card-stats"><?php echo $total_apartments > 0 ? round(($occupied_count/$total_apartments)*100) : 0; ?>% d'occupation</div>
                    </div>

                    <div class="quick-card vacant">
                        <div class="quick-card-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="quick-card-title">Appartements Vacants</div>
                        <div class="quick-card-count"><?php echo $vacant_count; ?></div>
                        <div class="quick-card-stats">Disponibles</div>
                    </div>

                    <div class="quick-card floors">
                        <div class="quick-card-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="quick-card-title">Étages</div>
                        <div class="quick-card-count"><?php echo $floors_count; ?></div>
                        <div class="quick-card-stats">Dans l'immeuble</div>
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
                        <a href="#">Gestion Immeuble</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Appartements</span>
                    </div>

                    <!-- Table Header -->
                    <div class="table-header">
                        <button class="add-btn" onclick="openCreateModal()">
                            <i class="fas fa-plus"></i>
                            Nouvel Appartement
                        </button>
                    </div>

                    <!-- Data Table -->
                    <table class="data-table" id="apartmentsTable">
                        <thead class="table-header-row">
                            <tr>
                                <th>Appartement</th>
                                <th>Résident</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Dernière Modification</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($apartments)): ?>
                            <tr class="table-row">
                                <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                                    <i class="fas fa-home" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                                    <div>Aucun appartement trouvé</div>
                                    <div style="font-size: 12px; margin-top: 8px;">Créez votre premier appartement pour commencer</div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($apartments as $apartment): ?>
                                <tr class="table-row">
                                    <td>
                                        <div class="file-item">
                                            <div class="table-icon" style="background: <?php echo $apartment['role'] == 1 ? '#10b981' : '#f59e0b'; ?>;">
                                                <i class="fas fa-home"></i>
                                            </div>
                                            <div class="file-info">
                                                <div class="file-name">Apt. <?php echo htmlspecialchars($apartment['apartment_number']); ?></div>
                                                <div style="font-size: 12px; color: #64748b;">Étage <?php echo htmlspecialchars($apartment['floor']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($apartment['role'] == 1): ?>
                                            <div class="sharing-avatars">
                                                <div class="sharing-avatar"><?php echo strtoupper(substr($apartment['resident_name'], 0, 2)); ?></div>
                                                <span style="margin-left: 8px; font-size: 14px;"><?php echo htmlspecialchars($apartment['resident_name']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #64748b; font-style: italic;">Vacant</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($apartment['apartment_type']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $apartment['role'] == 1 ? 'status-occupied' : 'status-vacant'; ?>">
                                            <?php echo $apartment['role'] == 1 ? 'Occupé' : 'Vacant'; ?>
                                        </span>
                                    </td>
                                    <td>Aujourd'hui</td>
                                    <td>
                                        <div style="display: flex; gap: 8px;">
                                            <button class="btn btn-sm btn-secondary" onclick="editApartment(<?php echo htmlspecialchars(json_encode($apartment)); ?>)" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($apartment['role'] != 1): ?>
                                                <button class="btn btn-sm btn-success" onclick="assignResident(<?php echo $apartment['id_apartment']; ?>)" title="Assigner résident">
                                                    <i class="fas fa-user-plus"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-danger" onclick="deleteApartment(<?php echo $apartment['id_apartment']; ?>, '<?php echo $apartment['apartment_number']; ?>')" title="Supprimer">
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
                            <div style="font-size: 12px; margin-top: 4px;">Les modifications apparaîtront ici</div>
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

    <!-- Create/Edit Apartment Modal -->
    <div id="apartmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">
                    <i class="fas fa-plus"></i>
                    <span id="modalTitleText">Nouvel appartement</span>
                </h2>
                <button class="close" onclick="closeModal('apartmentModal')">&times;</button>
            </div>
            
            <form id="apartmentForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create_apartment">
                    <input type="hidden" name="apartment_id" id="apartmentId">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="apartment_number">
                                Numéro d'appartement <span class="required">*</span>
                            </label>
                            <input type="number" name="apartment_number" id="apartment_number" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="apartment_floor">
                                Étage <span class="required">*</span>
                            </label>
                            <input type="text" name="apartment_floor" id="apartment_floor" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="apartment_type">
                                Type d'appartement <span class="required">*</span>
                            </label>
                            <select name="apartment_type" id="apartment_type" required>
                                <option value="">Choisir un type</option>
                                <option value="Studio">Studio</option>
                                <option value="T1">T1</option>
                                <option value="T2">T2</option>
                                <option value="T3">T3</option>
                                <option value="T4">T4</option>
                                <option value="T5">T5</option>
                                <option value="Duplex">Duplex</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="resident_id">
                                Résident (optionnel)
                            </label>
                            <select name="resident_id" id="resident_id">
                                <option value="">Appartement vacant</option>
                                <?php foreach ($available_residents as $resident): ?>
                                    <option value="<?php echo $resident['id_member']; ?>">
                                        <?php echo htmlspecialchars($resident['full_name']); ?> (<?php echo htmlspecialchars($resident['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('apartmentModal')">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> <span id="submitText">Créer l'appartement</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Resident Modal -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-user-plus"></i>
                    Assigner un résident
                </h2>
                <button class="close" onclick="closeModal('assignModal')">&times;</button>
            </div>
            
            <form id="assignForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_resident">
                    <input type="hidden" name="apartment_id" id="assignApartmentId">
                    
                    <div class="form-group">
                        <label for="assign_resident_id">
                            Résident <span class="required">*</span>
                        </label>
                        <select name="resident_id" id="assign_resident_id" required>
                            <option value="">Sélectionner un résident</option>
                            <?php foreach ($available_residents as $resident): ?>
                                <option value="<?php echo $resident['id_member']; ?>">
                                    <?php echo htmlspecialchars($resident['full_name']); ?> (<?php echo htmlspecialchars($resident['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('assignModal')">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-user-plus"></i> Assigner
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden forms for actions -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_apartment">
        <input type="hidden" name="apartment_id" id="deleteApartmentId">
    </form>

    <script>
        // Modal functions
        function openCreateModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> <span>Nouvel appartement</span>';
            document.getElementById('formAction').value = 'create_apartment';
            document.getElementById('apartmentId').value = '';
            document.getElementById('submitText').textContent = 'Créer l\'appartement';
            document.getElementById('apartmentForm').reset();
            document.getElementById('apartmentModal').classList.add('show');
        }

        function editApartment(apartment) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> <span>Modifier l\'appartement</span>';
            document.getElementById('formAction').value = 'update_apartment';
            document.getElementById('apartmentId').value = apartment.id_apartment;
            document.getElementById('apartment_number').value = apartment.apartment_number;
            document.getElementById('apartment_floor').value = apartment.floor;
            document.getElementById('apartment_type').value = apartment.apartment_type;
            document.getElementById('resident_id').value = apartment.role == 1 ? apartment.id_member : '';
            document.getElementById('submitText').textContent = 'Mettre à jour';
            document.getElementById('apartmentModal').classList.add('show');
        }

        function assignResident(apartmentId) {
            document.getElementById('assignApartmentId').value = apartmentId;
            document.getElementById('assignModal').classList.add('show');
        }

        function deleteApartment(apartmentId, apartmentNumber) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer l'appartement ${apartmentNumber} ?\n\nCette action est irréversible.`)) {
                document.getElementById('deleteApartmentId').value = apartmentId;
                document.getElementById('deleteForm').submit();
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Search functionality
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const table = document.getElementById('apartmentsTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const text = row.textContent.toLowerCase();
                
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }

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
                console.log(`Filtering by ${cardType}`);
                // Add filtering logic here
            });
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
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

            // Storage animation on load
            const storageFill = document.querySelector('.storage-fill');
            const originalWidth = storageFill.style.width;
            storageFill.style.width = '0%';
            setTimeout(() => {
                storageFill.style.width = originalWidth;
            }, 500);
        });

        // Table hover effects
        document.querySelectorAll('.table-row').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8fafc';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });

        // Enhanced form validation
        document.getElementById('apartmentForm').addEventListener('submit', function(e) {
            const apartmentNumber = document.getElementById('apartment_number').value;
            const apartmentFloor = document.getElementById('apartment_floor').value;
            const apartmentType = document.getElementById('apartment_type').value;
            
            if (!apartmentNumber || !apartmentFloor || !apartmentType) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires.');
                return;
            }
            
            if (apartmentNumber < 1) {
                e.preventDefault();
                alert('Le numéro d\'appartement doit être supérieur à 0.');
                return;
            }
        });
    </script>
</body>
</html>