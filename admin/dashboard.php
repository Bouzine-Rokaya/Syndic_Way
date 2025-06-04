<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit();
}
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

    // Get latest subscription purchases
    $stmt = $conn->prepare("
        SELECT 
            a.name AS admin_name, 
            m.full_name AS member_name,
            s.name_subscription AS subscription_name,
            s.price_subscription,
            ams.date_payment,
            ams.amount,
            ams.is_processed
        FROM admin_member_subscription ams
        JOIN admin a ON ams.id_admin = a.id_admin
        JOIN member m ON ams.id_member = m.id_member
        JOIN subscription s ON ams.id_subscription = s.id_subscription
        ORDER BY ams.date_payment DESC
        LIMIT 5
    ");
    $stmt->execute();
    $latest_subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "An error occurred while fetching dashboard data.";
}

$latest_subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                        <div class="stat-number"><?php echo $total_members; ?></div>
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
                                        <td><?php echo date('j M Y', strtotime($purchase['purchase_date'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($purchase['syndic_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($purchase['syndic_email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($purchase['plan_name']); ?></td>
                                        <td><?php echo number_format($purchase['amount_paid'], 2); ?>DH</td>
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