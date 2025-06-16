<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
$current_user = getCurrentUser();


// Initialize dashboard statistics
$stats = [
    'active_syndics' => 0,
    'pending_requests' => 0,
    'total_residents' => 0,
    'total_revenue' => 0,
    'total_residences' => 0,
    'total_apartments' => 0
];

$recent_activities = [];

try {
    // Get active syndics (role = 2 means syndic)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member WHERE role = 2 AND status = 'active'");
    $stmt->execute();
    $stats['active_syndics'] = $stmt->fetch()['count'];

    // Get pending requests
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending_requests'] = $stmt->fetch()['count'];

    // Get total residents (all members)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member ");
    $stmt->execute();
    $stats['total_residents'] = $stmt->fetch()['count'];

    // Get total residences
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM residence");
    $stmt->execute();
    $stats['total_residences'] = $stmt->fetch()['count'];

    // Get total apartments
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM apartment");
    $stmt->execute();
    $stats['total_apartments'] = $stmt->fetch()['count'];

    // Calculate total subscription revenue
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM admin_member_subscription WHERE amount");
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_revenue'] = $result['total'] ?? 0;

    // Get recent activities with member information
    $stmt = $conn->prepare("
        SELECT 
            m.full_name,
            m.date_created,
            m.status,
            m.role,
            'member_created' as activity_type
        FROM member m
        ORDER BY m.date_created DESC
        LIMIT 5
    ");
    $stmt->execute();
    $activities = $stmt->fetchAll();

    foreach ($activities as $activity) {
        $icon_class = 'create';
        $icon = 'fa-plus';
        $description = "Nouveau membre inscrit: " . $activity['full_name'];

        if ($activity['status'] === 'pending') {
            $icon_class = 'update';
            $icon = 'fa-clock';
            $description = "Demande en attente: " . $activity['full_name'];
        }

        if ($activity['role'] == 2) {
            $icon_class = 'create';
            $icon = 'fa-building';
            $description = "Nouveau syndic: " . $activity['full_name'];
        }

        $recent_activities[] = [
            'icon_class' => $icon_class,
            'icon' => $icon,
            'description' => $description,
            'user_initials' => substr($activity['full_name'], 0, 2),
            'user_name' => $activity['full_name'],
            'time' => timeAgo($activity['date_created']),
            'tag' => $activity['role'] == 2 ? 'Syndic' : 'Résident'
        ];
    }

    // Get syndics for main table
    $stmt = $conn->prepare("
        SELECT 
            m.id_member,
            m.full_name,
            m.email,
            m.status,
            m.date_created,
            COUNT(a.id_apartment) as apartment_count,
            r.name as residence_name
        FROM member m
        LEFT JOIN apartment a ON m.id_member = a.id_member
        LEFT JOIN residence r ON a.id_residence = r.id_residence
        WHERE m.role = 2
        GROUP BY m.id_member
        ORDER BY m.date_created DESC
        LIMIT 5
    ");
    $stmt->execute();
    $syndics_data = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $_SESSION['error'] = "Une erreur s'est produite lors du chargement des données.";
}

// Helper function to format time ago
function timeAgo($datetime)
{
    if (!$datetime)
        return 'Inconnu';

    $time = time() - strtotime($datetime);

    if ($time < 60)
        return 'À l\'instant';
    if ($time < 3600)
        return floor($time / 60) . ' min';
    if ($time < 86400)
        return floor($time / 3600) . ' h';
    if ($time < 2592000)
        return floor($time / 86400) . ' j';
    if ($time < 31536000)
        return floor($time / 2592000) . ' mois';

    return floor($time / 31536000) . ' ans';
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Syndic Way</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/dashboard.css">
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
                    <?php echo htmlspecialchars($_SESSION['success']);
                    unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Header -->
            <?php require_once __DIR__ ."/../includes/navigation.php"?>

            <!-- Quick Access -->
            <div class="quick-access">
                <div class="quick-access-header">
                    <div class="quick-access-title">Accès Rapide</div>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card syndics">
                        <div class="quick-card-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="quick-card-title">Syndics Actifs</div>
                        <div class="quick-card-count"><?php echo $stats['active_syndics']; ?></div>
                        <div class="quick-card-stats"><?php echo $stats['pending_requests']; ?> en attente</div>
                    </div>

                    <div class="quick-card residents">
                        <div class="quick-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="quick-card-title">Total Résidents</div>
                        <div class="quick-card-count"><?php echo number_format($stats['total_residents']); ?></div>
                        <div class="quick-card-stats"><?php echo $stats['total_apartments']; ?> appartements</div>
                    </div>

                    <div class="quick-card payments">
                        <div class="quick-card-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="quick-card-title">Revenus Totaux</div>
                        <div class="quick-card-count"><?php echo number_format($stats['total_revenue'], 0, ',', ' '); ?>
                            DH</div>
                        <div class="quick-card-stats">Abonnements</div>
                    </div>

                    <div class="quick-card reports">
                        <div class="quick-card-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="quick-card-title">Résidences</div>
                        <div class="quick-card-count"><?php echo $stats['total_residences']; ?></div>
                        <div class="quick-card-stats">Actives</div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div class="main-panel">
                    <!-- Breadcrumb -->
                    <div class="breadcrumb">
                        <a href="#">Accueil</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <a href="#">Gestion Syndics</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Tous les Syndics</span>
                    </div>

                    <!-- Table Header -->
                    <div class="table-header">
                        <a href="syndic-accounts.php" class="add-btn">
                            <i class="fas fa-plus"></i>
                            Ajouter Nouveau
                        </a>
                    </div>

                    <!-- Data Table -->
                    <table class="data-table">
                        <thead class="table-header-row">
                            <tr>
                                <th>Nom</th>
                                <th>Contact</th>
                                <th>Appartements</th>
                                <th>Status</th>
                                <th>Inscrit</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($syndics_data)): ?>
                                <tr class="table-row">
                                    <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                                        <i class="fas fa-building"
                                            style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                                        <div>Aucun syndic trouvé</div>
                                        <div style="font-size: 12px; margin-top: 8px;">Créez votre premier syndic pour commencer</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($syndics_data as $syndic): ?>
                                    <tr class="table-row">
                                        <td>
                                            <div class="file-item">
                                                <div class="table-icon" style="background: #FFCB32;">
                                                    <i class="fas fa-user-tie"></i>
                                                </div>
                                                <div class="file-info">
                                                    <div class="file-name"><?php echo htmlspecialchars($syndic['full_name']); ?>
                                                    </div>
                                                    <div style="font-size: 12px; color: #64748b;">
                                                        <?php echo htmlspecialchars($syndic['residence_name'] ?? 'Aucune résidence'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 14px;"><?php echo htmlspecialchars($syndic['email']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="text-align: center;">
                                                <span style="font-weight: 600;"><?php echo $syndic['apartment_count']; ?></span>
                                                <div style="font-size: 12px; color: #64748b;">appartements</div>
                                            </div>
                                        </td>
                                        <td>
                                            <span
                                                class="status-badge <?php echo $syndic['status'] === 'active' ? 'status-active' : 'status-pending'; ?>">
                                                <?php echo $syndic['status'] === 'active' ? 'Actif' : 'En attente'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo timeAgo($syndic['date_created']); ?></td>
                                        <td>
                                            <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <!-- Add some sample management rows -->
                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #10b981;">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Tous les Résidents</div>
                                            <div style="font-size: 12px; color: #64748b;">Gestion des résidents</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="sharing-avatars">
                                        <div class="sharing-avatar">AM</div>
                                        <div class="sharing-avatar">KH</div>
                                        <span
                                            class="sharing-count">+<?php echo max(0, $stats['total_residents'] - 2); ?></span>
                                    </div>
                                </td>
                                <td><?php echo number_format($stats['total_residents']); ?> résidents</td>
                                <td><span class="status-badge status-active">Actif</span></td>
                                <td>Aujourd'hui</td>
                                <td>
                                    <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                                </td>
                            </tr>

                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #f59e0b;">
                                            <i class="fas fa-home"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Résidences</div>
                                            <div style="font-size: 12px; color: #64748b;">Gestion immobilière</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="sharing-avatars">
                                        <div class="sharing-avatar">RE</div>
                                        <div class="sharing-avatar">AN</div>
                                        <div class="sharing-avatar">MA</div>
                                    </div>
                                </td>
                                <td><?php echo $stats['total_residences']; ?> résidences</td>
                                <td><span class="status-badge status-active">Actif</span></td>
                                <td>Il y a 2 jours</td>
                                <td>
                                    <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                                </td>
                            </tr>

                            <tr class="table-row">
                                <td>
                                    <div class="file-item">
                                        <div class="table-icon" style="background: #8b5cf6;">
                                            <i class="fas fa-file-invoice"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name">Rapports Financiers</div>
                                            <div style="font-size: 12px; color: #64748b;">Analyses mensuelles</div>
                                        </div>
                                    </div>
                                </td>
                                <td>Administrateur</td>
                                <td><?php echo number_format($stats['total_revenue'], 0, ',', ' '); ?> DH</td>
                                <td><span class="status-badge status-active">Généré</span></td>
                                <td>Il y a 3 jours</td>
                                <td>
                                    <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                                </td>
                            </tr>

                            <?php if ($stats['pending_requests'] > 0): ?>
                                <tr class="table-row">
                                    <td>
                                        <div class="file-item">
                                            <div class="table-icon" style="background: #ef4444;">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </div>
                                            <div class="file-info">
                                                <div class="file-name">Demandes en Attente</div>
                                                <div style="font-size: 12px; color: #64748b;">Nécessite une action</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="sharing-avatars">
                                            <div class="sharing-avatar" style="background: #ef4444;">!</div>
                                        </div>
                                    </td>
                                    <td><?php echo $stats['pending_requests']; ?> demandes</td>
                                    <td><span class="status-badge status-pending">En attente</span></td>
                                    <td>Il y a 1 heure</td>
                                    <td>
                                        <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Activity Panel -->
                <div class="activity-panel">
                    <div class="activity-header">
                        <div class="activity-title">Activité</div>
                        <button class="close-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <?php if (empty($recent_activities)): ?>
                        <div style="text-align: center; padding: 40px; color: #64748b;">
                            <i class="fas fa-clock" style="font-size: 32px; margin-bottom: 12px; opacity: 0.3;"></i>
                            <div style="font-size: 14px;">Aucune activité récente</div>
                            <div style="font-size: 12px; margin-top: 4px;">Les nouvelles activités apparaîtront ici</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon <?php echo $activity['icon_class']; ?>">
                                    <i class="fas <?php echo $activity['icon']; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-text"><?php echo htmlspecialchars($activity['description']); ?></div>
                                    <div class="activity-time"><?php echo $activity['time']; ?></div>
                                    <div class="activity-meta">
                                        <div class="sharing-avatar"><?php echo strtoupper($activity['user_initials']); ?></div>
                                        <span
                                            style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($activity['user_name']); ?></span>
                                        <div class="tag"><?php echo $activity['tag']; ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function (e) {
                if (!this.href.includes('login.php') && !this.href.includes('.php')) {
                    e.preventDefault();
                    document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });

        // Close activity panel
        document.querySelector('.close-btn').addEventListener('click', function () {
            document.querySelector('.activity-panel').style.display = 'none';
            document.querySelector('.main-panel').style.marginRight = '0';
        });

        // Quick card clicks
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('click', function () {
                const cardType = this.classList[1];
                console.log(`Navigating to ${cardType} section`);

                // Add actual navigation logic here
                switch (cardType) {
                    case 'syndics':
                        window.location.href = 'syndic-accounts.php';
                        break;
                    case 'residents':
                        window.location.href = 'users.php';
                        break;
                    case 'payments':
                        window.location.href = 'purchases.php';
                        break;
                    case 'reports':
                        window.location.href = 'reports.php';
                        break;
                    
                }
            });
        });

        // Table row hover effects
        document.querySelectorAll('.table-row').forEach(row => {
            row.addEventListener('mouseenter', function () {
                this.style.backgroundColor = '#f8fafc';
            });

            row.addEventListener('mouseleave', function () {
                this.style.backgroundColor = '';
            });
        });

        // Search functionality
        document.querySelector('.search-box input').addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.table-row').forEach(row => {
                const fileName = row.querySelector('.file-name');
                if (fileName) {
                    const text = fileName.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });

        // Storage animation on load
        window.addEventListener('load', function () {
            const storageFill = document.querySelector('.storage-fill');
            const originalWidth = storageFill.style.width;
            storageFill.style.width = '0%';
            setTimeout(() => {
                storageFill.style.width = originalWidth;
            }, 500);
        });

        // Auto-refresh data every 30 seconds (optional)
        setInterval(function () {
            // You can add AJAX calls here to refresh statistics
            console.log('Auto-refresh data...');
        }, 30000);

        // Notification bell functionality
        document.querySelector('.fa-bell').addEventListener('click', function () {
            // Add notification panel toggle logic
            alert('Notifications: ' + <?php echo $stats['pending_requests']; ?> + ' demandes en attente');
        });

        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });

            // Add smooth loading animation
            document.body.style.opacity = '0';
            setTimeout(() => {
                document.body.style.transition = 'opacity 0.3s ease';
                document.body.style.opacity = '1';
            }, 100);
        });

        // Enhanced table interactions
        document.querySelectorAll('.table-row').forEach(row => {
            row.addEventListener('click', function (e) {
                if (e.target.classList.contains('fa-ellipsis-h')) {
                    // Show context menu for row actions
                    console.log('Context menu for row:', this);
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            // Ctrl/Cmd + K for search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.querySelector('.search-box input').focus();
            }

            // Escape to clear search
            if (e.key === 'Escape') {
                const searchInput = document.querySelector('.search-box input');
                if (searchInput === document.activeElement) {
                    searchInput.value = '';
                    searchInput.blur();
                    // Reset table visibility
                    document.querySelectorAll('.table-row').forEach(row => {
                        row.style.display = '';
                    });
                }
            }
        });

        // Real-time stats update (simulate)
        function updateStats() {
            // This would typically fetch from an API
            console.log('Updating statistics...');
        }

        // Enhanced quick cards with loading states
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('mouseenter', function () {
                this.style.transform = 'translateY(-4px) scale(1.02)';
                this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
            });

            card.addEventListener('mouseleave', function () {
                this.style.transform = 'translateY(0) scale(1)';
                this.style.boxShadow = '';
            });
        });

        // Activity panel enhancements
        function toggleActivityPanel() {
            const panel = document.querySelector('.activity-panel');
            const mainPanel = document.querySelector('.main-panel');

            if (panel.style.display === 'none') {
                panel.style.display = 'block';
                mainPanel.style.marginRight = '320px';
            } else {
                panel.style.display = 'none';
                mainPanel.style.marginRight = '0';
            }
        }

        // Add activity panel toggle button functionality
        document.addEventListener('DOMContentLoaded', function () {
            // Add a toggle button if needed
            const toggleBtn = document.createElement('button');
            toggleBtn.innerHTML = '<i class="fas fa-bell"></i>';
            toggleBtn.style.cssText = 'position: fixed; bottom: 20px; right: 20px; width: 50px; height: 50px; border-radius: 50%; background: #FFCB32; color: white; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000;';
            toggleBtn.addEventListener('click', toggleActivityPanel);
            // Uncomment to add the toggle button
            // document.body.appendChild(toggleBtn);
        });
    </script>
</body>

</html>