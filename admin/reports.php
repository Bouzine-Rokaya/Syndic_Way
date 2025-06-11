<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit();
}

// Handle report generation requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'generate_revenue_report') {
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            
            if (empty($start_date) || empty($end_date)) {
                throw new Exception("Les dates de début et de fin sont requises.");
            }
            
            $_SESSION['report_params'] = [
                'type' => 'revenue',
                'start_date' => $start_date,
                'end_date' => $end_date
            ];
            
            $_SESSION['success'] = "Rapport de revenus généré avec succès.";
            
        } elseif ($action === 'generate_subscription_report') {
            $_SESSION['report_params'] = [
                'type' => 'subscription'
            ];
            
            $_SESSION['success'] = "Rapport d'abonnements généré avec succès.";
            
        } elseif ($action === 'generate_user_activity_report') {
            $period = $_POST['period'] ?? 'month';
            
            $_SESSION['report_params'] = [
                'type' => 'user_activity',
                'period' => $period
            ];
            
            $_SESSION['success'] = "Rapport d'activité utilisateurs généré avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header('Location: reports.php');
    exit();
}

// Get report data based on session parameters
$current_report = null;
$report_data = [];

if (isset($_SESSION['report_params'])) {
    $params = $_SESSION['report_params'];
    
    try {
        switch ($params['type']) {
            case 'revenue':
                $current_report = 'revenue';
                $report_data = generateRevenueReport($conn, $params['start_date'], $params['end_date']);
                break;
                
            case 'subscription':
                $current_report = 'subscription';
                $report_data = generateSubscriptionReport($conn);
                break;
                
            case 'user_activity':
                $current_report = 'user_activity';
                $report_data = generateUserActivityReport($conn, $params['period']);
                break;
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de la génération du rapport: " . $e->getMessage();
    }
}

// Revenue Report Function
function generateRevenueReport($conn, $start_date, $end_date) {
    $data = [];
    
    // Total revenue in period
    $stmt = $conn->prepare("
        SELECT SUM(amount) as total_revenue, COUNT(*) as total_transactions
        FROM admin_member_subscription 
        WHERE date_payment BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $data['summary'] = $stmt->fetch();
    
    // Revenue by subscription type
    $stmt = $conn->prepare("
        SELECT s.name_subscription, SUM(ams.amount) as revenue, COUNT(*) as count
        FROM admin_member_subscription ams
        JOIN subscription s ON ams.id_subscription = s.id_subscription
        WHERE ams.date_payment BETWEEN ? AND ?
        GROUP BY s.id_subscription
        ORDER BY revenue DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $data['by_subscription'] = $stmt->fetchAll();
    
    // Revenue by month
    $stmt = $conn->prepare("
        SELECT YEAR(date_payment) as year, MONTH(date_payment) as month, 
               SUM(amount) as revenue, COUNT(*) as transactions
        FROM admin_member_subscription 
        WHERE date_payment BETWEEN ? AND ?
        GROUP BY YEAR(date_payment), MONTH(date_payment)
        ORDER BY year, month
    ");
    $stmt->execute([$start_date, $end_date]);
    $data['by_month'] = $stmt->fetchAll();
    
    // Revenue by city
    $stmt = $conn->prepare("
        SELECT c.city_name, SUM(ams.amount) as revenue, COUNT(*) as customers
        FROM admin_member_subscription ams
        JOIN member m ON ams.id_member = m.id_member
        JOIN apartment ap ON ap.id_member = m.id_member
        JOIN residence r ON r.id_residence = ap.id_residence
        JOIN city c ON c.id_city = r.id_city
        WHERE ams.date_payment BETWEEN ? AND ?
        GROUP BY c.id_city
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $data['by_city'] = $stmt->fetchAll();
    
    $data['period'] = ['start' => $start_date, 'end' => $end_date];
    
    return $data;
}

// Subscription Report Function
function generateSubscriptionReport($conn) {
    $data = [];
    
    // Subscription overview
    $stmt = $conn->prepare("
        SELECT s.*, COUNT(ams.id_subscription) as active_subscribers,
               SUM(ams.amount) as total_revenue
        FROM subscription s
        LEFT JOIN admin_member_subscription ams ON s.id_subscription = ams.id_subscription
        GROUP BY s.id_subscription
        ORDER BY active_subscribers DESC
    ");
    $stmt->execute();
    $data['subscriptions'] = $stmt->fetchAll();
    
    // Subscription trends (last 6 months)
    $stmt = $conn->prepare("
        SELECT s.name_subscription, 
               YEAR(ams.date_payment) as year, 
               MONTH(ams.date_payment) as month,
               COUNT(*) as new_subscribers
        FROM admin_member_subscription ams
        JOIN subscription s ON ams.id_subscription = s.id_subscription
        WHERE ams.date_payment >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY s.id_subscription, YEAR(ams.date_payment), MONTH(ams.date_payment)
        ORDER BY year, month, s.name_subscription
    ");
    $stmt->execute();
    $data['trends'] = $stmt->fetchAll();
    
    // Most popular subscription by price range
    $data['price_analysis'] = [
        'budget' => ['range' => '0-99 DH', 'count' => 0, 'revenue' => 0],
        'standard' => ['range' => '100-199 DH', 'count' => 0, 'revenue' => 0],
        'premium' => ['range' => '200+ DH', 'count' => 0, 'revenue' => 0]
    ];
    
    foreach ($data['subscriptions'] as $sub) {
        if ($sub['price_subscription'] < 100) {
            $data['price_analysis']['budget']['count'] += $sub['active_subscribers'];
            $data['price_analysis']['budget']['revenue'] += $sub['total_revenue'];
        } elseif ($sub['price_subscription'] < 200) {
            $data['price_analysis']['standard']['count'] += $sub['active_subscribers'];
            $data['price_analysis']['standard']['revenue'] += $sub['total_revenue'];
        } else {
            $data['price_analysis']['premium']['count'] += $sub['active_subscribers'];
            $data['price_analysis']['premium']['revenue'] += $sub['total_revenue'];
        }
    }
    
    return $data;
}

// User Activity Report Function
function generateUserActivityReport($conn, $period = 'month') {
    $data = [];
    
    // Determine date range based on period
    switch ($period) {
        case 'week':
            $date_condition = "date_created >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $date_condition = "date_created >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'year':
            $date_condition = "date_created >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
        default:
            $date_condition = "date_created >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    }
    
    // User registrations by status
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as count, 
               ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM member WHERE role = 2 AND $date_condition), 2) as percentage
        FROM member 
        WHERE role = 2 AND $date_condition
        GROUP BY status
    ");
    $stmt->execute();
    $data['by_status'] = $stmt->fetchAll();
    
    // New registrations over time
    $stmt = $conn->prepare("
        SELECT DATE(date_created) as registration_date, COUNT(*) as new_users
        FROM member 
        WHERE role = 2 AND $date_condition
        GROUP BY DATE(date_created)
        ORDER BY registration_date
    ");
    $stmt->execute();
    $data['registrations_timeline'] = $stmt->fetchAll();
    
    // Geographic distribution
    $stmt = $conn->prepare("
        SELECT c.city_name, COUNT(m.id_member) as user_count
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        JOIN residence r ON r.id_residence = ap.id_residence
        JOIN city c ON c.id_city = r.id_city
        WHERE m.role = 2 AND m.$date_condition
        GROUP BY c.id_city
        ORDER BY user_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $data['by_city'] = $stmt->fetchAll();
    
    // Conversion rate (pending to active)
    $stmt = $conn->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
            COUNT(*) as total_count
        FROM member 
        WHERE role = 2 AND $date_condition
    ");
    $stmt->execute();
    $conversion_data = $stmt->fetch();
    
    $data['conversion_rate'] = [
        'pending' => $conversion_data['pending_count'],
        'active' => $conversion_data['active_count'],
        'total' => $conversion_data['total_count'],
        'rate' => $conversion_data['total_count'] > 0 ? 
            round(($conversion_data['active_count'] / $conversion_data['total_count']) * 100, 2) : 0
    ];
    
    $data['period'] = $period;
    
    return $data;
}

// Get overall platform statistics
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM subscription WHERE is_active = 1");
    $stmt->execute();
    $total_subscriptions = $stmt->fetch()['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM member WHERE role = 2");
    $stmt->execute();
    $total_syndics = $stmt->fetch()['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM admin_member_subscription");
    $stmt->execute();
    $total_purchases = $stmt->fetch()['total'];
    
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM admin_member_subscription");
    $stmt->execute();
    $total_revenue = $stmt->fetch()['total'] ?? 0;
    
} catch (PDOException $e) {
    $total_subscriptions = 0;
    $total_syndics = 0;
    $total_purchases = 0;
    $total_revenue = 0;
}

$page_title = "Rapports - Syndic Way";
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/dashboard.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/reports.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-brand">
            <h2><i class="fas fa-shield-alt"></i> Admin Panel</h2>
        </div>
        <div class="nav-user">
            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
            <a href="../public/logout.php" class="btn btn-logout">
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
                    <li class="active">
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
                    <h1><i class="fas fa-chart-bar"></i> Rapports et Analyses</h1>
                    <p>Générez des rapports détaillés sur les performances de votre plateforme</p>
                </div>
                <button class="btn btn-primary" onclick="clearReports()">
                    <i class="fas fa-refresh"></i> Actualiser
                </button>
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

            <!-- Platform Overview -->
            <div class="reports-overview">
                <div class="stat-card subscriptions">
                    <i class="fas fa-tags"></i>
                    <div class="stat-number"><?php echo $total_subscriptions; ?></div>
                    <div class="stat-label">Forfaits Actifs</div>
                </div>
                <div class="stat-card syndics">
                    <i class="fas fa-building"></i>
                    <div class="stat-number"><?php echo $total_syndics; ?></div>
                    <div class="stat-label">Syndics Enregistrés</div>
                </div>
                <div class="stat-card purchases">
                    <i class="fas fa-shopping-cart"></i>
                    <div class="stat-number"><?php echo $total_purchases; ?></div>
                    <div class="stat-label">Achats Totaux</div>
                </div>
                <div class="stat-card revenue">
                    <i class="fas fa-coins"></i>
                    <div class="stat-number"><?php echo number_format($total_revenue, 0); ?> DH</div>
                    <div class="stat-label">Revenus Totaux</div>
                </div>
            </div>

            <!-- Report Generators -->
            <div class="report-generators">
                <!-- Revenue Report -->
                <div class="report-card">
                    <h3><i class="fas fa-chart-line"></i> Rapport de Revenus</h3>
                    <p>Analysez les revenus par période, forfait et région géographique.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_revenue_report">
                        
                        <div class="form-group">
                            <label for="start_date">Date de début</label>
                            <input type="date" name="start_date" id="start_date" 
                                   value="<?php echo date('Y-m-01'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date">Date de fin</label>
                            <input type="date" name="end_date" id="end_date" 
                                   value="<?php echo date('Y-m-t'); ?>" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full">
                            <i class="fas fa-chart-line"></i> Générer le Rapport
                        </button>
                    </form>
                </div>

                <!-- Subscription Report -->
                <div class="report-card">
                    <h3><i class="fas fa-tags"></i> Rapport d'Abonnements</h3>
                    <p>Analysez la performance de vos différents forfaits d'abonnement.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_subscription_report">
                        
                        <div style="margin-bottom: 1.5rem; padding: 1rem; background: var(--color-light-grey); border-radius: 8px;">
                            <small>Ce rapport inclut les statistiques complètes de tous les forfaits, les tendances et l'analyse des prix.</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full">
                            <i class="fas fa-tags"></i> Générer le Rapport
                        </button>
                    </form>
                </div>

                <!-- User Activity Report -->
                <div class="report-card">
                    <h3><i class="fas fa-users"></i> Rapport d'Activité Utilisateurs</h3>
                    <p>Suivez l'activité et l'engagement des utilisateurs sur votre plateforme.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_user_activity_report">
                        
                        <div class="form-group">
                            <label for="period">Période d'analyse</label>
                            <select name="period" id="period" required>
                                <option value="week">Dernière semaine</option>
                                <option value="month" selected>Dernier mois</option>
                                <option value="year">Dernière année</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full">
                            <i class="fas fa-users"></i> Générer le Rapport
                        </button>
                    </form>
                </div>
            </div>

            <!-- Report Results -->
            <?php if ($current_report && !empty($report_data)): ?>
                <div class="report-results">
                    <div class="report-header">
                        <h2>
                            <?php 
                                $report_titles = [
                                    'revenue' => 'Rapport de Revenus',
                                    'subscription' => 'Rapport d\'Abonnements',
                                    'user_activity' => 'Rapport d\'Activité Utilisateurs'
                                ];
                                echo $report_titles[$current_report];
                            ?>
                        </h2>
                        <div class="export-actions">
                            <button class="btn btn-secondary" onclick="exportReport('pdf')">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>
                            <button class="btn btn-secondary" onclick="exportReport('excel')">
                                <i class="fas fa-file-excel"></i> Excel
                            </button>
                            <button class="btn btn-secondary" onclick="printReport()">
                                <i class="fas fa-print"></i> Imprimer
                            </button>
                        </div>
                    </div>

                    <div class="report-content">
                        <?php if ($current_report === 'revenue'): ?>
                            <!-- Revenue Report Content -->
                            <div class="summary-grid">
                                <div class="metric-card">
                                <div class="metric-value"><?php echo number_format($report_data['summary']['total_revenue'], 2); ?> DH</div>
                                   <div class="metric-label">Revenus Totaux</div>
                               </div>
                               <div class="metric-card">
                                   <div class="metric-value"><?php echo $report_data['summary']['total_transactions']; ?></div>
                                   <div class="metric-label">Transactions</div>
                               </div>
                               <div class="metric-card">
                                   <div class="metric-value">
                                       <?php 
                                           $avg_transaction = $report_data['summary']['total_transactions'] > 0 ? 
                                               $report_data['summary']['total_revenue'] / $report_data['summary']['total_transactions'] : 0;
                                           echo number_format($avg_transaction, 2);
                                       ?> DH
                                   </div>
                                   <div class="metric-label">Panier Moyen</div>
                               </div>
                               <div class="metric-card">
                                   <div class="metric-value">
                                       <?php 
                                           $start = new DateTime($report_data['period']['start']);
                                           $end = new DateTime($report_data['period']['end']);
                                           $days = $start->diff($end)->days + 1;
                                           $daily_avg = $days > 0 ? $report_data['summary']['total_revenue'] / $days : 0;
                                           echo number_format($daily_avg, 2);
                                       ?> DH
                                   </div>
                                   <div class="metric-label">Moyenne Quotidienne</div>
                               </div>
                           </div>

                           <div class="data-grid">
                               <div class="data-section">
                                   <h4><i class="fas fa-tags"></i> Revenus par Forfait</h4>
                                   <table class="data-table">
                                       <thead>
                                           <tr>
                                               <th>Forfait</th>
                                               <th>Revenus</th>
                                               <th>Ventes</th>
                                           </tr>
                                       </thead>
                                       <tbody>
                                           <?php foreach ($report_data['by_subscription'] as $sub): ?>
                                               <tr>
                                                   <td><?php echo htmlspecialchars($sub['name_subscription']); ?></td>
                                                   <td><?php echo number_format($sub['revenue'], 2); ?> DH</td>
                                                   <td><?php echo $sub['count']; ?></td>
                                               </tr>
                                           <?php endforeach; ?>
                                       </tbody>
                                   </table>
                               </div>

                               <div class="data-section">
                                   <h4><i class="fas fa-map-marker-alt"></i> Revenus par Ville</h4>
                                   <table class="data-table">
                                       <thead>
                                           <tr>
                                               <th>Ville</th>
                                               <th>Revenus</th>
                                               <th>Clients</th>
                                           </tr>
                                       </thead>
                                       <tbody>
                                           <?php foreach ($report_data['by_city'] as $city): ?>
                                               <tr>
                                                   <td><?php echo htmlspecialchars($city['city_name']); ?></td>
                                                   <td><?php echo number_format($city['revenue'], 2); ?> DH</td>
                                                   <td><?php echo $city['customers']; ?></td>
                                               </tr>
                                           <?php endforeach; ?>
                                       </tbody>
                                   </table>
                               </div>
                           </div>

                           <div class="chart-container">
                               <canvas id="revenueChart"></canvas>
                           </div>

                       <?php elseif ($current_report === 'subscription'): ?>
                           <!-- Subscription Report Content -->
                           <div class="data-grid">
                               <div class="data-section">
                                   <h4><i class="fas fa-chart-pie"></i> Analyse par Gamme de Prix</h4>
                                   <?php foreach ($report_data['price_analysis'] as $key => $data): ?>
                                       <div style="margin-bottom: 1rem;">
                                           <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                               <span><strong><?php echo ucfirst($key); ?> (<?php echo $data['range']; ?>)</strong></span>
                                               <span><?php echo $data['count']; ?> abonnés</span>
                                           </div>
                                           <div class="progress-bar">
                                               <div class="progress-fill" style="width: <?php 
                                                   $total_subscribers = array_sum(array_column($report_data['price_analysis'], 'count'));
                                                   echo $total_subscribers > 0 ? ($data['count'] / $total_subscribers) * 100 : 0;
                                               ?>%;"></div>
                                           </div>
                                           <small>Revenus: <?php echo number_format($data['revenue'], 2); ?> DH</small>
                                       </div>
                                   <?php endforeach; ?>
                               </div>

                               <div class="data-section">
                                   <h4><i class="fas fa-trophy"></i> Performance des Forfaits</h4>
                                   <table class="data-table">
                                       <thead>
                                           <tr>
                                               <th>Forfait</th>
                                               <th>Prix</th>
                                               <th>Abonnés</th>
                                               <th>Revenus</th>
                                           </tr>
                                       </thead>
                                       <tbody>
                                           <?php foreach ($report_data['subscriptions'] as $sub): ?>
                                               <tr>
                                                   <td>
                                                       <?php echo htmlspecialchars($sub['name_subscription']); ?>
                                                       <?php if (!$sub['is_active']): ?>
                                                           <span style="color: var(--color-grey); font-size: 0.8em;">(Inactif)</span>
                                                       <?php endif; ?>
                                                   </td>
                                                   <td><?php echo number_format($sub['price_subscription'], 2); ?> DH</td>
                                                   <td>
                                                       <?php echo $sub['active_subscribers']; ?>
                                                       <small style="color: var(--color-grey);">
                                                           / <?php echo $sub['max_residents']; ?> max
                                                       </small>
                                                   </td>
                                                   <td><?php echo number_format($sub['total_revenue'], 2); ?> DH</td>
                                               </tr>
                                           <?php endforeach; ?>
                                       </tbody>
                                   </table>
                               </div>
                           </div>

                           <div class="chart-container">
                               <canvas id="subscriptionChart"></canvas>
                           </div>

                       <?php elseif ($current_report === 'user_activity'): ?>
                           <!-- User Activity Report Content -->
                           <div class="summary-grid">
                               <div class="metric-card">
                                   <div class="metric-value"><?php echo $report_data['conversion_rate']['total']; ?></div>
                                   <div class="metric-label">Nouveaux Utilisateurs</div>
                               </div>
                               <div class="metric-card">
                                   <div class="metric-value"><?php echo $report_data['conversion_rate']['active']; ?></div>
                                   <div class="metric-label">Comptes Activés</div>
                               </div>
                               <div class="metric-card">
                                   <div class="metric-value"><?php echo $report_data['conversion_rate']['pending']; ?></div>
                                   <div class="metric-label">En Attente</div>
                               </div>
                               <div class="metric-card">
                                   <div class="metric-value"><?php echo $report_data['conversion_rate']['rate']; ?>%</div>
                                   <div class="metric-label">Taux de Conversion</div>
                               </div>
                           </div>

                           <div class="data-grid">
                               <div class="data-section">
                                   <h4><i class="fas fa-chart-line"></i> Répartition par Statut</h4>
                                   <?php foreach ($report_data['by_status'] as $status): ?>
                                       <div style="margin-bottom: 1rem;">
                                           <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                               <span><strong><?php echo ucfirst($status['status']); ?></strong></span>
                                               <span class="percentage-badge"><?php echo $status['percentage']; ?>%</span>
                                           </div>
                                           <div class="progress-bar">
                                               <div class="progress-fill" style="width: <?php echo $status['percentage']; ?>%;"></div>
                                           </div>
                                           <small><?php echo $status['count']; ?> utilisateurs</small>
                                       </div>
                                   <?php endforeach; ?>
                               </div>

                               <div class="data-section">
                                   <h4><i class="fas fa-map-marker-alt"></i> Distribution Géographique</h4>
                                   <table class="data-table">
                                       <thead>
                                           <tr>
                                               <th>Ville</th>
                                               <th>Utilisateurs</th>
                                           </tr>
                                       </thead>
                                       <tbody>
                                           <?php foreach ($report_data['by_city'] as $city): ?>
                                               <tr>
                                                   <td><?php echo htmlspecialchars($city['city_name']); ?></td>
                                                   <td><?php echo $city['user_count']; ?></td>
                                               </tr>
                                           <?php endforeach; ?>
                                       </tbody>
                                   </table>
                               </div>
                           </div>

                           <div class="chart-container">
                               <canvas id="activityChart"></canvas>
                           </div>
                       <?php endif; ?>
                   </div>
               </div>
           <?php endif; ?>
       </main>
   </div>

   <script>
       // Chart configurations and data
       <?php if ($current_report === 'revenue' && !empty($report_data)): ?>
           // Revenue Chart
           const revenueCtx = document.getElementById('revenueChart').getContext('2d');
           const revenueChart = new Chart(revenueCtx, {
               type: 'line',
               data: {
                   labels: [
                       <?php foreach ($report_data['by_month'] as $month): ?>
                           '<?php echo date('M Y', mktime(0, 0, 0, $month['month'], 1, $month['year'])); ?>',
                       <?php endforeach; ?>
                   ],
                   datasets: [{
                       label: 'Revenus (DH)',
                       data: [
                           <?php foreach ($report_data['by_month'] as $month): ?>
                               <?php echo $month['revenue']; ?>,
                           <?php endforeach; ?>
                       ],
                       borderColor: 'rgb(244, 185, 66)',
                       backgroundColor: 'rgba(244, 185, 66, 0.1)',
                       tension: 0.4,
                       fill: true
                   }]
               },
               options: {
                   responsive: true,
                   maintainAspectRatio: false,
                   plugins: {
                       legend: {
                           display: true
                       },
                       title: {
                           display: true,
                           text: 'Évolution des Revenus'
                       }
                   },
                   scales: {
                       y: {
                           beginAtZero: true,
                           ticks: {
                               callback: function(value) {
                                   return value.toLocaleString() + ' DH';
                               }
                           }
                       }
                   }
               }
           });

       <?php elseif ($current_report === 'subscription' && !empty($report_data)): ?>
           // Subscription Chart
           const subscriptionCtx = document.getElementById('subscriptionChart').getContext('2d');
           const subscriptionChart = new Chart(subscriptionCtx, {
               type: 'doughnut',
               data: {
                   labels: [
                       <?php foreach ($report_data['subscriptions'] as $sub): ?>
                           '<?php echo htmlspecialchars($sub['name_subscription']); ?>',
                       <?php endforeach; ?>
                   ],
                   datasets: [{
                       data: [
                           <?php foreach ($report_data['subscriptions'] as $sub): ?>
                               <?php echo $sub['active_subscribers']; ?>,
                           <?php endforeach; ?>
                       ],
                       backgroundColor: [
                           'rgb(244, 185, 66)',
                           'rgb(52, 144, 220)',
                           'rgb(40, 201, 151)',
                           'rgb(243, 156, 18)',
                           'rgb(155, 89, 182)'
                       ]
                   }]
               },
               options: {
                   responsive: true,
                   maintainAspectRatio: false,
                   plugins: {
                       legend: {
                           position: 'bottom'
                       },
                       title: {
                           display: true,
                           text: 'Répartition des Abonnés par Forfait'
                       }
                   }
               }
           });

       <?php elseif ($current_report === 'user_activity' && !empty($report_data)): ?>
           // User Activity Chart
           const activityCtx = document.getElementById('activityChart').getContext('2d');
           const activityChart = new Chart(activityCtx, {
               type: 'bar',
               data: {
                   labels: [
                       <?php foreach ($report_data['registrations_timeline'] as $reg): ?>
                           '<?php echo date('d/m', strtotime($reg['registration_date'])); ?>',
                       <?php endforeach; ?>
                   ],
                   datasets: [{
                       label: 'Nouvelles Inscriptions',
                       data: [
                           <?php foreach ($report_data['registrations_timeline'] as $reg): ?>
                               <?php echo $reg['new_users']; ?>,
                           <?php endforeach; ?>
                       ],
                       backgroundColor: 'rgba(244, 185, 66, 0.8)',
                       borderColor: 'rgb(244, 185, 66)',
                       borderWidth: 1
                   }]
               },
               options: {
                   responsive: true,
                   maintainAspectRatio: false,
                   plugins: {
                       legend: {
                           display: true
                       },
                       title: {
                           display: true,
                           text: 'Nouvelles Inscriptions par Jour'
                       }
                   },
                   scales: {
                       y: {
                           beginAtZero: true,
                           ticks: {
                               stepSize: 1
                           }
                       }
                   }
               }
           });
       <?php endif; ?>

       // Utility functions
       function clearReports() {
           if (confirm('Êtes-vous sûr de vouloir effacer les rapports actuels ?')) {
               fetch('reports.php', {
                   method: 'POST',
                   headers: {
                       'Content-Type': 'application/x-www-form-urlencoded',
                   },
                   body: 'action=clear_reports'
               }).then(() => {
                   location.reload();
               });
           }
       }

       function exportReport(format) {
           alert(`Export en ${format.toUpperCase()} en cours de développement...`);
           // Here you would implement the actual export functionality
       }

       function printReport() {
           window.print();
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

           // Animate statistics counters
           document.querySelectorAll('.stat-number').forEach(counter => {
               const target = parseInt(counter.textContent.replace(/[^0-9]/g, ''));
               if (!isNaN(target)) {
                   animateCounter(counter, target);
               }
           });

           // Animate metric values
           document.querySelectorAll('.metric-value').forEach(metric => {
               const target = parseFloat(metric.textContent.replace(/[^0-9.]/g, ''));
               if (!isNaN(target)) {
                   animateCounter(metric, target, true);
               }
           });

           // Animate progress bars
           setTimeout(() => {
               document.querySelectorAll('.progress-fill').forEach(bar => {
                   const width = bar.style.width;
                   bar.style.width = '0%';
                   setTimeout(() => {
                       bar.style.width = width;
                   }, 100);
               });
           }, 500);
       });

       function animateCounter(element, target, isDecimal = false) {
           let current = 0;
           const increment = target / 50;
           const originalText = element.textContent;
           const suffix = originalText.replace(/[0-9.,]/g, '');
           
           const timer = setInterval(() => {
               current += increment;
               if (current >= target) {
                   current = target;
                   clearInterval(timer);
               }
               
               if (isDecimal) {
                   element.textContent = current.toFixed(2) + suffix;
               } else {
                   element.textContent = Math.floor(current).toLocaleString() + suffix;
               }
           }, 20);
       }

       // Form validation
       document.querySelectorAll('form').forEach(form => {
           form.addEventListener('submit', function(event) {
               const startDate = this.querySelector('input[name="start_date"]');
               const endDate = this.querySelector('input[name="end_date"]');
               
               if (startDate && endDate) {
                   if (new Date(startDate.value) > new Date(endDate.value)) {
                       event.preventDefault();
                       alert('La date de début ne peut pas être postérieure à la date de fin.');
                       return;
                   }
               }
               
               // Show loading state
               const submitBtn = this.querySelector('button[type="submit"]');
               const originalText = submitBtn.innerHTML;
               submitBtn.disabled = true;
               submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Génération...';
               
               setTimeout(() => {
                   submitBtn.disabled = false;
                   submitBtn.innerHTML = originalText;
               }, 5000);
           });
       });

       // Enhanced interactions
       document.querySelectorAll('.report-card').forEach(card => {
           card.addEventListener('mouseenter', function() {
               this.style.transform = 'translateY(-8px) scale(1.02)';
           });
           
           card.addEventListener('mouseleave', function() {
               this.style.transform = 'translateY(0) scale(1)';
           });
       });

       // Real-time date validation
       document.getElementById('start_date')?.addEventListener('change', function() {
           const endDate = document.getElementById('end_date');
           if (endDate && this.value) {
               endDate.min = this.value;
           }
       });

       document.getElementById('end_date')?.addEventListener('change', function() {
           const startDate = document.getElementById('start_date');
           if (startDate && this.value) {
               startDate.max = this.value;
           }
       });

       // Keyboard shortcuts
       document.addEventListener('keydown', function(event) {
           // Ctrl/Cmd + R for refresh
           if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
               event.preventDefault();
               clearReports();
           }
           
           // Ctrl/Cmd + P for print
           if ((event.ctrlKey || event.metaKey) && event.key === 'p') {
               event.preventDefault();
               printReport();
           }
       });

       // Advanced chart interactions
       <?php if ($current_report && !empty($report_data)): ?>
           // Add click handlers for chart elements
           document.addEventListener('DOMContentLoaded', function() {
               // You can add specific chart interaction handlers here
               console.log('Report generated successfully:', '<?php echo $current_report; ?>');
           });
       <?php endif; ?>

       // Auto-refresh for real-time data (optional)
       function enableAutoRefresh() {
           setInterval(() => {
               // Check if we should auto-refresh data
               const now = new Date();
               if (now.getMinutes() % 15 === 0) { // Every 15 minutes
                   console.log('Auto-refresh check...');
                   // You could implement background data refresh here
               }
           }, 60000); // Check every minute
       }

       // Enable auto-refresh if needed
       // enableAutoRefresh();

       // Report sharing functionality
       function shareReport(platform) {
           const url = window.location.href;
           const title = document.title;
           
           switch(platform) {
               case 'email':
                   window.location.href = `mailto:?subject=${encodeURIComponent(title)}&body=${encodeURIComponent(url)}`;
                   break;
               case 'copy':
                   navigator.clipboard.writeText(url).then(() => {
                       alert('Lien copié dans le presse-papiers !');
                   });
                   break;
           }
       }

       // Performance monitoring
       function trackReportGeneration(reportType) {
           const startTime = performance.now();
           
           return function() {
               const endTime = performance.now();
               console.log(`Rapport ${reportType} généré en ${endTime - startTime} ms`);
           };
       }
   </script>

   <script src="http://localhost/syndicplatform/js/admin/reports.js"></script>

   <?php 
   // Clear report parameters after displaying
   if (isset($_SESSION['report_params'])) {
       unset($_SESSION['report_params']);
   }
   ?>
</body>
</html>