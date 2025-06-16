<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in as resident
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'resident') {
    header('Location: ../public/login.php');
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

    // Get property manager/syndic information
    $stmt = $conn->prepare("
SELECT DISTINCT a.id_admin, a.name as syndic_name, a.email as syndic_email,
               m_admin.id_member as syndic_member_id, m_admin.full_name as syndic_full_name
        FROM admin a
        JOIN admin_member_link aml ON aml.id_admin = a.id_admin
        JOIN member m ON m.id_member = aml.id_member
        JOIN apartment ap ON ap.id_member = m.id_member
        LEFT JOIN member m_admin ON m_admin.email = a.email
        WHERE ap.id_residence = ?
        LIMIT 1
    ");
    $stmt->execute([$resident_info['id_residence'] ?? 0]);
    $property_manager = $stmt->fetch(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log($e->getMessage());
    $resident_info = null;
    $property_manager = null;
}

// Get announcements data
try {
    // Get all announcements received by this resident
    $stmt = $conn->prepare("
        SELECT ma.*, 
               poster.full_name as poster_name, 
               poster.email as poster_email,
               ap_poster.number as poster_apartment,
               ap_poster.floor as poster_floor,
               DATE_FORMAT(ma.date_announcement, '%d/%m/%Y à %H:%i') as formatted_date,
               DATE_FORMAT(ma.date_announcement, '%Y-%m-%d') as date_only
        FROM member_announcements ma
        JOIN member poster ON poster.id_member = ma.id_poster
        LEFT JOIN apartment ap_poster ON ap_poster.id_member = poster.id_member
        WHERE ma.id_receiver = ?
        ORDER BY ma.date_announcement DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $all_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Categorize announcements
    $property_manager_announcements = [];
    $recent_announcements = [];
    
    foreach($all_announcements as $announcement) {
        // Check if announcement is from property manager
        if($property_manager && ($announcement['poster_email'] === $property_manager['syndic_email'] || 
                               $announcement['id_poster'] === $property_manager['syndic_member_id'])) {
            $property_manager_announcements[] = $announcement;
        }
        
        // Check if announcement is recent (last 7 days)
        if(strtotime($announcement['date_announcement']) > strtotime('-7 days')) {
            $recent_announcements[] = $announcement;
        }
    }

    // Statistics
    $total_announcements = count($all_announcements);
    $property_manager_announcements_count = count($property_manager_announcements);
    $recent_count = count($recent_announcements);

} catch(PDOException $e) {
    error_log($e->getMessage());
    $resident_info = null;
    $property_manager = null;
    $all_announcements = [];
    $property_manager_announcements = [];
    $recent_announcements = [];
    $total_announcements = 0;
    $property_manager_announcements_count = 0;
    $recent_count = 0;
}

// Get filter parameters
$filter_type = $_GET['filter'] ?? 'all';
$search_term = $_GET['search'] ?? '';

// Apply filters
$filtered_announcements = $all_announcements;

if($filter_type === 'syndic') {
    $filtered_announcements = $property_manager_announcements;
} elseif($filter_type === 'recent') {
    $filtered_announcements = $recent_announcements;
}

// Apply search filter
if($search_term) {
    $filtered_announcements = array_filter($filtered_announcements, function($announcement) use ($search_term) {
        return stripos($announcement['poster_name'], $search_term) !== false ||
               stripos($announcement['title'], $search_term) !== false ||
               stripos($announcement['content'], $search_term) !== false;
    });
}

$page_title = "Mes Annonces - Syndic Way";
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
.user-info {
    margin-top: auto;
    padding: 0 20px;
    border-top: 1px solid #e2e8f0;
    padding-top: 20px;
}
.user-avatar {
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
.user-name {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}
.user-role {
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
.header-user-avatar {
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
    padding: 12px 24px;
    margin: 0 24px 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
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
.quick-card.total .quick-card-icon { background: #FFCB32; } /* Changed to #FFCB32 */
.quick-card.syndic .quick-card-icon { background: #10b981; }
.quick-card.recent .quick-card-icon { background: #f59e0b; }
.quick-card.priority .quick-card-icon { background: #ef4444; }
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
/* Property Manager Card */
.property-manager-card {
    background: #FFCB32; 
    color: white;
    border-radius: 12px;
    padding: 20px;
    margin: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.manager-avatar {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.manager-info {
    flex: 1;
}
.manager-name {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 4px;
}
.manager-title {
    opacity: 0.9;
    margin-bottom: 8px;
}
.manager-contact {
    font-size: 14px;
    opacity: 0.8;
}
/* Content Area */
.content-area {
    flex: 1;
    padding: 24px;
}
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
/* Filter Section */
.filter-section {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 20px;
    margin-bottom: 20px;
}
.filter-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.filter-title {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}
.filter-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}
.filter-tab {
    padding: 8px 16px;
    border: 1px solid #e2e8f0;
    background: white;
    border-radius: 20px;
    font-size: 14px;
    color: #64748b;
    text-decoration: none;
    transition: all 0.2s;
}
.filter-tab:hover,
.filter-tab.active {
    background: #FFCB32; /* Changed to #FFCB32 */
    color: white;
    border-color: #FFCB32; /* Changed to #FFCB32 */
}
.search-form {
    display: flex;
    gap: 8px;
}
.search-input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
}
.search-btn {
    padding: 8px 16px;
    background: #FFCB32; /* Changed to #FFCB32 */
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}
/* Announcements List */
.announcements-container {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}
.announcements-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
}
.announcements-title {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}
.announcements-list {
    max-height: 600px;
    overflow-y: auto;
}
.announcement-item {
    padding: 20px;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.2s;
}
.announcement-item:hover {
    background: #f8fafc;
}
.announcement-item:last-child {
    border-bottom: none;
}
.announcement-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}
.announcement-avatar {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
}
.announcement-avatar.syndic {
    background: #FFCB32; /* Changed to #FFCB32 */
}
.announcement-avatar.resident {
    background: #8b5cf6;
}
.announcement-content {
    flex: 1;
}
.announcement-sender {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 2px;
}
.announcement-details {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 8px;
}
.announcement-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 8px;
}
.announcement-text {
    color: #4b5563;
    line-height: 1.5;
    margin-bottom: 8px;
}
.announcement-time {
    font-size: 12px;
    color: #94a3b8;
    margin-left: auto;
}
.announcement-badges {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}
.announcement-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}
.badge-syndic {
    background: #dcfce7;
    color: #16a34a;
}
.badge-recent {
    background: #FFF0C3; /* Changed to #FFF0C3 */
    color: #FFCB32; /* Changed to #FFCB32 */
}
.badge-urgent {
    background: #fee2e2;
    color: #dc2626;
}
.badge-high {
    background: #FFF0C3; /* Changed to #FFF0C3 */
    color: #FFCB32; /* Changed to #FFCB32 */
}
.badge-normal {
    background: #e0e7ff;
    color: #3b82f6;
}
.badge-low {
    background: #f3f4f6;
    color: #6b7280;
}
/* Actions */
.announcement-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}
.btn {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-sm {
    padding: 4px 8px;
    font-size: 11px;
}
.btn-primary {
    background: #FFCB32; /* Changed to #FFCB32 */
    color: white;
}
.btn-secondary {
    background: #6b7280;
    color: white;
}
.btn-outline {
    background: transparent;
    border: 1px solid #e2e8f0;
    color: #64748b;
}
.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
    margin-bottom: 8px;
    color: #1e293b;
}
/* Responsive */
@media (max-width: 1024px) {
    .quick-access-grid {
        grid-template-columns: repeat(2, 1fr);
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
    .filter-tabs {
        flex-wrap: wrap;
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
                    <i class="fas fa-home"></i>
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
                <a href="announcements.php" class="nav-item active">
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

            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'R', 0, 1)); ?></div>
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Résident'); ?></div>
                <div class="user-role">Résident</div>
                <?php if($resident_info): ?>
                    <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">
                        Apt. <?php echo $resident_info['number']; ?> - <?php echo htmlspecialchars($resident_info['building_name']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-nav">
                    <a href="announcements.php" class="active">Annonces</a>
                    <a href="messages.php">Messages</a>
                    <a href="#">Voisinage</a>
                    <a href="profile.php">Profil</a>
                </div>

                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Rechercher des annonces..." id="headerSearch">
                    </div>
                    <i class="fas fa-bell" style="color: #64748b; cursor: pointer;"></i>
                    <div class="header-user">
                        <div class="header-user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'R', 0, 1)); ?></div>
                    </div>
                </div>
            </div>

            <!-- Property Manager Card -->
            <?php if($property_manager): ?>
                <div class="property-manager-card">
                    <div class="manager-avatar">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="manager-info">
                        <div class="manager-name"><?php echo htmlspecialchars($property_manager['syndic_name']); ?></div>
                        <div class="manager-title">Votre Syndic</div>
                        <div class="manager-contact">
                            <i class="fas fa-envelope"></i>
                            <?php echo htmlspecialchars($property_manager['syndic_email']); ?>
                        </div>
                    </div>
                    <div>
                        <a href="messages.php" style="background: rgba(255,255,255,0.2); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px;">
                            <i class="fas fa-paper-plane"></i> Contacter
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Access -->
            <div class="quick-access">
                <div class="quick-access-header">
                    <div class="quick-access-title">Aperçu des Annonces</div>
                    <i class="fas fa-sync-alt" style="color: #64748b; cursor: pointer;" onclick="window.location.reload()"></i>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card total" onclick="applyFilter('all')">
                        <div class="quick-card-icon">
                            <i class="fas fa-list"></i>
                        </div>
                        <div class="quick-card-title">Total Annonces</div>
                        <div class="quick-card-count"><?php echo $total_announcements; ?></div>
                        <div class="quick-card-stats">Toutes reçues</div>
                    </div>

                    <div class="quick-card syndic" onclick="applyFilter('syndic')">
                        <div class="quick-card-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="quick-card-title">Du Syndic</div>
                        <div class="quick-card-count"><?php echo $property_manager_announcements_count; ?></div>
                        <div class="quick-card-stats">Communications officielles</div>
                    </div>

                    <div class="quick-card recent" onclick="applyFilter('recent')">
                        <div class="quick-card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="quick-card-title">Récentes</div>
                        <div class="quick-card-count"><?php echo $recent_count; ?></div>
                        <div class="quick-card-stats">7 derniers jours</div>
                    </div>

                    <div class="quick-card priority">
                        <div class="quick-card-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="quick-card-title">Priorité</div>
                        <div class="quick-card-count">
                            <?php 
                                $urgent_count = count(array_filter($all_announcements, function($a) { 
                                    return $a['Priority'] === 'urgent' || $a['Priority'] === 'high'; 
                                }));
                                echo $urgent_count; 
                            ?>
                        </div>
                        <div class="quick-card-stats">Urgentes/Importantes</div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Breadcrumb -->
                <div class="breadcrumb">
                    <a href="dashboard.php">Accueil</a>
                    <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                    <a href="#">Mes Annonces</a>
                    <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                    <span><?php echo ucfirst($filter_type === 'all' ? 'Toutes' : $filter_type); ?></span>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="filter-header">
                        <div class="filter-title">Filtrer les annonces</div>
                        <div style="font-size: 14px; color: #64748b;">
                            <?php echo count($filtered_announcements); ?> annonce<?php echo count($filtered_announcements) > 1 ? 's' : ''; ?>
                        </div>
                    </div>

                    <div class="filter-tabs">
                        <a href="?filter=all<?php echo $search_term ? '&search='.urlencode($search_term) : ''; ?>" 
                           class="filter-tab <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i> Toutes (<?php echo $total_announcements; ?>)
                        </a>
                        <a href="?filter=syndic<?php echo $search_term ? '&search='.urlencode($search_term) : ''; ?>" 
                           class="filter-tab <?php echo $filter_type === 'syndic' ? 'active' : ''; ?>">
                            <i class="fas fa-user-tie"></i> Syndic (<?php echo $property_manager_announcements_count; ?>)
                        </a>
                        <a href="?filter=recent<?php echo $search_term ? '&search='.urlencode($search_term) : ''; ?>" 
                           class="filter-tab <?php echo $filter_type === 'recent' ? 'active' : ''; ?>">
                            <i class="fas fa-clock"></i> Récentes (<?php echo $recent_count; ?>)
                        </a>
                    </div>

                    <form method="GET" class="search-form">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter_type); ?>">
                        <input type="text" name="search" class="search-input" 
                               placeholder="Rechercher par titre, contenu ou expéditeur..." 
                               value="<?php echo htmlspecialchars($search_term); ?>">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </form>
                </div>

                <!-- Announcements Container -->
                <div class="announcements-container">
                    <div class="announcements-header">
                        <div class="announcements-title">
                            <i class="fas fa-bullhorn"></i>
                            Mes Annonces
                            <?php if($search_term): ?>
                                - Résultats pour "<?php echo htmlspecialchars($search_term); ?>"
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if(!empty($filtered_announcements)): ?>
                        <div class="announcements-list">
                            <?php foreach($filtered_announcements as $announcement): ?>
                                <?php 
                                    $is_syndic = $property_manager && ($announcement['poster_email'] === $property_manager['syndic_email'] || 
                                                $announcement['id_poster'] === $property_manager['syndic_member_id']);
                                    $is_recent = strtotime($announcement['date_announcement']) > strtotime('-7 days');
                                ?>
                                <div class="announcement-item">
                                    <div class="announcement-header">
                                        <div class="announcement-avatar <?php echo $is_syndic ? 'syndic' : 'resident'; ?>">
                                            <?php if($is_syndic): ?>
                                                <i class="fas fa-user-tie"></i>
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($announcement['poster_name'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="announcement-content">
                                            <div class="announcement-sender">
                                                <?php echo htmlspecialchars($announcement['poster_name']); ?>
                                            </div>
                                            <div class="announcement-details">
                                                <?php if($announcement['poster_apartment']): ?>
                                                    Appartement <?php echo $announcement['poster_apartment']; ?>
                                                    <?php if($announcement['poster_floor']): ?>
                                                        - Étage <?php echo $announcement['poster_floor']; ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($announcement['poster_email']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="announcement-title">
                                                <?php echo htmlspecialchars($announcement['title']); ?>
                                            </div>
                                            <div class="announcement-text">
                                                <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                            </div>
                                            <div class="announcement-badges">
                                                <?php if($is_syndic): ?>
                                                    <span class="announcement-badge badge-syndic">Syndic</span>
                                                <?php endif; ?>
                                                <?php if($is_recent): ?>
                                                    <span class="announcement-badge badge-recent">Nouveau</span>
                                                <?php endif; ?>
                                                <?php if($announcement['Priority']): ?>
                                                    <span class="announcement-badge badge-<?php echo $announcement['Priority']; ?>">
                                                        <?php echo ucfirst($announcement['Priority']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="announcement-actions">
                                                <button class="btn btn-secondary btn-sm" onclick="markAsRead(this)">
                                                    <i class="fas fa-check"></i> Marquer comme lu
                                                </button>
                                                <?php if($is_syndic && $property_manager): ?>
                                                    <a href="messages.php" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-reply"></i> Répondre au syndic
                                                    </a>
                                                <?php endif; ?>
                                                <button class="btn btn-outline btn-sm" onclick="printAnnouncement(this)">
                                                    <i class="fas fa-print"></i> Imprimer
                                                </button>
                                            </div>
                                        </div>
                                        <div class="announcement-time">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo $announcement['formatted_date']; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bullhorn"></i>
                            <h3>Aucune annonce trouvée</h3>
                            <?php if($filter_type !== 'all'): ?>
                                <p>Aucune annonce trouvée pour ce filtre.</p>
                                <a href="?filter=all" class="btn btn-primary">
                                    <i class="fas fa-list"></i> Voir toutes les annonces
                                </a>
                            <?php elseif($search_term): ?>
                                <p>Aucune annonce trouvée pour "<?php echo htmlspecialchars($search_term); ?>".</p>
                                <a href="?filter=<?php echo $filter_type; ?>" class="btn btn-primary">
                                    <i class="fas fa-times"></i> Effacer la recherche
                                </a>
                            <?php else: ?>
                                <p>Vous n'avez reçu aucune annonce pour le moment.</p>
                                <p>Les annonces de votre syndic et des autres résidents apparaîtront ici.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Apply filter function
        function applyFilter(filterType) {
            const currentSearch = new URLSearchParams(window.location.search).get('search') || '';
            let url = '?filter=' + filterType;
            if(currentSearch) {
                url += '&search=' + encodeURIComponent(currentSearch);
            }
            window.location.href = url;
        }

        // Mark announcement as read
        function markAsRead(button) {
            button.innerHTML = '<i class="fas fa-check-circle"></i> Lu';
            button.style.background = '#10b981';
            button.style.color = 'white';
            button.disabled = true;
            
            // Add fade effect to announcement
            const announcement = button.closest('.announcement-item');
            announcement.style.opacity = '0.7';
            
            // Show success message
            showNotification('Annonce marquée comme lue', 'success');
        }

        // Print announcement
        function printAnnouncement(button) {
            const announcement = button.closest('.announcement-item');
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Annonce</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 2rem; line-height: 1.6; }
                            .header { border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 2rem; }
                            .content { font-size: 14px; }
                            .meta { color: #666; font-size: 12px; margin-top: 1rem; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>Annonce</h1>
                            <p>Résident: <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Résident'); ?></p>
                            <p>Immeuble: <?php echo htmlspecialchars($resident_info['building_name'] ?? 'Non défini'); ?></p>
                            <p>Appartement: <?php echo $resident_info['number'] ?? 'N/A'; ?></p>
                        </div>
                        <div class="content">
                            ${announcement.innerHTML}
                        </div>
                        <div class="meta">
                            <p>Imprimé le: ${new Date().toLocaleDateString('fr-FR')}</p>
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                ${message}
            `;
            
            document.querySelector('.main-content').insertBefore(
                notification, 
                document.querySelector('.quick-access')
            );
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-20px)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Header search functionality
        document.getElementById('headerSearch').addEventListener('keypress', function(e) {
            if(e.key === 'Enter') {
                const searchTerm = this.value;
                const currentFilter = new URLSearchParams(window.location.search).get('filter') || 'all';
                let url = '?filter=' + currentFilter;
                if(searchTerm) {
                    url += '&search=' + encodeURIComponent(searchTerm);
                }
                window.location.href = url;
            }
        });

        // Quick card interactions
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Enhanced keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // Quick filter shortcuts
            if(event.altKey && !event.ctrlKey) {
                switch(event.key) {
                    case '1':
                        event.preventDefault();
                        applyFilter('all');
                        break;
                    case '2':
                        event.preventDefault();
                        applyFilter('syndic');
                        break;
                    case '3':
                        event.preventDefault();
                        applyFilter('recent');
                        break;
                }
            }
            
            // Focus search with Ctrl+F
            if(event.ctrlKey && event.key === 'f') {
                event.preventDefault();
                document.querySelector('.search-input').focus();
            }
            
            // Refresh with F5 or Ctrl+R
            if(event.key === 'F5' || (event.ctrlKey && event.key === 'r')) {
                event.preventDefault();
                window.location.reload();
            }
        });

        // Animation on load
        document.addEventListener('DOMContentLoaded', function() {
            // Animate statistics counters
            document.querySelectorAll('.quick-card-count').forEach((counter, index) => {
                const target = parseInt(counter.textContent);
                counter.textContent = '0';
                setTimeout(() => {
                    animateCounter(counter, target);
                }, index * 200);
            });

            // Animate announcement items
            document.querySelectorAll('.announcement-item').forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.3s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Enhanced hover effects
            document.querySelectorAll('.quick-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
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

        // Filter announcements by priority
        function filterByPriority(priority) {
            const announcements = document.querySelectorAll('.announcement-item');
            
            announcements.forEach(announcement => {
                const badges = announcement.querySelectorAll('.announcement-badge');
                let show = priority === 'all';
                
                badges.forEach(badge => {
                    if (badge.classList.contains('badge-' + priority)) {
                        show = true;
                    }
                });
                
                announcement.style.display = show ? 'block' : 'none';
            });
        }

        // Real-time search
        function setupRealTimeSearch() {
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const announcements = document.querySelectorAll('.announcement-item');
                    
                    announcements.forEach(announcement => {
                        const title = announcement.querySelector('.announcement-title')?.textContent.toLowerCase() || '';
                        const content = announcement.querySelector('.announcement-text')?.textContent.toLowerCase() || '';
                        const sender = announcement.querySelector('.announcement-sender')?.textContent.toLowerCase() || '';
                        
                        const matches = title.includes(searchTerm) || 
                                       content.includes(searchTerm) || 
                                       sender.includes(searchTerm);
                        
                        announcement.style.display = matches ? 'block' : 'none';
                    });
                });
            }
        }

        // Initialize real-time search
        document.addEventListener('DOMContentLoaded', setupRealTimeSearch);

        // Responsive menu toggle for mobile
        function toggleMobileMenu() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('mobile-open');
        }

        // Add mobile styles for responsive behavior
        if(window.innerWidth <= 768) {
            document.querySelector('.container').style.flexDirection = 'column';
            document.querySelector('.sidebar').style.width = '100%';
            document.querySelector('.sidebar').style.height = 'auto';
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            if(window.innerWidth <= 768) {
                document.querySelector('.container').style.flexDirection = 'column';
                document.querySelector('.sidebar').style.width = '100%';
                document.querySelector('.sidebar').style.height = 'auto';
            } else {
                document.querySelector('.container').style.flexDirection = 'row';
                document.querySelector('.sidebar').style.width = '240px';
                document.querySelector('.sidebar').style.height = '100vh';
            }
        });

        // Auto-save read status (local storage simulation)
        function saveReadStatus(announcementId) {
            const readAnnouncements = JSON.parse(localStorage.getItem('readAnnouncements') || '[]');
            if (!readAnnouncements.includes(announcementId)) {
                readAnnouncements.push(announcementId);
                localStorage.setItem('readAnnouncements', JSON.stringify(readAnnouncements));
            }
        }

        // Load read status on page load
        document.addEventListener('DOMContentLoaded', function() {
            const readAnnouncements = JSON.parse(localStorage.getItem('readAnnouncements') || '[]');
            
            document.querySelectorAll('.announcement-item').forEach((item, index) => {
                if (readAnnouncements.includes(index.toString())) {
                    item.style.opacity = '0.7';
                    const readBtn = item.querySelector('.btn-secondary');
                    if (readBtn && readBtn.textContent.includes('Marquer')) {
                        readBtn.innerHTML = '<i class="fas fa-check-circle"></i> Lu';
                        readBtn.style.background = '#10b981';
                        readBtn.style.color = 'white';
                        readBtn.disabled = true;
                    }
                }
            });
        });

        // Enhanced mark as read with persistence
        function markAsRead(button) {
            const announcement = button.closest('.announcement-item');
            const announcementIndex = Array.from(announcement.parentNode.children).indexOf(announcement);
            
            button.innerHTML = '<i class="fas fa-check-circle"></i> Lu';
            button.style.background = '#10b981';
            button.style.color = 'white';
            button.disabled = true;
            
            announcement.style.opacity = '0.7';
            
            // Save to local storage
            saveReadStatus(announcementIndex.toString());
            
            showNotification('Annonce marquée comme lue', 'success');
        }
    </script>
</body>
</html>