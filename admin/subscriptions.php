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
            $name = trim($_POST['name_subscription']);
            $price = floatval($_POST['price_subscription']);
            $description = trim($_POST['description']);
            $duration = intval($_POST['duration_months']);
            $max_residents = intval($_POST['max_residents']);
            $max_apartments = intval($_POST['max_apartments']);

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
                $_SESSION['success'] = "Abonnement supprimé avec succès.";
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

// Get all subscriptions
try {
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
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/sections/dashboard.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/subscriptions.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Enhanced styles for improved modals */
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .content-header h1 {
            color: var(--color-yellow);
            margin: 0;
        }

        .subscriptions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .subscription-card {
            background: var(--color-white);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.4s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .subscription-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--color-yellow), var(--primary-color));
        }

        .subscription-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            border-color: var(--color-yellow);
        }

        .subscription-card.inactive {
            opacity: 0.7;
            border: 2px dashed var(--color-grey);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .card-header h3 {
            margin: 0;
            color: var(--color-dark-grey);
            font-size: 1.4rem;
            font-weight: 700;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: linear-gradient(135deg, var(--color-green), #20c997);
            color: var(--color-white);
        }

        .status-inactive {
            background: linear-gradient(135deg, var(--color-grey), #6c757d);
            color: var(--color-white);
        }

        .card-price {
            text-align: center;
            margin: 2rem 0;
            padding: 2rem;
            background: linear-gradient(135deg, var(--color-yellow), var(--primary-color));
            border-radius: 15px;
            color: var(--color-white);
            position: relative;
            overflow: hidden;
        }

        .card-price::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0%, 100% { transform: translateX(-50%) translateY(-50%); }
            50% { transform: translateX(-30%) translateY(-30%); }
        }

        .price-amount {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1;
            position: relative;
        }

        .price-currency {
            font-size: 1.3rem;
            margin-left: 0.5rem;
        }

        .price-period {
            font-size: 1.1rem;
            opacity: 0.9;
            display: block;
            margin-top: 0.5rem;
        }

        .card-features {
            margin: 2rem 0;
        }

        .card-features ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .card-features li {
            padding: 1rem 0;
            color: var(--color-dark-grey);
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--color-light-grey);
            transition: all 0.3s ease;
        }

        .card-features li:hover {
            background: rgba(244, 185, 66, 0.05);
            transform: translateX(5px);
        }

        .card-features li:last-child {
            border-bottom: none;
        }

        .card-features i {
            color: var(--color-yellow);
            margin-right: 1rem;
            width: 24px;
            text-align: center;
            font-size: 1.2rem;
        }

        .card-actions {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0.75rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--color-light-grey);
        }

        .card-actions .btn {
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border-radius: 8px;
        }

        /* Enhanced Modal Styles */
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
            animation: fadeIn 0.4s ease;
        }

        .modal.show {
            display: block;
        }

        @keyframes fadeIn {
            from { 
                opacity: 0;
                backdrop-filter: blur(0px);
            }
            to { 
                opacity: 1;
                backdrop-filter: blur(8px);
            }
        }

        @keyframes slideInUp {
            from { 
                opacity: 0;
                transform: translateY(50px) scale(0.95);
            }
            to { 
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-content {
            background-color: var(--color-white);
            margin: 2% auto;
            border-radius: 20px;
            width: 90%;
            max-width: 650px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
            animation: slideInUp 0.4s ease;
            overflow: hidden;
            max-height: 95vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2.5rem;
            background: linear-gradient(135deg, var(--color-yellow), var(--primary-color));
            color: var(--color-white);
            position: relative;
        }

        .modal-header::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .close {
            color: var(--color-white);
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
        }

        .close:hover {
            background-color: rgba(255,255,255,0.2);
            transform: rotate(90deg);
        }

        .modal form {
            padding: 2.5rem;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--color-dark-grey);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--color-light-grey);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.7rem;
            font-weight: 600;
            color: var(--color-dark-grey);
            font-size: 0.95rem;
        }

        .form-group .required {
            color: var(--primary-color);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 1.2rem;
            border: 2px solid var(--color-light-grey);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
            background: var(--color-white);
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--color-yellow);
            box-shadow: 0 0 0 4px rgba(244, 185, 66, 0.15);
            transform: translateY(-2px);
        }

        .form-group input:hover,
        .form-group textarea:hover,
        .form-group select:hover {
            border-color: var(--color-yellow);
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-grey);
            transition: color 0.3s ease;
        }

        .input-group input {
            padding-left: 3rem;
        }

        .input-group input:focus + i {
            color: var(--color-yellow);
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2.5rem;
            padding-top: 2rem;
            border-top: 2px solid var(--color-light-grey);
        }

        .modal-actions .btn {
            padding: 1rem 2rem;
            font-weight: 700;
            font-size: 1rem;
            border-radius: 10px;
            min-width: 130px;
        }

        /* Delete Confirmation Modal */
        .delete-modal .modal-content {
            max-width: 500px;
            text-align: center;
        }

        .delete-modal .modal-header {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .delete-warning {
            padding: 2rem;
            color: var(--color-dark-grey);
        }

        .delete-warning i {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }

        .delete-warning h3 {
            margin-bottom: 1rem;
            color: var(--color-dark-grey);
        }

        .delete-warning p {
            margin-bottom: 0;
            line-height: 1.6;
        }

        .delete-actions {
            display: flex;
            gap: 1rem;
            padding: 2rem;
            border-top: 2px solid var(--color-light-grey);
        }

        .delete-actions .btn {
            flex: 1;
            padding: 1rem;
            font-weight: 700;
        }

        /* Button animations and improvements */
        .btn {
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            transition: all 0.3s ease;
            transform: translate(-50%, -50%);
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--color-yellow), var(--primary-color));
            color: var(--color-white);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--color-grey), var(--color-dark-grey));
            color: var(--color-white);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: var(--color-white);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: var(--color-white);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--color-green), #20c997);
            color: var(--color-white);
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .subscriptions-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .card-actions {
                grid-template-columns: 1fr;
            }

            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .modal-content {
                margin: 5% auto;
                width: 95%;
            }

            .modal form,
            .modal-header {
                padding: 1.5rem;
            }
        }

        /* Loading states */
        .btn-loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .btn-loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Form validation styles */
        .form-group input:invalid {
            border-color: #dc3545;
        }

        .form-group input:valid {
            border-color: var(--color-green);
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
                    <li class="active">
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
                <div>
                    <h1><i class="fas fa-tags"></i> Gestion des Abonnements</h1>
                    <p>Créez et gérez vos forfaits d'abonnement avec leurs fonctionnalités</p>
                </div>
                <button class="btn btn-primary" onclick="openModal('create')">
                    <i class="fas fa-plus"></i> Nouveau forfait
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
                            <p><?php echo htmlspecialchars($subscription['description']); ?></p>
                        </div>

                        <div class="card-features">
                            <ul>
                                <li>
                                    <i class="fas fa-users"></i> 
                                    Jusqu'à <?php echo $subscription['max_residents']; ?> résidents
                                </li>
                                <li>
                                    <i class="fas fa-building"></i> 
                                    Jusqu'à <?php echo $subscription['max_apartments']; ?> appartements
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
                <div class="empty-state">
                    <i class="fas fa-tags"></i>
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
                            <div class="input-group">
                                <input type="text" name="name_subscription" id="name_subscription" 
                                       placeholder="Ex: Forfait Premium" required>
                                <i class="fas fa-tag"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="price_subscription">
                                Prix mensuel (DH) <span class="required">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" name="price_subscription" id="price_subscription" 
                                       step="0.01" min="0" placeholder="299.00" required>
                                <i class="fas fa-dollar-sign"></i>
                            </div>
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
                           <div class="input-group">
                               <input type="number" name="duration_months" id="duration_months" 
                                      value="12" min="1" max="60" required>
                               <i class="fas fa-calendar"></i>
                           </div>
                       </div>
                       
                       <div class="form-group">
                           <label for="max_residents">
                               Max résidents <span class="required">*</span>
                           </label>
                           <div class="input-group">
                               <input type="number" name="max_residents" id="max_residents" 
                                      min="1" max="10000" placeholder="100" required>
                               <i class="fas fa-users"></i>
                           </div>
                       </div>
                   </div>

                   <div class="form-group">
                       <label for="max_apartments">
                           Max appartements <span class="required">*</span>
                       </label>
                       <div class="input-group">
                           <input type="number" name="max_apartments" id="max_apartments" 
                                  min="1" max="1000" placeholder="50" required>
                           <i class="fas fa-building"></i>
                       </div>
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

   <script>
       // Enhanced Modal management
       function openModal(action) {
           const modal = document.getElementById('subscriptionModal');
           const form = document.getElementById('subscriptionForm');
           const title = document.getElementById('modalTitleText');
           const icon = document.getElementById('modalIcon');
           const actionInput = document.getElementById('formAction');
           const submitBtn = document.getElementById('submitBtn');
           
           modal.classList.add('show');
           actionInput.value = action;
           
           if (action === 'create') {
               title.textContent = 'Nouveau forfait';
               icon.className = 'fas fa-plus';
               form.reset();
               document.getElementById('subscriptionId').value = '';
               submitBtn.innerHTML = '<i class="fas fa-plus"></i> Créer le forfait';
               
               // Set default values
               document.getElementById('duration_months').value = '12';
           } else {
               title.textContent = 'Modifier forfait';
               icon.className = 'fas fa-edit';
               submitBtn.innerHTML = '<i class="fas fa-save"></i> Mettre à jour';
           }
           
           // Focus first input with animation
           setTimeout(() => {
               const firstInput = document.getElementById('name_subscription');
               firstInput.focus();
               firstInput.style.transform = 'scale(1.02)';
               setTimeout(() => {
                   firstInput.style.transform = 'scale(1)';
               }, 200);
           }, 400);
       }

       function closeModal(modalId) {
           const modal = document.getElementById(modalId);
           modal.classList.remove('show');
           
           // Reset any transformations
           const inputs = modal.querySelectorAll('input, textarea');
           inputs.forEach(input => {
               input.style.transform = 'scale(1)';
           });
       }

       function editSubscription(subscription) {
           openModal('update');
           
           // Populate form with animation
           setTimeout(() => {
               document.getElementById('subscriptionId').value = subscription.id_subscription;
               
               const fields = [
                   { id: 'name_subscription', value: subscription.name_subscription },
                   { id: 'price_subscription', value: subscription.price_subscription },
                   { id: 'description', value: subscription.description || '' },
                   { id: 'duration_months', value: subscription.duration_months },
                   { id: 'max_residents', value: subscription.max_residents },
                   { id: 'max_apartments', value: subscription.max_apartments }
               ];
               
               fields.forEach((field, index) => {
                   setTimeout(() => {
                       const element = document.getElementById(field.id);
                       element.value = field.value;
                       element.style.transform = 'scale(1.02)';
                       setTimeout(() => {
                           element.style.transform = 'scale(1)';
                       }, 200);
                   }, index * 100);
               });
           }, 200);
       }

       function confirmDelete(id, name, subscriberCount) {
           const modal = document.getElementById('deleteModal');
           const title = document.getElementById('deleteTitle');
           const message = document.getElementById('deleteMessage');
           const confirmBtn = document.getElementById('confirmDeleteBtn');
           
           document.getElementById('deleteSubscriptionId').value = id;
           
           title.textContent = `Supprimer "${name}"`;
           
           if (subscriberCount > 0) {
               message.innerHTML = `
                   <strong>Attention !</strong> Ce forfait a actuellement <strong>${subscriberCount}</strong> abonnés actifs.<br>
                   La suppression sera impossible tant qu'il y a des abonnés.
               `;
               confirmBtn.disabled = true;
               confirmBtn.innerHTML = '<i class="fas fa-ban"></i> Suppression impossible';
               confirmBtn.className = 'btn btn-secondary';
           } else {
               message.innerHTML = `
                   Êtes-vous sûr de vouloir supprimer ce forfait ?<br>
                   <strong>Cette action est irréversible.</strong>
               `;
               confirmBtn.disabled = false;
               confirmBtn.innerHTML = '<i class="fas fa-trash"></i> Supprimer définitivement';
               confirmBtn.className = 'btn btn-danger';
           }
           
           modal.classList.add('show');
       }

       function toggleStatus(id, newStatus) {
           const action = newStatus ? 'activer' : 'désactiver';
           
           if (confirm(`Êtes-vous sûr de vouloir ${action} cet abonnement ?`)) {
               document.getElementById('toggleSubscriptionId').value = id;
               document.getElementById('toggleNewStatus').value = newStatus;
               document.getElementById('toggleForm').submit();
           }
       }

       // Enhanced event listeners
       document.addEventListener('DOMContentLoaded', function() {
           // Auto-hide alerts with animation
           const alerts = document.querySelectorAll('.alert');
           alerts.forEach(alert => {
               setTimeout(() => {
                   alert.style.opacity = '0';
                   alert.style.transform = 'translateY(-20px)';
                   setTimeout(() => alert.remove(), 300);
               }, 5000);
           });

           // Enhanced card hover effects
           document.querySelectorAll('.subscription-card').forEach(card => {
               card.addEventListener('mouseenter', function() {
                   this.style.transform = 'translateY(-8px) scale(1.02)';
               });
               
               card.addEventListener('mouseleave', function() {
                   this.style.transform = 'translateY(0) scale(1)';
               });
           });

           // Form validation and enhancement
           const form = document.getElementById('subscriptionForm');
           const inputs = form.querySelectorAll('input[required]');
           
           inputs.forEach(input => {
               input.addEventListener('blur', function() {
                   if (this.validity.valid) {
                       this.style.borderColor = 'var(--color-green)';
                   } else {
                       this.style.borderColor = '#dc3545';
                   }
               });
               
               input.addEventListener('input', function() {
                   if (this.validity.valid) {
                       this.style.borderColor = 'var(--color-green)';
                   }
               });
           });

           // Enhanced form submission
           form.addEventListener('submit', function(event) {
               const submitBtn = document.getElementById('submitBtn');
               
               // Add loading state with animation
               submitBtn.classList.add('btn-loading');
               submitBtn.disabled = true;
               
               const originalText = submitBtn.innerHTML;
               submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
               
               // Reset after timeout if form doesn't redirect
               setTimeout(() => {
                   submitBtn.classList.remove('btn-loading');
                   submitBtn.disabled = false;
                   submitBtn.innerHTML = originalText;
               }, 3000);
           });

           // Delete form submission
           document.getElementById('deleteForm').addEventListener('submit', function(event) {
               const confirmBtn = document.getElementById('confirmDeleteBtn');
               
               if (!confirmBtn.disabled) {
                   confirmBtn.classList.add('btn-loading');
                   confirmBtn.disabled = true;
                   confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Suppression...';
               }
           });
       });

       // Close modal when clicking outside
       window.onclick = function(event) {
           const subscriptionModal = document.getElementById('subscriptionModal');
           const deleteModal = document.getElementById('deleteModal');
           
           if (event.target === subscriptionModal) {
               closeModal('subscriptionModal');
           }
           if (event.target === deleteModal) {
               closeModal('deleteModal');
           }
       }

       // Keyboard shortcuts
       document.addEventListener('keydown', function(event) {
           if (event.key === 'Escape') {
               closeModal('subscriptionModal');
               closeModal('deleteModal');
           }
           
           // Ctrl/Cmd + N for new subscription
           if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
               event.preventDefault();
               openModal('create');
           }
       });

       // Price formatting
       document.getElementById('price_subscription').addEventListener('input', function() {
           let value = parseFloat(this.value);
           if (!isNaN(value)) {
               // Optional: Format the display
               this.setAttribute('title', `${value.toFixed(2)} DH`);
           }
       });

       // Enhanced animations for better UX
       function animateCardUpdate(cardId) {
           const card = document.querySelector(`[data-subscription-id="${cardId}"]`);
           if (card) {
               card.style.transform = 'scale(1.05)';
               card.style.boxShadow = '0 20px 40px rgba(244, 185, 66, 0.3)';
               
               setTimeout(() => {
                   card.style.transform = 'scale(1)';
                   card.style.boxShadow = '';
               }, 500);
           }
       }

       // Success animation for form submission
       function showSuccessAnimation() {
           const successIcon = document.createElement('div');
           successIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
           successIcon.style.cssText = `
               position: fixed;
               top: 50%;
               left: 50%;
               transform: translate(-50%, -50%);
               font-size: 4rem;
               color: var(--color-green);
               z-index: 9999;
               animation: successPulse 1s ease-out;
           `;
           
           document.body.appendChild(successIcon);
           
           setTimeout(() => {
               successIcon.remove();
           }, 1000);
       }

       // Add CSS for success animation
       const style = document.createElement('style');
       style.textContent = `
           @keyframes successPulse {
               0% { transform: translate(-50%, -50%) scale(0); opacity: 0; }
               50% { transform: translate(-50%, -50%) scale(1.2); opacity: 1; }
               100% { transform: translate(-50%, -50%) scale(1); opacity: 0; }
           }
       `;
       document.head.appendChild(style);
   </script>
</body>
</html>