<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
$current_user = getCurrentUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_syndic_status') {
            $member_id = intval($_POST['member_id']);
            $new_status = $_POST['new_status'];

            $stmt = $conn->prepare("UPDATE member SET status = ? WHERE id_member = ? AND role = 2");
            $stmt->execute([$new_status, $member_id]);

            $_SESSION['success'] = "Statut du syndic mis à jour avec succès.";

        } elseif ($action === 'reset_password') {
            $member_id = intval($_POST['member_id']);
            $new_password = 'syndic123';
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE member SET password = ? WHERE id_member = ? AND role = 2");
            $stmt->execute([$hashed_password, $member_id]);

            $_SESSION['success'] = "Mot de passe réinitialisé. Nouveau mot de passe: syndic123";
        }

    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }

    header('Location: users.php');
    exit();
}

// Get filters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$city_filter = $_GET['city'] ?? '';

// Build query conditions
$where_conditions = ["m.role IN (1, 2)"];
$params = [];

if ($search) {
    $where_conditions[] = "(m.full_name LIKE ? OR m.email LIKE ? OR r.name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($role_filter) {
    if ($role_filter === 'syndic') {
        $where_conditions[] = "m.role = 2";
    } elseif ($role_filter === 'resident') {
        $where_conditions[] = "m.role = 1";
    }
}

if ($status_filter) {
    $where_conditions[] = "m.status = ?";
    $params[] = $status_filter;
}

if ($city_filter) {
    $where_conditions[] = "c.city_name = ?";
    $params[] = $city_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Get platform statistics
$platform_stats = [
    'total_syndics' => 0,
    'active_syndics' => 0,
    'total_residents' => 0,
    'active_residents' => 0,
    'pending_users' => 0
];

try {
    // Total syndics
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member WHERE role = 2");
    $stmt->execute();
    $platform_stats['total_syndics'] = $stmt->fetch()['count'];
    
    // Active syndics
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member WHERE role = 2 AND status = 'active'");
    $stmt->execute();
    $platform_stats['active_syndics'] = $stmt->fetch()['count'];
    
    // Total residents
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member WHERE role = 1");
    $stmt->execute();
    $platform_stats['total_residents'] = $stmt->fetch()['count'];
    
    // Active residents
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member WHERE role = 1 AND status = 'active'");
    $stmt->execute();
    $platform_stats['active_residents'] = $stmt->fetch()['count'];
    
    // Pending users
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member WHERE role IN (1, 2) AND status = 'inactive'");
    $stmt->execute();
    $platform_stats['pending_users'] = $stmt->fetch()['count'];

    // Get platform users
    $query = "
        SELECT 
            m.id_member,
            m.full_name,
            m.email,
            m.phone,
            m.role,
            m.status,
            m.date_created,
            CASE 
                WHEN m.role = 1 THEN 'Résident'
                WHEN m.role = 2 THEN 'Syndic'
                ELSE 'Autre'
            END as role_name,
            r.name as company_name,
            r.address,
            c.city_name,
            s.name_subscription,
            s.price_subscription,
            ams.date_payment as subscription_date
        FROM member m
        LEFT JOIN apartment ap ON ap.id_member = m.id_member
        LEFT JOIN residence r ON r.id_residence = ap.id_residence
        LEFT JOIN city c ON c.id_city = r.id_city
        LEFT JOIN admin_member_subscription ams ON ams.id_member = m.id_member
        LEFT JOIN subscription s ON s.id_subscription = ams.id_subscription
        WHERE $where_clause
        GROUP BY m.id_member
        ORDER BY 
            CASE WHEN m.role = 2 THEN 1 ELSE 2 END,
            m.date_created DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $platform_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get cities for filter
    $stmt = $conn->prepare("
        SELECT DISTINCT c.city_name 
        FROM city c
        JOIN residence r ON r.id_city = c.id_city
        JOIN apartment a ON a.id_residence = r.id_residence
        JOIN member m ON m.id_member = a.id_member
        WHERE m.role IN (1, 2)
        ORDER BY c.city_name
    ");
    $stmt->execute();
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get recent activities
    $stmt = $conn->prepare("
        SELECT 
            m.full_name,
            m.date_created,
            m.status,
            m.role,
            'user_activity' as activity_type
        FROM member m
        WHERE m.role IN (1, 2)
        ORDER BY m.date_created DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors du chargement des données.";
    $platform_users = [];
    $cities = [];
    $recent_activities = [];
}

// Helper function to format time ago
function timeAgo($datetime) {
    if (!$datetime) return 'Inconnu';
    
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'À l\'instant';
    if ($time < 3600) return floor($time/60) . ' min';
    if ($time < 86400) return floor($time/3600) . ' h';
    if ($time < 2592000) return floor($time/86400) . ' j';
    if ($time < 31536000) return floor($time/2592000) . ' mois';
    
    return floor($time/31536000) . ' ans';
}

$page_title = "Utilisateurs - Syndic Way";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/users.css">

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
                    <div class="quick-access-title">Statistiques Utilisateurs</div>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card syndics">
                        <div class="quick-card-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="quick-card-title">Syndics</div>
                        <div class="quick-card-count"><?php echo $platform_stats['total_syndics']; ?></div>
                        <div class="quick-card-stats"><?php echo $platform_stats['active_syndics']; ?> actifs</div>
                    </div>

                    <div class="quick-card residents">
                        <div class="quick-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="quick-card-title">Résidents</div>
                        <div class="quick-card-count"><?php echo $platform_stats['total_residents']; ?></div>
                        <div class="quick-card-stats"><?php echo $platform_stats['active_residents']; ?> actifs</div>
                    </div>

                    <div class="quick-card pending">
                        <div class="quick-card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="quick-card-title">En Attente</div>
                        <div class="quick-card-count"><?php echo $platform_stats['pending_users']; ?></div>
                        <div class="quick-card-stats">À traiter</div>
                    </div>

                    <div class="quick-card total">
                        <div class="quick-card-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div class="quick-card-title">Total Actif</div>
                        <div class="quick-card-count"><?php echo $platform_stats['active_syndics'] + $platform_stats['active_residents']; ?></div>
                        <div class="quick-card-stats">Tous rôles</div>
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
                        <a href="#">Gestion Utilisateurs</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Tous les Utilisateurs</span>
                    </div>

                    <!-- Filters -->
                    <div class="filters-section">
                        <form method="GET" id="filtersForm">
                            <div class="filters-grid">
                                <div class="filter-group">
                                    <label for="search">Rechercher</label>
                                    <input type="text" name="search" id="search" 
                                           placeholder="Nom, email..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>

                                <div class="filter-group">
                                    <label for="role">Type</label>
                                    <select name="role" id="role">
                                        <option value="">Tous</option>
                                        <option value="syndic" <?php echo $role_filter === 'syndic' ? 'selected' : ''; ?>>Syndics</option>
                                        <option value="resident" <?php echo $role_filter === 'resident' ? 'selected' : ''; ?>>Résidents</option>
                                    </select>
                                </div>

                                <div class="filter-group">
                                    <label for="status">Statut</label>
                                    <select name="status" id="status">
                                        <option value="">Tous</option>
                                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actif</option>
                                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                                    </select>
                                </div>

                                <div class="filter-group">
                                    <label for="city">Ville</label>
                                    <select name="city" id="city">
                                        <option value="">Toutes</option>
                                        <?php foreach ($cities as $city): ?>
                                            <option value="<?php echo htmlspecialchars($city); ?>" 
                                                    <?php echo $city_filter === $city ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($city); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="filter-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filtrer
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <!-- Data Table -->
                    <table class="data-table">
                        <thead class="table-header-row">
                            <tr>
                                <th>Utilisateur</th>
                                <th>Type</th>
                                <th>Contact</th>
                                <th>Statut</th>
                                <th>Inscrit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($platform_users)): ?>
                            <tr class="table-row">
                                <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                                    <i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                                    <div>Aucun utilisateur trouvé</div>
                                    <div style="font-size: 12px; margin-top: 8px;">Modifiez vos critères de recherche</div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($platform_users as $user): ?>
                                <tr class="table-row">
                                    <td>
                                        <div class="file-item">
                                            <div class="table-icon" style="background: <?php echo $user['role'] == 2 ? '#3b82f6' : '#10b981'; ?>;">
                                                <i class="fas fa-<?php echo $user['role'] == 2 ? 'user-tie' : 'user'; ?>"></i>
                                            </div>
                                            <div class="file-info">
                                                <div class="file-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($user['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="tag <?php echo $user['role'] == 1 ? 'resident' : ''; ?>">
                                            <?php echo $user['role'] == 2 ? 'Syndic' : 'Résident'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px;">
                                            <?php if ($user['phone']): ?>
                                                <div><i class="fas fa-phone" style="width: 12px; margin-right: 6px;"></i><?php echo htmlspecialchars($user['phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($user['city_name']): ?>
                                                <div><i class="fas fa-map-marker-alt" style="width: 12px; margin-right: 6px;"></i><?php echo htmlspecialchars($user['city_name']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['status']; ?>">
                                            <?php 
                                                $status_text = [
                                                    'active' => 'Actif',
                                                    'pending' => 'En attente',
                                                    'inactive' => 'Inactif'
                                                ];
                                                echo $status_text[$user['status']] ?? $user['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo timeAgo($user['date_created']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-secondary" 
                                                    onclick="viewUserDetails(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                                    title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($user['role'] == 2): ?>
                                                <!-- Syndic actions -->
                                                <?php if ($user['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="updateSyndicStatus(<?php echo $user['id_member']; ?>, 'active')"
                                                            title="Activer">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php elseif ($user['status'] === 'active'): ?>
                                                    <button class="btn btn-sm btn-warning" 
                                                            onclick="updateSyndicStatus(<?php echo $user['id_member']; ?>, 'inactive')"
                                                            title="Suspendre">
                                                        <i class="fas fa-pause"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="updateSyndicStatus(<?php echo $user['id_member']; ?>, 'active')"
                                                            title="Réactiver">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button class="btn btn-sm btn-secondary" 
                                                        onclick="resetPassword(<?php echo $user['id_member']; ?>)"
                                                        title="Reset mot de passe">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                            <?php else: ?>
                                                <!-- Resident actions - view only -->
                                                <span class="btn btn-sm" style="background: #f3f4f6; color: #6b7280; cursor: not-allowed;" 
                                                      title="Lecture seule">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Activity Panel -->
                <div class="activity-panel">
                    <div class="activity-header">
                        <div class="activity-title">Activité Récente</div>
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
                            <div class="activity-icon <?php echo $activity['role'] == 2 ? 'syndic' : ($activity['status'] === 'pending' ? 'pending' : 'resident'); ?>">
                                <i class="fas fa-<?php echo $activity['role'] == 2 ? 'building' : 'user'; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <?php 
                                        $action_text = $activity['role'] == 2 ? 'Nouveau syndic' : 'Nouveau résident';
                                        if ($activity['status'] === 'pending') {
                                            $action_text = 'Demande en attente';
                                        }
                                        echo $action_text . ': ' . htmlspecialchars($activity['full_name']);
                                    ?>
                                </div>
                                <div class="activity-time"><?php echo timeAgo($activity['date_created']); ?></div>
                                <div class="activity-meta">
                                    <div class="sharing-avatar"><?php echo strtoupper(substr($activity['full_name'], 0, 2)); ?></div>
                                    <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($activity['full_name']); ?></span>
                                    <div class="tag <?php echo $activity['role'] == 1 ? 'resident' : ''; ?>">
                                        <?php echo $activity['role'] == 2 ? 'Syndic' : 'Résident'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="userDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-circle"></i> Détails de l'utilisateur</h2>
                <button class="close" onclick="closeModal('userDetailsModal')">&times;</button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Hidden forms for actions -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_syndic_status">
        <input type="hidden" name="member_id" id="statusMemberId">
        <input type="hidden" name="new_status" id="newStatus">
    </form>

    <form id="passwordForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="member_id" id="passwordMemberId">
    </form>

    <script>
        // View user details
        function viewUserDetails(userData) {
            let message = `Informations générales:\n`;
            message += `- Nom: ${userData.full_name}\n`;
            message += `- Email: ${userData.email}\n`;
            message += `- Téléphone: ${userData.phone || 'Non renseigné'}\n`;
            message += `- Rôle: ${userData.role == 2 ? 'Syndic' : 'Résident'}\n\n`;

            message += `Localisation:\n`;
            message += `- Ville: ${userData.city_name || 'Non définie'}\n`;
            message += `- Entreprise: ${userData.company_name || 'Non définie'}\n`;
            message += `- Statut: ${getStatusText(userData.status)}\n\n`;

            if (userData.role == 2 && userData.name_subscription) {
                message += `Abonnement:\n`;
                message += `- Forfait: ${userData.name_subscription}\n`;
                message += `- Prix: ${userData.price_subscription} DH/mois\n\n`;
            }

            message += `Permissions:\n`;
            message += userData.role == 2 
                ? 'Syndic: Peut être géré par l\'administrateur.\n'
                : 'Résident: Lecture seule.\n';

            alert(message);
}

        function getStatusText(status) {
            const statusTexts = {
                'active': 'Actif',
                'pending': 'En attente',
                'inactive': 'Inactif'
            };
            return statusTexts[status] || status;
        }

        // Update syndic status
        function updateSyndicStatus(memberId, newStatus) {
            const statusText = {
                'active': 'activer',
                'inactive': 'suspendre'
            };
            
            if (confirm(`Êtes-vous sûr de vouloir ${statusText[newStatus]} ce syndic ?`)) {
                document.getElementById('statusMemberId').value = memberId;
                document.getElementById('newStatus').value = newStatus;
                document.getElementById('statusForm').submit();
            }
        }

        // Reset password
        function resetPassword(memberId) {
            if (confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe ?\n\nLe nouveau mot de passe sera : syndic123')) {
                document.getElementById('passwordMemberId').value = memberId;
                document.getElementById('passwordForm').submit();
            }
        }

        // Export users
        function exportUsers() {
            // Create form for export
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.name = 'action';
            actionInput.value = 'export_users';
            form.appendChild(actionInput);
            
            // Add current filters
            ['search', 'role', 'status', 'city'].forEach(filterName => {
                const input = document.createElement('input');
                input.name = `export_${filterName}`;
                input.value = document.getElementById(filterName).value;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // Refresh data
        function refreshData() {
            window.location.reload();
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Global search functionality
        document.getElementById('globalSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.table-row').forEach(row => {
                if (row.querySelector('td[colspan]')) return; // Skip empty state row
                
                const name = row.querySelector('.file-name')?.textContent.toLowerCase() || '';
                const email = row.querySelector('.file-info div:last-child')?.textContent.toLowerCase() || '';
                
                if (name.includes(searchTerm) || email.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

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
            document.querySelector('.main-panel').style.marginRight = '0';
        });

        // Quick card clicks
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('click', function() {
                const cardType = this.classList[1];
                
                // Filter by card type
                const roleSelect = document.getElementById('role');
                const statusSelect = document.getElementById('status');
                
                switch(cardType) {
                    case 'syndics':
                        roleSelect.value = 'syndic';
                        break;
                    case 'residents':
                        roleSelect.value = 'resident';
                        break;
                    case 'pending':
                        statusSelect.value = 'pending';
                        break;
                    default:
                        roleSelect.value = '';
                        statusSelect.value = '';
                }
                
                document.getElementById('filtersForm').submit();
            });
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('userDetailsModal');
            if (event.target === modal) {
                closeModal('userDetailsModal');
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

            // Storage animation on load
            const storageFill = document.querySelector('.storage-fill');
            const originalWidth = storageFill.style.width;
            storageFill.style.width = '0%';
            setTimeout(() => {
                storageFill.style.width = originalWidth;
            }, 500);

            // Animate counters
            document.querySelectorAll('.quick-card-count').forEach((counter, index) => {
                const target = parseInt(counter.textContent);
                counter.textContent = '0';
                setTimeout(() => {
                    animateCounter(counter, target);
                }, index * 200);
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

        // Enhanced table interactions
        document.querySelectorAll('.table-row').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8fafc';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });

        // Form auto-submit on filter change
        document.querySelectorAll('#filtersForm select').forEach(select => {
            select.addEventListener('change', function() {
                document.getElementById('filtersForm').submit();
            });
        });
    </script>
</body>
</html>