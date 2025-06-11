<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is a syndic
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'syndic') {
    header('Location: http://localhost/syndicplatform/public/login.php');
    exit();
}

// Get syndic information and building statistics
try {
    // Get syndic details with building info
    $stmt = $conn->prepare("
        SELECT m.*, r.name as building_name, r.address, c.city_name,
               COUNT(DISTINCT ap2.id_apartment) as total_apartments,
               COUNT(DISTINCT m2.id_member) as total_residents
        FROM member m
        LEFT JOIN apartment ap ON ap.id_member = m.id_member
        LEFT JOIN residence r ON r.id_residence = ap.id_residence
        LEFT JOIN city c ON c.id_city = r.id_city
        LEFT JOIN apartment ap2 ON ap2.id_residence = r.id_residence
        LEFT JOIN member m2 ON m2.id_member = ap2.id_member AND m2.role = 1
        WHERE m.id_member = ?
        GROUP BY m.id_member
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $syndic_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent residents in this building
    $stmt = $conn->prepare("
        SELECT m.full_name, m.email, m.phone, m.date_created, 
               ap.type, ap.floor, ap.number
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        JOIN residence r ON r.id_residence = ap.id_residence
        WHERE r.id_residence = (
            SELECT r2.id_residence 
            FROM apartment ap2 
            JOIN residence r2 ON r2.id_residence = ap2.id_residence 
            WHERE ap2.id_member = ?
        ) AND m.role = 1
        ORDER BY m.date_created DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mock data for statistics
    $pending_requests = 3;
    $monthly_revenue = ($syndic_info['total_residents'] ?? 0) * 500;

} catch(PDOException $e) {
    error_log($e->getMessage());
    $syndic_info = null;
    $recent_residents = [];
    $pending_requests = 0;
    $monthly_revenue = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Syndic Dashboard - Syndic Way</title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/sections/dashboard.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .building-info-card {
            background: linear-gradient(135deg, var(--color-primary), var(--color-green));
            color: var(--color-white);
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .building-info-card h2 {
            margin: 0 0 1rem 0;
            font-size: 1.8rem;
        }

        .building-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .building-detail {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .building-detail i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: var(--color-white);
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--color-primary), var(--color-green));
        }

        .action-card:hover {
            transform: translateY(-5px);
            border-color: var(--color-primary);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .action-card i {
            font-size: 2.5rem;
            color: var(--color-primary);
            margin-bottom: 1rem;
        }

        .action-card h3 {
            color: var(--color-dark-grey);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .action-card p {
            color: var(--color-grey);
            font-size: 0.9rem;
            margin: 0;
        }

        .notifications-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 0.3rem 0.6rem;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .residents-table {
            background: var(--color-white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .table-header {
            background: linear-gradient(135deg, var(--color-primary), var(--color-green));
            color: var(--color-white);
            padding: 1.5rem;
        }

        .table-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .residents-list {
            overflow-x: auto;
        }

        .residents-list table {
            width: 100%;
            border-collapse: collapse;
        }

        .residents-list th,
        .residents-list td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--color-light-grey);
        }

        .residents-list th {
            background: var(--color-light-grey);
            font-weight: 600;
            color: var(--color-dark-grey);
        }

        .residents-list tr:hover {
            background: rgba(0,123,255,0.05);
        }

        @media (max-width: 768px) {
            .building-details {
                grid-template-columns: 1fr;
            }
            
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-brand">
            <h2><i class="fas fa-building"></i> Espace Syndic</h2>
        </div>
        <div class="nav-user">
            <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Syndic'); ?></span>
            <a href="http://localhost/syndicplatform/public/logout.php" class="btn btn-logout">
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
                        <a href="residents.php">
                            <i class="fas fa-users"></i> Gestion résidents
                        </a>
                    </li>
                    <li>
                        <a href="apartments.php">
                            <i class="fas fa-home"></i> Gestion appartements
                        </a>
                    </li>
                    <li>
                        <a href="maintenance.php">
                            <i class="fas fa-tools"></i> Demandes maintenance
                            <?php if($pending_requests > 0): ?>
                                <span class="notifications-badge"><?php echo $pending_requests; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="payments.php">
                            <i class="fas fa-money-bill-wave"></i> Gestion paiements
                        </a>
                    </li>
                    <li>
                        <a href="announcements.php">
                            <i class="fas fa-bullhorn"></i> Annonces
                        </a>
                    </li>
                    <li>
                        <a href="documents.php">
                            <i class="fas fa-file-alt"></i> Documents
                        </a>
                    </li>
                    <li>
                        <a href="reports.php">
                            <i class="fas fa-chart-bar"></i> Rapports
                        </a>
                    </li>
                    <li>
                        <a href="profile.php">
                            <i class="fas fa-user-cog"></i> Mon profil
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Building Info Card -->
            <?php if ($syndic_info): ?>
                <div class="building-info-card">
                    <h2><i class="fas fa-building"></i> <?php echo htmlspecialchars($syndic_info['building_name'] ?? 'Mon Immeuble'); ?></h2>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($syndic_info['address'] ?? ''); ?>, <?php echo htmlspecialchars($syndic_info['city_name'] ?? ''); ?></p>
                    
                    <div class="building-details">
                        <div class="building-detail">
                            <i class="fas fa-users"></i>
                            <h4><?php echo $syndic_info['total_residents'] ?? 0; ?> résidents</h4>
                            <p>Résidents actifs</p>
                        </div>
                        <div class="building-detail">
                            <i class="fas fa-home"></i>
                            <h4><?php echo $syndic_info['total_apartments'] ?? 0; ?> appartements</h4>
                            <p>Total d'appartements</p>
                        </div>
                        <div class="building-detail">
                            <i class="fas fa-money-bill-wave"></i>
                            <h4><?php echo number_format($monthly_revenue); ?> DH</h4>
                            <p>Revenus mensuels</p>
                        </div>
                        <div class="building-detail">
                            <i class="fas fa-calendar"></i>
                            <h4><?php echo date('M Y', strtotime($syndic_info['date_created'])); ?></h4>
                            <p>Syndic depuis</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

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
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Résidents</h3>
                        <div class="stat-number"><?php echo $syndic_info['total_residents'] ?? 0; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Appartements</h3>
                        <div class="stat-number"><?php echo $syndic_info['total_apartments'] ?? 0; ?></div>
                    </div>
                </div>

                <div class="stat-card <?php echo $pending_requests > 0 ? 'pending' : ''; ?>">
                    <div class="stat-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Demandes en attente</h3>
                        <div class="stat-number"><?php echo $pending_requests; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Revenus mensuel</h3>
                        <div class="stat-number"><?php echo number_format($monthly_revenue); ?> DH</div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="content-section">
                <h2>Actions rapides</h2>
                <div class="quick-actions-grid">
                    <a href="residents.php?action=add" class="action-card">
                        <i class="fas fa-user-plus"></i>
                        <h3>Ajouter un résident</h3>
                        <p>Enregistrer un nouveau résident dans l'immeuble</p>
                    </a>

                    <a href="maintenance.php" class="action-card">
                        <i class="fas fa-wrench"></i>
                        <h3>Gérer maintenance</h3>
                        <p>Voir et traiter les demandes de maintenance</p>
                        <?php if($pending_requests > 0): ?>
                            <span class="notifications-badge"><?php echo $pending_requests; ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="payments.php" class="action-card">
                        <i class="fas fa-credit-card"></i>
                        <h3>Gestion paiements</h3>
                        <p>Suivre les paiements et les retards</p>
                    </a>

                    <a href="announcements.php?action=new" class="action-card">
                        <i class="fas fa-bullhorn"></i>
                        <h3>Nouvelle annonce</h3>
                        <p>Publier une annonce pour tous les résidents</p>
                    </a>

                    <a href="documents.php" class="action-card">
                        <i class="fas fa-file-upload"></i>
                        <h3>Gérer documents</h3>
                        <p>Télécharger et organiser les documents</p>
                    </a>

                    <a href="reports.php" class="action-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>Générer rapport</h3>
                        <p>Créer des rapports financiers et de gestion</p>
                    </a>
                </div>
            </div>

            <!-- Recent Residents Table -->
            <div class="residents-table">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-users"></i>
                        Résidents récents
                    </h3>
                </div>

                <div class="residents-list">
                    <?php if (!empty($recent_residents)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Nom complet</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Appartement</th>
                                    <th>Date d'inscription</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_residents as $resident): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($resident['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($resident['email']); ?></td>
                                        <td><?php echo htmlspecialchars($resident['phone'] ?? 'N/A'); ?></td>
                                        <td>Apt <?php echo htmlspecialchars($resident['number']); ?> - Étage <?php echo $resident['floor']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($resident['date_created'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div style="padding: 1rem; text-align: center;">
                            <a href="residents.php" class="btn btn-primary">
                                <i class="fas fa-eye"></i> Voir tous les résidents
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 2rem; text-align: center;">
                            <i class="fas fa-users" style="font-size: 2rem; color: var(--color-grey); margin-bottom: 1rem;"></i>
                            <h4>Aucun résident</h4>
                            <p>Il n'y a pas encore de résidents enregistrés.</p>
                            <a href="residents.php?action=add" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Ajouter le premier résident
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
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

            // Animate statistics counters
            document.querySelectorAll('.stat-number').forEach(counter => {
                const target = parseInt(counter.textContent.replace(/[^0-9]/g, ''));
                if (!isNaN(target)) {
                    animateCounter(counter, target);
                }
            });
        });

        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                
                if (element.textContent.includes('DH')) {
                    element.textContent = Math.floor(current).toLocaleString() + ' DH';
                } else {
                    element.textContent = Math.floor(current);
                }
            }, 20);
        }

        // Enhanced card hover effects
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Keyboard shortcuts for quick actions
        document.addEventListener('keydown', function(event) {
            // Alt + R for residents
            if (event.altKey && event.key === 'r') {
                event.preventDefault();
                window.location.href = 'residents.php';
            }
            
            // Alt + M for maintenance
            if (event.altKey && event.key === 'm') {
                event.preventDefault();
                window.location.href = 'maintenance.php';
            }
            
            // Alt + P for payments
            if (event.altKey && event.key === 'p') {
                event.preventDefault();
                window.location.href = 'payments.php';
            }
            
            // Alt + A for announcements
            if (event.altKey && event.key === 'a') {
                event.preventDefault();
                window.location.href = 'announcements.php';
            }
        });
    </script>
</body>
</html>