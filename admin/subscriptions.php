<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
$current_user = getCurrentUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $name = trim($_POST['name_subscription']);
            $price = floatval($_POST['price_subscription']);
            $description = trim($_POST['description']);
            $duration = intval($_POST['duration_months']);
            $max_residents = intval($_POST['max_residents']);
            $max_apartments = intval($_POST['max_apartments']);

            // Validation
            if (empty($name) || $price <= 0 || $duration <= 0 || $max_residents <= 0 || $max_apartments <= 0) {
                throw new Exception("Tous les champs obligatoires doivent être remplis avec des valeurs valides.");
            }

            $stmt = $conn->prepare("
                INSERT INTO subscription 
                (name_subscription, price_subscription, description, duration_months, max_residents, max_apartments, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$name, $price, $description, $duration, $max_residents, $max_apartments]);
            
            $_SESSION['success'] = "Nouvel abonnement créé avec succès.";
            
        } elseif ($action === 'update') {
            $id = intval($_POST['id_subscription']);
            $name = trim($_POST['name_subscription']);
            $price = floatval($_POST['price_subscription']);
            $description = trim($_POST['description']);
            $duration = intval($_POST['duration_months']);
            $max_residents = intval($_POST['max_residents']);
            $max_apartments = intval($_POST['max_apartments']);

            // Validation
            if (empty($name) || $price <= 0 || $duration <= 0 || $max_residents <= 0 || $max_apartments <= 0) {
                throw new Exception("Tous les champs obligatoires doivent être remplis avec des valeurs valides.");
            }

            $stmt = $conn->prepare("
                UPDATE subscription 
                SET name_subscription = ?, price_subscription = ?, description = ?, 
                    duration_months = ?, max_residents = ?, max_apartments = ?
                WHERE id_subscription = ?
            ");
            $stmt->execute([$name, $price, $description, $duration, $max_residents, $max_apartments, $id]);
            
            $_SESSION['success'] = "Abonnement mis à jour avec succès.";
            
        } elseif ($action === 'delete') {
            $id = intval($_POST['id_subscription']);
            
            // Check if subscription has active users
            $stmt = $conn->prepare("SELECT COUNT(*) FROM admin_member_subscription WHERE id_subscription = ?");
            $stmt->execute([$id]);
            $subscriber_count = $stmt->fetchColumn();
            
            if ($subscriber_count > 0) {
                $_SESSION['error'] = "Impossible de supprimer cet abonnement. Il y a {$subscriber_count} abonnés actifs.";
            } else {
                $stmt = $conn->prepare("DELETE FROM subscription WHERE id_subscription = ?");
                $stmt->execute([$id]);
            }
            
        } elseif ($action === 'toggle_status') {
            $id = intval($_POST['id_subscription']);
            $new_status = intval($_POST['new_status']);
            
            $stmt = $conn->prepare("UPDATE subscription SET is_active = ? WHERE id_subscription = ?");
            $stmt->execute([$new_status, $id]);
            
            $status_text = $new_status ? 'activé' : 'désactivé';
            $_SESSION['success'] = "Abonnement {$status_text} avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header('Location: subscriptions.php');
    exit();
}

// Get subscription statistics
$subscription_stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'total_subscribers' => 0
];

try {
    // Total subscriptions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM subscription");
    $stmt->execute();
    $subscription_stats['total'] = $stmt->fetch()['count'];
    
    // Active subscriptions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM subscription WHERE is_active = 1");
    $stmt->execute();
    $subscription_stats['active'] = $stmt->fetch()['count'];
    
    // Inactive subscriptions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM subscription WHERE is_active = 0");
    $stmt->execute();
    $subscription_stats['inactive'] = $stmt->fetch()['count'];
    
    // Total subscribers
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_member_subscription");
    $stmt->execute();
    $subscription_stats['total_subscribers'] = $stmt->fetch()['count'];

    // Get all subscriptions with subscriber count
    $stmt = $conn->prepare("
        SELECT s.*, 
               COUNT(ams.id_subscription) as total_subscribers
        FROM subscription s
        LEFT JOIN admin_member_subscription ams ON s.id_subscription = ams.id_subscription
        GROUP BY s.id_subscription
        ORDER BY s.price_subscription ASC
    ");
    $stmt->execute();
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors du chargement des abonnements.";
    $subscriptions = [];
}

$page_title = "Gestion des Abonnements - Syndic Way";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/dashboard.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/subscriptions.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div>
                    <h1><i class="fas fa-tags"></i> Gestion des Abonnements</h1>
                    <p>Créez et gérez vos forfaits d'abonnement avec leurs fonctionnalités</p>
                </div>
                <button class="btn btn-primary" onclick="openModal('create')">
                    <i class="fas fa-plus"></i> Nouveau forfait
                </button>
            </div>

           

            <!-- Stats Cards -->
            <div class="stats-grid" style="margin-bottom: 2rem;">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Forfaits</h3>
                        <div class="stat-number"><?php echo $subscription_stats['total']; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Forfaits Actifs</h3>
                        <div class="stat-number"><?php echo $subscription_stats['active']; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Forfaits Inactifs</h3>
                        <div class="stat-number"><?php echo $subscription_stats['inactive']; ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Abonnés</h3>
                        <div class="stat-number"><?php echo $subscription_stats['total_subscribers']; ?></div>
                    </div>
                </div>
            </div>

            <!-- Subscriptions Grid -->
            <div class="subscriptions-grid">
                <?php foreach ($subscriptions as $subscription): ?>
                    <div class="subscription-card <?php echo !$subscription['is_active'] ? 'inactive' : ''; ?>" 
                         data-subscription-id="<?php echo $subscription['id_subscription']; ?>">
                        <div class="card-header">
                            <h3><?php echo htmlspecialchars($subscription['name_subscription']); ?></h3>
                            <div class="card-status">
                                <span class="status-badge status-<?php echo $subscription['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $subscription['is_active'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="card-price">
                            <div>
                                <span class="price-amount"><?php echo number_format($subscription['price_subscription'], 0); ?></span>
                                <span class="price-currency">DH</span>
                                <span class="price-period">/mois</span>
                            </div>
                        </div>

                        <div class="card-description">
                            <p><?php echo htmlspecialchars($subscription['description'] ?: 'Aucune description'); ?></p>
                        </div>

                        <div class="card-features">
                            <ul>
                                <li>
                                    <i class="fas fa-users"></i> 
                                    Jusqu'à <?php echo number_format($subscription['max_residents']); ?> résidents
                                </li>
                                <li>
                                    <i class="fas fa-building"></i> 
                                    Jusqu'à <?php echo number_format($subscription['max_apartments']); ?> appartements
                                </li>
                                <li>
                                    <i class="fas fa-calendar"></i> 
                                    Durée: <?php echo $subscription['duration_months']; ?> mois
                                </li>
                                <li>
                                    <i class="fas fa-user-check"></i> 
                                    <?php echo $subscription['total_subscribers']; ?> abonnés
                                </li>
                            </ul>
                        </div>

                        <div class="card-actions">
                            <button class="btn btn-secondary" onclick="editSubscription(<?php echo htmlspecialchars(json_encode($subscription)); ?>)">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                            
                            <button class="btn <?php echo $subscription['is_active'] ? 'btn-warning' : 'btn-success'; ?>" 
                                    onclick="toggleStatus(<?php echo $subscription['id_subscription']; ?>, <?php echo $subscription['is_active'] ? 0 : 1; ?>)">
                                <i class="fas fa-<?php echo $subscription['is_active'] ? 'pause' : 'play'; ?>"></i>
                                <?php echo $subscription['is_active'] ? 'Désactiver' : 'Activer'; ?>
                            </button>
                            
                            <button class="btn btn-danger" onclick="confirmDelete(<?php echo $subscription['id_subscription']; ?>, '<?php echo htmlspecialchars($subscription['name_subscription']); ?>', <?php echo $subscription['total_subscribers']; ?>)">
                                <i class="fas fa-trash"></i> Supprimer
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($subscriptions)): ?>
                <div class="empty-state" style="padding: 3rem; text-align: center;">
                    <i class="fas fa-tags" style="font-size: 3rem; color: var(--color-grey); margin-bottom: 1rem;"></i>
                    <h3>Aucun abonnement trouvé</h3>
                    <p>Commencez par créer votre premier forfait d'abonnement.</p>
                    <button class="btn btn-primary" onclick="openModal('create')">
                        <i class="fas fa-plus"></i> Créer un forfait
                    </button>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modal for Create/Edit Subscription -->
    <div id="subscriptionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">
                    <i id="modalIcon" class="fas fa-plus"></i>
                    <span id="modalTitleText">Nouveau forfait</span>
                </h2>
                <span class="close" onclick="closeModal('subscriptionModal')">&times;</span>
            </div>
            
            <form id="subscriptionForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id_subscription" id="subscriptionId">
                
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-info-circle"></i>
                        Informations générales
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name_subscription">
                                Nom du forfait <span class="required">*</span>
                            </label>
                            <input type="text" name="name_subscription" id="name_subscription" 
                                   placeholder="Ex: Forfait Premium" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="price_subscription">
                                Prix mensuel (DH) <span class="required">*</span>
                            </label>
                            <input type="number" name="price_subscription" id="price_subscription" 
                                   step="0.01" min="0.01" placeholder="299.00" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">
                            Description du forfait
                        </label>
                        <textarea name="description" id="description" rows="3" 
                                  placeholder="Décrivez les avantages et fonctionnalités de ce forfait..."></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-cogs"></i>
                        Configuration des limites
                    </div>
                    
                    <div class="form-row">
                       <div class="form-group">
                           <label for="duration_months">
                               Durée (mois) <span class="required">*</span>
                           </label>
                           <input type="number" name="duration_months" id="duration_months" 
                                  value="12" min="1" max="60" required>
                       </div>
                       
                       <div class="form-group">
                           <label for="max_residents">
                               Max résidents <span class="required">*</span>
                           </label>
                           <input type="number" name="max_residents" id="max_residents" 
                                  min="1" max="10000" placeholder="100" required>
                       </div>
                   </div>

                   <div class="form-group">
                       <label for="max_apartments">
                           Max appartements <span class="required">*</span>
                       </label>
                       <input type="number" name="max_apartments" id="max_apartments" 
                              min="1" max="1000" placeholder="50" required>
                   </div>
               </div>

               <div class="modal-actions">
                   <button type="button" class="btn btn-secondary" onclick="closeModal('subscriptionModal')">
                       <i class="fas fa-times"></i> Annuler
                   </button>
                   <button type="submit" class="btn btn-primary" id="submitBtn">
                       <i class="fas fa-save"></i> Enregistrer
                   </button>
               </div>
           </form>
       </div>
   </div>

   <!-- Delete Confirmation Modal -->
   <div id="deleteModal" class="modal delete-modal">
       <div class="modal-content">
           <div class="modal-header">
               <h2>
                   <i class="fas fa-exclamation-triangle"></i>
                   Confirmer la suppression
               </h2>
               <span class="close" onclick="closeModal('deleteModal')">&times;</span>
           </div>
           
           <div class="delete-warning">
               <i class="fas fa-trash-alt"></i>
               <h3 id="deleteTitle">Supprimer le forfait</h3>
               <p id="deleteMessage">Cette action est irréversible.</p>
           </div>

           <form id="deleteForm" method="POST">
               <input type="hidden" name="action" value="delete">
               <input type="hidden" name="id_subscription" id="deleteSubscriptionId">
               
               <div class="delete-actions">
                   <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">
                       <i class="fas fa-times"></i> Annuler
                   </button>
                   <button type="submit" class="btn btn-danger" id="confirmDeleteBtn">
                       <i class="fas fa-trash"></i> Supprimer définitivement
                   </button>
               </div>
           </form>
       </div>
   </div>

   <!-- Hidden forms for actions -->
   <form id="toggleForm" method="POST" style="display: none;">
       <input type="hidden" name="action" value="toggle_status">
       <input type="hidden" name="id_subscription" id="toggleSubscriptionId">
       <input type="hidden" name="new_status" id="toggleNewStatus">
   </form>

   <script src="http://localhost/syndicplatform/js/admin/subscriptions.js"></script>
</body>
</html>