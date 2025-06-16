<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit();
}

$current_user = [
    'id' => $_SESSION['user_id'],
    'role' => $_SESSION['user_role'],
    'name' => $_SESSION['user_name'] ?? 'Admin',
    'email' => $_SESSION['user_email'] ?? 'admin@syndic.ma'
];

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
        SELECT status, COUNT(*) as count
        FROM member 
        WHERE role = 2 AND $date_condition
        GROUP BY status
    ");
    $stmt->execute();
    $data['by_status'] = $stmt->fetchAll();
    
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/reports.css">
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php require_once __DIR__ ."/../includes/sidebar_admin.php"?>
        
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
            <?php require_once __DIR__ ."/../includes/navigation.php"?>


            <!-- Quick Access -->
            <div class="quick-access">
                <div class="quick-access-header">
                    <div class="quick-access-title">Aperçu de la Plateforme</div>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card subscriptions">
                        <div class="quick-card-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="quick-card-title">Forfaits Actifs</div>
                        <div class="quick-card-count"><?php echo $total_subscriptions; ?></div>
                        <div class="quick-card-stats">Abonnements disponibles</div>
                    </div>

                    <div class="quick-card syndics">
                        <div class="quick-card-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="quick-card-title">Syndics Enregistrés</div>
                        <div class="quick-card-count"><?php echo $total_syndics; ?></div>
                        <div class="quick-card-stats">Comptes actifs</div>
                    </div>

                    <div class="quick-card purchases">
                        <div class="quick-card-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="quick-card-title">Achats Totaux</div>
                        <div class="quick-card-count"><?php echo $total_purchases; ?></div>
                        <div class="quick-card-stats">Transactions</div>
                    </div>

                    <div class="quick-card revenue">
                        <div class="quick-card-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="quick-card-title">Revenus Totaux</div>
                        <div class="quick-card-count"><?php echo number_format($total_revenue, 0); ?> DH</div>
                        <div class="quick-card-stats">Chiffre d'affaires</div>
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
                        <a href="#">Rapports et Analyses</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Générateurs</span>
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
                                
                                <button type="submit" class="btn btn-primary">
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
                                
                                <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                                    <small>Ce rapport inclut les statistiques complètes de tous les forfaits et les tendances.</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
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
                                
                                <button type="submit" class="btn btn-primary">
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
                                    </div>

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

                                <?php elseif ($current_report === 'subscription'): ?>
                                    <!-- Subscription Report Content -->
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
                                                            <span style="color: #6b7280; font-size: 0.8em;">(Inactif)</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo number_format($sub['price_subscription'], 2); ?> DH</td>
                                                    <td><?php echo $sub['active_subscribers']; ?></td>
                                                    <td><?php echo number_format($sub['total_revenue'], 2); ?> DH</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>

                                <?php elseif ($current_report === 'user_activity'): ?>
                                    <!-- User Activity Report Content -->
                                    <h4><i class="fas fa-chart-line"></i> Répartition par Statut</h4>
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Statut</th>
                                                <th>Nombre</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data['by_status'] as $status): ?>
                                                <tr>
                                                    <td><?php echo ucfirst($status['status']); ?></td>
                                                    <td><?php echo $status['count']; ?> utilisateurs</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Activity Panel -->
                <div class="activity-panel">
                    <div class="activity-header">
                        <div class="activity-title">Historique des Rapports</div>
                        <button class="close-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="activity-tabs">
                        <div class="activity-tab active">Récents</div>
                        <div class="activity-tab">Favoris</div>
                        <div class="activity-tab">Planifiés</div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon report">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Rapport de revenus généré</div>
                            <div class="activity-time">Il y a 2 heures</div>
                            <div class="activity-meta">
                                <div class="tag">Mensuel</div>
                            </div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon export">
                            <i class="fas fa-download"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Export CSV réalisé</div>
                            <div class="activity-time">Il y a 4 heures</div>
                            <div class="activity-meta">
                                <div class="tag">Abonnements</div>
                            </div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon analysis">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Analyse utilisateurs</div>
                            <div class="activity-time">Hier</div>
                            <div class="activity-meta">
                                <div class="tag">Mensuel</div>
                            </div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon report">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Rapport automatique</div>
                            <div class="activity-time">Il y a 2 jours</div>
                            <div class="activity-meta">
                                <div class="tag">Auto</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Export functions
        function exportReport(format) {
            alert(`Export en ${format.toUpperCase()} en cours de développement...`);
        }

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
            document.querySelector('.main-panel').style.marginRight = '0';
        });

        // Quick card clicks
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('click', function() {
                const cardType = this.classList[1];
                console.log(`Focusing on ${cardType} reports`);
            });
        });

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

        // Storage animation on load
        window.addEventListener('load', function() {
            const storageFill = document.querySelector('.storage-fill');
            const percentage = Math.min((<?php echo $total_purchases; ?> / 50) * 100, 100);
            storageFill.style.width = '0%';
            setTimeout(() => {
                storageFill.style.width = percentage + '%';
            }, 500);
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

            // Animate statistics counters
            document.querySelectorAll('.quick-card-count').forEach(counter => {
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

        // Enhanced report card interactions
        document.querySelectorAll('.report-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
                this.style.boxShadow = '0 12px 35px rgba(0, 0, 0, 0.15)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.1)';
            });
        });
    </script>

    <?php 
    // Clear report parameters after displaying
    if (isset($_SESSION['report_params'])) {
        unset($_SESSION['report_params']);
    }
    ?>
</body>
</html>