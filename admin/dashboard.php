<?php
session_start();
require_once '../config.php';
// require_once '../config/database.php';
// require_once '../utils/helpers.php';

// Check if user is logged in and is admin


$page_title = "Admin Dashboard";

// Get statistics

// try {
//     // Get total syndics
//     $stmt = $db->query("SELECT COUNT(*) as count FROM syndic");
//     $total_syndics = $stmt->fetch()['count'];

//     // Get total users
//     $stmt = $db->query("SELECT COUNT(*) as count FROM utilisateur WHERE is_active = 1");
//     $total_users = $stmt->fetch()['count'];

//     // Get pending purchases
//     $stmt = $db->query("SELECT COUNT(*) as count FROM subscription_purchases WHERE is_processed = 0");
//     $pending_purchases = $stmt->fetch()['count'];

//     // Get total subscriptions
//     $stmt = $db->query("SELECT COUNT(*) as count FROM subscriptions WHERE is_active = 1");
//     $total_subscriptions = $stmt->fetch()['count'];

//     // Get recent purchases
//     $stmt = $db->query("SELECT sp.*, s.name as plan_name FROM subscription_purchases sp 
//                         JOIN subscriptions s ON sp.subscription_id = s.id_subscription 
//                         ORDER BY sp.purchase_date DESC LIMIT 5");
//     $recent_purchases = $stmt->fetchAll();

// } catch (Exception $e) {
//     $total_syndics = 0;
//     $total_users = 0;
//     $pending_purchases = 0;
//     $total_subscriptions = 0;
//     $recent_purchases = [];
// }
?>

<!DOCTYPE html>
<html lang="en">

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
            <span><i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?></span>
            <a href="logout.php" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
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
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="subscriptions.php">
                            <i class="fas fa-tags"></i> Subscriptions
                        </a>
                    </li>
                    <li>
                        <a href="syndic-accounts.php">
                            <i class="fas fa-building"></i> Syndic Accounts
                        </a>
                    </li>
                    <li>
                        <a href="users.php">
                            <i class="fas fa-users"></i> Users
                        </a>
                    </li>
                    <li>
                        <a href="purchases.php">
                            <i class="fas fa-shopping-cart"></i> Purchases
                        </a>
                    </li>
                    <li>
                        <a href="reports.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                    <li>
                        <a href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?php echo $_SESSION['user_name']; ?>!</p>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success'];
                    unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $_SESSION['error'];
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card" data-stat="syndics">
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Syndics</h3>
                        <div class="stat-number"><?php echo $total_syndics; ?></div>
                    </div>
                </div>

                <div class="stat-card" data-stat="users">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Users</h3>
                        <div class="stat-number"><?php echo $total_users; ?></div>
                    </div>
                </div>

                <div class="stat-card pending" data-stat="pending">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Pending Purchases</h3>
                        <div class="stat-number"><?php echo $pending_purchases; ?></div>
                    </div>
                </div>

                <div class="stat-card" data-stat="subscriptions">
                    <div class="stat-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Active Plans</h3>
                        <div class="stat-number"><?php echo $total_subscriptions; ?></div>
                    </div>
                </div>
            </div>

            <!-- last updated indicator -->
            <div class="stats-footer">
                <small class="text-muted last-updated">Last updated: <?php echo date('H:i:s'); ?></small>
            </div>

            <!-- Quick Actions -->
            <div class="content-section">
                <h2>Quick Actions</h2>
                <div class="quick-actions">
                    <a href="syndic-accounts.php" class="action-card">
                        <i class="fas fa-plus-circle"></i>
                        <h3>Create Syndic Account</h3>
                        <p>Process new subscription purchases</p>
                    </a>

                    <a href="subscriptions.php" class="action-card">
                        <i class="fas fa-edit"></i>
                        <h3>Manage Subscriptions</h3>
                        <p>Edit pricing and features</p>
                    </a>

                    <a href="users.php" class="action-card">
                        <i class="fas fa-user-cog"></i>
                        <h3>User Management</h3>
                        <p>Manage system users</p>
                    </a>

                    <a href="reports.php" class="action-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>View Reports</h3>
                        <p>System analytics and reports</p>
                    </a>
                </div>
            </div>

            <!-- Recent Purchases -->
            <div class="content-section">
                <h2>Recent Purchases</h2>
                <?php if (!empty($recent_purchases)): ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Plan</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_purchases as $purchase): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($purchase['purchase_date'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($purchase['syndic_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($purchase['syndic_email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($purchase['plan_name']); ?></td>
                                        <td>€<?php echo number_format($purchase['amount_paid'], 2); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $purchase['payment_status']; ?>">
                                                <?php echo ucfirst($purchase['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!$purchase['is_processed']): ?>
                                                <a href="process-purchase.php?id=<?php echo $purchase['id']; ?>"
                                                    class="btn btn-sm btn-primary">Process</a>
                                            <?php else: ?>
                                                <span class="text-muted">Processed</span>
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
                        <h3>No recent purchases</h3>
                        <p>New subscription purchases will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

</body>

</html>