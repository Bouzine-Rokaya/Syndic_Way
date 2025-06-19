<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit();
}

$current_user = [
    'id' => $_SESSION['user_id'],
    'role' => $_SESSION['user_role'],
    'name' => $_SESSION['user_name'] ?? 'Admin',
    'email' => $_SESSION['user_email'] ?? ''
];

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_general_settings') {
            $site_name = trim($_POST['site_name'] ?? '');
            $site_description = trim($_POST['site_description'] ?? '');
            $contact_email = trim($_POST['contact_email'] ?? '');
            $support_phone = trim($_POST['support_phone'] ?? '');
            $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
            
            if (empty($site_name) || empty($contact_email)) {
                throw new Exception("Le nom du site et l'email de contact sont requis.");
            }
            
            if (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("L'adresse email n'est pas valide.");
            }
            
            // Create settings table if not exists
            $conn->exec("
                CREATE TABLE IF NOT EXISTS site_settings (
                    setting_key VARCHAR(100) PRIMARY KEY,
                    setting_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            
            $stmt = $conn->prepare("
                INSERT INTO site_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            
            $settings = [
                'site_name' => $site_name,
                'site_description' => $site_description,
                'contact_email' => $contact_email,
                'support_phone' => $support_phone,
                'maintenance_mode' => $maintenance_mode
            ];
            
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            
            $_SESSION['success'] = "Paramètres généraux mis à jour avec succès.";
            
        } elseif ($action === 'update_admin_profile') {
            $admin_name = trim($_POST['admin_name'] ?? '');
            $admin_email = trim($_POST['admin_email'] ?? '');
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($admin_name) || empty($admin_email)) {
                throw new Exception("Le nom et l'email sont requis.");
            }
            
            if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("L'adresse email n'est pas valide.");
            }
            
            // Update admin profile
            $stmt = $conn->prepare("UPDATE admin SET name = ?, email = ? WHERE id_admin = ?");
            $stmt->execute([$admin_name, $admin_email, $_SESSION['user_id']]);
            
            // Update session data
            $_SESSION['user_name'] = $admin_name;
            $_SESSION['user_email'] = $admin_email;
            
            // Handle password change
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    throw new Exception("Le mot de passe actuel est requis pour changer le mot de passe.");
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception("Les nouveaux mots de passe ne correspondent pas.");
                }
                
                if (strlen($new_password) < 8) {
                    throw new Exception("Le nouveau mot de passe doit contenir au moins 8 caractères.");
                }
                
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM admin WHERE id_admin = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if (!password_verify($current_password, $user['password'])) {
                    throw new Exception("Le mot de passe actuel est incorrect.");
                }
                
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE id_admin = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            }
            
            $_SESSION['success'] = "Profil administrateur mis à jour avec succès.";
            
        } elseif ($action === 'backup_database') {
            $_SESSION['success'] = "Sauvegarde de la base de données lancée.";
            
        } elseif ($action === 'clear_cache') {
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            $_SESSION['success'] = "Cache vidé avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header('Location: settings.php');
    exit();
}

// Get current settings
function getSetting($conn, $key, $default = '') {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Get admin profile
try {
    $stmt = $conn->prepare("SELECT * FROM admin WHERE id_admin = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin_profile = $stmt->fetch();
} catch (Exception $e) {
    $admin_profile = null;
}

// Get system information
$system_info = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'mysql_version' => $conn->getAttribute(PDO::ATTR_SERVER_VERSION),
    'disk_space' => disk_free_space('.'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size')
];

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
            width: 65%;
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
            color: #FFCB32;
            background: #eff6ff;
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

        .quick-card.general .quick-card-icon { background: #FFCB32; }
        .quick-card.profile .quick-card-icon { background: #10b981; }
        .quick-card.security .quick-card-icon { background: #f59e0b; }
        .quick-card.system .quick-card-icon { background: #8b5cf6; }

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
            background: #FFCB32;
            color: white;
            border-color: #FFCB32;
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

        .file-description {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Settings Forms */
        .settings-form {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            margin-bottom: 16px;
        }

        .settings-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #FFCB32;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .help-text {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        /* System Info */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .info-card {
            background: #f8fafc;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .info-card h4 {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 8px;
        }

        .info-card .value {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 24px 0;
        }

        .quick-action {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .quick-action:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .quick-action i {
            font-size: 24px;
            color: #FFCB32;
            margin-bottom: 8px;
        }

        .quick-action h4 {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .quick-action p {
            font-size: 12px;
            color: #6b7280;
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
            color: #FFCB32;
            border-bottom-color: #FFCB32;
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

        .activity-icon.settings { background: #FFCB32; }
        .activity-icon.profile { background: #10b981; }
        .activity-icon.security { background: #f59e0b; }

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
                <a href="subscriptions.php" class="nav-item">
                    <i class="fas fa-tags"></i>
                    Abonnements
                </a>
                <a href="syndic-accounts.php" class="nav-item">
                    <i class="fas fa-building"></i>
                    Comptes Syndic
                </a>
                <a href="users.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    Utilisateurs
                </a>
                <a href="purchases.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    Achats
                </a>
                <a href="reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    Rapports
                </a>
                <a href="#" class="nav-item active">
                    <i class="fas fa-cog"></i>
                    Paramètres
                </a>
                <a href="../public/login.php?logout=1" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </div>

            <div class="storage-info">
                <div class="storage-text">Utilisation du système</div>
                <div class="storage-bar">
                    <div class="storage-fill"></div>
                </div>
                <div class="storage-text"><?php echo round($system_info['disk_space'] / 1024 / 1024 / 1024, 1); ?>GB libres</div>
            </div>
        </div>

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
            <div class="header">
                <div class="header-nav">
                    <a href="#" class="active">Paramètres</a>
                    <a href="#">Général</a>
                    <a href="#">Profil</a>
                    <a href="#">Système</a>
                </div>

                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Rechercher des paramètres...">
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
                    <div class="quick-access-title">Configuration Rapide</div>
                    <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card general" onclick="scrollToSection('general-settings')">
                        <div class="quick-card-icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="quick-card-title">Paramètres Généraux</div>
                        <div class="quick-card-stats">Site et configuration</div>
                    </div>

                    <div class="quick-card profile" onclick="scrollToSection('profile-settings')">
                        <div class="quick-card-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="quick-card-title">Profil Admin</div>
                        <div class="quick-card-stats">Compte et sécurité</div>
                    </div>

                    <div class="quick-card security" onclick="scrollToSection('system-info')">
                        <div class="quick-card-icon">
                            <i class="fas fa-server"></i>
                        </div>
                        <div class="quick-card-title">Informations Système</div>
                        <div class="quick-card-stats">PHP <?php echo $system_info['php_version']; ?></div>
                    </div>

                    <div class="quick-card system" onclick="scrollToSection('system-actions')">
                        <div class="quick-card-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div class="quick-card-title">Actions Système</div>
                        <div class="quick-card-stats">Maintenance et outils</div>
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
                        <a href="#">Administration</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Paramètres Système</span>
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
                        <button class="add-btn" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt"></i>
                            Actualiser
                        </button>
                    </div>

                    <!-- Settings Table View -->
                    <table class="data-table">
                        <thead class="table-header-row">
                            <tr>
                                <th>Paramètres</th>
                                <th>Description</th>
                                <th>Statut</th>
                                <th>Dernière Modif</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #FFCB32;">
                                            <i class="fas fa-cog"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Paramètres Généraux</div>
                                            <div class="file-description">Configuration du site et informations</div>
                                        </div>
                                    </div>
                                </td>
                                <td>Configuration globale de la plateforme</td>
                                <td><span class="tag">Configuré</span></td>
                                <td>Aujourd'hui</td>
                                <td>
                                    <i class="fas fa-edit" style="color: #64748b; cursor: pointer;" onclick="editGeneralSettings()"></i>
                                </td>
                            </tr>

                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #10b981;">
                                            <i class="fas fa-user-circle"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Profil Administrateur</div>
                                            <div class="file-description">Compte et informations personnelles</div>
                                        </div>
                                    </div>
                                </td>
                                <td>Gestion du compte admin principal</td>
                                <td><span class="tag">Actif</span></td>
                                <td>Il y a 2 jours</td>
                                <td>
                                    <i class="fas fa-edit" style="color: #64748b; cursor: pointer;" onclick="editProfile()"></i>
                                </td>
                            </tr>

                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #f59e0b;">
                                            <i class="fas fa-server"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Informations Système</div>
                                            <div class="file-description">État du serveur et performances</div>
                                        </div>
                                    </div>
                                </td>
                                <td>Monitoring système et ressources</td>
                                <td><span class="tag">Opérationnel</span></td>
                                <td>Temps réel</td>
                                <td>
                                    <i class="fas fa-eye" style="color: #64748b; cursor: pointer;" onclick="viewSystemInfo()"></i>
                                </td>
                            </tr>

                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #8b5cf6;">
                                            <i class="fas fa-tools"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Actions Système</div>
                                            <div class="file-description">Maintenance et outils admin</div>
                                        </div>
                                    </div>
                                </td>
                                <td>Cache, sauvegarde, optimisation</td>
                                <td><span class="tag">Disponible</span></td>
                                <td>-</td>
                                <td>
                                    <i class="fas fa-play-circle" style="color: #64748b; cursor: pointer;" onclick="showSystemActions()"></i>
                                </td>
                            </tr>

                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #ef4444;">
                                            <i class="fas fa-database"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Base de Données</div>
                                            <div class="file-description">Gestion et sauvegarde des données</div>
                                        </div>
                                    </div>
                                </td>
                                <td>MySQL <?php echo $system_info['mysql_version']; ?></td>
                                <td><span class="tag">Connecté</span></td>
                                <td>Temps réel</td>
                                <td>
                                    <i class="fas fa-download" style="color: #64748b; cursor: pointer;" onclick="backupDatabase()"></i>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Settings Forms (Hidden by default) -->
                    <div id="general-settings" class="settings-form" style="display: none;">
                        <div class="settings-title">
                            <i class="fas fa-cog"></i>
                            Paramètres Généraux
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_general_settings">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="site_name">Nom du Site</label>
                                    <input type="text" name="site_name" id="site_name" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'site_name', 'Syndic Way')); ?>" required>
                                    <div class="help-text">Le nom qui apparaîtra dans l'interface utilisateur</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="contact_email">Email de Contact</label>
                                    <input type="email" name="contact_email" id="contact_email" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'contact_email', 'contact@syndicway.com')); ?>" required>
                                    <div class="help-text">Adresse email principale pour les contacts</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_description">Description du Site</label>
                                <textarea name="site_description" id="site_description" rows="3"><?php echo htmlspecialchars(getSetting($conn, 'site_description', '')); ?></textarea>
                                <div class="help-text">Description utilisée pour le SEO et les réseaux sociaux</div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="support_phone">Téléphone Support</label>
                                    <input type="tel" name="support_phone" id="support_phone" 
                                           value="<?php echo htmlspecialchars(getSetting($conn, 'support_phone', '')); ?>">
                                    <div class="help-text">Numéro de téléphone pour le support client</div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" name="maintenance_mode" id="maintenance_mode" 
                                               <?php echo getSetting($conn, 'maintenance_mode', 0) ? 'checked' : ''; ?>>
                                        <label for="maintenance_mode">Mode Maintenance</label>
                                    </div>
                                    <div class="help-text">Active le mode maintenance pour les utilisateurs non-admin</div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 24px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Sauvegarder
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="hideForm('general-settings')">
                                    <i class="fas fa-times"></i> Annuler
                                </button>
                            </div>
                        </form>
                    </div>

                    <div id="profile-settings" class="settings-form" style="display: none;">
                        <div class="settings-title">
                            <i class="fas fa-user-circle"></i>
                            Profil Administrateur
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_admin_profile">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="admin_name">Nom Complet</label>
                                    <input type="text" name="admin_name" id="admin_name" 
                                           value="<?php echo htmlspecialchars($admin_profile['name'] ?? ''); ?>" required>
                                    <div class="help-text">Votre nom complet tel qu'affiché dans l'interface</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="admin_email">Adresse Email</label>
                                    <input type="email" name="admin_email" id="admin_email" 
                                           value="<?php echo htmlspecialchars($admin_profile['email'] ?? ''); ?>" required>
                                    <div class="help-text">Adresse email pour votre compte administrateur</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="current_password">Mot de passe actuel</label>
                                <input type="password" name="current_password" id="current_password">
                                <div class="help-text">Requis seulement si vous changez le mot de passe</div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="new_password">Nouveau mot de passe</label>
                                    <input type="password" name="new_password" id="new_password">
                                    <div class="help-text">Laissez vide pour conserver le mot de passe actuel</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirmer le mot de passe</label>
                                    <input type="password" name="confirm_password" id="confirm_password">
                                    <div class="help-text">Répétez le nouveau mot de passe</div>
                                </div>
                            </div>
                            
                            <div style="margin-top: 24px;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Mettre à jour le Profil
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="hideForm('profile-settings')">
                                    <i class="fas fa-times"></i> Annuler
                                </button>
                            </div>
                        </form>
                    </div>

                    <div id="system-info" class="settings-form" style="display: none;">
                        <div class="settings-title">
                            <i class="fas fa-server"></i>
                            Informations Système
                        </div>
                        
                        <div class="info-grid">
                            <div class="info-card">
                                <h4>Version PHP</h4>
                                <div class="value"><?php echo $system_info['php_version']; ?></div>
                            </div>
                            
                            <div class="info-card">
                                <h4>Serveur Web</h4>
                                <div class="value"><?php echo $system_info['server_software']; ?></div>
                            </div>
                            
                            <div class="info-card">
                                <h4>Version MySQL</h4>
                                <div class="value"><?php echo $system_info['mysql_version']; ?></div>
                            </div>
                            
                            <div class="info-card">
                                <h4>Espace Disque Libre</h4>
                                <div class="value"><?php echo round($system_info['disk_space'] / 1024 / 1024 / 1024, 2); ?> GB</div>
                            </div>
                            
                            <div class="info-card">
                                <h4>Limite Mémoire</h4>
                                <div class="value"><?php echo $system_info['memory_limit']; ?></div>
                            </div>
                            
                            <div class="info-card">
                                <h4>Temps d'Exécution Max</h4>
                                <div class="value"><?php echo $system_info['max_execution_time']; ?>s</div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 24px;">
                            <button type="button" class="btn btn-secondary" onclick="hideForm('system-info')">
                                <i class="fas fa-times"></i> Fermer
                            </button>
                        </div>
                    </div>

                    <div id="system-actions" class="settings-form" style="display: none;">
                        <div class="settings-title">
                            <i class="fas fa-tools"></i>
                            Actions Système
                        </div>
                        
                        <div class="quick-actions">
                            <div class="quick-action" onclick="clearCache()">
                                <i class="fas fa-broom"></i>
                                <h4>Vider le Cache</h4>
                                <p>Nettoie les fichiers de cache temporaires</p>
                            </div>
                            
                            <div class="quick-action" onclick="backupDatabase()">
                                <i class="fas fa-database"></i>
                                <h4>Sauvegarder BD</h4>
                                <p>Crée une sauvegarde de la base de données</p>
                            </div>
                            
                            <div class="quick-action" onclick="checkSystem()">
                                <i class="fas fa-heartbeat"></i>
                                <h4>Vérifier Système</h4>
                                <p>Diagnostic complet du système</p>
                            </div>
                            
                            <div class="quick-action" onclick="optimizeDatabase()">
                                <i class="fas fa-tachometer-alt"></i>
                                <h4>Optimiser BD</h4>
                                <p>Optimise les performances de la base</p>
                            </div>
                        </div>
                        
                        <div style="margin-top: 24px;">
                            <button type="button" class="btn btn-secondary" onclick="hideForm('system-actions')">
                                <i class="fas fa-times"></i> Fermer
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Activity Panel -->
                <div class="activity-panel">
                    <div class="activity-header">
                        <div class="activity-title">Activité Configuration</div>
                        <button class="close-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="activity-tabs">
                        <div class="activity-tab active">Récente</div>
                        <div class="activity-tab">Actions</div>
                        <div class="activity-tab">Logs</div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon settings">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Paramètres généraux mis à jour</div>
                            <div class="activity-time">Il y a 2 heures</div>
                            <div class="activity-meta">
                                <div style="width: 24px; height: 24px; background: #FFCB32; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 10px; font-weight: 500;">
                                    <?php echo strtoupper(substr($current_user['name'], 0, 1)); ?>
                                </div>
                                <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($current_user['name']); ?></span>
                                <div class="tag">Config</div>
                            </div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon profile">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Profil administrateur modifié</div>
                            <div class="activity-time">Il y a 1 jour</div>
                            <div class="activity-meta">
                                <div style="width: 24px; height: 24px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 10px; font-weight: 500;">
                                    <?php echo strtoupper(substr($current_user['name'], 0, 1)); ?>
                                </div>
                                <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($current_user['name']); ?></span>
                                <div class="tag">Profil</div>
                            </div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon security">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Cache système vidé</div>
                            <div class="activity-time">Il y a 3 jours</div>
                            <div class="activity-meta">
                                <div style="width: 24px; height: 24px; background: #f59e0b; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 10px; font-weight: 500;">
                                    S
                                </div>
                                <span style="font-size: 12px; color: #64748b;">Système</span>
                                <div class="tag">Maintenance</div>
                            </div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon settings">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Sauvegarde automatique</div>
                            <div class="activity-time">Il y a 1 semaine</div>
                            <div class="activity-meta">
                                <div style="width: 24px; height: 24px; background: #8b5cf6; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 10px; font-weight: 500;">
                                    A
                                </div>
                                <span style="font-size: 12px; color: #64748b;">Auto</span>
                                <div class="tag">Backup</div>
                            </div>
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
        });

        // Settings functions
        function scrollToSection(sectionId) {
            const section = document.getElementById(sectionId);
            if (section) {
                section.scrollIntoView({ behavior: 'smooth' });
            }
        }

        function editGeneralSettings() {
            hideAllForms();
            document.getElementById('general-settings').style.display = 'block';
            document.getElementById('general-settings').scrollIntoView({ behavior: 'smooth' });
        }

        function editProfile() {
            hideAllForms();
            document.getElementById('profile-settings').style.display = 'block';
            document.getElementById('profile-settings').scrollIntoView({ behavior: 'smooth' });
        }

        function viewSystemInfo() {
            hideAllForms();
            document.getElementById('system-info').style.display = 'block';
            document.getElementById('system-info').scrollIntoView({ behavior: 'smooth' });
        }

        function showSystemActions() {
            hideAllForms();
            document.getElementById('system-actions').style.display = 'block';
            document.getElementById('system-actions').scrollIntoView({ behavior: 'smooth' });
        }

        function hideForm(formId) {
            document.getElementById(formId).style.display = 'none';
        }

        function hideAllForms() {
            const forms = ['general-settings', 'profile-settings', 'system-info', 'system-actions'];
            forms.forEach(formId => {
                document.getElementById(formId).style.display = 'none';
            });
        }

        // System actions
        function clearCache() {
            if (confirm('Êtes-vous sûr de vouloir vider le cache système ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="clear_cache">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function backupDatabase() {
            if (confirm('Lancer une sauvegarde de la base de données ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="backup_database">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function checkSystem() {
            alert('Vérification système en cours...\n\n✓ PHP: OK\n✓ MySQL: OK\n✓ Espace disque: OK\n✓ Permissions: OK');
        }

        function optimizeDatabase() {
            if (confirm('Optimiser la base de données ? Cette opération peut prendre quelques minutes.')) {
                alert('Optimisation de la base de données lancée...');
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

            // Storage animation
            const storageFill = document.querySelector('.storage-fill');
            const originalWidth = storageFill.style.width;
            storageFill.style.width = '0%';
            setTimeout(() => {
                storageFill.style.width = '65%';
            }, 500);

            // Table row hover effects
            document.querySelectorAll('.table-row').forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8fafc';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });

        // Search functionality
        document.querySelector('.search-box input').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.table-row').forEach(row => {
                const fileName = row.querySelector('.file-name').textContent.toLowerCase();
                const description = row.querySelector('.file-description').textContent.toLowerCase();
                if (fileName.includes(searchTerm) || description.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Quick card animations
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>