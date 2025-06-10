<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit();
}

// Initialize variables
$total_subscriptions = 0;
$total_admins = 0;
$total_members = 0;
$total_syndics = 0;
$pending_purchases = 0;
$recent_purchases = [];

try {
    // Get total subscriptions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM subscription WHERE is_active = 1");
    $stmt->execute();
    $total_subscriptions = $stmt->fetch()['count'];

    // Get total admins
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin");
    $stmt->execute();
    $total_admins = $stmt->fetch()['count'];

    // Get total members
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member");
    $stmt->execute();
    $total_members = $stmt->fetch()['count'];

    // Get total syndics
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member WHERE role = 2");
    $stmt->execute();
    $total_syndics = $stmt->fetch()['count'];

    // Get total pending purchases
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member WHERE status = 'pending'");
    $stmt->execute();
    $pending_purchases = $stmt->fetch()['count'];

    // Get recent purchases with proper data
    $stmt = $conn->prepare("
        SELECT 
            m.id_member,
            m.full_name AS syndic_name,
            m.email AS syndic_email,
            s.name_subscription AS plan_name,
            ams.amount AS amount_paid,
            ams.date_payment AS purchase_date,
            m.status AS payment_status,
            CASE WHEN m.status = 'active' THEN 1 ELSE 0 END AS is_processed
        FROM admin_member_subscription ams
        JOIN member m ON ams.id_member = m.id_member
        JOIN subscription s ON ams.id_subscription = s.id_subscription
        ORDER BY ams.date_payment DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "Une erreur s'est produite lors du chargement des données du tableau de bord.";
}

$page_title = "Admin Dashboard - Syndic Way";
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
                    <li class="active">
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
                    <li>
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
                <h1>Tableau de bord administrateur</h1>
                <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>!</p>
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

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card" data-stat="syndics">
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Syndics au total</h3>
                        <div class="stat-number"><?php echo $total_syndics; ?></div>
                    </div>
                </div>

                <div class="stat-card" data-stat="users">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Utilisateurs au total</h3>
                        <div class="stat-number"><?php echo $total_members; ?></div>
                    </div>
                </div>

                <div class="stat-card pending" data-stat="pending">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Achats en attente</h3>
                        <div class="stat-number"><?php echo $pending_purchases; ?></div>
                    </div>
                </div>

                <div class="stat-card" data-stat="subscriptions">
                    <div class="stat-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Forfaits actifs</h3>
                        <div class="stat-number"><?php echo $total_subscriptions; ?></div>
                    </div>
                </div>
            </div>

            <!-- Last updated indicator -->
            <div class="stats-footer">
                <small class="text-muted last-updated">Dernière mise à jour : <?php echo date('H:i:s'); ?></small>
            </div>

            <!-- Quick Actions -->
            <div class="content-section">
                <h2>Actions rapides</h2>
                <div class="quick-actions">
                    <a href="syndic-accounts.php" class="action-card">
                        <i class="fas fa-plus-circle"></i>
                        <h3>Créer un compte Syndic</h3>
                        <p>Traiter les nouveaux achats d'abonnements</p>
                    </a>

                    <a href="subscriptions.php" class="action-card">
                        <i class="fas fa-edit"></i>
                        <h3>Gérer les abonnements</h3>
                        <p>Modifier les tarifs et les fonctionnalités</p>
                    </a>

                    <a href="users.php" class="action-card">
                        <i class="fas fa-user-cog"></i>
                        <h3>Gestion des utilisateurs</h3>
                        <p>Gérer les utilisateurs du système</p>
                    </a>

                    <a href="reports.php" class="action-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>Voir les rapports</h3>
                        <p>Analyses et rapports du système</p>
                    </a>
                </div>
            </div>

            <!-- Recent Purchases -->
            <div class="content-section">
                <h2>Achats récents</h2>
                <?php if (!empty($recent_purchases)): ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Forfait</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_purchases as $purchase): ?>
                                   <tr>
                                        <td><?php echo date('j M Y', strtotime($purchase['purchase_date'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($purchase['syndic_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($purchase['syndic_email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($purchase['plan_name']); ?></td>
                                        <td><?php echo number_format($purchase['amount_paid'], 2); ?> DH</td>
                                        <td>
                                            <span class="status-badge status-<?php echo $purchase['payment_status']; ?>">
                                                <?php 
                                                    $status_text = $purchase['payment_status'] == 'pending' ? 'En attente' : 'Actif';
                                                    echo $status_text;
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!$purchase['is_processed']): ?>
                                                <a href="process-purchase.php?id=<?php echo $purchase['id_member']; ?>"
                                                    class="btn btn-sm btn-primary">Traiter</a>
                                            <?php else: ?>
                                                <span class="text-muted">Traité</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Aucun achat récent</h3>
                        <p>Les nouveaux achats d'abonnements apparaîtront ici.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

</body>
</html>