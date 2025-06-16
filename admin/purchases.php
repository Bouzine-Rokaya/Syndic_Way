<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
$current_user = getCurrentUser();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'process_purchase') {
            $member_id = intval($_POST['member_id']);

            $stmt = $conn->prepare("UPDATE member SET status = 'active' WHERE id_member = ?");
            $stmt->execute([$member_id]);

            $_SESSION['success'] = "Achat traité avec succès. Le compte syndic est maintenant actif.";

        } elseif ($action === 'cancel_purchase') {
            $member_id = intval($_POST['member_id']);

            $conn->beginTransaction();

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

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Get purchases data
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
        $where_conditions[] = "(m.full_name LIKE ? OR m.email LIKE ? OR r.name LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }

    if ($status_filter) {
        $where_conditions[] = "m.status = ?";
        $params[] = $status_filter;
    }

    if ($where_conditions) {
        $query .= " AND " . implode(" AND ", $where_conditions);
    }

    $query .= " ORDER BY ams.date_payment DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $total_purchases = count($purchases);
    $pending_count = 0;
    $active_count = 0;
    $total_revenue = 0;

    foreach ($purchases as $purchase) {
        if ($purchase['purchase_status'] === 'pending')
            $pending_count++;
        if ($purchase['purchase_status'] === 'active')
            $active_count++;
        $total_revenue += $purchase['amount_paid'];
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors du chargement des achats.";
    $purchases = [];
    $total_purchases = 0;
    $pending_count = 0;
    $active_count = 0;
    $total_revenue = 0;
}

$page_title = "Gestion des Achats - Syndic Way";
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/purchases.css">
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
            <?php require_once __DIR__ . "/../includes/navigation.php" ?>


            <!-- Quick Access -->
            <div class="quick-access">
                <div class="quick-access-header">
                    <div class="quick-access-title">Statistiques des Achats</div>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card total">
                        <div class="quick-card-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="quick-card-title">Total Achats</div>
                        <div class="quick-card-count"><?php echo $total_purchases; ?></div>
                        <div class="quick-card-stats">Tous les achats</div>
                    </div>

                    <div class="quick-card pending">
                        <div class="quick-card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="quick-card-title">En Attente</div>
                        <div class="quick-card-count"><?php echo $pending_count; ?></div>
                        <div class="quick-card-stats">À traiter</div>
                    </div>

                    <div class="quick-card active">
                        <div class="quick-card-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="quick-card-title">Traités</div>
                        <div class="quick-card-count"><?php echo $active_count; ?></div>
                        <div class="quick-card-stats">Activés</div>
                    </div>

                    <div class="quick-card revenue">
                        <div class="quick-card-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="quick-card-title">Revenus</div>
                        <div class="quick-card-count"><?php echo number_format($total_revenue, 0); ?> DH</div>
                        <div class="quick-card-stats">Total des achats</div>
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
                        <a href="#">Gestion des Achats</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Tous les Achats</span>
                    </div>

                    <!-- Table Header -->
                    <div class="table-header">
                        <div class="view-options">
                            <div class="view-btn active">
                                <i class="fas fa-th"></i>
                            </div>
                            <div class="view-btn">
                                <i class="fas fa-list"></i>
                            </div>
                        </div>
                        <a href="#" class="add-btn">
                            <i class="fas fa-download"></i>
                            Exporter CSV
                        </a>
                    </div>

                    <!-- Data Table -->
                    <table class="data-table">
                        <thead class="table-header-row">
                            <tr>
                                <th>Client</th>
                                <th>Forfait</th>
                                <th>Montant</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($purchases)): ?>
                                <tr class="table-row">
                                    <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                                        <i class="fas fa-shopping-cart"
                                            style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;"></i>
                                        <div>Aucun achat trouvé</div>
                                        <div style="font-size: 12px; margin-top: 8px;">Les achats apparaîtront ici</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($purchases as $purchase): ?>
                                    <tr class="table-row">
                                        <td>
                                            <div class="file-item">
                                                <div class="table-icon" style="background: #3b82f6;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div class="file-info">
                                                    <div class="file-name">
                                                        <?php echo htmlspecialchars($purchase['client_name']); ?></div>
                                                    <div style="font-size: 12px; color: #64748b;">
                                                        <?php echo htmlspecialchars($purchase['client_email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;">
                                                <?php echo htmlspecialchars($purchase['subscription_name']); ?></div>
                                            <div style="font-size: 12px; color: #64748b;">
                                                <?php echo htmlspecialchars($purchase['company_name'] ?? 'Non définie'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: #1e293b;">
                                                <?php echo number_format($purchase['amount_paid'], 2); ?> DH</div>
                                        </td>
                                        <td>
                                            <div><?php echo date('j M Y', strtotime($purchase['payment_date'])); ?></div>
                                            <div style="font-size: 12px; color: #64748b;">
                                                <?php echo date('H:i', strtotime($purchase['payment_date'])); ?></div>
                                        </td>
                                        <td>
                                            <span
                                                class="status-badge <?php echo $purchase['purchase_status'] === 'active' ? 'status-active' : 'status-pending'; ?>">
                                                <?php
                                                $status_text = [
                                                    'active' => 'Actif',
                                                    'pending' => 'En attente',
                                                    'refunded' => 'Remboursé'
                                                ];
                                                echo $status_text[$purchase['purchase_status']] ?? $purchase['purchase_status'];
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 8px;">
                                                <?php if ($purchase['purchase_status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success"
                                                        onclick="processPurchase(<?php echo $purchase['id_member']; ?>, '<?php echo htmlspecialchars($purchase['client_name']); ?>')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger"
                                                        onclick="cancelPurchase(<?php echo $purchase['id_member']; ?>, '<?php echo htmlspecialchars($purchase['client_name']); ?>')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php elseif ($purchase['purchase_status'] === 'active'): ?>
                                                    <button class="btn btn-sm btn-secondary"
                                                        onclick="refundPurchase(<?php echo $purchase['id_member']; ?>, '<?php echo htmlspecialchars($purchase['client_name']); ?>')">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"
                                                    onclick="showPurchaseDetails(<?php echo htmlspecialchars(json_encode($purchase)); ?>)"></i>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
        // Process purchase
        function processPurchase(memberId, clientName) {
            if (confirm(`Êtes-vous sûr de vouloir traiter l'achat de ${clientName} ?`)) {
                document.getElementById('processMemberId').value = memberId;
                document.getElementById('processForm').submit();
            }
        }

        // Cancel purchase
        function cancelPurchase(memberId, clientName) {
            if (confirm(`Êtes-vous sûr de vouloir annuler l'achat de ${clientName} ?\n\nCette action supprimera définitivement toutes les données associées.`)) {
                document.getElementById('cancelMemberId').value = memberId;
                document.getElementById('cancelForm').submit();
            }
        }

        // Refund purchase
        function refundPurchase(memberId, clientName) {
            if (confirm(`Êtes-vous sûr de vouloir rembourser l'achat de ${clientName} ?`)) {
                document.getElementById('refundMemberId').value = memberId;
                document.getElementById('refundForm').submit();
            }
        }

        // Show purchase details
        function showPurchaseDetails(purchase) {
            alert(`Détails de l'achat:\n\nClient: ${purchase.client_name}\nEmail: ${purchase.client_email}\nForfait: ${purchase.subscription_name}\nMontant: ${purchase.amount_paid} DH\nDate: ${purchase.payment_date}`);
        }

        // View toggle functionality
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Search functionality
        document.querySelector('.search-box input').addEventListener('input', function () {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.table-row').forEach(row => {
                const clientName = row.querySelector('.file-name');
                if (clientName) {
                    const text = clientName.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });

        // Quick card clicks
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('click', function () {
                const cardType = this.classList[1];
                console.log(`Filtering by ${cardType}`);
                // Add filtering logic here
            });
        });

        // Storage animation on load
        window.addEventListener('load', function () {
            const storageFill = document.querySelector('.storage-fill');
            const percentage = Math.min((<?php echo $total_purchases; ?> / 100) * 100, 100);
            storageFill.style.width = '0%';
            setTimeout(() => {
                storageFill.style.width = percentage + '%';
            }, 500);
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
        });
    </script>
</body>

</html>