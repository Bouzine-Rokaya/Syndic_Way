<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $city = trim($_POST['city']);
            $company_name = trim($_POST['company_name']);
            $address = trim($_POST['address']);
            $subscription_id = intval($_POST['subscription_id']);
            
            // Check if email exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM member WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Un compte avec cet email existe déjà.");
            }
            
            $conn->beginTransaction();
            
            // Insert or get city
            $stmt = $conn->prepare("SELECT id_city FROM city WHERE city_name = ?");
            $stmt->execute([$city]);
            $city_id = $stmt->fetchColumn();
            
            if (!$city_id) {
                $stmt = $conn->prepare("INSERT INTO city (city_name) VALUES (?)");
                $stmt->execute([$city]);
                $city_id = $conn->lastInsertId();
            }
            
            // Insert residence
            $stmt = $conn->prepare("INSERT INTO residence (id_city, name, address) VALUES (?, ?, ?)");
            $stmt->execute([$city_id, $company_name, $address]);
            $residence_id = $conn->lastInsertId();
            
            // Insert member
            $default_password = password_hash('syndic123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO member (full_name, email, password, phone, role, status) VALUES (?, ?, ?, ?, 2, 'active')");
            $stmt->execute([$full_name, $email, $default_password, $phone]);
            $member_id = $conn->lastInsertId();
            
            // Create apartment
            $stmt = $conn->prepare("INSERT INTO apartment (id_residence, id_member, type, floor, number) VALUES (?, ?, 'Bureau', '1', 1)");
            $stmt->execute([$residence_id, $member_id]);
            
            // Link to admin
            $stmt = $conn->prepare("INSERT INTO admin_member_link (id_admin, id_member, date_created) VALUES (1, ?, NOW())");
            $stmt->execute([$member_id]);
            
            // Create subscription
            $stmt = $conn->prepare("SELECT price_subscription FROM subscription WHERE id_subscription = ?");
            $stmt->execute([$subscription_id]);
            $price = $stmt->fetchColumn();
            
            $stmt = $conn->prepare("INSERT INTO admin_member_subscription (id_admin, id_member, id_subscription, date_payment, amount) VALUES (1, ?, ?, NOW(), ?)");
            $stmt->execute([$member_id, $subscription_id, $price]);
            
            $conn->commit();
            $_SESSION['success'] = "Compte syndic créé avec succès. Mot de passe par défaut: syndic123";
            
        } elseif ($action === 'update_status') {
            $member_id = intval($_POST['member_id']);
            $new_status = $_POST['new_status'];
            
            $stmt = $conn->prepare("UPDATE member SET status = ? WHERE id_member = ? AND role = 2");
            $stmt->execute([$new_status, $member_id]);
            
            $_SESSION['success'] = "Statut mis à jour avec succès.";
            
        } elseif ($action === 'delete') {
            $member_id = intval($_POST['member_id']);
            
            $conn->beginTransaction();
            
            // Delete related records first
            $stmt = $conn->prepare("DELETE FROM admin_member_subscription WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $stmt = $conn->prepare("DELETE FROM admin_member_link WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $stmt = $conn->prepare("DELETE FROM apartment WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $stmt = $conn->prepare("DELETE FROM member WHERE id_member = ? AND role = 2");
            $stmt->execute([$member_id]);
            
            $conn->commit();
            $_SESSION['success'] = "Compte syndic supprimé avec succès.";
        }
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header('Location: syndic-accounts.php');
    exit();
}

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$city_filter = $_GET['city'] ?? '';

// Build query
$where_conditions = ["m.role = 2"];
$params = [];

if ($search) {
    $where_conditions[] = "(m.full_name LIKE ? OR m.email LIKE ? OR r.name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
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

// Get syndic accounts
try {
    $query = "
        SELECT 
            m.id_member,
            m.full_name,
            m.email,
            m.phone,
            m.status,
            m.date_created,
            r.name as company_name,
            r.address,
            c.city_name,
            s.name_subscription,
            ams.amount as subscription_amount,
            ams.date_payment
        FROM member m
        LEFT JOIN apartment ap ON ap.id_member = m.id_member
        LEFT JOIN residence r ON r.id_residence = ap.id_residence
        LEFT JOIN city c ON c.id_city = r.id_city
        LEFT JOIN admin_member_subscription ams ON ams.id_member = m.id_member
        LEFT JOIN subscription s ON s.id_subscription = ams.id_subscription
        WHERE $where_clause
        ORDER BY m.date_created DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $syndic_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get cities for filter
    $stmt = $conn->prepare("SELECT DISTINCT city_name FROM city ORDER BY city_name");
    $stmt->execute();
    $cities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get active subscriptions for form
    $stmt = $conn->prepare("SELECT * FROM subscription WHERE is_active = 1 ORDER BY price_subscription ASC");
    $stmt->execute();
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors du chargement des données.";
    $syndic_accounts = [];
    $cities = [];
    $subscriptions = [];
}

$page_title = "Comptes Syndic - Syndic Way";
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/dashboard.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/syndic-accounts.css">
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
                    <li class="active">
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
                <div>
                    <h1><i class="fas fa-building"></i> Comptes Syndic</h1>
                    <p>Gérez les comptes des syndics de copropriété</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()">
                    <i class="fas fa-plus"></i> Nouveau syndic
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

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" id="filtersForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Rechercher</label>
                            <input type="text" name="search" id="search" 
                                   placeholder="Nom, email ou entreprise..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
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

            <!-- Accounts Table -->
            <div class="accounts-table">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-list"></i>
                        Liste des comptes syndic (<?php echo count($syndic_accounts); ?>)
                    </h3>
                </div>

                <?php if (!empty($syndic_accounts)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Syndic</th>
                                <th>Entreprise</th>
                                <th>Ville</th>
                                <th>Forfait</th>
                                <th>Statut</th>
                                <th>Date création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($syndic_accounts as $account): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($account['full_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($account['email']); ?></small><br>
                                        <small><?php echo htmlspecialchars($account['phone']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($account['company_name'] ?? 'Non défini'); ?></strong><br>
                                        <small><?php echo htmlspecialchars($account['address'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($account['city_name'] ?? 'Non définie'); ?></td>
                                    <td>
                                        <?php if ($account['name_subscription']): ?>
                                            <strong><?php echo htmlspecialchars($account['name_subscription']); ?></strong><br>
                                            <small><?php echo number_format($account['subscription_amount'], 2); ?> DH/mois</small>
                                        <?php else: ?>
                                            <span class="text-muted">Aucun</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $account['status']; ?>">
                                            <?php 
                                                $status_text = [
                                                    'active' => 'Actif',
                                                    'pending' => 'En attente',
                                                    'inactive' => 'Inactif'
                                                ];
                                                echo $status_text[$account['status']] ?? $account['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('j M Y', strtotime($account['date_created'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($account['status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="updateStatus(<?php echo $account['id_member']; ?>, 'active')">
                                                    <i class="fas fa-check"></i> Activer
                                                </button>
                                            <?php elseif ($account['status'] === 'active'): ?>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="updateStatus(<?php echo $account['id_member']; ?>, 'inactive')">
                                                    <i class="fas fa-pause"></i> Suspendre
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="updateStatus(<?php echo $account['id_member']; ?>, 'active')">
                                                    <i class="fas fa-play"></i> Réactiver
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-sm btn-danger" 
                                                    onclick="confirmDelete(<?php echo $account['id_member']; ?>, '<?php echo htmlspecialchars($account['full_name']); ?>')">
                                                <i class="fas fa-trash"></i> Supprimer
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state" style="padding: 3rem; text-align: center;">
                        <i class="fas fa-building" style="font-size: 3rem; color: var(--color-grey); margin-bottom: 1rem;"></i>
                        <h3>Aucun compte syndic trouvé</h3>
                        <p>Aucun compte ne correspond aux critères de recherche.</p>
                        <button class="btn btn-primary" onclick="openModal()">
                            <i class="fas fa-plus"></i> Créer le premier compte
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Create Syndic Modal -->
    <div id="syndicModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-building"></i>
                    Nouveau compte syndic
                </h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Nom complet <span class="required">*</span></label>
                        <input type="text" name="full_name" id="full_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email <span class="required">*</span></label>
                        <input type="email" name="email" id="email" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Téléphone <span class="required">*</span></label>
                        <input type="tel" name="phone" id="phone" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="city">Ville <span class="required">*</span></label>
                        <input type="text" name="city" id="city" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="company_name">Nom de l'entreprise/immeuble <span class="required">*</span></label>
                    <input type="text" name="company_name" id="company_name" required>
                </div>

                <div class="form-group">
                    <label for="address">Adresse</label>
                    <textarea name="address" id="address" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="subscription_id">Forfait d'abonnement <span class="required">*</span></label>
                    <select name="subscription_id" id="subscription_id" required>
                        <option value="">Choisir un forfait</option>
                        <?php foreach ($subscriptions as $subscription): ?>
                            <option value="<?php echo $subscription['id_subscription']; ?>">
                                <?php echo htmlspecialchars($subscription['name_subscription']); ?> 
                                - <?php echo number_format($subscription['price_subscription'], 2); ?> DH/mois
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Créer le compte
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden forms for actions -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="member_id" id="statusMemberId">
        <input type="hidden" name="new_status" id="newStatus">
    </form>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="member_id" id="deleteMemberId">
    </form>

    <script src="http://localhost/syndicplatform/js/admin/syndic-accounts.js"></script>
</body>
</html>