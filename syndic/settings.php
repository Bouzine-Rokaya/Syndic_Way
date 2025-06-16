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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_profile') {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone'] ?? '');
            
            if (empty($name) || empty($email)) {
                throw new Exception("Le nom et l'email sont obligatoires.");
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("L'adresse email n'est pas valide.");
            }
            
            // Check if email is already used by another user
            $stmt = $conn->prepare("SELECT id_member FROM member WHERE email = ? AND id_member != ?");
            $stmt->execute([$email, $current_user['id']]);
            if ($stmt->fetch()) {
                throw new Exception("Cette adresse email est déjà utilisée.");
            }
            
            // Update profile
            $stmt = $conn->prepare("UPDATE member SET full_name = ?, email = ?, phone = ? WHERE id_member = ?");
            $stmt->execute([$name, $email, $phone, $current_user['id']]);
            
            // Update session
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            
            $_SESSION['success'] = "Profil mis à jour avec succès.";
            
        } elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception("Tous les champs sont obligatoires.");
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception("Les nouveaux mots de passe ne correspondent pas.");
            }
            
            if (strlen($new_password) < 8) {
                throw new Exception("Le nouveau mot de passe doit contenir au moins 8 caractères.");
            }
            
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM member WHERE id_member = ?");
            $stmt->execute([$current_user['id']]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!password_verify($current_password, $user_data['password'])) {
                throw new Exception("Le mot de passe actuel est incorrect.");
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE member SET password = ? WHERE id_member = ?");
            $stmt->execute([$hashed_password, $current_user['id']]);
            
            $_SESSION['success'] = "Mot de passe modifié avec succès.";
            
        } elseif ($action === 'update_building') {
            $building_name = trim($_POST['building_name']);
            $building_address = trim($_POST['building_address']);
            $city_id = intval($_POST['city_id']);
            
            if (empty($building_name) || empty($building_address) || empty($city_id)) {
                throw new Exception("Tous les champs du bâtiment sont obligatoires.");
            }
            
            // Get current building
            $stmt = $conn->prepare("
                SELECT DISTINCT r.id_residence 
                FROM apartment ap
                JOIN residence r ON r.id_residence = ap.id_residence
                WHERE ap.id_member = ?
                LIMIT 1
            ");
            $stmt->execute([$current_user['id']]);
            $building = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($building) {
                $stmt = $conn->prepare("UPDATE residence SET name = ?, address = ?, id_city = ? WHERE id_residence = ?");
                $stmt->execute([$building_name, $building_address, $city_id, $building['id_residence']]);
                
                $_SESSION['success'] = "Informations du bâtiment mises à jour avec succès.";
            } else {
                throw new Exception("Aucun bâtiment associé trouvé.");
            }
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header('Location: settings.php');
    exit();
}

// Get current user data and statistics
try {
    $stmt = $conn->prepare("SELECT * FROM member WHERE id_member = ?");
    $stmt->execute([$current_user['id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get building info
    $stmt = $conn->prepare("
        SELECT r.*, c.city_name 
        FROM apartment ap
        JOIN residence r ON r.id_residence = ap.id_residence
        JOIN city c ON c.id_city = r.id_city
        WHERE ap.id_member = ?
        LIMIT 1
    ");
    $stmt->execute([$current_user['id']]);
    $building_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all cities for dropdown
    $stmt = $conn->prepare("SELECT * FROM city ORDER BY city_name");
    $stmt->execute();
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get building statistics
    if ($building_info) {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_apartments,
                COUNT(CASE WHEN m.role = 1 THEN 1 END) as occupied_apartments,
                COUNT(DISTINCT ap.floor) as total_floors
            FROM apartment ap
            LEFT JOIN member m ON ap.id_member = m.id_member
            WHERE ap.id_residence = ?
        ");
        $stmt->execute([$building_info['id_residence']]);
        $building_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $building_stats = ['total_apartments' => 0, 'occupied_apartments' => 0, 'total_floors' => 0];
    }
    
    // Get activity stats
    $stmt = $conn->prepare("
        SELECT COUNT(*) as recent_actions
        FROM member_announcements 
        WHERE id_poster = ? 
        AND date_announcement >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$current_user['id']]);
    $activity_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors du chargement des données.";
    $user_data = [];
    $building_info = null;
    $cities = [];
    $building_stats = ['total_apartments' => 0, 'occupied_apartments' => 0, 'total_floors' => 0];
    $activity_stats = ['recent_actions' => 0];
}

$page_title = "Paramètres - Syndic Way";
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 24px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .welcome-section h1 {
            font-size: 2rem;
            color: #2d3748;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-section p {
            color: #718096;
            font-size: 1rem;
        }

        .search-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: white;
            font-size: 0.9rem;
            width: 300px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }

        .notifications {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notifications:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 300px 1fr 350px;
            gap: 2rem;
            height: calc(100vh - 200px);
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        /* Sidebar Navigation */
        .sidebar {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow-y: auto;
        }

        .sidebar-header {
            margin-bottom: 2rem;
            text-align: center;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 auto 1rem;
            border: 4px solid white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .profile-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }

        .profile-role {
            color: #718096;
            font-size: 0.9rem;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #718096;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .nav-link:hover,
        .nav-link.active {
            background: #eff6ff;
            color: #3b82f6;
            transform: translateX(4px);
        }

        .nav-link i {
            width: 18px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow-y: auto;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .content-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .content-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Settings Tabs */
        .settings-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            background: #f8fafc;
            padding: 0.5rem;
            border-radius: 12px;
        }

        .tab-button {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            background: transparent;
            color: #64748b;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .tab-button.active {
            background: white;
            color: #3b82f6;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .tab-button:hover {
            color: #3b82f6;
        }

        /* Settings Content */
        .settings-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .settings-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-select {
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1rem;
            padding-right: 3rem;
        }

        .required {
            color: #ef4444;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* Activity Panel */
        .activity-panel {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow-y: auto;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .activity-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2d3748;
        }

        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
            flex-shrink: 0;
        }

        .activity-icon.success { background: #10b981; }
        .activity-icon.warning { background: #f59e0b; }
        .activity-icon.info { background: #3b82f6; }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 0.9rem;
            color: #374151;
            margin-bottom: 0.25rem;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #6b7280;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 48px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #3b82f6;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }

        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .setting-item:last-child {
            border-bottom: none;
        }

        .setting-info h4 {
            color: #374151;
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }

        .setting-info p {
            color: #6b7280;
            font-size: 0.85rem;
        }

        /* Security Section */
        .security-requirements {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1rem 0;
        }

        .security-requirements h4 {
            color: #374151;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }

        .requirement-list {
            list-style: none;
        }

        .requirement-list li {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }

        .requirement-list li i {
            width: 16px;
        }

        .requirement-met {
            color: #10b981;
        }

        .requirement-not-met {
            color: #ef4444;
        }

        /* Password Strength */
        .password-strength {
            margin-top: 0.5rem;
        }

        .strength-bar {
            width: 100%;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { background: #ef4444; width: 25%; }
        .strength-fair { background: #f59e0b; width: 50%; }
        .strength-good { background: #3b82f6; width: 75%; }
        .strength-strong { background: #10b981; width: 100%; }

        .strength-text {
            font-size: 0.8rem;
            margin-top: 0.25rem;
            color: #6b7280;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .main-grid {
                grid-template-columns: 250px 1fr;
            }
            
            .activity-panel {
                grid-column: 1 / -1;
                margin-top: 2rem;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .dashboard-container {
                padding: 1rem;
            }
            
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .main-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .settings-tabs {
                flex-direction: column;
            }
            
            .tab-button {
                justify-content: flex-start;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <div class="header">
            <div class="welcome-section">
                <h1>Paramètres Syndic</h1>
                <p>Configurez votre espace de gestion syndic</p>
            </div>
            <div class="search-section">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher un paramètre...">
                </div>
                <div class="notifications">
                    <i class="fas fa-bell"></i>
                </div>
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

        <div class="main-grid">
            <!-- Sidebar Navigation -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($current_user['name'], 0, 1)); ?>
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($current_user['name']); ?></div>
                    <div class="profile-role">Syndic de Copropriété</div>
                </div>

                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            <i class="fas fa-tachometer-alt"></i>
                            Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../public/login.php?logout=1" class="nav-link">
                            <i class="fas fa-sign-out-alt"></i>
                            Déconnexion
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="content-header">
                    <div class="content-title">
                        <i class="fas fa-cog"></i>
                        Configuration
                    </div>
                    <div class="content-actions">
                        <button class="btn btn-secondary" onclick="backupData()">
                            <i class="fas fa-download"></i>
                            Sauvegarder
                        </button>
                        <button class="btn btn-primary" onclick="saveAllSettings()">
                            <i class="fas fa-save"></i>
                            Tout sauvegarder
                        </button>
                    </div>
                </div>

                <!-- Settings Tabs -->
                <div class="settings-tabs">
                    <button class="tab-button active" data-tab="profile">
                        <i class="fas fa-user"></i>
                        Profil
                    </button>
                    <button class="tab-button" data-tab="building">
                        <i class="fas fa-building"></i>
                        Bâtiment
                    </button>
                    <button class="tab-button" data-tab="notifications">
                        <i class="fas fa-bell"></i>
                        Notifications
                    </button>
                    <button class="tab-button" data-tab="security">
                        <i class="fas fa-shield-alt"></i>
                        Sécurité
                    </button>
                </div>

                <!-- Profile Settings -->
                <div id="profile-settings" class="settings-section active">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-home"></i>
                            </div>
                            <div class="stat-value"><?php echo $building_stats['total_apartments']; ?></div>
                            <div class="stat-label">Appartements gérés</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-value"><?php echo $building_stats['occupied_apartments']; ?></div>
                            <div class="stat-label">Résidents actifs</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="stat-value"><?php echo $activity_stats['recent_actions']; ?></div>
                            <div class="stat-label">Annonces (30j)</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div class="stat-value"><?php echo $building_stats['total_floors']; ?></div>
                            <div class="stat-label">Étages</div>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label" for="name">
                                    Nom complet <span class="required">*</span>
                                </label>
                                <input type="text" name="name" id="name" class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['full_name'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="email">
                                    Adresse email <span class="required">*</span>
                                </label>
                                <input type="email" name="email" id="email" class="form-input"
                                       value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="phone">Téléphone</label>
                            <input type="tel" name="phone" id="phone" class="form-input"
                                   value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" 
                                   placeholder="+33 6 12 34 56 78">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Mettre à jour le profil
                        </button>
                    </form>
                </div>

                <!-- Building Settings -->
                <div id="building-settings" class="settings-section">
                    <?php if ($building_info): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_building">
                            
                            <div class="form-group">
                                <label class="form-label" for="building_name">
                                    Nom du bâtiment <span class="required">*</span>
                                </label>
                                <input type="text" name="building_name" id="building_name" class="form-input"
                                       value="<?php echo htmlspecialchars($building_info['name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="building_address">
                                    Adresse <span class="required">*</span>
                                </label>
                                <textarea name="building_address" id="building_address" class="form-input form-textarea" required><?php echo htmlspecialchars($building_info['address']); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="city_id">
                                    Ville <span class="required">*</span>
                                </label>
                                <select name="city_id" id="city_id" class="form-input form-select" required>
                                    <option value="">Sélectionner une ville</option>
                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?php echo $city['id_city']; ?>" 
                                                <?php echo $city['id_city'] == $building_info['id_city'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($city['city_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-grid">
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <h4>Gestion automatique des charges</h4>
                                        <p>Calcul automatique des charges mensuelles</p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="setting-item">
                                    <div class="setting-info">
                                        <h4>Rappels automatiques</h4>
                                        <p>Envoi automatique des rappels de paiement</p>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Mettre à jour les informations
                            </button>
                        </form>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem; color: #6b7280;">
                            <i class="fas fa-building" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <h3>Aucun bâtiment associé</h3>
                            <p>Aucun bâtiment n'est associé à votre compte.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Notifications Settings -->
                <div id="notifications-settings" class="settings-section">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_notifications">
                        
                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Notifications par email</h4>
                                <p>Recevoir les notifications importantes par email</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_notifications" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Rappels de paiement</h4>
                                <p>Notifications pour les échéances de paiement</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="payment_reminders" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Alertes de maintenance</h4>
                                <p>Notifications pour les travaux et interventions</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="maintenance_alerts" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div class="setting-item">
                            <div class="setting-info">
                                <h4>Assemblées générales</h4>
                                <p>Convocations et rappels pour les AG</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="meeting_notifications" checked>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>

                        <div class="form-grid" style="margin-top: 2rem;">
                            <div class="form-group">
                                <label class="form-label" for="notification_start">Début des notifications</label>
                                <input type="time" id="notification_start" class="form-input" value="08:00">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="notification_end">Fin des notifications</label>
                                <input type="time" id="notification_end" class="form-input" value="20:00">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Sauvegarder les préférences
                        </button>
                    </form>
                </div>

                <!-- Security Settings -->
                <div id="security-settings" class="settings-section">
                    <form method="POST" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label class="form-label" for="current_password">
                                Mot de passe actuel <span class="required">*</span>
                            </label>
                            <input type="password" name="current_password" id="current_password" class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="new_password">
                                Nouveau mot de passe <span class="required">*</span>
                            </label>
                            <input type="password" name="new_password" id="new_password" class="form-input" 
                                   minlength="8" required onkeyup="checkPasswordStrength(this.value)">
                            <div class="password-strength">
                                <div class="strength-bar">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                                <div class="strength-text" id="strengthText">Entrez un mot de passe</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">
                                Confirmer le nouveau mot de passe <span class="required">*</span>
                            </label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-input" 
                                   minlength="8" required>
                        </div>

                        <div class="security-requirements">
                            <h4>Exigences du mot de passe :</h4>
                            <ul class="requirement-list">
                                <li id="length-req" class="requirement-not-met">
                                    <i class="fas fa-times"></i>
                                    Au moins 8 caractères
                                </li>
                                <li id="uppercase-req" class="requirement-not-met">
                                    <i class="fas fa-times"></i>
                                    Au moins une majuscule
                                </li>
                                <li id="lowercase-req" class="requirement-not-met">
                                    <i class="fas fa-times"></i>
                                    Au moins une minuscule
                                </li>
                                <li id="number-req" class="requirement-not-met">
                                    <i class="fas fa-times"></i>
                                    Au moins un chiffre
                                </li>
                                <li id="special-req" class="requirement-not-met">
                                    <i class="fas fa-times"></i>
                                    Au moins un caractère spécial
                                </li>
                            </ul>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-lock"></i> Changer le mot de passe
                        </button>
                    </form>
                </div>
            </div>

            <!-- Activity Panel -->
            <div class="activity-panel">
                <div class="activity-header">
                    <div class="activity-title">Activité Récente</div>
                    <button class="btn btn-secondary" style="padding: 0.5rem; font-size: 0.8rem;">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>

                <div class="activity-item">
                    <div class="activity-icon success">
                        <i class="fas fa-sign-in-alt"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">Connexion réussie</div>
                        <div class="activity-time">Aujourd'hui à 14:30</div>
                    </div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon info">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">Profil mis à jour</div>
                        <div class="activity-time">Hier à 16:45</div>
                    </div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon success">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">Nouvelle annonce publiée</div>
                        <div class="activity-time">Il y a 2 jours</div>
                    </div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon warning">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">Tentative de connexion</div>
                        <div class="activity-time">Il y a 3 jours</div>
                    </div>
                </div>

                <div class="activity-item">
                    <div class="activity-icon info">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">Paramètres modifiés</div>
                        <div class="activity-time">Il y a 5 jours</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const targetTab = this.dataset.tab;
                
                // Remove active class from all tabs and sections
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.settings-section').forEach(section => section.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding section
                this.classList.add('active');
                document.getElementById(`${targetTab}-settings`).classList.add('active');
            });
        });

        // Password strength checker
        function checkPasswordStrength(password) {
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            let score = 0;
            let feedback = '';
            
            // Length check
            const lengthReq = document.getElementById('length-req');
            if (password.length >= 8) {
                score++;
                lengthReq.className = 'requirement-met';
                lengthReq.innerHTML = '<i class="fas fa-check"></i> Au moins 8 caractères';
            } else {
                lengthReq.className = 'requirement-not-met';
                lengthReq.innerHTML = '<i class="fas fa-times"></i> Au moins 8 caractères';
            }
            
            // Uppercase check
            const uppercaseReq = document.getElementById('uppercase-req');
            if (/[A-Z]/.test(password)) {
                score++;
                uppercaseReq.className = 'requirement-met';
                uppercaseReq.innerHTML = '<i class="fas fa-check"></i> Au moins une majuscule';
            } else {
                uppercaseReq.className = 'requirement-not-met';
                uppercaseReq.innerHTML = '<i class="fas fa-times"></i> Au moins une majuscule';
            }
            
            // Lowercase check
            const lowercaseReq = document.getElementById('lowercase-req');
            if (/[a-z]/.test(password)) {
                score++;
                lowercaseReq.className = 'requirement-met';
                lowercaseReq.innerHTML = '<i class="fas fa-check"></i> Au moins une minuscule';
            } else {
                lowercaseReq.className = 'requirement-not-met';
                lowercaseReq.innerHTML = '<i class="fas fa-times"></i> Au moins une minuscule';
            }
            
            // Number check
            const numberReq = document.getElementById('number-req');
            if (/[0-9]/.test(password)) {
                score++;
                numberReq.className = 'requirement-met';
                numberReq.innerHTML = '<i class="fas fa-check"></i> Au moins un chiffre';
            } else {
                numberReq.className = 'requirement-not-met';
                numberReq.innerHTML = '<i class="fas fa-times"></i> Au moins un chiffre';
            }
            
            // Special character check
            const specialReq = document.getElementById('special-req');
            if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
                score++;
                specialReq.className = 'requirement-met';
                specialReq.innerHTML = '<i class="fas fa-check"></i> Au moins un caractère spécial';
            } else {
                specialReq.className = 'requirement-not-met';
                specialReq.innerHTML = '<i class="fas fa-times"></i> Au moins un caractère spécial';
            }
            
            // Update strength bar
            if (score === 0 || password.length === 0) {
                strengthFill.className = 'strength-fill';
                strengthFill.style.width = '0%';
                feedback = 'Entrez un mot de passe';
            } else if (score <= 2) {
                strengthFill.className = 'strength-fill strength-weak';
                feedback = 'Faible';
            } else if (score <= 3) {
                strengthFill.className = 'strength-fill strength-fair';
                feedback = 'Moyen';
            } else if (score <= 4) {
                strengthFill.className = 'strength-fill strength-good';
                feedback = 'Bon';
            } else {
                strengthFill.className = 'strength-fill strength-strong';
                feedback = 'Excellent';
            }
            
            strengthText.textContent = feedback;
        }

        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
                return false;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 8 caractères.');
                return false;
            }
        });

        // Save all settings function
        function saveAllSettings() {
            if (confirm('Êtes-vous sûr de vouloir sauvegarder tous les paramètres ?')) {
                // Here you would implement saving all forms
                alert('Tous les paramètres ont été sauvegardés.');
            }
        }

        // Backup data function
        function backupData() {
            if (confirm('Créer une sauvegarde de vos données ?')) {
                // Here you would implement backup functionality
                alert('Sauvegarde créée avec succès.');
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

            // Animate statistics on load
            document.querySelectorAll('.stat-value').forEach((counter, index) => {
                const target = parseInt(counter.textContent);
                counter.textContent = '0';
                setTimeout(() => {
                    animateCounter(counter, target);
                }, index * 200);
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

        // Search functionality
        document.querySelector('.search-box input').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const allSettings = document.querySelectorAll('.form-group, .setting-item');
            
            allSettings.forEach(setting => {
                const text = setting.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    setting.style.display = '';
                } else {
                    setting.style.display = 'none';
                }
            });
        });

        // Enhanced animations
        document.querySelectorAll('.card, .stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Smooth scrolling for navigation
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                }
            });
        });
    </script>
</body>
</html>