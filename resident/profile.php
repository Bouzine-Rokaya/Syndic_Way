<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is a resident
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'resident') {
    header('Location: http://localhost/syndicplatform/public/login.php');
    exit();
}

// Get resident complete information
try {
    // Get member basic info
    $stmt = $conn->prepare("
        SELECT m.*, 
               ap.type as apartment_type, ap.floor, ap.number as apartment_number,
               r.name as building_name, r.address as building_address, 
               c.city_name,
               r.id_residence
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        JOIN residence r ON r.id_residence = ap.id_residence
        JOIN city c ON c.id_city = r.id_city
        WHERE m.id_member = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $resident_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get subscription information
    $stmt = $conn->prepare("
        SELECT s.*, ams.date_payment as last_payment, ams.amount as last_amount
        FROM subscription s
        JOIN admin_member_subscription ams ON ams.id_subscription = s.id_subscription
        WHERE ams.id_member = ?
        ORDER BY ams.date_payment DESC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $subscription_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get admin/syndic managing this resident
    $stmt = $conn->prepare("
        SELECT DISTINCT a.id_admin, a.name as admin_name, a.email as admin_email
        FROM admin a
        JOIN admin_member_link aml ON aml.id_admin = a.id_admin
        WHERE aml.id_member = ?
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $managing_admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get activity statistics
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_messages_sent
        FROM member_messages
        WHERE id_sender = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $messages_sent = $stmt->fetch()['total_messages_sent'] ?? 0;

    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_messages_received
        FROM member_messages
        WHERE id_receiver = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $messages_received = $stmt->fetch()['total_messages_received'] ?? 0;

    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_payments
        FROM member_payments
        WHERE id_payer = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $total_payments = $stmt->fetch()['total_payments'] ?? 0;

    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_announcements
        FROM member_announcements
        WHERE id_receiver = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $total_announcements = $stmt->fetch()['total_announcements'] ?? 0;

    // Get recent activity
    $stmt = $conn->prepare("
        SELECT 'payment' as activity_type, date_payment as activity_date, 'Paiement effectué' as activity_description
        FROM member_payments 
        WHERE id_payer = ?
        UNION ALL
        SELECT 'message_sent' as activity_type, date_message as activity_date, 'Message envoyé' as activity_description
        FROM member_messages 
        WHERE id_sender = ?
        UNION ALL
        SELECT 'message_received' as activity_type, date_message as activity_date, 'Message reçu' as activity_description
        FROM member_messages 
        WHERE id_receiver = ?
        ORDER BY activity_date DESC
        LIMIT 8
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log($e->getMessage());
    $resident_info = null;
    $subscription_info = null;
    $managing_admin = null;
    $messages_sent = 0;
    $messages_received = 0;
    $total_payments = 0;
    $total_announcements = 0;
    $recent_activities = [];
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($full_name) || empty($email)) {
            throw new Exception("Le nom complet et l'email sont obligatoires.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format d'email invalide.");
        }

        // Check if email is already taken by another user
        $stmt = $conn->prepare("SELECT id_member FROM member WHERE email = ? AND id_member != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            throw new Exception("Cet email est déjà utilisé par un autre compte.");
        }

        // Prepare update query
        $update_fields = "full_name = ?, email = ?";
        $update_params = [$full_name, $email];

        if (!empty($phone)) {
            $update_fields .= ", phone = ?";
            $update_params[] = $phone;
        }

        // Handle password change
        if (!empty($new_password)) {
            if (empty($current_password)) {
                throw new Exception("Veuillez saisir votre mot de passe actuel pour le modifier.");
            }

            // Verify current password
            if (!password_verify($current_password, $resident_info['password'])) {
                throw new Exception("Mot de passe actuel incorrect.");
            }

            if ($new_password !== $confirm_password) {
                throw new Exception("Les nouveaux mots de passe ne correspondent pas.");
            }

            if (strlen($new_password) < 6) {
                throw new Exception("Le nouveau mot de passe doit contenir au moins 6 caractères.");
            }

            $update_fields .= ", password = ?";
            $update_params[] = password_hash($new_password, PASSWORD_DEFAULT);
        }

        $update_params[] = $_SESSION['user_id'];

        // Execute update
        $stmt = $conn->prepare("UPDATE member SET {$update_fields} WHERE id_member = ?");
        $stmt->execute($update_params);

        // Update session if name changed
        if ($_SESSION['user_name'] !== $full_name) {
            $_SESSION['user_name'] = $full_name;
        }

        $_SESSION['success'] = "Profil mis à jour avec succès !";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    } catch(PDOException $e) {
        error_log($e->getMessage());
        $_SESSION['error'] = "Erreur lors de la mise à jour. Veuillez réessayer.";
    }
}

// Calculate membership duration
$membership_duration = '';
if($resident_info && $resident_info['date_created']) {
    $created_date = new DateTime($resident_info['date_created']);
    $current_date = new DateTime();
    $interval = $created_date->diff($current_date);
    
    if($interval->y > 0) {
        $membership_duration = $interval->y . ' an' . ($interval->y > 1 ? 's' : '');
        if($interval->m > 0) {
            $membership_duration .= ' et ' . $interval->m . ' mois';
        }
    } elseif($interval->m > 0) {
        $membership_duration = $interval->m . ' mois';
    } else {
        $membership_duration = $interval->d . ' jour' . ($interval->d > 1 ? 's' : '');
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Syndic Way</title>
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
            width: 75%;
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
            background: #ecfdf5;
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

        /* Profile Header Section */
        .profile-header {
            padding: 24px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
        }

        .profile-header-content {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .profile-avatar-large {
            width: 100px;
            height: 100px;
            background: #FFCB32;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: 600;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }

        .profile-details h1 {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .profile-details p {
            color: #64748b;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #ecfdf5;
            color: #FFCB32;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-top: 8px;
        }

        /* Quick Access Cards */
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

        .quick-card.messages .quick-card-icon { background: #FFCB32; }
        .quick-card.payments .quick-card-icon { background: #3b82f6; }
        .quick-card.announcements .quick-card-icon { background: #f59e0b; }
        .quick-card.notifications .quick-card-icon { background: #8b5cf6; }

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

        /* Profile Form Section */
        .profile-form-section {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .form-header {
            background: #f8fafc;
            padding: 16px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }

        .form-content {
            padding: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
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
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-group.readonly input {
            background: #f8fafc;
            color: #64748b;
            cursor: not-allowed;
        }

        .password-section {
            border-top: 1px solid #e2e8f0;
            padding-top: 24px;
            margin-top: 24px;
        }

        .password-section h4 {
            font-size: 16px;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #FFCB32;
            color: white;
            border-color: #FFCB32;
        }

        .btn-primary:hover {
            background: #059669;
            border-color: #059669;
        }

        .btn-secondary {
            background: white;
            color: #64748b;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            color: #374151;
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

        .activity-icon.payment { background: #FFCB32; }
        .activity-icon.message_sent { background: #3b82f6; }
        .activity-icon.message_received { background: #8b5cf6; }

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

        /* Info Cards */
        .info-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .info-card-header {
            background: #f8fafc;
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-card-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }

        .info-card-content {
            padding: 20px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-size: 14px;
            color: #64748b;
        }

        .info-value {
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
        }

        .subscription-card {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border: 1px solid #FFCB32;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .subscription-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .subscription-name {
            font-size: 18px;
            font-weight: 600;
            color: #FFCB32;
        }

        .subscription-price {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .content-area {
                flex-direction: column;
            }
            
            .activity-panel {
                width: 100%;
                border-left: none;
                border-top: 1px solid #e2e8f0;
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .info-cards {
                grid-template-columns: 1fr;
            }
            
            .quick-access-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .profile-header-content {
                flex-direction: column;
                text-align: center;
                gap: 16px;
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
                <a href="payments.php" class="nav-item">
                    <i class="fas fa-credit-card"></i>
                    Mes Paiements
                </a>
                <a href="messages.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    Messages
                </a>
                <a href="announcements.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    Annonces
                </a>
                <a href="notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    Notifications
                </a>
                <a href="neighbors.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    Voisinage
                </a>
                <a href="profile.php" class="nav-item active">
                    <i class="fas fa-user-cog"></i>
                    Mon Profil
                </a>
                <a href="../public/login.php?logout=1" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </div>

            <div class="storage-info">
                <div class="storage-text">Profil complété</div>
                <div class="storage-bar">
                    <div class="storage-fill"></div>
                </div>
                <div class="storage-text">75% des informations renseignées</div>
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
                    <a href="#" class="active">Profil</a>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="messages.php">Messages</a>
                    <a href="payments.php">Paiements</a>
                </div>

                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Rechercher...">
                    </div>
                    <i class="fas fa-bell" style="color: #64748b; cursor: pointer;"></i>
                    <div class="header-user">
                        <div class="user-avatar"><?php echo strtoupper(substr($resident_info['full_name'] ?? 'R', 0, 1)); ?></div>
                    </div>
                </div>
            </div>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-header-content">
                    <div class="profile-avatar-large">
                        <?php echo strtoupper(substr($resident_info['full_name'] ?? 'R', 0, 1)); ?>
                    </div>
                    <div class="profile-details">
                        <h1><?php echo htmlspecialchars($resident_info['full_name'] ?? 'Résident'); ?></h1>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($resident_info['email'] ?? ''); ?></p>
                        <?php if($resident_info['phone']): ?>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($resident_info['phone']); ?></p>
                        <?php endif; ?>
                        <p><i class="fas fa-home"></i> 
                            Appartement <?php echo $resident_info['apartment_number'] ?? 'N/A'; ?> 
                            - Étage <?php echo $resident_info['floor'] ?? 'N/A'; ?>
                        </p>
                        <p><i class="fas fa-calendar"></i> 
                            Membre depuis <?php echo $membership_duration; ?>
                        </p>
                        <div class="profile-badge">
                            <i class="fas fa-user"></i>
                            Résident
                        </div>
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
                    <div class="quick-card messages" onclick="window.location.href='messages.php'">
                        <div class="quick-card-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="quick-card-title">Messages</div>
                        <div class="quick-card-count"><?php echo $messages_sent + $messages_received; ?></div>
                        <div class="quick-card-stats"><?php echo $messages_sent; ?> envoyés, <?php echo $messages_received; ?> reçus</div>
                    </div>

                    <div class="quick-card payments" onclick="window.location.href='payments.php'">
                        <div class="quick-card-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="quick-card-title">Paiements</div>
                        <div class="quick-card-count"><?php echo $total_payments; ?></div>
                        <div class="quick-card-stats">Transactions effectuées</div>
                    </div>

                    <div class="quick-card announcements" onclick="window.location.href='announcements.php'">
                        <div class="quick-card-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="quick-card-title">Annonces</div>
                        <div class="quick-card-count"><?php echo $total_announcements; ?></div>
                        <div class="quick-card-stats">Reçues</div>
                    </div>

                    <div class="quick-card notifications" onclick="window.location.href='notifications.php'">
                        <div class="quick-card-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="quick-card-title">Notifications</div>
                        <div class="quick-card-count">3</div>
                        <div class="quick-card-stats">Non lues</div>
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
                        <a href="#">Mon Compte</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Profil</span>
                    </div>

                    <!-- Info Cards -->
                    <div class="info-cards">
                        <!-- Apartment Info -->
                        <div class="info-card">
                            <div class="info-card-header">
                                <i class="fas fa-building"></i>
                                <h3>Mon Logement</h3>
                            </div>
                            <div class="info-card-content">
                                <div class="info-item">
                                    <span class="info-label">Immeuble</span>
                                    <span class="info-value"><?php echo htmlspecialchars($resident_info['building_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Adresse</span>
                                    <span class="info-value"><?php echo htmlspecialchars($resident_info['building_address'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Ville</span>
                                    <span class="info-value"><?php echo htmlspecialchars($resident_info['city_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Appartement</span>
                                    <span class="info-value">N° <?php echo $resident_info['apartment_number'] ?? 'N/A'; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Étage</span>
                                    <span class="info-value"><?php echo $resident_info['floor'] ?? 'N/A'; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Type</span>
                                    <span class="info-value"><?php echo htmlspecialchars($resident_info['apartment_type'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Subscription Info -->
                        <div class="info-card">
                            <div class="info-card-header">
                                <i class="fas fa-star"></i>
                                <h3>Mon Abonnement</h3>
                            </div>
                            <div class="info-card-content">
                                <?php if($subscription_info): ?>
                                    <div class="subscription-card">
                                        <div class="subscription-header">
                                            <div class="subscription-name"><?php echo htmlspecialchars($subscription_info['name_subscription']); ?></div>
                                            <div class="subscription-price"><?php echo number_format($subscription_info['price_subscription']); ?> DH</div>
                                        </div>
                                        <p style="font-size: 14px; color: #64748b; margin-bottom: 12px;"><?php echo htmlspecialchars($subscription_info['description']); ?></p>
                                        
                                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; font-size: 12px;">
                                            <div><strong>Durée:</strong> <?php echo $subscription_info['duration_months']; ?> mois</div>
                                            <div><strong>Max résidents:</strong> <?php echo $subscription_info['max_residents']; ?></div>
                                            <div><strong>Max appartements:</strong> <?php echo $subscription_info['max_apartments']; ?></div>
                                            <div><strong>Statut:</strong> 
                                                <span style="color: #FFCB32;">
                                                    <?php echo $subscription_info['is_active'] ? 'Actif' : 'Inactif'; ?>
                                                </span>
                                            </div>
                                        </div>

                                        <?php if($subscription_info['last_payment']): ?>
                                            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(0,0,0,0.1); font-size: 12px;">
                                                <strong>Dernier paiement:</strong> 
                                                <?php echo date('d/m/Y', strtotime($subscription_info['last_payment'])); ?>
                                                (<?php echo number_format($subscription_info['last_amount']); ?> DH)
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <a href="payments.php" class="btn btn-primary" style="width: 100%; justify-content: center;">
                                        <i class="fas fa-credit-card"></i> Voir mes paiements
                                    </a>
                                <?php else: ?>
                                    <div style="text-align: center; padding: 20px; color: #64748b;">
                                        <i class="fas fa-star" style="font-size: 2rem; margin-bottom: 8px; opacity: 0.3;"></i>
                                        <p>Aucun abonnement actif</p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if($managing_admin): ?>
                                    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                                        <h4 style="font-size: 14px; color: #1e293b; margin-bottom: 8px;">
                                            <i class="fas fa-user-tie"></i> Administrateur
                                        </h4>
                                        <div class="info-item">
                                            <span class="info-label">Nom</span>
                                            <span class="info-value"><?php echo htmlspecialchars($managing_admin['admin_name']); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">Email</span>
                                            <span class="info-value"><?php echo htmlspecialchars($managing_admin['admin_email']); ?></span>
                                        </div>
                                        <a href="messages.php" class="btn btn-secondary" style="width: 100%; justify-content: center; margin-top: 8px;">
                                            <i class="fas fa-envelope"></i> Contacter
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Form -->
                    <div class="profile-form-section">
                        <div class="form-header">
                            <i class="fas fa-user"></i>
                            <h3>Informations Personnelles</h3>
                        </div>
                        <div class="form-content">
                            <form method="POST" id="profileForm">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="full_name">Nom complet *</label>
                                        <input type="text" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($resident_info['full_name'] ?? ''); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="email">Adresse email *</label>
                                        <input type="email" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($resident_info['email'] ?? ''); ?>" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="phone">Téléphone</label>
                                        <input type="tel" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($resident_info['phone'] ?? ''); ?>">
                                    </div>

                                    <div class="form-group readonly">
                                        <label>Statut du compte</label>
                                        <input type="text" value="<?php echo htmlspecialchars($resident_info['status'] ?? 'Actif'); ?>" readonly>
                                    </div>
                                </div>

                                <div class="password-section">
                                    <h4>
                                        <i class="fas fa-lock"></i>
                                        Changer le mot de passe
                                    </h4>
                                    
                                    <div class="form-grid">
                                        <div class="form-group" style="grid-column: 1 / -1;">
                                            <label for="current_password">Mot de passe actuel</label>
                                            <input type="password" id="current_password" name="current_password">
                                        </div>

                                        <div class="form-group">
                                            <label for="new_password">Nouveau mot de passe</label>
                                            <input type="password" id="new_password" name="new_password">
                                            <small style="color: #64748b; font-size: 12px; margin-top: 4px;">Minimum 6 caractères</small>
                                        </div>

                                        <div class="form-group">
                                            <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                                            <input type="password" id="confirm_password" name="confirm_password">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                        <i class="fas fa-undo"></i> Annuler
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Sauvegarder les modifications
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Activity Panel -->
                <div class="activity-panel">
                    <div class="activity-header">
                        <div class="activity-title">Activité Récente</div>
                    </div>

                    <div class="activity-tabs">
                        <div class="activity-tab active">Activité</div>
                        <div class="activity-tab">Statistiques</div>
                    </div>

                    <?php if(!empty($recent_activities)): ?>
                        <?php foreach($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $activity['activity_type']; ?>">
                                <?php
                                    switch($activity['activity_type']) {
                                        case 'payment':
                                            echo '<i class="fas fa-credit-card"></i>';
                                            break;
                                        case 'message_sent':
                                            echo '<i class="fas fa-paper-plane"></i>';
                                            break;
                                        case 'message_received':
                                            echo '<i class="fas fa-inbox"></i>';
                                            break;
                                        default:
                                            echo '<i class="fas fa-circle"></i>';
                                    }
                                ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text"><?php echo htmlspecialchars($activity['activity_description']); ?></div>
                                <div class="activity-time"><?php echo date('d/m/Y à H:i', strtotime($activity['activity_date'])); ?></div>
                                <div class="activity-meta">
                                    <div class="tag">
                                        <?php 
                                            switch($activity['activity_type']) {
                                                case 'payment': echo 'Paiement'; break;
                                                case 'message_sent': echo 'Message'; break;
                                                case 'message_received': echo 'Reçu'; break;
                                                default: echo 'Activité';
                                            }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px 20px; color: #64748b;">
                            <i class="fas fa-history" style="font-size: 2rem; margin-bottom: 12px; opacity: 0.3;"></i>
                            <h4 style="margin-bottom: 8px;">Aucune activité récente</h4>
                            <p style="font-size: 14px;">Votre activité apparaîtra ici</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
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

            // Animate quick cards on hover
            document.querySelectorAll('.quick-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Form validation
            setupFormValidation();
        });

        function setupFormValidation() {
            const form = document.getElementById('profileForm');
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            // Real-time password validation
            newPassword.addEventListener('input', function() {
                validatePasswordStrength(this.value);
            });
            
            confirmPassword.addEventListener('input', function() {
                validatePasswordMatch();
            });
            
            newPassword.addEventListener('input', validatePasswordMatch);
            
            // Form submission validation
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                }
            });
        }

        function validatePasswordStrength(password) {
            if (password.length === 0) return;
            
            const newPasswordGroup = document.getElementById('new_password').parentNode;
            let strengthIndicator = newPasswordGroup.querySelector('.password-strength');
            
            if (!strengthIndicator) {
                strengthIndicator = document.createElement('div');
                strengthIndicator.className = 'password-strength';
                strengthIndicator.style.cssText = 'margin-top: 4px; font-size: 12px;';
                newPasswordGroup.appendChild(strengthIndicator);
            }
            
            const strength = calculatePasswordStrength(password);
            const colors = ['#ef4444', '#f59e0b', '#FFCB32'];
            const texts = ['Faible', 'Moyen', 'Fort'];
            
            strengthIndicator.innerHTML = `<span style="color: ${colors[strength]};">Force: ${texts[strength]}</span>`;
        }

        function calculatePasswordStrength(password) {
            let score = 0;
            if(password.length >= 8) score++;
            if(/[A-Z]/.test(password)) score++;
            if(/[0-9]/.test(password)) score++;
            if(/[^A-Za-z0-9]/.test(password)) score++;
            
            if(score <= 1) return 0; // Weak
            if(score <= 2) return 1; // Medium
            return 2; // Strong
        }

        function validatePasswordMatch() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (confirmPassword.value.length === 0) return;
            
            const confirmGroup = confirmPassword.parentNode;
            let matchIndicator = confirmGroup.querySelector('.password-match');
            
            if (!matchIndicator) {
                matchIndicator = document.createElement('div');
                matchIndicator.className = 'password-match';
                matchIndicator.style.cssText = 'margin-top: 4px; font-size: 12px;';
                confirmGroup.appendChild(matchIndicator);
            }
            
            if(newPassword.value === confirmPassword.value) {
                matchIndicator.innerHTML = '<span style="color: #FFCB32;">✓ Les mots de passe correspondent</span>';
                return true;
            } else {
                matchIndicator.innerHTML = '<span style="color: #ef4444;">✗ Les mots de passe ne correspondent pas</span>';
                return false;
            }
        }

        function validateForm() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const currentPassword = document.getElementById('current_password').value;
            
            // If trying to change password
            if(newPassword || confirmPassword) {
                if(!currentPassword) {
                    alert('Veuillez saisir votre mot de passe actuel pour le modifier.');
                    return false;
                }
                
                if(newPassword !== confirmPassword) {
                    alert('Les nouveaux mots de passe ne correspondent pas.');
                    return false;
                }
                
                if(newPassword.length < 6) {
                    alert('Le nouveau mot de passe doit contenir au moins 6 caractères.');
                    return false;
                }
            }
            
            return true;
        }

        function resetForm() {
            if (confirm('Êtes-vous sûr de vouloir annuler les modifications ?')) {
                document.getElementById('profileForm').reset();
                
                // Remove validation indicators
                document.querySelectorAll('.password-strength, .password-match').forEach(el => {
                    el.remove();
                });
            }
        }

        // Activity tabs
        document.querySelectorAll('.activity-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.activity-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // You can add logic here to switch between activity views
                if (this.textContent.trim() === 'Statistiques') {
                    // Show statistics view
                } else {
                    // Show activity view
                }
            });
        });

        // Navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (!this.href.includes('login.php')) {
                    e.preventDefault();
                    document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });

        // Search functionality
        document.querySelector('.search-box input').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            // Add search logic here if needed
        });

        // Storage animation on load
        window.addEventListener('load', function() {
            const storageFill = document.querySelector('.storage-fill');
            const originalWidth = storageFill.style.width || '75%';
            storageFill.style.width = '0%';
            setTimeout(() => {
                storageFill.style.width = originalWidth;
            }, 500);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // Ctrl + S to save profile
            if(event.ctrlKey && event.key === 's') {
                event.preventDefault();
                document.getElementById('profileForm').submit();
            }
        });
    </script>
</body>
</html>