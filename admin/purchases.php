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
        if ($action === 'process_purchase') {
            $member_id = intval($_POST['member_id']);
            
            // Update member status to active
            $stmt = $conn->prepare("UPDATE member SET status = 'active' WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $_SESSION['success'] = "Achat traité avec succès. Le compte syndic est maintenant actif.";
            
        } elseif ($action === 'cancel_purchase') {
            $member_id = intval($_POST['member_id']);
            
            $conn->beginTransaction();
            
            // Delete the purchase and related records
            $stmt = $conn->prepare("DELETE FROM admin_member_subscription WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $stmt = $conn->prepare("DELETE FROM admin_member_link WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $stmt = $conn->prepare("DELETE FROM apartment WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $stmt = $conn->prepare("DELETE FROM member WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $conn->commit();
            $_SESSION['success'] = "Achat annulé et données supprimées avec succès.";
            
        } elseif ($action === 'refund_purchase') {
            $member_id = intval($_POST['member_id']);
            
            // Mark as refunded but keep the record
            $stmt = $conn->prepare("UPDATE member SET status = 'refunded' WHERE id_member = ?");
            $stmt->execute([$member_id]);
            
            $_SESSION['success'] = "Remboursement traité. Le statut a été mis à jour.";
        }
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header('Location: purchases.php');
    exit();
}

// Get filters - only apply if form was submitted
$search = '';
$status_filter = '';
$date_filter = '';
$subscription_filter = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET)) {
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $date_filter = $_GET['date'] ?? '';
    $subscription_filter = $_GET['subscription'] ?? '';
}

// Get all purchases with detailed information
try {
    $query = "
        SELECT 
            m.id_member,
            m.full_name AS client_name,
            m.email AS client_email,
            m.phone AS client_phone,
            m.status AS purchase_status,
            m.date_created AS registration_date,
            s.name_subscription AS subscription_name,
            s.price_subscription AS subscription_price,
            ams.amount AS amount_paid,
            ams.date_payment AS payment_date,
            r.name AS company_name,
            r.address AS company_address,
            c.city_name AS company_city,
            CASE 
                WHEN m.status = 'pending' THEN 'En attente'
                WHEN m.status = 'active' THEN 'Actif'
                WHEN m.status = 'inactive' THEN 'Inactif'
                WHEN m.status = 'refunded' THEN 'Remboursé'
                ELSE m.status
            END AS status_text,
            DATEDIFF(NOW(), ams.date_payment) AS days_since_purchase
        FROM admin_member_subscription ams
        JOIN member m ON ams.id_member = m.id_member
        JOIN subscription s ON ams.id_subscription = s.id_subscription
        LEFT JOIN apartment ap ON ap.id_member = m.id_member
        LEFT JOIN residence r ON r.id_residence = ap.id_residence
        LEFT JOIN city c ON c.id_city = r.id_city
        WHERE m.role = 2
    ";
    
    $params = [];
    $where_conditions = [];
    
    if ($search) {
        $where_conditions[] = "(m.full_name LIKE ? OR m.email LIKE ? OR r.name LIKE ? OR c.city_name LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    if ($status_filter) {
        $where_conditions[] = "m.status = ?";
        $params[] = $status_filter;
    }
    
    if ($subscription_filter) {
        $where_conditions[] = "s.id_subscription = ?";
        $params[] = $subscription_filter;
    }
    
    if ($date_filter) {
        switch ($date_filter) {
            case 'today':
                $where_conditions[] = "DATE(ams.date_payment) = CURDATE()";
                break;
            case 'week':
                $where_conditions[] = "WEEK(ams.date_payment) = WEEK(CURDATE()) AND YEAR(ams.date_payment) = YEAR(CURDATE())";
                break;
            case 'month':
                $where_conditions[] = "MONTH(ams.date_payment) = MONTH(CURDATE()) AND YEAR(ams.date_payment) = YEAR(CURDATE())";
                break;
            case 'year':
                $where_conditions[] = "YEAR(ams.date_payment) = YEAR(CURDATE())";
                break;
        }
    }
    
    if ($where_conditions) {
        $query .= " AND " . implode(" AND ", $where_conditions);
    }
    
    $query .= " ORDER BY ams.date_payment DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get subscription options for filter
    $stmt = $conn->prepare("SELECT * FROM subscription ORDER BY name_subscription ASC");
    $stmt->execute();
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $total_purchases = count($purchases);
    $pending_count = 0;
    $active_count = 0;
    $total_revenue = 0;
    $monthly_revenue = 0;
    
    foreach ($purchases as $purchase) {
        if ($purchase['purchase_status'] === 'pending') $pending_count++;
        if ($purchase['purchase_status'] === 'active') $active_count++;
        $total_revenue += $purchase['amount_paid'];
        
        if (date('Y-m', strtotime($purchase['payment_date'])) === date('Y-m')) {
            $monthly_revenue += $purchase['amount_paid'];
        }
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors du chargement des achats.";
    $purchases = [];
    $subscriptions = [];
    $total_purchases = 0;
    $pending_count = 0;
    $active_count = 0;
    $total_revenue = 0;
    $monthly_revenue = 0;
}

$page_title = "Gestion des Achats - Syndic Way";
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/dashboard.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/purchases.css">
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
                    <li class="active">
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
                    <h1><i class="fas fa-shopping-cart"></i> Gestion des Achats</h1>
                    <p>Gérez tous les achats d'abonnements et les paiements</p>
                </div>
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

            <!-- Statistics Overview -->
            <div class="stats-overview">
                <div class="stat-card total-stat">
                    <i class="fas fa-shopping-cart"></i>
                    <div class="stat-number"><?php echo $total_purchases; ?></div>
                    <div class="stat-label">Total Achats</div>
                </div>
                <div class="stat-card pending-stat">
                    <i class="fas fa-clock"></i>
                    <div class="stat-number"><?php echo $pending_count; ?></div>
                    <div class="stat-label">En Attente</div>
                </div>
                <div class="stat-card active-stat">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-number"><?php echo $active_count; ?></div>
                    <div class="stat-label">Traités</div>
                </div>
                <div class="stat-card revenue-stat">
                    <i class="fas fa-coins"></i>
                    <div class="stat-number"><?php echo number_format($monthly_revenue, 0); ?> DH</div>
                    <div class="stat-label">Revenus du Mois</div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" id="filtersForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Rechercher</label>
                            <input type="text" name="search" id="search" 
                                   placeholder="Client, email, entreprise, ville..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Statut</label>
                            <select name="status" id="status">
                                <option value="">Tous</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                                <option value="refunded" <?php echo $status_filter === 'refunded' ? 'selected' : ''; ?>>Remboursé</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="date">Période</label>
                            <select name="date" id="date">
                                <option value="">Toutes</option>
                                <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                                <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Cette semaine</option>
                                <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Ce mois</option>
                                <option value="year" <?php echo $date_filter === 'year' ? 'selected' : ''; ?>>Cette année</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="subscription">Forfait</label>
                            <select name="subscription" id="subscription">
                                <option value="">Tous</option>
                                <?php foreach ($subscriptions as $sub): ?>
                                    <option value="<?php echo $sub['id_subscription']; ?>" 
                                            <?php echo $subscription_filter == $sub['id_subscription'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sub['name_subscription']); ?>
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

            <!-- Purchases Table -->
            <div class="purchases-table">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-list"></i>
                        Liste des achats (<?php echo count($purchases); ?>)
                    </h3>
                </div>

                <?php if (!empty($purchases)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Entreprise</th>
                                <th>Forfait</th>
                                <th>Montant</th>
                                <th>Date Achat</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $purchase): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($purchase['client_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($purchase['client_email']); ?></small><br>
                                        <?php if ($purchase['client_phone']): ?>
                                            <small><?php echo htmlspecialchars($purchase['client_phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($purchase['company_name'] ?? 'Non définie'); ?></strong><br>
                                        <small><?php echo htmlspecialchars($purchase['company_city'] ?? 'Ville non définie'); ?></small>
                                        <?php if ($purchase['days_since_purchase'] <= 7 && $purchase['purchase_status'] === 'pending'): ?>
                                            <br><span class="days-badge priority-high">Nouveau</span>
                                        <?php else: ?>
                                            <br><span class="days-badge">Il y a <?php echo $purchase['days_since_purchase']; ?> jours</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($purchase['subscription_name']); ?></strong><br>
                                        <small><?php echo number_format($purchase['subscription_price'], 2); ?> DH/mois</small>
                                    </td>
                                    <td>
                                        <span class="amount-highlight"><?php echo number_format($purchase['amount_paid'], 2); ?> DH</span>
                                    </td>
                                    <td>
                                        <?php echo date('j M Y', strtotime($purchase['payment_date'])); ?><br>
                                        <small><?php echo date('H:i', strtotime($purchase['payment_date'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $purchase['purchase_status']; ?>">
                                            <?php echo $purchase['status_text']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($purchase['purchase_status'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="processPurchase(<?php echo $purchase['id_member']; ?>, '<?php echo htmlspecialchars($purchase['client_name']); ?>')">
                                                    <i class="fas fa-check"></i> Traiter
                                                </button>
                                                <button class="btn btn-sm btn-danger" 
                                                        onclick="cancelPurchase(<?php echo $purchase['id_member']; ?>, '<?php echo htmlspecialchars($purchase['client_name']); ?>')">
                                                    <i class="fas fa-times"></i> Annuler
                                                </button>
                                            <?php elseif ($purchase['purchase_status'] === 'active'): ?>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="refundPurchase(<?php echo $purchase['id_member']; ?>, '<?php echo htmlspecialchars($purchase['client_name']); ?>')">
                                                    <i class="fas fa-undo"></i> Rembourser
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-sm btn-secondary" 
                                                    onclick="viewPurchaseDetails(<?php echo htmlspecialchars(json_encode($purchase)); ?>)">
                                                <i class="fas fa-eye"></i> Détails
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state" style="padding: 3rem; text-align: center;">
                        <i class="fas fa-shopping-cart" style="font-size: 3rem; color: var(--color-grey); margin-bottom: 1rem;"></i>
                        <h3>Aucun achat trouvé</h3>
                        <p>Aucun achat ne correspond aux critères de recherche.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Purchase Details Modal -->
    <div id="purchaseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-receipt"></i>
                    Détails de l'achat
               </h2>
               <span class="close" onclick="closeModal()">&times;</span>
           </div>
           
           <div class="modal-body">
               <div id="purchaseDetailsContent">
                   <!-- Content will be populated by JavaScript -->
               </div>
           </div>
       </div>
   </div>

   <!-- Hidden forms for actions -->
   <form id="processForm" method="POST" style="display: none;">
       <input type="hidden" name="action" value="process_purchase">
       <input type="hidden" name="member_id" id="processMemberId">
   </form>

   <form id="cancelForm" method="POST" style="display: none;">
       <input type="hidden" name="action" value="cancel_purchase">
       <input type="hidden" name="member_id" id="cancelMemberId">
   </form>

   <form id="refundForm" method="POST" style="display: none;">
       <input type="hidden" name="action" value="refund_purchase">
       <input type="hidden" name="member_id" id="refundMemberId">
   </form>

   <script>
       // Auto-refresh every 5 minutes for pending purchases
       setInterval(function() {
           const pendingCount = <?php echo $pending_count; ?>;
           if (pendingCount > 0) {
               // Optional: Show a notification about auto-refresh
               console.log('Auto-refresh: Checking for new purchases...');
               // You could implement AJAX refresh here instead of full page reload
           }
       }, 300000); // 5 minutes
   </script>

   <script src="http://localhost/syndicplatform/js/admin/purchases.js"></script>
</body>
</html>