<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_admin') {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $password = trim($_POST['password']);
            
            // Check if email exists in admin table
            $stmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Un admin avec cet email existe déjà.");
            }
            
            // Check if email exists in member table
            $stmt = $conn->prepare("SELECT COUNT(*) FROM member WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Un utilisateur avec cet email existe déjà.");
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admin (name, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hashed_password]);
            
            $_SESSION['success'] = "Administrateur créé avec succès.";
            
        } elseif ($action === 'create_member') {
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $role = intval($_POST['role']);
            $password = trim($_POST['password']);
            
            // Check if email exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM member WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Un membre avec cet email existe déjà.");
            }
            
            $stmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Un admin avec cet email existe déjà.");
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO member (full_name, email, password, phone, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$full_name, $email, $hashed_password, $phone, $role]);
            
            $_SESSION['success'] = "Membre créé avec succès.";
            
        } elseif ($action === 'update_member_status') {
            $member_id = intval($_POST['member_id']);
            $new_status = $_POST['new_status'];
            
            $stmt = $conn->prepare("UPDATE member SET status = ? WHERE id_member = ?");
            $stmt->execute([$new_status, $member_id]);
            
            $_SESSION['success'] = "Statut mis à jour avec succès.";
            
        } elseif ($action === 'delete_admin') {
            $admin_id = intval($_POST['admin_id']);
            
            // Check if trying to delete current admin
            if ($admin_id == $_SESSION['user_id']) {
                throw new Exception("Vous ne pouvez pas supprimer votre propre compte.");
            }
            
            // Check if admin has dependencies
            $stmt = $conn->prepare("SELECT COUNT(*) FROM admin_member_link WHERE id_admin = ?");
            $stmt->execute([$admin_id]);
            $link_count = $stmt->fetchColumn();
            
            if ($link_count > 0) {
                throw new Exception("Impossible de supprimer cet admin. Il gère actuellement {$link_count} syndics.");
            }
            
            $stmt = $conn->prepare("DELETE FROM admin WHERE id_admin = ?");
            $stmt->execute([$admin_id]);
            
            $_SESSION['success'] = "Administrateur supprimé avec succès.";
            
        } elseif ($action === 'delete_member') {
            $member_id = intval($_POST['member_id']);
            
            $conn->beginTransaction();
            
            // Delete related records first
            $stmt = $conn->prepare("DELETE FROM admin_member_subscription WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $stmt = $conn->prepare("DELETE FROM admin_member_link WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $stmt = $conn->prepare("DELETE FROM apartment WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $stmt = $conn->prepare("DELETE FROM member_messages WHERE id_sender = ? OR id_receiver = ?");
            $stmt->execute([$member_id, $member_id]);
            
            $stmt = $conn->prepare("DELETE FROM member_payments WHERE id_payer = ? OR id_receiver = ?");
            $stmt->execute([$member_id, $member_id]);
            
            $stmt = $conn->prepare("DELETE FROM member_announcements WHERE id_poster = ? OR id_receiver = ?");
            $stmt->execute([$member_id, $member_id]);
            
            $stmt = $conn->prepare("DELETE FROM member_notifications WHERE id_sender = ? OR id_receiver = ?");
            $stmt->execute([$member_id, $member_id]);
            
            $stmt = $conn->prepare("DELETE FROM member WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $conn->commit();
            $_SESSION['success'] = "Membre supprimé avec succès.";
            
        } elseif ($action === 'reset_password') {
            $user_type = $_POST['user_type'];
            $user_id = intval($_POST['user_id']);
            $new_password = 'password123';
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            if ($user_type === 'admin') {
                $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id_admin = ?");
                $stmt->execute([$hashed_password, $user_id]);
            } else {
                $stmt = $conn->prepare("UPDATE member SET password = ? WHERE id_member = ?");
                $stmt->execute([$hashed_password, $user_id]);
            }
            
            $_SESSION['success'] = "Mot de passe réinitialisé. Nouveau mot de passe: password123";
        }
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header('Location: users.php');
    exit();
}

// Get filters - only apply if form was submitted
$search = '';
$role_filter = '';
$status_filter = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    $search = $_GET['search'] ?? '';
    $role_filter = $_GET['role'] ?? '';
    $status_filter = $_GET['status'] ?? '';
}

// Get all users (admins and members)
try {
    // Initialize arrays
    $admins = [];
    $members = [];
    
    // Only get admins if no role filter or if admin role is selected
    if (empty($role_filter) || $role_filter === 'admin') {
        $admin_query = "SELECT id_admin as user_id, name as full_name, email, 'admin' as user_type, 'active' as status, NULL as phone, NULL as role_name, NULL as company_name, NULL as city_name, NULL as date_created FROM admin";
        $admin_params = [];
        
        if ($search) {
            $admin_query .= " WHERE (name LIKE ? OR email LIKE ?)";
            $search_param = "%$search%";
            $admin_params = [$search_param, $search_param];
        }
        
        $stmt = $conn->prepare($admin_query);
        $stmt->execute($admin_params);
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Only get members if no role filter or if member/syndic/resident role is selected
    if (empty($role_filter) || in_array($role_filter, ['member', 'syndic', 'resident'])) {
        $member_query = "
            SELECT 
                m.id_member as user_id,
                m.full_name,
                m.email,
                m.phone,
                m.status,
                m.date_created,
                'member' as user_type,
                CASE 
                    WHEN m.role = 1 THEN 'Résident'
                    WHEN m.role = 2 THEN 'Syndic'
                    ELSE 'Autre'
                END as role_name,
                r.name as company_name,
                c.city_name
            FROM member m
            LEFT JOIN apartment ap ON ap.id_member = m.id_member
            LEFT JOIN residence r ON r.id_residence = ap.id_residence
            LEFT JOIN city c ON c.id_city = r.id_city
        ";
        
        $member_where_conditions = [];
        $member_params = [];
        
        if ($search) {
            $member_where_conditions[] = "(m.full_name LIKE ? OR m.email LIKE ? OR r.name LIKE ?)";
            $search_param = "%$search%";
            $member_params = array_merge($member_params, [$search_param, $search_param, $search_param]);
        }
        
        if ($status_filter && $status_filter !== 'admin') {
            $member_where_conditions[] = "m.status = ?";
            $member_params[] = $status_filter;
        }
        
        // Filter by specific member role
        if ($role_filter === 'syndic') {
            $member_where_conditions[] = "m.role = 2";
        } elseif ($role_filter === 'resident') {
            $member_where_conditions[] = "m.role = 1";
        }
        
        if ($member_where_conditions) {
            $member_query .= " WHERE " . implode(" AND ", $member_where_conditions);
        }
        
        $member_query .= " ORDER BY m.date_created DESC";
        
        $stmt = $conn->prepare($member_query);
        $stmt->execute($member_params);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Combine and sort all users
    $all_users = array_merge($admins, $members);
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors du chargement des utilisateurs.";
    $all_users = [];
}

$page_title = "Gestion des Utilisateurs - Syndic Way";
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/sections/dashboard.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--color-white);
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-card.admin-stat i {
            color: var(--primary-color);
        }

        .stat-card.syndic-stat i {
            color: var(--color-yellow);
        }

        .stat-card.resident-stat i {
            color: var(--color-green);
        }

        .stat-card.total-stat i {
            color: var(--color-dark-grey);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--color-dark-grey);
        }

        .stat-label {
            color: var(--color-grey);
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .filters-section {
            background: var(--color-white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1.5rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            color: var(--color-dark-grey);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 1rem;
            border: 2px solid var(--color-light-grey);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--color-yellow);
            box-shadow: 0 0 0 4px rgba(244, 185, 66, 0.15);
        }

        .users-table {
            background: var(--color-white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .table-header {
            background: linear-gradient(135deg, var(--color-yellow), var(--primary-color));
            color: var(--color-white);
            padding: 1.5rem;
        }

        .table-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 1.2rem;
            text-align: left;
            border-bottom: 1px solid var(--color-light-grey);
        }

        .data-table th {
            background: var(--color-light-grey);
            font-weight: 700;
            color: var(--color-dark-grey);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table tr:hover {
            background: rgba(244, 185, 66, 0.05);
        }

        .user-type-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .type-admin {
            background: linear-gradient(135deg, var(--primary-color), #2c5282);
            color: var(--color-white);
        }

        .type-syndic {
            background: linear-gradient(135deg, var(--color-yellow), #f39c12);
            color: var(--color-white);
        }

        .type-resident {
            background: linear-gradient(135deg, var(--color-green), #20c997);
            color: var(--color-white);
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-active {
            background: var(--color-green);
            color: var(--color-white);
        }

        .status-pending {
            background: var(--color-yellow);
            color: var(--color-white);
        }

        .status-inactive {
            background: var(--color-grey);
            color: var(--color-white);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.75rem;
            border-radius: 6px;
            min-width: 80px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
        }

        .modal.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background-color: var(--color-white);
            margin: 2% auto;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
            animation: slideInUp 0.3s ease;
            max-height: 95vh;
            overflow-y: auto;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--color-yellow), var(--primary-color));
            color: var(--color-white);
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .close {
            color: var(--color-white);
            font-size: 2rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close:hover {
            background-color: rgba(255,255,255,0.2);
        }

        .modal form {
            padding: 2rem;
        }

        .form-tabs {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--color-light-grey);
        }

        .tab-button {
            flex: 1;
            padding: 1rem;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: var(--color-grey);
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }

        .tab-button.active {
            color: var(--color-yellow);
            border-bottom-color: var(--color-yellow);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--color-dark-grey);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--color-light-grey);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--color-yellow);
            box-shadow: 0 0 0 4px rgba(244, 185, 66, 0.15);
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid var(--color-light-grey);
        }

        .required {
            color: var(--primary-color);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInUp {
            from { 
                opacity: 0;
                transform: translateY(50px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .header-actions {
                width: 100%;
                justify-content: stretch;
            }

            .header-actions .btn {
                flex: 1;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-brand">
            <h2><i class="fas fa-shield-alt"></i> Admin Panel</h2>
        </div>
        <div class="nav-user">
            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
            <a href="logout.php" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Navigation</h3>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Tableau de bord
                        </a>
                    </li>
                    <li>
                        <a href="subscriptions.php">
                            <i class="fas fa-tags"></i> Abonnements
                        </a>
                    </li>
                    <li>
                        <a href="syndic-accounts.php">
                            <i class="fas fa-building"></i> Comptes Syndic
                        </a>
                    </li>
                    <li class="active">
                        <a href="users.php">
                            <i class="fas fa-users"></i> Utilisateurs
                        </a>
                    </li>
                    <li>
                        <a href="purchases.php">
                            <i class="fas fa-shopping-cart"></i> Achats
                        </a>
                    </li>
                    <li>
                        <a href="reports.php">
                            <i class="fas fa-chart-bar"></i> Rapports
                        </a>
                    </li>
                    <li>
                        <a href="settings.php">
                            <i class="fas fa-cog"></i> Paramètres
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div>
                    <h1><i class="fas fa-users"></i> Gestion des Utilisateurs</h1>
                    <p>Gérez tous les utilisateurs du système (administrateurs et membres)</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openModal()">
                        <i class="fas fa-user-plus"></i> Nouvel utilisateur
                    </button>
                </div>
            </div>

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

            <!-- Statistics Overview -->
            <div class="stats-overview">
                <div class="stat-card admin-stat">
                    <i class="fas fa-user-shield"></i>
                    <div class="stat-number">
                        <?php 
                            $admin_count = 0;
                            foreach($all_users as $user) {
                                if($user['user_type'] === 'admin') $admin_count++;
                            }
                            echo $admin_count;
                        ?>
                    </div>
                    <div class="stat-label">Administrateurs</div>
                </div>
                <div class="stat-card syndic-stat">
                    <i class="fas fa-building"></i>
                    <div class="stat-number">
                        <?php 
                            $syndic_count = 0;
                            foreach($all_users as $user) {
                                if(isset($user['role_name']) && $user['role_name'] === 'Syndic') $syndic_count++;
                            }
                            echo $syndic_count;
                        ?>
                    </div>
                    <div class="stat-label">Syndics</div>
                </div>
                <div class="stat-card resident-stat">
                    <i class="fas fa-home"></i>
                    <div class="stat-number">
                        <?php 
                            $resident_count = 0;
                            foreach($all_users as $user) {
                                if(isset($user['role_name']) && $user['role_name'] === 'Résident') $resident_count++;
                            }
                            echo $resident_count;
                        ?>
                    </div>
                    <div class="stat-label">Résidents</div>
                </div>
                <div class="stat-card total-stat">
                    <i class="fas fa-users"></i>
                    <div class="stat-number"><?php echo count($all_users); ?></div>
                    <div class="stat-label">Total Utilisateurs</div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" id="filtersForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Rechercher</label>
                            <input type="text" name="search" id="search" 
                                   placeholder="Nom, email ou entreprise..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="role">Type d'utilisateur</label>
                            <select name="role" id="role">
                                <option value="">Tous</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrateurs</option>
                                <option value="syndic" <?php echo $role_filter === 'syndic' ? 'selected' : ''; ?>>Syndics</option>
                                <option value="resident" <?php echo $role_filter === 'resident' ? 'selected' : ''; ?>>Résidents</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Statut</label>
                            <select name="status" id="status">
                                <option value="">Tous</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actif</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="users-table">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-list"></i>
                        Liste des utilisateurs (<?php echo count($all_users); ?>)
                    </h3>
                </div>

                <?php if (!empty($all_users)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Type</th>
                                <th>Contact</th>
                                <th>Entreprise/Ville</th>
                                <th>Statut</th>
                               <th>Date création</th>
                               <th>Actions</th>
                           </tr>
                       </thead>
                       <tbody>
                           <?php foreach ($all_users as $user): ?>
                               <tr>
                                   <td>
                                       <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                       <small><?php echo htmlspecialchars($user['email']); ?></small>
                                   </td>
                                   <td>
                                       <?php if ($user['user_type'] === 'admin'): ?>
                                           <span class="user-type-badge type-admin">
                                               <i class="fas fa-user-shield"></i> Admin
                                           </span>
                                       <?php elseif ($user['role_name'] === 'Syndic'): ?>
                                           <span class="user-type-badge type-syndic">
                                               <i class="fas fa-building"></i> Syndic
                                           </span>
                                       <?php elseif ($user['role_name'] === 'Résident'): ?>
                                           <span class="user-type-badge type-resident">
                                               <i class="fas fa-home"></i> Résident
                                           </span>
                                       <?php else: ?>
                                           <span class="user-type-badge type-resident">
                                               <i class="fas fa-user"></i> Autre
                                           </span>
                                       <?php endif; ?>
                                   </td>
                                   <td>
                                       <?php echo htmlspecialchars($user['email']); ?><br>
                                       <?php if ($user['phone']): ?>
                                           <small><?php echo htmlspecialchars($user['phone']); ?></small>
                                       <?php endif; ?>
                                   </td>
                                   <td>
                                       <?php if (isset($user['company_name']) && $user['company_name']): ?>
                                           <strong><?php echo htmlspecialchars($user['company_name']); ?></strong><br>
                                       <?php endif; ?>
                                       <?php if (isset($user['city_name']) && $user['city_name']): ?>
                                           <small><?php echo htmlspecialchars($user['city_name']); ?></small>
                                       <?php else: ?>
                                           <small class="text-muted">Non définie</small>
                                       <?php endif; ?>
                                   </td>
                                   <td>
                                       <span class="status-badge status-<?php echo $user['status']; ?>">
                                           <?php 
                                               $status_text = [
                                                   'active' => 'Actif',
                                                   'pending' => 'En attente',
                                                   'inactive' => 'Inactif'
                                               ];
                                               echo $status_text[$user['status']] ?? $user['status'];
                                           ?>
                                       </span>
                                   </td>
                                   <td>
                                       <?php 
                                           if ($user['user_type'] === 'admin' || !isset($user['date_created']) || !$user['date_created']) {
                                               echo '<span class="text-muted">N/A</span>';
                                           } else {
                                               echo date('j M Y', strtotime($user['date_created']));
                                           }
                                       ?>
                                   </td>
                                   <td>
                                       <div class="action-buttons">
                                           <?php if ($user['user_type'] === 'member'): ?>
                                               <?php if ($user['status'] === 'pending'): ?>
                                                   <button class="btn btn-sm btn-success" 
                                                           onclick="updateMemberStatus(<?php echo $user['user_id']; ?>, 'active')">
                                                       <i class="fas fa-check"></i> Activer
                                                   </button>
                                               <?php elseif ($user['status'] === 'active'): ?>
                                                   <button class="btn btn-sm btn-warning" 
                                                           onclick="updateMemberStatus(<?php echo $user['user_id']; ?>, 'inactive')">
                                                       <i class="fas fa-pause"></i> Suspendre
                                                   </button>
                                               <?php else: ?>
                                                   <button class="btn btn-sm btn-success" 
                                                           onclick="updateMemberStatus(<?php echo $user['user_id']; ?>, 'active')">
                                                       <i class="fas fa-play"></i> Réactiver
                                                   </button>
                                               <?php endif; ?>
                                           <?php endif; ?>
                                           
                                           <button class="btn btn-sm btn-secondary" 
                                                   onclick="resetPassword('<?php echo $user['user_type']; ?>', <?php echo $user['user_id']; ?>)">
                                               <i class="fas fa-key"></i> Reset MDP
                                           </button>
                                           
                                           <?php if ($user['user_type'] === 'admin' && $user['user_id'] != $_SESSION['user_id']): ?>
                                               <button class="btn btn-sm btn-danger" 
                                                       onclick="confirmDeleteAdmin(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                                   <i class="fas fa-trash"></i> Supprimer
                                               </button>
                                           <?php elseif ($user['user_type'] === 'member'): ?>
                                               <button class="btn btn-sm btn-danger" 
                                                       onclick="confirmDeleteMember(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                                   <i class="fas fa-trash"></i> Supprimer
                                               </button>
                                           <?php endif; ?>
                                       </div>
                                   </td>
                               </tr>
                           <?php endforeach; ?>
                       </tbody>
                   </table>
               <?php else: ?>
                   <div class="empty-state" style="padding: 3rem; text-align: center;">
                       <i class="fas fa-users" style="font-size: 3rem; color: var(--color-grey); margin-bottom: 1rem;"></i>
                       <h3>Aucun utilisateur trouvé</h3>
                       <p>Aucun utilisateur ne correspond aux critères de recherche.</p>
                       <button class="btn btn-primary" onclick="openModal()">
                           <i class="fas fa-user-plus"></i> Créer le premier utilisateur
                       </button>
                   </div>
               <?php endif; ?>
           </div>
       </main>
   </div>

   <!-- Create User Modal -->
   <div id="userModal" class="modal">
       <div class="modal-content">
           <div class="modal-header">
               <h2>
                   <i class="fas fa-user-plus"></i>
                   Nouvel utilisateur
               </h2>
               <span class="close" onclick="closeModal()">&times;</span>
           </div>
           
           <div class="form-tabs">
               <button type="button" class="tab-button active" onclick="switchTab('admin')">
                   <i class="fas fa-user-shield"></i> Administrateur
               </button>
               <button type="button" class="tab-button" onclick="switchTab('member')">
                   <i class="fas fa-users"></i> Membre
               </button>
           </div>

           <!-- Admin Form -->
           <div id="adminTab" class="tab-content active">
               <form method="POST" id="adminForm">
                   <input type="hidden" name="action" value="create_admin">
                   
                   <div class="form-group">
                       <label for="admin_name">Nom complet <span class="required">*</span></label>
                       <input type="text" name="name" id="admin_name" required>
                   </div>
                   
                   <div class="form-group">
                       <label for="admin_email">Email <span class="required">*</span></label>
                       <input type="email" name="email" id="admin_email" required>
                   </div>
                   
                   <div class="form-group">
                       <label for="admin_password">Mot de passe <span class="required">*</span></label>
                       <input type="password" name="password" id="admin_password" required minlength="6">
                       <small style="color: var(--color-grey);">Minimum 6 caractères</small>
                   </div>

                   <div class="modal-actions">
                       <button type="button" class="btn btn-secondary" onclick="closeModal()">
                           <i class="fas fa-times"></i> Annuler
                       </button>
                       <button type="submit" class="btn btn-primary">
                           <i class="fas fa-save"></i> Créer l'administrateur
                       </button>
                   </div>
               </form>
           </div>

           <!-- Member Form -->
           <div id="memberTab" class="tab-content">
               <form method="POST" id="memberForm">
                   <input type="hidden" name="action" value="create_member">
                   
                   <div class="form-row">
                       <div class="form-group">
                           <label for="member_name">Nom complet <span class="required">*</span></label>
                           <input type="text" name="full_name" id="member_name" required>
                       </div>
                       
                       <div class="form-group">
                           <label for="member_email">Email <span class="required">*</span></label>
                           <input type="email" name="email" id="member_email" required>
                       </div>
                   </div>

                   <div class="form-row">
                       <div class="form-group">
                           <label for="member_phone">Téléphone <span class="required">*</span></label>
                           <input type="tel" name="phone" id="member_phone" required>
                       </div>
                       
                       <div class="form-group">
                           <label for="member_role">Rôle <span class="required">*</span></label>
                           <select name="role" id="member_role" required>
                               <option value="">Choisir un rôle</option>
                               <option value="1">Résident</option>
                               <option value="2">Syndic</option>
                           </select>
                       </div>
                   </div>

                   <div class="form-group">
                       <label for="member_password">Mot de passe <span class="required">*</span></label>
                       <input type="password" name="password" id="member_password" required minlength="6">
                       <small style="color: var(--color-grey);">Minimum 6 caractères</small>
                   </div>

                   <div class="modal-actions">
                       <button type="button" class="btn btn-secondary" onclick="closeModal()">
                           <i class="fas fa-times"></i> Annuler
                       </button>
                       <button type="submit" class="btn btn-primary">
                           <i class="fas fa-save"></i> Créer le membre
                       </button>
                   </div>
               </form>
           </div>
       </div>
   </div>

   <!-- Hidden forms for actions -->
   <form id="statusForm" method="POST" style="display: none;">
       <input type="hidden" name="action" value="update_member_status">
       <input type="hidden" name="member_id" id="statusMemberId">
       <input type="hidden" name="new_status" id="newStatus">
   </form>

   <form id="passwordForm" method="POST" style="display: none;">
       <input type="hidden" name="action" value="reset_password">
       <input type="hidden" name="user_type" id="passwordUserType">
       <input type="hidden" name="user_id" id="passwordUserId">
   </form>

   <form id="deleteAdminForm" method="POST" style="display: none;">
       <input type="hidden" name="action" value="delete_admin">
       <input type="hidden" name="admin_id" id="deleteAdminId">
   </form>

   <form id="deleteMemberForm" method="POST" style="display: none;">
       <input type="hidden" name="action" value="delete_member">
       <input type="hidden" name="member_id" id="deleteMemberId">
   </form>

   <script>
       function openModal() {
           document.getElementById('userModal').classList.add('show');
           // Reset to admin tab
           switchTab('admin');
           document.getElementById('admin_name').focus();
       }

       function closeModal() {
           document.getElementById('userModal').classList.remove('show');
           // Reset forms
           document.getElementById('adminForm').reset();
           document.getElementById('memberForm').reset();
       }

       function switchTab(tabName) {
           // Hide all tab contents
           document.querySelectorAll('.tab-content').forEach(tab => {
               tab.classList.remove('active');
           });
           
           // Remove active from all tab buttons
           document.querySelectorAll('.tab-button').forEach(button => {
               button.classList.remove('active');
           });
           
           // Show selected tab
           document.getElementById(tabName + 'Tab').classList.add('active');
           
           // Activate corresponding button
           event.target.classList.add('active');
           
           // Focus first input
           setTimeout(() => {
               const firstInput = document.querySelector(`#${tabName}Tab input[type="text"], #${tabName}Tab input[type="email"]`);
               if (firstInput) {
                   firstInput.focus();
               }
           }, 100);
       }

       function updateMemberStatus(memberId, newStatus) {
           const statusText = {
               'active': 'activer',
               'inactive': 'suspendre',
               'pending': 'mettre en attente'
           };
           
           if (confirm(`Êtes-vous sûr de vouloir ${statusText[newStatus]} ce membre ?`)) {
               document.getElementById('statusMemberId').value = memberId;
               document.getElementById('newStatus').value = newStatus;
               document.getElementById('statusForm').submit();
           }
       }

       function resetPassword(userType, userId) {
           if (confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe ?\n\nLe nouveau mot de passe sera : password123')) {
               document.getElementById('passwordUserType').value = userType;
               document.getElementById('passwordUserId').value = userId;
               document.getElementById('passwordForm').submit();
           }
       }

       function confirmDeleteAdmin(adminId, adminName) {
           if (confirm(`Êtes-vous sûr de vouloir supprimer l'administrateur "${adminName}" ?\n\nCette action est irréversible.`)) {
               document.getElementById('deleteAdminId').value = adminId;
               document.getElementById('deleteAdminForm').submit();
           }
       }

       function confirmDeleteMember(memberId, memberName) {
           if (confirm(`Êtes-vous sûr de vouloir supprimer le membre "${memberName}" ?\n\nCette action supprimera toutes les données associées et est irréversible.`)) {
               document.getElementById('deleteMemberId').value = memberId;
               document.getElementById('deleteMemberForm').submit();
           }
       }

       // Manual filter submission - only when button is clicked
       document.getElementById('filtersForm').addEventListener('submit', function(event) {
           // Form will submit normally when button is clicked
       });

       // Prevent auto-submission on input change - remove all auto-submit listeners
       // Only submit when the filter button is clicked

       // Close modal when clicking outside
       window.onclick = function(event) {
           const modal = document.getElementById('userModal');
           if (event.target === modal) {
               closeModal();
           }
       }

       // Close modal with Escape key
       document.addEventListener('keydown', function(event) {
           if (event.key === 'Escape') {
               closeModal();
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

           // Update statistics with animation
           document.querySelectorAll('.stat-card').forEach((card, index) => {
               setTimeout(() => {
                   card.style.opacity = '0';
                   card.style.transform = 'translateY(20px)';
                   setTimeout(() => {
                       card.style.transition = 'all 0.5s ease';
                       card.style.opacity = '1';
                       card.style.transform = 'translateY(0)';
                   }, 50);
               }, index * 100);
           });
       });

       // Form validation
       document.getElementById('adminForm').addEventListener('submit', function(event) {
           const email = document.getElementById('admin_email').value;
           const password = document.getElementById('admin_password').value;
           
           if (password.length < 6) {
               event.preventDefault();
               alert('Le mot de passe doit contenir au moins 6 caractères.');
               return;
           }
           
           const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
           if (!emailRegex.test(email)) {
               event.preventDefault();
               alert('Veuillez entrer une adresse email valide.');
               return;
           }
       });

       document.getElementById('memberForm').addEventListener('submit', function(event) {
           const email = document.getElementById('member_email').value;
           const password = document.getElementById('member_password').value;
           const role = document.getElementById('member_role').value;
           
           if (!role) {
               event.preventDefault();
               alert('Veuillez sélectionner un rôle.');
               return;
           }
           
           if (password.length < 6) {
               event.preventDefault();
               alert('Le mot de passe doit contenir au moins 6 caractères.');
               return;
           }
           
           const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
           if (!emailRegex.test(email)) {
               event.preventDefault();
               alert('Veuillez entrer une adresse email valide.');
               return;
           }
       });

       // Format phone number
       document.getElementById('member_phone').addEventListener('input', function() {
           let value = this.value.replace(/\D/g, '');
           if (value.length >= 10) {
               value = value.replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5');
           }
           this.value = value;
       });

       // Auto-capitalize names
       document.getElementById('admin_name').addEventListener('input', function() {
           this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
       });

       document.getElementById('member_name').addEventListener('input', function() {
           this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
       });

       // Show loading state on form submission
       document.querySelectorAll('#adminForm, #memberForm').forEach(form => {
           form.addEventListener('submit', function() {
               const submitBtn = this.querySelector('button[type="submit"]');
               const originalText = submitBtn.innerHTML;
               
               submitBtn.disabled = true;
               submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création en cours...';
               
               setTimeout(() => {
                   submitBtn.disabled = false;
                   submitBtn.innerHTML = originalText;
               }, 5000);
           });
       });

       // Enhanced table interactions
       document.querySelectorAll('.data-table tbody tr').forEach(row => {
           row.addEventListener('mouseenter', function() {
               this.style.backgroundColor = 'rgba(244, 185, 66, 0.1)';
               this.style.transform = 'scale(1.01)';
           });
           
           row.addEventListener('mouseleave', function() {
               this.style.backgroundColor = '';
               this.style.transform = 'scale(1)';
           });
       });

       // Keyboard shortcuts
       document.addEventListener('keydown', function(event) {
           // Ctrl/Cmd + N for new user
           if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
               event.preventDefault();
               openModal();
           }
           
           // Ctrl/Cmd + F for search focus
           if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
               event.preventDefault();
               document.getElementById('search').focus();
           }
       });

       // Real-time form validation
       document.querySelectorAll('#userModal input[required]').forEach(field => {
           field.addEventListener('blur', function() {
               if (!this.value.trim()) {
                   this.style.borderColor = '#dc3545';
               } else {
                   this.style.borderColor = 'var(--color-green)';
               }
           });

           field.addEventListener('input', function() {
               if (this.style.borderColor === 'rgb(220, 53, 69)' && this.value.trim()) {
                   this.style.borderColor = 'var(--color-green)';
               }
           });
       });

       // Enhanced button animations
       document.querySelectorAll('.btn').forEach(btn => {
           btn.addEventListener('mouseenter', function() {
               this.style.transform = 'translateY(-2px)';
           });
           
           btn.addEventListener('mouseleave', function() {
               this.style.transform = 'translateY(0)';
           });
       });

       // Dynamic statistics counter animation
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

       // Initialize counter animations on page load
       document.addEventListener('DOMContentLoaded', function() {
           document.querySelectorAll('.stat-number').forEach(counter => {
               const target = parseInt(counter.textContent);
               counter.textContent = '0';
               setTimeout(() => {
                   animateCounter(counter, target);
               }, 500);
           });
       });
   </script>
</body>
</html>