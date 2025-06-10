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
    
    <style>
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--color-white);
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--color-yellow), var(--primary-color));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-card.total-stat i {
            color: var(--primary-color);
        }

        .stat-card.pending-stat i {
            color: var(--color-yellow);
        }

        .stat-card.active-stat i {
            color: var(--color-green);
        }

        .stat-card.revenue-stat i {
            color: var(--color-dark-grey);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--color-dark-grey);
        }

        .stat-label {
            color: var(--color-grey);
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .filters-section {
            background: var(--color-white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 1.5rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            color: var(--color-dark-grey);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 1rem;
            border: 2px solid var(--color-light-grey);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--color-yellow);
            box-shadow: 0 0 0 4px rgba(244, 185, 66, 0.15);
        }

        .purchases-table {
            background: var(--color-white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .table-header {
            background: linear-gradient(135deg, var(--color-yellow), var(--primary-color));
            color: var(--color-white);
            padding: 1.5rem;
        }

        .table-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 1.2rem;
            text-align: left;
            border-bottom: 1px solid var(--color-light-grey);
        }

        .data-table th {
            background: var(--color-light-grey);
            font-weight: 700;
            color: var(--color-dark-grey);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table tr:hover {
            background: rgba(244, 185, 66, 0.05);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-pending {
            background: var(--color-yellow);
            color: var(--color-white);
        }

        .status-active {
            background: var(--color-green);
            color: var(--color-white);
        }

        .status-inactive {
            background: var(--color-grey);
            color: var(--color-white);
        }

        .status-refunded {
            background: var(--primary-color);
            color: var(--color-white);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            border-radius: 6px;
            min-width: 90px;
        }

        .purchase-details {
            background: var(--color-light-grey);
            padding: 1rem;
            border-radius: 10px;
            margin: 1rem 0;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .detail-row:last-child {
            margin-bottom: 0;
        }

        .amount-highlight {
            font-weight: 700;
            color: var(--color-green);
            font-size: 1.1rem;
        }

        .days-badge {
            background: var(--color-light-grey);
            color: var(--color-dark-grey);
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .priority-high {
            background: var(--primary-color);
            color: var(--color-white);
        }

        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        /* Enhanced modal for purchase details */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
        }

        .modal.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background-color: var(--color-white);
            margin: 2% auto;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
            animation: slideInUp 0.3s ease;
            max-height: 95vh;
            overflow-y: auto;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--color-yellow), var(--primary-color));
            color: var(--color-white);
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .close {
            color: var(--color-white);
            font-size: 2rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close:hover {
            background-color: rgba(255,255,255,0.2);
        }

        .modal-body {
            padding: 2rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInUp {
            from { 
                opacity: 0;
                transform: translateY(50px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
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
       function processPurchase(memberId, clientName) {
           if (confirm(`Êtes-vous sûr de vouloir traiter l'achat de "${clientName}" ?\n\nCela activera le compte syndic et permettra l'accès à la plateforme.`)) {
               document.getElementById('processMemberId').value = memberId;
               document.getElementById('processForm').submit();
           }
       }

       function cancelPurchase(memberId, clientName) {
           if (confirm(`Êtes-vous sûr de vouloir annuler l'achat de "${clientName}" ?\n\nCette action supprimera définitivement toutes les données associées.`)) {
               document.getElementById('cancelMemberId').value = memberId;
               document.getElementById('cancelForm').submit();
           }
       }

       function refundPurchase(memberId, clientName) {
           if (confirm(`Êtes-vous sûr de vouloir rembourser l'achat de "${clientName}" ?\n\nLe statut sera marqué comme remboursé.`)) {
               document.getElementById('refundMemberId').value = memberId;
               document.getElementById('refundForm').submit();
           }
       }

       function viewPurchaseDetails(purchase) {
           const content = document.getElementById('purchaseDetailsContent');
           
           content.innerHTML = `
               <div class="purchase-details">
                   <h4><i class="fas fa-user"></i> Informations Client</h4>
                   <div class="detail-row">
                       <span>Nom complet:</span>
                       <strong>${purchase.client_name}</strong>
                   </div>
                   <div class="detail-row">
                       <span>Email:</span>
                       <strong>${purchase.client_email}</strong>
                   </div>
                   <div class="detail-row">
                       <span>Téléphone:</span>
                       <strong>${purchase.client_phone || 'Non renseigné'}</strong>
                   </div>
               </div>

               <div class="purchase-details">
                   <h4><i class="fas fa-building"></i> Informations Entreprise</h4>
                   <div class="detail-row">
                       <span>Nom de l'entreprise:</span>
                       <strong>${purchase.company_name || 'Non définie'}</strong>
                   </div>
                   <div class="detail-row">
                       <span>Ville:</span>
                       <strong>${purchase.company_city || 'Non définie'}</strong>
                   </div>
                   <div class="detail-row">
                       <span>Adresse:</span>
                       <strong>${purchase.company_address || 'Non renseignée'}</strong>
                   </div>
               </div>

               <div class="purchase-details">
                   <h4><i class="fas fa-credit-card"></i> Détails de l'Achat</h4>
                   <div class="detail-row">
                       <span>Forfait:</span>
                       <strong>${purchase.subscription_name}</strong>
                   </div>
                   <div class="detail-row">
                       <span>Prix du forfait:</span>
                       <strong>${parseFloat(purchase.subscription_price).toFixed(2)} DH/mois</strong>
                   </div>
                   <div class="detail-row">
                       <span>Montant payé:</span>
                       <strong class="amount-highlight">${parseFloat(purchase.amount_paid).toFixed(2)} DH</strong>
                   </div>
                   <div class="detail-row">
                       <span>Date de paiement:</span>
                       <strong>${new Date(purchase.payment_date).toLocaleString('fr-FR')}</strong>
                   </div>
                   <div class="detail-row">
                       <span>Statut actuel:</span>
                       <span class="status-badge status-${purchase.purchase_status}">${purchase.status_text}</span>
                   </div>
                   <div class="detail-row">
                       <span>Il y a:</span>
                       <strong>${purchase.days_since_purchase} jours</strong>
                   </div>
               </div>

               <div class="purchase-details">
                   <h4><i class="fas fa-calendar"></i> Historique</h4>
                   <div class="detail-row">
                       <span>Date d'inscription:</span>
                       <strong>${new Date(purchase.registration_date).toLocaleString('fr-FR')}</strong>
                   </div>
                   <div class="detail-row">
                       <span>Date de paiement:</span>
                       <strong>${new Date(purchase.payment_date).toLocaleString('fr-FR')}</strong>
                   </div>
               </div>
           `;
           
           document.getElementById('purchaseModal').classList.add('show');
       }

       function closeModal() {
           document.getElementById('purchaseModal').classList.remove('show');
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

           // Update statistics with animation
           document.querySelectorAll('.stat-card').forEach((card, index) => {
               setTimeout(() => {
                   card.style.opacity = '0';
                   card.style.transform = 'translateY(20px)';
                   setTimeout(() => {
                       card.style.transition = 'all 0.5s ease';
                       card.style.opacity = '1';
                       card.style.transform = 'translateY(0)';
                   }, 50);
               }, index * 100);
           });
       });

       // Close modal when clicking outside
       window.onclick = function(event) {
           const modal = document.getElementById('purchaseModal');
           if (event.target === modal) {
               closeModal();
           }
       }

       // Close modal with Escape key
       document.addEventListener('keydown', function(event) {
           if (event.key === 'Escape') {
               closeModal();
           }
       });

       // Enhanced table interactions
       document.querySelectorAll('.data-table tbody tr').forEach(row => {
           row.addEventListener('mouseenter', function() {
               this.style.backgroundColor = 'rgba(244, 185, 66, 0.1)';
               this.style.transform = 'scale(1.01)';
           });
           
           row.addEventListener('mouseleave', function() {
               this.style.backgroundColor = '';
               this.style.transform = 'scale(1)';
           });
       });

       // Enhanced button animations
       document.querySelectorAll('.btn').forEach(btn => {
           btn.addEventListener('mouseenter', function() {
               this.style.transform = 'translateY(-2px)';
           });
           
           btn.addEventListener('mouseleave', function() {
               this.style.transform = 'translateY(0)';
           });
       });

       // Dynamic statistics counter animation
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

       // Initialize counter animations on page load
       document.addEventListener('DOMContentLoaded', function() {
           document.querySelectorAll('.stat-number').forEach(counter => {
               const target = parseInt(counter.textContent.replace(/[^0-9]/g, ''));
               if (!isNaN(target)) {
                   counter.textContent = '0';
                   setTimeout(() => {
                       animateCounter(counter, target);
                       // Add back the currency for revenue
                       if (counter.closest('.revenue-stat')) {
                           const timer = setInterval(() => {
                               if (parseInt(counter.textContent) >= target) {
                                   counter.textContent = target.toLocaleString() + ' DH';
                                   clearInterval(timer);
                               }
                           }, 100);
                       }
                   }, 500);
               }
           });
       });

       // Quick action keyboard shortcuts
       document.addEventListener('keydown', function(event) {
           // Ctrl/Cmd + F for search focus
           if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
               event.preventDefault();
               document.getElementById('search').focus();
           }
       });

       // Real-time search suggestion (optional enhancement)
       let searchTimeout;
       document.getElementById('search').addEventListener('input', function() {
           // Clear previous timeout
           clearTimeout(searchTimeout);
           
           // Optional: Add search suggestions or highlighting
           const searchTerm = this.value.toLowerCase();
           if (searchTerm.length >= 2) {
               // You could implement live search suggestions here
               console.log('Searching for:', searchTerm);
           }
       });

       // Export functionality (you can add this as an enhancement)
       function exportPurchases() {
           // This could export the current filtered results to CSV
           console.log('Export functionality would go here');
       }

       // Bulk actions (you can add this as an enhancement)
       function bulkAction(action) {
           const checkedBoxes = document.querySelectorAll('.purchase-checkbox:checked');
           if (checkedBoxes.length === 0) {
               alert('Veuillez sélectionner au moins un achat.');
               return;
           }
           
           const ids = Array.from(checkedBoxes).map(cb => cb.value);
           console.log(`Bulk ${action} for IDs:`, ids);
       }

       // Refresh data function
       function refreshData() {
           location.reload();
       }

       // Auto-refresh every 5 minutes for pending purchases
       setInterval(function() {
           const pendingCount = <?php echo $pending_count; ?>;
           if (pendingCount > 0) {
               // Optional: Show a notification about auto-refresh
               console.log('Auto-refresh: Checking for new purchases...');
               // You could implement AJAX refresh here instead of full page reload
           }
       }, 300000); // 5 minutes

       // Status change animations
       function animateStatusChange(memberId, newStatus) {
           const row = document.querySelector(`tr[data-member-id="${memberId}"]`);
           if (row) {
               row.style.transition = 'all 0.3s ease';
               row.style.transform = 'scale(1.02)';
               row.style.backgroundColor = 'rgba(244, 185, 66, 0.2)';
               
               setTimeout(() => {
                   row.style.transform = 'scale(1)';
                   row.style.backgroundColor = '';
               }, 300);
           }
       }

       // Show loading state for actions
       function showLoadingState(button, action) {
           const originalText = button.innerHTML;
           button.disabled = true;
           button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + action + '...';
           
           // Reset after timeout if action doesn't complete
           setTimeout(() => {
               button.disabled = false;
               button.innerHTML = originalText;
           }, 5000);
       }

       // Enhanced purchase processing with loading state
       document.querySelectorAll('.btn-success').forEach(btn => {
           if (btn.textContent.includes('Traiter')) {
               btn.addEventListener('click', function() {
                   showLoadingState(this, 'Traitement');
               });
           }
       });

       // Filter form enhancements
       document.getElementById('filtersForm').addEventListener('submit', function() {
           const submitBtn = this.querySelector('button[type="submit"]');
           const originalText = submitBtn.innerHTML;
           
           submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Filtrage...';
           
           setTimeout(() => {
               submitBtn.innerHTML = originalText;
           }, 2000);
       });

       // Add visual feedback for successful actions
       function showSuccessMessage(message) {
           const successDiv = document.createElement('div');
           successDiv.className = 'alert alert-success';
           successDiv.style.position = 'fixed';
           successDiv.style.top = '20px';
           successDiv.style.right = '20px';
           successDiv.style.zIndex = '9999';
           successDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
           
           document.body.appendChild(successDiv);
           
           setTimeout(() => {
               successDiv.style.opacity = '0';
               setTimeout(() => successDiv.remove(), 300);
           }, 3000);
       }

       // Purchase statistics updates
       function updateStatistics() {
           // This could be enhanced to update statistics via AJAX
           // without requiring a full page refresh
       }

       // Advanced filtering with URL parameters
       function updateURLWithFilters() {
           const form = document.getElementById('filtersForm');
           const formData = new FormData(form);
           const params = new URLSearchParams();
           
           for (let [key, value] of formData.entries()) {
               if (value) {
                   params.append(key, value);
               }
           }
           
           const newURL = window.location.pathname + '?' + params.toString();
           window.history.pushState({}, '', newURL);
       }

       // Initialize tooltips for status badges
       document.querySelectorAll('.status-badge').forEach(badge => {
           badge.addEventListener('mouseenter', function() {
               // You could add tooltips explaining what each status means
               this.title = getStatusDescription(this.textContent.trim());
           });
       });

       function getStatusDescription(status) {
           const descriptions = {
               'En attente': 'Achat effectué, en attente de traitement administratif',
               'Actif': 'Compte activé, accès complet à la plateforme',
               'Inactif': 'Compte suspendu temporairement',
               'Remboursé': 'Achat remboursé, compte désactivé'
           };
           return descriptions[status] || 'Statut du compte';
       }

       // Print functionality for purchase details
       function printPurchaseDetails(purchase) {
           const printWindow = window.open('', '', 'height=600,width=800');
           printWindow.document.write(`
               <html>
               <head>
                   <title>Détails de l'achat - ${purchase.client_name}</title>
                   <style>
                       body { font-family: Arial, sans-serif; margin: 20px; }
                       .header { text-align: center; margin-bottom: 30px; }
                       .detail-section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; }
                       .detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
                       .amount { font-weight: bold; color: #28a745; font-size: 1.2em; }
                   </style>
               </head>
               <body>
                   <div class="header">
                       <h1>Syndic Way - Détails de l'achat</h1>
                       <p>Généré le ${new Date().toLocaleString('fr-FR')}</p>
                   </div>
                   <!-- Purchase details would be formatted here -->
               </body>
               </html>
           `);
           printWindow.document.close();
           printWindow.print();
       }
   </script>
</body>
</html>