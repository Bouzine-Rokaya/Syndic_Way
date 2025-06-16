<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in as syndic
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'syndic') {
    header('Location: ../public/login.php');
    exit();
}

$current_user = [
    'id' => $_SESSION['user_id'],
    'role' => $_SESSION['user_role'],
    'name' => $_SESSION['user_name'] ?? 'Syndic',
    'email' => $_SESSION['user_email'] ?? ''
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'record_payment') {
            $payer_id = intval($_POST['payer_id']);
            $amount = floatval($_POST['amount']);
            $month_paid = $_POST['month_paid'];
            $payment_method = $_POST['payment_method'] ?? 'cash';
            $notes = trim($_POST['notes'] ?? '');
            
            // Verify payer exists and belongs to syndic's building
            $stmt = $conn->prepare("
                SELECT m.id_member, m.full_name
                FROM member m
                JOIN apartment ap ON ap.id_member = m.id_member
                JOIN residence r ON r.id_residence = ap.id_residence
                WHERE m.id_member = ? AND r.id_residence IN (
                    SELECT DISTINCT r2.id_residence
                    FROM apartment ap2
                    JOIN residence r2 ON r2.id_residence = ap2.id_residence
                    WHERE ap2.id_member = ? AND m.role = 1
                )
            ");
            $stmt->execute([$payer_id, $current_user['id']]);
            $payer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payer) {
                throw new Exception("Résident introuvable ou non autorisé.");
            }
            
            // Check if payment already exists for this month
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM member_payments 
                WHERE id_payer = ? AND id_receiver = ? AND month_paid = ?
            ");
            $stmt->execute([$payer_id, $current_user['id'], $month_paid]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Un paiement existe déjà pour ce mois.");
            }
            
            // Record payment
            $stmt = $conn->prepare("
                INSERT INTO member_payments (id_payer, id_receiver, date_payment, month_paid) 
                VALUES (?, ?, NOW(), ?)
            ");
            $stmt->execute([$payer_id, $current_user['id'], $month_paid]);
            
            // Send notification to resident
            $stmt = $conn->prepare("
                INSERT INTO member_notifications (id_sender, id_receiver, date_notification)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$current_user['id'], $payer_id]);
            
            $_SESSION['success'] = "Paiement enregistré avec succès pour " . htmlspecialchars($payer['full_name']) . ".";
            
        } elseif ($action === 'delete_payment') {
            $payer_id = intval($_POST['payer_id']);
            $month_paid = $_POST['month_paid'];
            
            $stmt = $conn->prepare("
                DELETE FROM member_payments 
                WHERE id_payer = ? AND id_receiver = ? AND month_paid = ?
            ");
            $stmt->execute([$payer_id, $current_user['id'], $month_paid]);
            
            $_SESSION['success'] = "Paiement supprimé avec succès.";
            
        } elseif ($action === 'send_reminder') {
            $resident_id = intval($_POST['resident_id']);
            $month = $_POST['month'];
            
            // Send notification
            $stmt = $conn->prepare("
                INSERT INTO member_notifications (id_sender, id_receiver, date_notification)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$current_user['id'], $resident_id]);
            
            $_SESSION['success'] = "Rappel envoyé avec succès.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header('Location: payments.php');
    exit();
}

// Get filters
$month_filter = $_GET['month'] ?? date('Y-m');
$status_filter = $_GET['status'] ?? '';
$resident_filter = $_GET['resident'] ?? '';

// Get payment data
try {
    // Get the syndic's building
    $stmt = $conn->prepare("
        SELECT r.id_residence
        FROM apartment ap
        JOIN residence r ON r.id_residence = ap.id_residence
        WHERE ap.id_member = ? AND EXISTS(
            SELECT 1 FROM admin WHERE id_admin = ?
        )
        LIMIT 1
    ");
    $stmt->execute([$current_user['id'], $current_user['id']]);
    $building = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$building) {
        // Fallback: get first available building
        $stmt = $conn->prepare("SELECT id_residence FROM residence LIMIT 1");
        $stmt->execute();
        $building = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $residence_id = $building['id_residence'] ?? 1;
    
    // Get all residents in this building
    $where_conditions = ["m.role = 1", "ap.id_residence = ?"];
    $params = [$residence_id];
    
    if ($resident_filter) {
        $where_conditions[] = "m.id_member = ?";
        $params[] = $resident_filter;
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    $stmt = $conn->prepare("
        SELECT 
            m.id_member,
            m.full_name,
            m.email,
            ap.number as apartment_number,
            ap.floor,
            r.name as building_name
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        JOIN residence r ON r.id_residence = ap.id_residence
        WHERE {$where_clause}
        ORDER BY ap.floor ASC, ap.number ASC
    ");
    $stmt->execute($params);
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get payments for selected month
    $payments_data = [];
    foreach ($residents as $resident) {
        $stmt = $conn->prepare("
            SELECT mp.date_payment, mp.month_paid
            FROM member_payments mp
            WHERE mp.id_payer = ? AND mp.id_receiver = ? 
            AND DATE_FORMAT(mp.month_paid, '%Y-%m') = ?
        ");
        $stmt->execute([$resident['id_member'], $current_user['id'], $month_filter]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $payments_data[] = [
            'resident' => $resident,
            'payment' => $payment,
            'status' => $payment ? 'paid' : 'unpaid'
        ];
    }
    
    // Apply status filter
    if ($status_filter) {
        $payments_data = array_filter($payments_data, function($item) use ($status_filter) {
            return $item['status'] === $status_filter;
        });
    }
    
    // Get building info
    $stmt = $conn->prepare("
        SELECT r.name, r.address, c.city_name
        FROM residence r
        JOIN city c ON c.id_city = r.id_city
        WHERE r.id_residence = ?
    ");
    $stmt->execute([$residence_id]);
    $building_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $total_residents = count($residents);
    $paid_count = count(array_filter($payments_data, function($item) { return $item['status'] === 'paid'; }));
    $unpaid_count = $total_residents - $paid_count;
    $collection_rate = $total_residents > 0 ? round(($paid_count / $total_residents) * 100, 1) : 0;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors du chargement des paiements: " . $e->getMessage();
    $residents = [];
    $payments_data = [];
    $building_info = null;
    $total_residents = 0;
    $paid_count = 0;
    $unpaid_count = 0;
    $collection_rate = 0;
}

$page_title = "Gestion des Paiements - Syndic Way";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            line-height: 1.5;
        }

        .container {
            display: flex;
            height: 100vh;
        }

        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin: 16px 24px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* Quick Access Section */
        .quick-access {
            padding: 24px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
        }

        .quick-access-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .quick-access-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }

        .quick-access-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .quick-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
        }

        .quick-card:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .quick-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 20px;
            color: white;
        }

        .quick-card.total .quick-card-icon { background: #6366f1; }
        .quick-card.paid .quick-card-icon { background: #10b981; }
        .quick-card.unpaid .quick-card-icon { background: #f59e0b; }
        .quick-card.rate .quick-card-icon { background: #8b5cf6; }

        .quick-card-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .quick-card-stats {
            font-size: 12px;
            color: #64748b;
        }

        .quick-card-count {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }

        /* Content Area */
        .content-area {
            flex: 1;
            display: flex;
        }

        .main-panel {
            flex: 1;
            padding: 24px;
        }

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #64748b;
        }

        .breadcrumb a {
            color: #64748b;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color:rgb(249, 222, 142);
        }

        /* Table Header */
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .view-options {
            display: flex;
            gap: 8px;
        }

        .view-btn {
            width: 32px;
            height: 32px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .view-btn.active {
            background: #FFCB32;
            color: white;
            border-color: #FFCB32;
        }

        .add-btn {
            background: #FFCB32;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .add-btn:hover {
            background:rgb(255, 232, 162);
        }

        /* Data Table */
        .data-table {
            width: 100%;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .table-header-row {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .table-header-row th {
            padding: 12px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-row {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        .table-row:hover {
            background: #f8fafc;
        }

        .table-row td {
            padding: 16px;
            font-size: 14px;
            color: #1e293b;
        }

        .table-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: white;
            font-size: 14px;
        }

        .file-item {
            display: flex;
            align-items: center;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: 500;
            color: #1e293b;
        }

        .sharing-avatars {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .sharing-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #FFCB32;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 10px;
            font-weight: 500;
            border: 2px solid white;
        }

        .sharing-count {
            font-size: 12px;
            color: #64748b;
            margin-left: 8px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-paid {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-unpaid {
            background: #fef3c7;
            color: #d97706;
        }

        /* Activity Panel */
        .activity-panel {
            width: 320px;
            background: white;
            border-left: 1px solid #e2e8f0;
            padding: 24px;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom:10px;
            border-bottom: 1px solid rgb(226, 231, 235);
        }

        .activity-title {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }

        .close-btn {
            width: 24px;
            height: 24px;
            border: none;
            background: none;
            color: #64748b;
            cursor: pointer;
        }



        .activity-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 12px;
            color: white;
        }

        .activity-icon.payment { background: #10b981; }
        .activity-icon.reminder { background: #f59e0b; }
        .activity-icon.record { background: #FFCB32; }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 14px;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .activity-time {
            font-size: 12px;
            color: #64748b;
        }

        .activity-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
        }

        .tag {
            background: #eff6ff;
            color: #FFCB32;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #FFCB32;
            color: white;
        }

        .btn-primary:hover {
            background: #FFCB32;
        }

        .btn-secondary {
            background: #64748b;
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Filters */
        .filters-section {
            background: white;
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            margin-bottom: 4px;
        }

        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }

        /* Progress Bar */
        .progress-section {
            background: white;
            padding: 20px 24px;
            margin-bottom: 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .progress-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .progress-text {
            font-size: 14px;
            color: #64748b;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #f1f5f9;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .progress-labels {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #64748b;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .close {
            background: none;
            border: none;
            font-size: 24px;
            color: #64748b;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close:hover {
            color: #1e293b;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-actions {
            padding: 20px 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        /* Form Styles */
        .form-section {
            margin-bottom: 24px;
        }

        .form-section-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #FFCB32;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .required {
            color: #ef4444;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #1e293b;
        }

        .empty-state p {
            margin-bottom: 24px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .action-btn {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
        }

        .action-btn:hover {
            border-color:rgb(249, 219, 128);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .action-btn i {
            font-size: 24px;
            color: #FFCB32;
            margin-bottom: 12px;
        }

        .action-btn span {
            display: block;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .action-btn small {
            color: #64748b;
            font-size: 12px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .content-area {
                flex-direction: column;
            }
            
            .activity-panel {
                width: 100%;
                border-left: none;
                border-top: 1px solid #e2e8f0;
            }
            
            .quick-access-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .quick-access-grid {
                grid-template-columns: 1fr;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .header-actions {
                flex-direction: column;
                gap: 8px;
            }
            
            .search-box input {
                width: 200px;
            }
        }

        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Animations */
        .slide-in {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <?php require_once __DIR__ ."/../includes/sidebar_syndic.php"?>


        <!-- Main Content -->
        <div class="main-content">
            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success slide-in">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error slide-in">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Header -->
            <?php require_once __DIR__ ."/../includes/navigation_syndic.php"?>


            <!-- Quick Access -->
            <div class="quick-access">
                <div class="quick-access-header">
                    <div class="quick-access-title">Statistiques des Paiements - <?php echo date('F Y', strtotime($month_filter . '-01')); ?></div>
                    <button class="add-btn" onclick="openPaymentModal()">
                        <i class="fas fa-plus"></i>
                        Nouveau Paiement
                    </button>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card total">
                        <div class="quick-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="quick-card-title">Total Résidents</div>
                        <div class="quick-card-count"><?php echo $total_residents; ?></div>
                        <div class="quick-card-stats">Appartements</div>
                    </div>

                    <div class="quick-card paid">
                        <div class="quick-card-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="quick-card-title">Paiements Reçus</div>
                        <div class="quick-card-count"><?php echo $paid_count; ?></div>
                        <div class="quick-card-stats">Résidents</div>
                    </div>

                    <div class="quick-card unpaid">
                        <div class="quick-card-icon">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <div class="quick-card-title">En Attente</div>
                        <div class="quick-card-count"><?php echo $unpaid_count; ?></div>
                        <div class="quick-card-stats">Résidents</div>
                    </div>

                    <div class="quick-card rate">
                        <div class="quick-card-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div class="quick-card-title">Taux de Collecte</div>
                        <div class="quick-card-count"><?php echo $collection_rate; ?>%</div>
                        <div class="quick-card-stats">Du mois</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" id="filtersForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="month">Mois</label>
                            <input type="month" name="month" id="month" 
                                   value="<?php echo htmlspecialchars($month_filter); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="status">Statut</label>
                            <select name="status" id="status">
                                <option value="">Tous</option>
                                <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Payé</option>
                                <option value="unpaid" <?php echo $status_filter === 'unpaid' ? 'selected' : ''; ?>>Non payé</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="resident">Résident</label>
                            <select name="resident" id="resident">
                                <option value="">Tous</option>
                                <?php foreach ($residents as $resident): ?>
                                    <option value="<?php echo $resident['id_member']; ?>" 
                                            <?php echo $resident_filter == $resident['id_member'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($resident['full_name']); ?> - Apt. <?php echo $resident['apartment_number']; ?>
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

       

            <!-- Quick Actions -->
            <div class="quick-actions">
                <button class="action-btn" onclick="sendBulkReminders()">
                    <i class="fas fa-bell"></i>
                    <span>Rappels en masse</span>
                    <small>Envoyer aux non-payeurs</small>
                </button>
                
                <button class="action-btn" onclick="generateReport()">
                    <i class="fas fa-file-pdf"></i>
                    <span>Rapport mensuel</span>
                    <small>PDF détaillé</small>
                </button>
                
                <button class="action-btn" onclick="exportData()">
                    <i class="fas fa-download"></i>
                    <span>Exporter données</span>
                    <small>CSV/Excel</small>
                </button>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div class="main-panel">
                    <!-- Breadcrumb -->
                    <div class="breadcrumb">
                        <a href="#">Accueil</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <a href="#">Gestion Syndic</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Paiements</span>
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
                        <button class="add-btn" onclick="openPaymentModal()">
                            <i class="fas fa-plus"></i>
                            Enregistrer Paiement
                        </button>
                    </div>

                    <!-- Data Table -->
                    <?php if (!empty($payments_data)): ?>
                        <table class="data-table">
                            <thead class="table-header-row">
                                <tr>
                                    <th>Résident</th>
                                    <th>Appartement</th>
                                    <th>Statut</th>
                                    <th>Date Paiement</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments_data as $data): ?>
                                <tr class="table-row">
                                    <td>
                                        <div class="file-item">
                                            <div class="table-icon" style="background: <?php echo $data['status'] === 'paid' ? '#10b981' : '#f59e0b'; ?>;">
                                                <i class="fas fa-<?php echo $data['status'] === 'paid' ? 'check' : 'clock'; ?>"></i>
                                            </div>
                                            <div class="file-info">
                                                <div class="file-name"><?php echo htmlspecialchars($data['resident']['full_name']); ?></div>
                                                <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($data['resident']['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="text-align: center;">
                                            <span style="font-weight: 600;">Apt. <?php echo $data['resident']['apartment_number']; ?></span>
                                            <div style="font-size: 12px; color: #64748b;">Étage <?php echo htmlspecialchars($data['resident']['floor']); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $data['status']; ?>">
                                            <?php echo $data['status'] === 'paid' ? 'Payé' : 'Non payé'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($data['payment']): ?>
                                            <?php echo date('d/m/Y', strtotime($data['payment']['date_payment'])); ?>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($data['status'] === 'unpaid'): ?>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="recordPayment(<?php echo $data['resident']['id_member']; ?>, '<?php echo htmlspecialchars($data['resident']['full_name']); ?>')">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="sendReminder(<?php echo $data['resident']['id_member']; ?>, '<?php echo htmlspecialchars($data['resident']['full_name']); ?>')">
                                                    <i class="fas fa-bell"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-danger" 
                                                        onclick="deletePayment(<?php echo $data['resident']['id_member']; ?>, '<?php echo $month_filter; ?>-01', '<?php echo htmlspecialchars($data['resident']['full_name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-euro-sign"></i>
                            <h3>Aucun paiement trouvé</h3>
                            <p>Aucun paiement ne correspond aux critères sélectionnés.</p>
                            <button class="btn btn-primary" onclick="openPaymentModal()">
                                <i class="fas fa-plus"></i> Enregistrer le premier paiement
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Activity Panel -->
                <div class="activity-panel">
                    <div class="activity-header">
                        <div class="activity-title">Activité</div>
                        <button class="close-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon payment">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Paiement enregistré</div>
                            <div class="activity-time">Il y a 2 heures</div>
                            <div class="activity-meta">
                                <div class="sharing-avatar">JD</div>
                                <span style="font-size: 12px; color: #64748b;">Jean Dupont</span>
                                <div class="tag">450 DH</div>
                            </div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon reminder">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Rappel envoyé</div>
                            <div class="activity-time">Il y a 4 heures</div>
                            <div class="activity-meta">
                                <div class="sharing-avatar">MS</div>
                                <span style="font-size: 12px; color: #64748b;">Marie Dubois</span>
                                <div class="tag">Rappel</div>
                            </div>
                        </div>
                    </div>

                    <div class="activity-item">
                        <div class="activity-icon record">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Nouveau résident</div>
                            <div class="activity-time">Hier</div>
                            <div class="activity-meta">
                                <div class="sharing-avatar">PL</div>
                                <span style="font-size: 12px; color: #64748b;">Pierre Lambert</span>
                                <div class="tag">Apt. 15</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-plus"></i>
                    Enregistrer un paiement
                </h2>
                <button class="close" onclick="closeModal('paymentModal')">&times;</button>
            </div>
            
            <form id="paymentForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="record_payment">
                    
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-user"></i>
                            Informations du paiement
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="payer_select">
                                    Résident <span class="required">*</span>
                                </label>
                                <select name="payer_id" id="payer_select" required>
                                    <option value="">Sélectionner un résident</option>
                                    <?php foreach ($residents as $resident): ?>
                                        <option value="<?php echo $resident['id_member']; ?>">
                                            <?php echo htmlspecialchars($resident['full_name']); ?> - Apt. <?php echo $resident['apartment_number']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="amount">
                                    Montant (MAD) <span class="required">*</span>
                                </label>
                                <input type="number" name="amount" id="amount" step="0.01" min="0" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="month_paid">
                                    Mois payé <span class="required">*</span>
                                </label>
                                <input type="month" name="month_paid" id="month_paid" 
                                       value="<?php echo $month_filter; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="payment_method">
                                    Mode de paiement
                                </label>
                                <select name="payment_method" id="payment_method">
                                    <option value="cash">Espèces</option>
                                    <option value="check">Chèque</option>
                                    <option value="transfer">Virement</option>
                                    <option value="card">Carte bancaire</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">
                                Notes (optionnel)
                            </label>
                            <textarea name="notes" id="notes" rows="3" 
                                      placeholder="Remarques ou informations supplémentaires..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('paymentModal')">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Enregistrer le paiement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden forms for actions -->
    <form id="deletePaymentForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_payment">
        <input type="hidden" name="payer_id" id="deletePayerId">
        <input type="hidden" name="month_paid" id="deleteMonthPaid">
    </form>

    <form id="reminderForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="send_reminder">
        <input type="hidden" name="resident_id" id="reminderResidentId">
        <input type="hidden" name="month" id="reminderMonth">
    </form>

    <script>
        function openPaymentModal() {
            document.getElementById('paymentModal').classList.add('show');
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Record payment
        function recordPayment(payerId, payerName) {
            document.getElementById('payer_select').value = payerId;
            openPaymentModal();
        }

        // Delete payment
        function deletePayment(payerId, monthPaid, payerName) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer le paiement de ${payerName} ?`)) {
                document.getElementById('deletePayerId').value = payerId;
                document.getElementById('deleteMonthPaid').value = monthPaid;
                document.getElementById('deletePaymentForm').submit();
            }
        }

        // Send reminder
        function sendReminder(residentId, residentName) {
            if (confirm(`Envoyer un rappel à ${residentName} ?`)) {
                document.getElementById('reminderResidentId').value = residentId;
                document.getElementById('reminderMonth').value = '<?php echo $month_filter; ?>';
                document.getElementById('reminderForm').submit();
            }
        }

        // Bulk actions
        function sendBulkReminders() {
            const unpaidCount = <?php echo $unpaid_count; ?>;
            if (unpaidCount === 0) {
                alert('Tous les résidents ont payé ce mois-ci !');
                return;
            }
            if (confirm(`Envoyer des rappels aux ${unpaidCount} résidents non-payeurs ?`)) {
                alert('Fonctionnalité en développement...');
            }
        }

        function generateReport() {
            alert('Génération du rapport PDF en cours...');
        }

        function exportData() {
            alert('Export des données en cours...');
        }

        // View toggle functionality
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });


        // Close activity panel
        document.querySelector('.close-btn').addEventListener('click', function() {
            document.querySelector('.activity-panel').style.display = 'none';
            document.querySelector('.main-panel').style.marginRight = '0';
        });



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

            // Animate statistics
            document.querySelectorAll('.quick-card-count').forEach((counter, index) => {
                const target = parseInt(counter.textContent.replace(/[^0-9]/g, ''));
                if (!isNaN(target)) {
                    counter.textContent = '0';
                    setTimeout(() => {
                        animateCounter(counter, target);
                    }, index * 200);
                }
            });


            // Animate storage bar
            setTimeout(() => {
                const storageFill = document.querySelector('.storage-fill');
                if (storageFill) {
                    const width = storageFill.style.width;
                    storageFill.style.width = '0%';
                    setTimeout(() => {
                        storageFill.style.width = width;
                    }, 100);
                }
            }, 500);
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('paymentModal');
            if (event.target === modal) {
                closeModal('paymentModal');
            }
        }

        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(event) {
            const payerId = document.getElementById('payer_select').value;
            const amount = document.getElementById('amount').value;
            const monthPaid = document.getElementById('month_paid').value;

            if (!payerId || !amount || !monthPaid) {
                event.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires.');
                return;
            }

            if (parseFloat(amount) <= 0) {
                event.preventDefault();
                alert('Le montant doit être supérieur à 0.');
                return;
            }

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner spinner"></i> Enregistrement...';

            // Reset button after 3 seconds if form doesn't submit
            setTimeout(() => {
                if (submitBtn.disabled) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }, 3000);
        });

        // Auto-refresh stats every 30 seconds
        setInterval(function() {
            // You can add AJAX calls here to refresh statistics
            console.log('Auto-refresh stats...');
        }, 30000);

        // Enhanced interactions
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
                this.style.boxShadow = '0 8px 20px rgba(0, 0, 0, 0.15)';
            });
            
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // Ctrl/Cmd + N for new payment
            if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
                event.preventDefault();
                openPaymentModal();
            }
            
            // Escape key to close modal
            if (event.key === 'Escape') {
                closeModal('paymentModal');
            }
        });

        // Enhanced table interactions
        document.querySelectorAll('.table-row').forEach(row => {
            row.addEventListener('click', function(event) {
                // Don't trigger if clicking on buttons
                if (event.target.closest('.action-buttons')) {
                    return;
                }
                
                // Add selection effect
                document.querySelectorAll('.table-row').forEach(r => r.classList.remove('selected'));
                this.classList.add('selected');
            });
        });

        // Performance monitoring
        function trackAction(actionName) {
            const startTime = performance.now();
            
            return function() {
                const endTime = performance.now();
                console.log(`Action ${actionName} completed in ${endTime - startTime} ms`);
            };
        }

        // Export functionality with proper formatting
        function exportData() {
            const data = [];
            document.querySelectorAll('.table-row').forEach(row => {
                const resident = row.querySelector('.file-name').textContent;
                const apartment = row.querySelector('td:nth-child(2)').textContent.trim();
                const status = row.querySelector('.status-badge').textContent.trim();
                const date = row.querySelector('td:nth-child(4)').textContent.trim();
                
                data.push({
                    resident,
                    apartment,
                    status,
                    date
                });
            });
            
            // Convert to CSV
            const csv = [
                ['Résident', 'Appartement', 'Statut', 'Date Paiement'],
                ...data.map(row => [row.resident, row.apartment, row.status, row.date])
            ].map(row => row.join(',')).join('\n');
            
            // Download
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `paiements_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Real-time updates simulation
        function simulateRealTimeUpdates() {
            // Simulate new payment notification
            setTimeout(() => {
                const activityPanel = document.querySelector('.activity-panel');
                if (activityPanel && Math.random() > 0.7) {
                    // Add new activity item
                    const newActivity = document.createElement('div');
                    newActivity.className = 'activity-item slide-in';
                    newActivity.innerHTML = `
                        <div class="activity-icon payment">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">Nouveau paiement reçu</div>
                            <div class="activity-time">À l'instant</div>
                            <div class="activity-meta">
                                <div class="sharing-avatar">NR</div>
                                <span style="font-size: 12px; color: #64748b;">Nouveau Résident</span>
                                <div class="tag">350 DH</div>
                            </div>
                        </div>
                    `;
                    
                    const firstActivity = activityPanel.querySelector('.activity-item');
                    if (firstActivity) {
                        firstActivity.parentNode.insertBefore(newActivity, firstActivity);
                    }
                }
            }, Math.random() * 30000 + 10000); // Random between 10-40 seconds
        }

        // Initialize real-time updates
        // simulateRealTimeUpdates();

        // Add CSS for selected row
        const style = document.createElement('style');
        style.textContent = `
            .table-row.selected {
                background-color: rgba(59, 130, 246, 0.1) !important;
                border-left: 4px solid #FFCB32;
            }
            
            .table-row {
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .table-row:hover {
                background-color: #f8fafc !important;
                transform: translateX(2px);
            }
            
            @media (prefers-reduced-motion: reduce) {
                .slide-in, .fade-in, .spinner {
                    animation: none;
                }
                
                .table-row:hover {
                    transform: none;
                }
                
                .quick-card:hover, .action-btn:hover {
                    transform: none;
                }
            }
        `;
        document.head.appendChild(style);

        console.log('Syndic Payments Dashboard initialized successfully');
        </script>