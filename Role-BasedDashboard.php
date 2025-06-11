<?php
// =======================
// ROLE-BASED PERMISSION SYSTEM
// File: includes/auth.php
// =======================

class RoleBasedAuth {
    private $conn;
    private $current_user;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * Check if user is logged in and get their role
     */
    public function checkAuth() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            $this->redirectToLogin();
            return false;
        }
        
        $this->current_user = [
            'id' => $_SESSION['user_id'],
            'role' => $_SESSION['user_role'],
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? ''
        ];
        
        return true;
    }
    
    /**
     * Check if user has required permission
     */
    public function hasPermission($required_role) {
        if (!$this->current_user) {
            return false;
        }
        
        $role_hierarchy = [
            'admin' => 3,
            'syndic' => 2,
            'resident' => 1
        ];
        
        $user_level = $role_hierarchy[$this->current_user['role']] ?? 0;
        $required_level = $role_hierarchy[$required_role] ?? 0;
        
        return $user_level >= $required_level;
    }
    
    /**
     * Require specific role or redirect
     */
    public function requireRole($required_role) {
        if (!$this->checkAuth() || !$this->hasPermission($required_role)) {
            $this->redirectToLogin();
            exit();
        }
    }
    
    /**
     * Get user information
     */
    public function getCurrentUser() {
        return $this->current_user;
    }
    
    /**
     * Redirect to appropriate dashboard based on role
     */
    public function redirectToDashboard() {
        $role = $this->current_user['role'];
        
        switch ($role) {
            case 'admin':
                header('Location: /syndicplatform/admin/dashboard.php');
                break;
            case 'syndic':
                header('Location: /syndicplatform/syndic/dashboard.php');
                break;
            case 'resident':
                header('Location: /syndicplatform/resident/dashboard.php');
                break;
            default:
                header('Location: /syndicplatform/public/login.php');
        }
        exit();
    }
    
    private function redirectToLogin() {
        header('Location: /syndicplatform/public/login.php');
        exit();
    }
}
?>
// =======================
// UPDATED LOGIN HANDLER
// File: public/login.php (Updated)
// =======================

<?php
session_start();
require_once __DIR__ . '/../config.php';

$auth = new RoleBasedAuth($conn);

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $auth->redirectToDashboard();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        // 1. Check Admin
        $stmt = $conn->prepare("SELECT * FROM admin WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['user_id'] = $admin['id_admin'];
            $_SESSION['user_role'] = 'admin';
            $_SESSION['user_name'] = $admin['name'];
            $_SESSION['user_email'] = $admin['email'];
            header("Location: /syndicplatform/admin/dashboard.php");
            exit;
        }

        // 2. Check Member (Syndic or Resident)
        $stmt = $conn->prepare("SELECT * FROM member WHERE email = ?");
        $stmt->execute([$email]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($member && password_verify($password, $member['password'])) {
            $_SESSION['user_id'] = $member['id_member'];
            $_SESSION['user_name'] = $member['full_name'];
            $_SESSION['user_email'] = $member['email'];

            // Determine role based on member's role field
            if ($member['role'] == 2) { // Syndic
                $_SESSION['user_role'] = 'syndic';
                header("Location: /syndicplatform/syndic/dashboard.php");
            } else { // Resident
                $_SESSION['user_role'] = 'resident';
                header("Location: /syndicplatform/resident/dashboard.php");
            }
            exit;
        }

        // If we reach here, login failed
        $_SESSION['error'] = "Email ou mot de passe incorrect.";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur de connexion.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Login - Syndic Way</title>
    <link rel="stylesheet" href="/syndicplatform/css/sections/signUp.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="auth-container">
        <div class="image">
            <img src="/syndicplatform/public/images/full-shot-colleagues-working-office 2.png">
        </div>
        <div class="all-content">
            <div class="auth-form">
                <div class="auth-header">
                    <h2><i class="fas fa-building"></i> Syndic Way</h2>
                    <p>Connexion à votre compte</p>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Email :</label><br>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Mot de passe :</label><br>
                        <input type="password" id="password" name="password" required>
                        <button type="button" id="toggle-password" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggle-icon"></i>
                        </button>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">
                        <i class="fas fa-sign-in-alt"></i> Se connecter
                    </button>
                </form>

                <div class="auth-footer">
                    <p><a href="/syndicplatform/public/">← Retour à l'accueil</a></p>
                    <p>Vous n'avez pas de compte ? <a href="/syndicplatform/public/">Abonnez-vous maintenant</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.querySelector('#password');
            const toggleIcon = document.querySelector('#toggle-icon');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>

<?php
// =======================
// ADMIN DASHBOARD
// File: admin/dashboard.php (Protected)
// =======================

session_start();
require_once __DIR__ . '/../config.php';

$auth = new RoleBasedAuth($conn);
$auth->requireRole('admin'); // Only admins can access

// Get dashboard statistics
try {
    // Total subscriptions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM subscription WHERE is_active = 1");
    $stmt->execute();
    $total_subscriptions = $stmt->fetch()['count'];

    // Total members
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member");
    $stmt->execute();
    $total_members = $stmt->fetch()['count'];

    // Total syndics
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member WHERE role = 2");
    $stmt->execute();
    $total_syndics = $stmt->fetch()['count'];

    // Pending purchases
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM member WHERE status = 'pending'");
    $stmt->execute();
    $pending_purchases = $stmt->fetch()['count'];

    // Recent purchases
    $stmt = $conn->prepare("
        SELECT 
            m.id_member,
            m.full_name AS syndic_name,
            m.email AS syndic_email,
            s.name_subscription AS plan_name,
            ams.amount AS amount_paid,
            ams.date_payment AS purchase_date,
            m.status AS payment_status
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
    $total_subscriptions = 0;
    $total_members = 0;
    $total_syndics = 0;
    $pending_purchases = 0;
    $recent_purchases = [];
}

$current_user = $auth->getCurrentUser();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Syndic Way</title>
    <link rel="stylesheet" href="/syndicplatform/css/sections/dashboard.css">
    <link rel="stylesheet" href="/syndicplatform/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-brand">
            <h2><i class="fas fa-shield-alt"></i> Admin Panel</h2>
        </div>
        <div class="nav-user">
            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($current_user['name']); ?></span>
            <a href="/syndicplatform/public/logout.php" class="btn btn-logout">
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
                <p>Bienvenue, <?php echo htmlspecialchars($current_user['name']); ?>!</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Syndics au total</h3>
                        <div class="stat-number"><?php echo $total_syndics; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Utilisateurs au total</h3>
                        <div class="stat-number"><?php echo $total_members; ?></div>
                    </div>
                </div>

                <div class="stat-card pending">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Achats en attente</h3>
                        <div class="stat-number"><?php echo $pending_purchases; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Forfaits actifs</h3>
                        <div class="stat-number"><?php echo $total_subscriptions; ?></div>
                    </div>
                </div>
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
                                                    echo $purchase['payment_status'] == 'pending' ? 'En attente' : 'Actif';
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($purchase['payment_status'] === 'pending'): ?>
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

<?php
// =======================
// SYNDIC DASHBOARD
// File: syndic/dashboard.php (Protected)
// =======================

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new RoleBasedAuth($conn);
$auth->requireRole('syndic'); // Only syndics can access

// Get syndic information and building statistics
try {
    $current_user = $auth->getCurrentUser();
    
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
    $stmt->execute([$current_user['id']]);
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
    $stmt->execute([$current_user['id']]);
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
    <link rel="stylesheet" href="/syndicplatform/css/sections/dashboard.css">
    <link rel="stylesheet" href="/syndicplatform/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-brand">
            <h2><i class="fas fa-building"></i> Espace Syndic</h2>
        </div>
        <div class="nav-user">
            <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($current_user['name']); ?></span>
            <a href="/syndicplatform/public/logout.php" class="btn btn-logout">
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
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1>Tableau de bord Syndic</h1>
                <p>Gérez votre immeuble et vos résidents</p>
            </div>

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
</body>
</html>

<?php
// =======================
// RESIDENT DASHBOARD
// File: resident/dashboard.php (Protected)
// =======================

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new RoleBasedAuth($conn);
$auth->requireRole('resident'); // Only residents can access

// Get resident information and building details
try {
    $current_user = $auth->getCurrentUser();
    
    // Get resident details with apartment and building info
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
    $stmt->execute([$current_user['id']]);
    $resident_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get syndic information for this building
    $stmt = $conn->prepare("
        SELECT m.full_name as syndic_name, m.email as syndic_email, m.phone as syndic_phone
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        JOIN residence r ON r.id_residence = ap.id_residence
        WHERE r.id_residence = ? AND m.role = 2
        LIMIT 1
    ");
    $stmt->execute([$resident_info['id_residence'] ?? 0]);
    $syndic_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get total residents in building
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_residents
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        WHERE ap.id_residence = ? AND m.role = 1
    ");
    $stmt->execute([$resident_info['id_residence'] ?? 0]);
    $total_residents = $stmt->fetch()['total_residents'] ?? 0;

    // Mock data for various metrics
    $my_charges = 750; // Monthly charges
    $payment_status = 'paid'; // paid, pending, overdue
    $last_payment_date = date('Y-m-d', strtotime('-15 days'));
    $next_payment_due = date('Y-m-d', strtotime('+15 days'));
    $pending_requests = 1; // Maintenance requests
    $unread_announcements = 2;

    // Get recent announcements (mock data)
    $recent_announcements = [
        [
            'id' => 1,
            'title' => 'Nettoyage des escaliers',
            'content' => 'Les escaliers seront nettoyés demain de 9h à 12h.',
            'date_posted' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'is_read' => false
        ],
        [
            'id' => 2,
            'title' => 'Assemblée générale',
            'content' => 'L\'assemblée générale aura lieu le 15 de ce mois.',
            'date_posted' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'is_read' => true
        ]
    ];

    // Get payment history (mock data)
    $payment_history = [
        [
            'month' => date('M Y', strtotime('-1 month')),
            'amount' => 750,
            'status' => 'paid',
            'payment_date' => date('Y-m-d', strtotime('-45 days'))
        ],
        [
            'month' => date('M Y', strtotime('-2 months')),
            'amount' => 750,
            'status' => 'paid',
            'payment_date' => date('Y-m-d', strtotime('-75 days'))
        ]
    ];

} catch(PDOException $e) {
    error_log($e->getMessage());
    $resident_info = null;
    $syndic_info = null;
    $total_residents = 0;
    $my_charges = 0;
    $payment_status = 'unknown';
    $pending_requests = 0;
    $unread_announcements = 0;
    $recent_announcements = [];
    $payment_history = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - Syndic Way</title>
    <link rel="stylesheet" href="/syndicplatform/css/sections/dashboard.css">
    <link rel="stylesheet" href="/syndicplatform/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .apartment-info-card {
            background: linear-gradient(135deg, var(--color-green), #20c997);
            color: var(--color-white);
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .apartment-info-card h2 {
            margin: 0 0 1rem 0;
            font-size: 1.8rem;
        }

        .apartment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .apartment-detail {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .apartment-detail i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .payment-status-card {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid;
        }

        .payment-status-card.paid {
            background: rgba(40, 167, 69, 0.1);
            border-color: var(--color-green);
        }

        .payment-status-card.pending {
            background: rgba(244, 185, 66, 0.1);
            border-color: var(--color-yellow);
        }

        .payment-status-card.overdue {
            background: rgba(220, 53, 69, 0.1);
            border-color: #dc3545;
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
            background: linear-gradient(135deg, var(--color-green), #20c997);
        }

        .action-card:hover {
            transform: translateY(-5px);
            border-color: var(--color-green);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .syndic-contact-card {
            background: var(--color-white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .two-column-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .apartment-details {
                grid-template-columns: 1fr;
            }
            
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
            
            .two-column-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-brand">
            <h2><i class="fas fa-home"></i> Espace Résident</h2>
        </div>
        <div class="nav-user">
            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($current_user['name']); ?></span>
            <a href="/syndicplatform/public/logout.php" class="btn btn-logout">
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
                        <a href="payments.php">
                            <i class="fas fa-credit-card"></i> Mes paiements
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
                        <a href="announcements.php">
                            <i class="fas fa-bullhorn"></i> Annonces
                            <?php if($unread_announcements > 0): ?>
                                <span class="notifications-badge"><?php echo $unread_announcements; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="neighbors.php">
                            <i class="fas fa-users"></i> Voisinage
                        </a>
                    </li>
                    <li>
                        <a href="documents.php">
                            <i class="fas fa-file-alt"></i> Documents
                        </a>
                    </li>
                    <li>
                        <a href="contact.php">
                            <i class="fas fa-envelope"></i> Contact syndic
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
            <!-- Apartment Info Card -->
            <?php if ($resident_info): ?>
                <div class="apartment-info-card">
                    <h2>Appartement <?php echo htmlspecialchars($resident_info['number']); ?></h2>
                    <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($resident_info['building_name'] ?? 'Mon Immeuble'); ?></p>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($resident_info['address'] ?? ''); ?>, <?php echo htmlspecialchars($resident_info['city_name'] ?? ''); ?></p>
                    
                    <div class="apartment-details">
                        <div class="apartment-detail">
                            <i class="fas fa-home"></i>
                            <h4>Appartement <?php echo $resident_info['number']; ?></h4>
                            <p>Étage <?php echo $resident_info['floor']; ?></p>
                        </div>
                        <div class="apartment-detail">
                            <i class="fas fa-door-open"></i>
                            <h4><?php echo htmlspecialchars($resident_info['type']); ?></h4>
                            <p>Type d'appartement</p>
                        </div>
                        <div class="apartment-detail">
                            <i class="fas fa-users"></i>
                            <h4><?php echo $total_residents; ?> résidents</h4>
                            <p>Dans l'immeuble</p>
                        </div>
                        <div class="apartment-detail">
                            <i class="fas fa-calendar"></i>
                            <h4><?php echo date('M Y', strtotime($resident_info['date_created'])); ?></h4>
                            <p>Résident depuis</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Payment Status -->
            <div class="payment-status-card <?php echo $payment_status; ?>">
                <h4>
                    <?php if ($payment_status === 'paid'): ?>
                        <i class="fas fa-check-circle"></i> Paiements à jour
                    <?php elseif ($payment_status === 'pending'): ?>
                        <i class="fas fa-clock"></i> Paiement en attente
                    <?php else: ?>
                        <i class="fas fa-exclamation-triangle"></i> Paiement en retard
                    <?php endif; ?>
                </h4>
                <p>
                    <?php if ($payment_status === 'paid'): ?>
                        Votre dernier paiement de <?php echo number_format($my_charges); ?> DH a été effectué le <?php echo date('d/m/Y', strtotime($last_payment_date)); ?>.
                        Prochain paiement prévu le <?php echo date('d/m/Y', strtotime($next_payment_due)); ?>.
                    <?php elseif ($payment_status === 'pending'): ?>
                        Votre paiement de <?php echo number_format($my_charges); ?> DH est attendu avant le <?php echo date('d/m/Y', strtotime($next_payment_due)); ?>.
                    <?php else: ?>
                        Votre paiement de <?php echo number_format($my_charges); ?> DH était dû le <?php echo date('d/m/Y', strtotime($next_payment_due)); ?>. Veuillez régulariser votre situation.
                    <?php endif; ?>
                </p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Charges mensuelles</h3>
                        <div class="stat-number"><?php echo number_format($my_charges); ?> DH</div>
                    </div>
                </div>

                <div class="stat-card <?php echo $payment_status !== 'paid' ? 'pending' : ''; ?>">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Statut paiement</h3>
                        <div class="stat-number">
                            <?php 
                                $status_text = [
                                    'paid' => 'À jour',
                                    'pending' => 'En attente',
                                    'overdue' => 'En retard'
                                ];
                                echo $status_text[$payment_status] ?? 'Inconnu';
                            ?>
                        </div>
                    </div>
                </div>

                <div class="stat-card <?php echo $pending_requests > 0 ? 'pending' : ''; ?>">
                    <div class="stat-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Demandes en cours</h3>
                        <div class="stat-number"><?php echo $pending_requests; ?></div>
                    </div>
                </div>

                <div class="stat-card <?php echo $unread_announcements > 0 ? 'pending' : ''; ?>">
                    <div class="stat-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Nouvelles annonces</h3>
                        <div class="stat-number"><?php echo $unread_announcements; ?></div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="content-section">
                <h2>Actions rapides</h2>
                <div class="quick-actions-grid">
                    <a href="payments.php?action=pay" class="action-card">
                        <i class="fas fa-credit-card"></i>
                        <h3>Payer mes charges</h3>
                        <p>Effectuer le paiement de mes charges mensuelles</p>
                    </a>

                    <a href="maintenance.php?action=new" class="action-card">
                        <i class="fas fa-wrench"></i>
                        <h3>Demande de maintenance</h3>
                        <p>Signaler un problème ou demander une intervention</p>
                    </a>

                    <a href="contact.php" class="action-card">
                        <i class="fas fa-envelope"></i>
                        <h3>Contacter le syndic</h3>
                        <p>Envoyer un message au syndic de l'immeuble</p>
                    </a>

                    <a href="documents.php" class="action-card">
                        <i class="fas fa-download"></i>
                        <h3>Mes documents</h3>
                        <p>Télécharger quittances et documents officiels</p>
                    </a>

                    <a href="neighbors.php" class="action-card">
                        <i class="fas fa-users"></i>
                        <h3>Annuaire des voisins</h3>
                        <p>Consulter les contacts des autres résidents</p>
                    </a>

                    <a href="profile.php" class="action-card">
                        <i class="fas fa-user-edit"></i>
                        <h3>Modifier mon profil</h3>
                        <p>Mettre à jour mes informations personnelles</p>
                    </a>
                </div>
            </div>

            <!-- Syndic Contact Card -->
            <?php if ($syndic_info): ?>
                <div class="syndic-contact-card">
                    <h3><i class="fas fa-user-tie"></i> Contact de votre syndic</h3>
                    <div class="contact-info">
                        <div class="contact-detail">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($syndic_info['syndic_name']); ?></span>
                        </div>
                        <div class="contact-detail">
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:<?php echo htmlspecialchars($syndic_info['syndic_email']); ?>">
                                <?php echo htmlspecialchars($syndic_info['syndic_email']); ?>
                            </a>
                        </div>
                        <?php if ($syndic_info['syndic_phone']): ?>
                            <div class="contact-detail">
                                <i class="fas fa-phone"></i>
                                <a href="tel:<?php echo htmlspecialchars($syndic_info['syndic_phone']); ?>">
                                    <?php echo htmlspecialchars($syndic_info['syndic_phone']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="contact-detail">
                            <i class="fas fa-comments"></i>
                            <a href="contact.php">Envoyer un message</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>

<?php
// =======================
// LOGOUT HANDLER
// File: public/logout.php
// =======================

session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: /syndicplatform/public/login.php');
exit();
?>

<?php
// =======================
// PERMISSION MIDDLEWARE FOR PAGES
// File: includes/page_protection.php
// =======================

/**
 * Include this at the top of any protected page
 * Usage: 
 * require_once __DIR__ . '/../includes/page_protection.php';
 * protectPage('admin'); // or 'syndic' or 'resident'
 */

function protectPage($required_role = null) {
    session_start();
    require_once __DIR__ . '/auth.php';
    
    $auth = new RoleBasedAuth($GLOBALS['conn']);
    
    if ($required_role) {
        $auth->requireRole($required_role);
    } else {
        $auth->checkAuth();
    }
    
    return $auth->getCurrentUser();
}

/**
 * Check if current user can access a specific feature
 */
function canAccess($feature, $current_user_role) {
    $permissions = [
        'admin' => [
            'manage_users', 'manage_subscriptions', 'manage_syndics', 
            'view_reports', 'manage_settings', 'view_purchases'
        ],
        'syndic' => [
            'manage_residents', 'manage_apartments', 'view_payments',
            'manage_maintenance', 'create_announcements', 'view_building_reports'
        ],
        'resident' => [
            'view_payments', 'create_maintenance_request', 'view_announcements',
            'view_documents', 'contact_syndic', 'update_profile'
        ]
    ];
    
    return in_array($feature, $permissions[$current_user_role] ?? []);
}

/**
 * Redirect user to appropriate dashboard based on role
 */
function redirectToDashboard($role) {
    switch ($role) {
        case 'admin':
            header('Location: /syndicplatform/admin/dashboard.php');
            break;
        case 'syndic':
            header('Location: /syndicplatform/syndic/dashboard.php');
            break;
        case 'resident':
            header('Location: /syndicplatform/resident/dashboard.php');
            break;
        default:
            header('Location: /syndicplatform/public/login.php');
    }
    exit();
}
?>
<?php
// =======================
// NAVIGATION COMPONENT
// File: includes/navigation.php
// =======================

function renderNavigation($current_user, $current_page = '') {
    $nav_items = [];
    
    switch ($current_user['role']) {
        case 'admin':
            $nav_items = [
                'dashboard.php' => ['icon' => 'tachometer-alt', 'label' => 'Tableau de bord'],
                'subscriptions.php' => ['icon' => 'tags', 'label' => 'Abonnements'],
                'syndic-accounts.php' => ['icon' => 'building', 'label' => 'Comptes Syndic'],
                'users.php' => ['icon' => 'users', 'label' => 'Utilisateurs'],
                'purchases.php' => ['icon' => 'shopping-cart', 'label' => 'Achats'],
                'reports.php' => ['icon' => 'chart-bar', 'label' => 'Rapports'],
                'settings.php' => ['icon' => 'cog', 'label' => 'Paramètres']
            ];
            break;
            
        case 'syndic':
            $nav_items = [
                'dashboard.php' => ['icon' => 'tachometer-alt', 'label' => 'Tableau de bord'],
                'residents.php' => ['icon' => 'users', 'label' => 'Gestion résidents'],
                'apartments.php' => ['icon' => 'home', 'label' => 'Gestion appartements'],
                'maintenance.php' => ['icon' => 'tools', 'label' => 'Demandes maintenance'],
                'payments.php' => ['icon' => 'money-bill-wave', 'label' => 'Gestion paiements'],
                'announcements.php' => ['icon' => 'bullhorn', 'label' => 'Annonces'],
                'documents.php' => ['icon' => 'file-alt', 'label' => 'Documents'],
                'reports.php' => ['icon' => 'chart-bar', 'label' => 'Rapports']
            ];
            break;
            
        case 'resident':
            $nav_items = [
                'dashboard.php' => ['icon' => 'tachometer-alt', 'label' => 'Tableau de bord'],
                'payments.php' => ['icon' => 'credit-card', 'label' => 'Mes paiements'],
                'maintenance.php' => ['icon' => 'tools', 'label' => 'Demandes maintenance'],
                'announcements.php' => ['icon' => 'bullhorn', 'label' => 'Annonces'],
                'neighbors.php' => ['icon' => 'users', 'label' => 'Voisinage'],
                'documents.php' => ['icon' => 'file-alt', 'label' => 'Documents'],
                'contact.php' => ['icon' => 'envelope', 'label' => 'Contact syndic'],
                'profile.php' => ['icon' => 'user-cog', 'label' => 'Mon profil']
            ];
            break;
    }
    
    echo '<nav class="sidebar-nav"><ul>';
    foreach ($nav_items as $page => $item) {
        $active = ($current_page === $page) ? 'class="active"' : '';
        echo "<li {$active}>";
        echo "<a href=\"{$page}\">";
        echo "<i class=\"fas fa-{$item['icon']}\"></i> {$item['label']}";
        echo "</a>";
        echo "</li>";
    }
    echo '</ul></nav>';
}
?>
<?php
// =======================
// SETUP INSTRUCTIONS
// File: setup_instructions.md
// =======================
/*

INSTALLATION INSTRUCTIONS:

1. Database Setup:
   - Your existing database structure is perfect and doesn't need changes
   - Run the setup.php file you already have to create the database
   - Make sure you have the admin account created

2. File Structure:
   Create this folder structure in your syndicplatform directory:

   syndicplatform/
   ├── includes/
   │   ├── auth.php (Role-based authentication class)
   │   ├── page_protection.php (Page protection functions)
   │   └── navigation.php (Navigation component)
   ├── public/
   │   ├── login.php (Updated login with role redirection)
   │   └── logout.php (Logout handler)
   ├── admin/
   │   ├── dashboard.php (Admin dashboard)
   │   ├── subscriptions.php (Your existing file)
   │   ├── users.php (Your existing file)
   │   ├── purchases.php (Your existing file)
   │   ├── reports.php (Your existing file)
   │   ├── settings.php (Your existing file)
   │   └── syndic-accounts.php (Your existing file)
   ├── syndic/
   │   ├── dashboard.php (Syndic dashboard)
   │   ├── residents.php (Manage residents)
   │   ├── apartments.php (Manage apartments)
   │   ├── maintenance.php (Maintenance requests)
   │   ├── payments.php (Payment management)
   │   ├── announcements.php (Create announcements)
   │   ├── documents.php (Document management)
   │   └── reports.php (Building reports)
   └── resident/
       ├── dashboard.php (Resident dashboard)
       ├── payments.php (View/pay charges)
       ├── maintenance.php (Create maintenance requests)
       ├── announcements.php (View announcements)
       ├── neighbors.php (View neighbors)
       ├── documents.php (View documents)
       ├── contact.php (Contact syndic)
       └── profile.php (Update profile)

3. Update Your Existing Files:
   - Replace your current login.php with the updated version
   - Add the auth.php class to includes/ folder
   - Add page protection to all your existing admin files

4. Add Page Protection to Existing Files:
   Add this to the top of each admin file (after session_start()):
   
   require_once __DIR__ . '/../includes/auth.php';
   $auth = new RoleBasedAuth($conn);
   $auth->requireRole('admin');
   $current_user = $auth->getCurrentUser();

5. Test Accounts:
   Create test accounts in your database:
   
   Admin: admin@syndic.ma / admin123 (already exists)
   Syndic: syndic@test.com / syndic123 (role = 2)
   Resident: resident@test.com / resident123 (role = 1)

SECURITY FEATURES:

1. Role-Based Access Control:
   - Admin: Full system access
   - Syndic: Building management only
   - Resident: Personal account access only

2. Session Management:
   - Secure session handling
   - Automatic role detection
   - Proper logout functionality

3. Permission Checks:
   - Page-level protection
   - Feature-level permissions
   - Automatic redirection based on role

4. Database Security:
   - Uses your existing secure database structure
   - No additional tables needed
   - Maintains data integrity

CUSTOMIZATION:

1. Adding New Pages:
   - Use the page protection function
   - Add navigation items in navigation.php
   - Follow the role-based pattern

2. Adding New Permissions:
   - Update the canAccess() function
   - Define new feature permissions
   - Implement in your pages

3. Styling:
   - Uses your existing CSS files
   - Consistent design across all dashboards
   - Responsive layout

DASHBOARD FEATURES BY ROLE:

Admin Dashboard:
- System overview statistics
- User management
- Subscription management
- Purchase processing
- System reports
- Global settings

Syndic Dashboard:
- Building overview
- Resident management
- Apartment management
- Maintenance request handling
- Payment tracking
- Announcement creation
- Building reports

Resident Dashboard:
- Personal apartment info
- Payment status and history
- Maintenance request creation
- Announcement viewing
- Neighbor directory
- Document access
- Syndic contact

*/

// =======================
// EXAMPLE SYNDIC PAGE: Residents Management
// File: syndic/residents.php
// =======================

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new RoleBasedAuth($conn);
$auth->requireRole('syndic'); // Only syndics can access
$current_user = $auth->getCurrentUser();

// Get residents in this syndic's building
try {
    $stmt = $conn->prepare("
        SELECT m.*, ap.type, ap.floor, ap.number
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        JOIN residence r ON r.id_residence = ap.id_residence
        WHERE r.id_residence = (
            SELECT r2.id_residence 
            FROM apartment ap2 
            JOIN residence r2 ON r2.id_residence = ap2.id_residence 
            WHERE ap2.id_member = ?
        ) AND m.role = 1
        ORDER BY ap.floor, ap.number
    ");
    $stmt->execute([$current_user['id']]);
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $residents = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Résidents - Syndic Way</title>
    <link rel="stylesheet" href="/syndicplatform/css/sections/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <h2><i class="fas fa-building"></i> Espace Syndic</h2>
        </div>
        <div class="nav-user">
            <span><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($current_user['name']); ?></span>
            <a href="/syndicplatform/public/logout.php" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Navigation</h3>
            </div>
            <?php renderNavigation($current_user, 'residents.php'); ?>
        </aside>

        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-users"></i> Gestion des Résidents</h1>
                <p>Gérez les résidents de votre immeuble</p>
                <button class="btn btn-primary" onclick="openAddResident()">
                    <i class="fas fa-user-plus"></i> Ajouter un résident
                </button>
            </div>

            <div class="residents-grid">
                <?php foreach ($residents as $resident): ?>
                    <div class="resident-card">
                        <div class="resident-info">
                            <h3><?php echo htmlspecialchars($resident['full_name']); ?></h3>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($resident['email']); ?></p>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($resident['phone'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="apartment-info">
                            <h4>Appartement <?php echo $resident['number']; ?></h4>
                            <p>Étage <?php echo $resident['floor']; ?> - <?php echo $resident['type']; ?></p>
                        </div>
                        <div class="resident-actions">
                            <button class="btn btn-sm btn-secondary">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                            <button class="btn btn-sm btn-primary">
                                <i class="fas fa-envelope"></i> Contacter
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</body>
</html>

<?php
// =======================
// EXAMPLE RESIDENT PAGE: Payment Management
// File: resident/payments.php
// =======================

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new RoleBasedAuth($conn);
$auth->requireRole('resident'); // Only residents can access
$current_user = $auth->getCurrentUser();

// Mock payment data - you can implement real payment logic
$monthly_charge = 750;
$payment_history = [
    ['month' => 'Nov 2024', 'amount' => 750, 'status' => 'paid', 'date' => '2024-11-05'],
    ['month' => 'Oct 2024', 'amount' => 750, 'status' => 'paid', 'date' => '2024-10-03'],
    ['month' => 'Sep 2024', 'amount' => 750, 'status' => 'paid', 'date' => '2024-09-02']
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Paiements - Syndic Way</title>
    <link rel="stylesheet" href="/syndicplatform/css/sections/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <h2><i class="fas fa-home"></i> Espace Résident</h2>
        </div>
        <div class="nav-user">
            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($current_user['name']); ?></span>
            <a href="/syndicplatform/public/logout.php" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Navigation</h3>
            </div>
            <?php renderNavigation($current_user, 'payments.php'); ?>
        </aside>

        <main class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-credit-card"></i> Mes Paiements</h1>
                <p>Gérez vos paiements de charges</p>
            </div>

            <!-- Current Payment Status -->
            <div class="payment-status-card">
                <h3>Paiement de Décembre 2024</h3>
                <div class="amount"><?php echo number_format($monthly_charge); ?> DH</div>
                <p>Échéance: 31 Décembre 2024</p>
                <button class="btn btn-primary">
                    <i class="fas fa-credit-card"></i> Payer maintenant
                </button>
            </div>

            <!-- Payment History -->
            <div class="payment-history">
                <h3>Historique des paiements</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mois</th>
                            <th>Montant</th>
                            <th>Date de paiement</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_history as $payment): ?>
                            <tr>
                                <td><?php echo $payment['month']; ?></td>
                                <td><?php echo number_format($payment['amount']); ?> DH</td>
                                <td><?php echo date('d/m/Y', strtotime($payment['date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $payment['status']; ?>">
                                        <?php echo $payment['status'] === 'paid' ? 'Payé' : 'En attente'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-secondary">
                                        <i class="fas fa-download"></i> Reçu
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>

<?php
// =======================
// SAMPLE PROTECTED PAGE TEMPLATE
// File: template_protected_page.php
// =======================

/*
Use this template for creating new protected pages:

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new RoleBasedAuth($conn);
$auth->requireRole('REQUIRED_ROLE'); // admin, syndic, or resident
$current_user = $auth->getCurrentUser();

// Your page logic here...

// HTML with navigation:
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Page Title - Syndic Way</title>
    <link rel="stylesheet" href="/syndicplatform/css/sections/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <h2><i class="fas fa-icon"></i> Space Name</h2>
        </div>
        <div class="nav-user">
            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($current_user['name']); ?></span>
            <a href="/syndicplatform/public/logout.php" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </nav>

    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Navigation</h3>
            </div>
            <?php renderNavigation($current_user, 'current_page.php'); ?>
        </aside>

        <main class="main-content">
            <!-- Your page content here -->
        </main>
    </div>
</body>
</html>
*/
?>
<?php
// =======================
// ADDITIONAL SECURITY FUNCTIONS
// File: includes/security.php
// =======================

/**
 * Additional security functions for the system
 */

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[0-9\s\-\+\(\)]+$/', $phone);
}

function generateSecureToken() {
    return bin2hex(random_bytes(32));
}

function logSecurityEvent($event, $user_id = null, $details = '') {
    $log_entry = date('Y-m-d H:i:s') . " - {$event}";
    if ($user_id) {
        $log_entry .= " - User ID: {$user_id}";
    }
    if ($details) {
        $log_entry .= " - Details: {$details}";
    }
    error_log($log_entry, 3, __DIR__ . '/../logs/security.log');
}

function checkBruteForce($email, $conn) {
    // Check if too many failed attempts in last 15 minutes
    $stmt = $conn->prepare("
        SELECT COUNT(*) as attempts 
        FROM login_attempts 
        WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$email]);
    $attempts = $stmt->fetch()['attempts'] ?? 0;
    
    return $attempts >= 5; // Block after 5 attempts
}

function recordLoginAttempt($email, $success, $conn) {
    // Record the login attempt (you'd need to create this table)
    $stmt = $conn->prepare("
        INSERT INTO login_attempts (email, success, attempt_time, ip_address) 
        VALUES (?, ?, NOW(), ?)
    ");
    $stmt->execute([$email, $success ? 1 : 0, $_SERVER['REMOTE_ADDR']]);
}
?>
<?php
// =======================
// DATABASE HELPER FUNCTIONS
// File: includes/database_helpers.php
// =======================

/**
 * Helper functions for database operations
 */

function getUsersByRole($conn, $role) {
    if ($role === 'admin') {
        $stmt = $conn->prepare("SELECT * FROM admin ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $role_id = ($role === 'syndic') ? 2 : 1;
        $stmt = $conn->prepare("SELECT * FROM member WHERE role = ? ORDER BY full_name");
        $stmt->execute([$role_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

function getBuildingInfo($conn, $user_id, $user_role) {
    if ($user_role !== 'syndic' && $user_role !== 'resident') {
        return null;
    }
    
    $stmt = $conn->prepare("
        SELECT r.*, c.city_name
        FROM residence r
        JOIN city c ON c.id_city = r.id_city
        JOIN apartment ap ON ap.id_residence = r.id_residence
        WHERE ap.id_member = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getResidentsInBuilding($conn, $building_id) {
    $stmt = $conn->prepare("
        SELECT m.*, ap.type, ap.floor, ap.number
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        WHERE ap.id_residence = ? AND m.role = 1
        ORDER BY ap.floor, ap.number
    ");
    $stmt->execute([$building_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSyndicForBuilding($conn, $building_id) {
    $stmt = $conn->prepare("
        SELECT m.*
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        WHERE ap.id_residence = ? AND m.role = 2
        LIMIT 1
    ");
    $stmt->execute([$building_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get dashboard statistics based on user role
 */
function getDashboardStats($conn, $user_id, $user_role) {
    $stats = [];
    
    switch ($user_role) {
        case 'admin':
            $stats = [
                'total_syndics' => getCount($conn, "SELECT COUNT(*) FROM member WHERE role = 2"),
                'total_residents' => getCount($conn, "SELECT COUNT(*) FROM member WHERE role = 1"),
                'total_subscriptions' => getCount($conn, "SELECT COUNT(*) FROM subscription WHERE is_active = 1"),
                'pending_purchases' => getCount($conn, "SELECT COUNT(*) FROM member WHERE status = 'pending'")
            ];
            break;
            
        case 'syndic':
            $building_id = getBuildingIdForUser($conn, $user_id);
            $stats = [
                'total_residents' => getCount($conn, "SELECT COUNT(*) FROM member m JOIN apartment ap ON ap.id_member = m.id_member WHERE ap.id_residence = ? AND m.role = 1", [$building_id]),
                'total_apartments' => getCount($conn, "SELECT COUNT(*) FROM apartment WHERE id_residence = ?", [$building_id]),
                'pending_maintenance' => 3, // Mock data
                'monthly_revenue' => 0 // Calculate based on residents
            ];
            break;
            
        case 'resident':
            $stats = [
                'monthly_charges' => 750, // Mock data
                'payment_status' => 'paid',
                'pending_requests' => 1,
                'unread_announcements' => 2
            ];
            break;
    }
    
    return $stats;
}

function getCount($conn, $query, $params = []) {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function getBuildingIdForUser($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id_residence FROM apartment WHERE id_member = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

?>

<!-- 
=======================
SETUP SUMMARY
=======================

Your role-based dashboard system is now complete! Here's what you have:

✅ SECURITY FEATURES:
- Role-based authentication (Admin, Syndic, Resident)
- Page-level protection
- Secure session management
- Automatic role detection and redirection

✅ DASHBOARD FEATURES:
- Admin: System management, user control, reports
- Syndic: Building management, resident control
- Resident: Personal account, payments, maintenance

✅ DATABASE INTEGRATION:
- Uses your existing database structure
- No changes needed to your current tables
- Maintains all existing data

✅ FILE ORGANIZATION:
- Clean separation by role
- Reusable components
- Easy to maintain and extend

✅ USER EXPERIENCE:
- Role-appropriate navigation
- Intuitive dashboards
- Responsive design

TO IMPLEMENT:
1. Create the folder structure as shown
2. Add the auth.php class to includes/
3. Update your login.php file
4. Add page protection to existing admin files
5. Create test accounts for each role
6. Test the login flow

The system automatically redirects users to their appropriate dashboard based on their role, and prevents unauthorized access to restricted areas.
-->