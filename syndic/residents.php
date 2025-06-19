<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if includes/auth.php exists, if not, use basic role check
if (file_exists(__DIR__ . '/../includes/auth.php')) {
    require_once __DIR__ . '/../includes/auth.php';
    requireRole('syndic');
    $current_user = getCurrentUser();
} else {
    // Fallback to basic check if auth.php doesn't exist yet
    if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'syndic' && $_SESSION['user_role'] !== 'member')) {
        header('Location: ../public/login.php');
        exit();
    }
    $current_user = [
        'id' => $_SESSION['user_id'],
        'role' => $_SESSION['user_role'],
        'name' => $_SESSION['user_name'] ?? 'Syndic',
        'email' => $_SESSION['user_email'] ?? ''
    ];
}

// Function to send email with credentials
function sendCredentialsEmail($to_email, $full_name, $email, $password, $building_name) {
    $subject = "Vos identifiants de connexion - " . $building_name;
    
    $message = "
    <html>
    <head></head>
    <body>
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #FFCB32;'>Bienvenue dans votre espace résident</h2>
            <p>Bonjour " . htmlspecialchars($full_name) . ",</p>
            
            <p>Votre compte résident a été créé pour la copropriété <strong>" . htmlspecialchars($building_name) . "</strong>.</p>
            
            <div style='background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='margin-top: 0;'>Vos identifiants de connexion :</h3>
                <p><strong>Email :</strong> " . htmlspecialchars($email) . "</p>
                <p><strong>Mot de passe :</strong> " . htmlspecialchars($password) . "</p>
            </div>
            
            <p>Vous pouvez vous connecter à votre espace à l'adresse suivante :</p>
            <p><a href='http://localhost/syndicplatform/public/login.php' style='background: #FFCB32; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Se connecter à mon espace</a></p>
            
            <p><strong>Nous vous recommandons fortement de changer votre mot de passe lors de votre première connexion.</strong></p>
            
            <p>Cordialement,<br>L'équipe de gestion</p>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: noreply@syndicway.com' . "\r\n";
    
    return mail($to_email, $subject, $message, $headers);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_resident') {
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $apartment_number = intval($_POST['apartment_number']);
            $apartment_floor = trim($_POST['apartment_floor']);
            $apartment_type = trim($_POST['apartment_type']);
            $send_email = isset($_POST['send_email']);
            
            // Generate random password
            $password = 'Resident' . rand(1000, 9999);
            
            // Check if email exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM member WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Un compte avec cet email existe déjà.");
            }
            
            // Get first available building
            $stmt = $conn->prepare("
                SELECT r.id_residence, r.name as building_name
                FROM residence r
                LIMIT 1
            ");
            $stmt->execute();
            $building = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$building) {
                throw new Exception("Aucun bâtiment disponible dans le système.");
            }
            
            // Check if apartment number already exists in this building
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM apartment 
                WHERE id_residence = ? AND number = ? AND floor = ?
            ");
            $stmt->execute([$building['id_residence'], $apartment_number, $apartment_floor]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cet appartement existe déjà dans ce bâtiment.");
            }
            
            $conn->beginTransaction();
            
            // Create member account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO member (full_name, email, password, phone, role, status, date_created) 
                VALUES (?, ?, ?, ?, 1, 'active', NOW())
            ");
            $stmt->execute([$full_name, $email, $hashed_password, $phone]);
            $member_id = $conn->lastInsertId();
            
            // Create apartment
            $stmt = $conn->prepare("
                INSERT INTO apartment (id_residence, id_member, type, floor, number) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$building['id_residence'], $member_id, $apartment_type, $apartment_floor, $apartment_number]);
            
            // Send notification to the new resident
            $stmt = $conn->prepare("
                INSERT INTO member_notifications (id_sender, id_receiver, date_notification)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$current_user['id'], $member_id]);
            
            $conn->commit();
            
            // Send email if requested
            if ($send_email) {
                if (sendCredentialsEmail($email, $full_name, $email, $password, $building['building_name'])) {
                    $_SESSION['success'] = "Résident créé avec succès. Email avec les identifiants envoyé à " . htmlspecialchars($email);
                } else {
                    $_SESSION['success'] = "Résident créé avec succès. Mot de passe: " . $password . " (l'email n'a pas pu être envoyé)";
                }
            } else {
                $_SESSION['success'] = "Résident créé avec succès. Mot de passe: " . $password;
            }
            
        } elseif ($action === 'update_resident') {
            $member_id = intval($_POST['member_id']);
            $full_name = trim($_POST['full_name']);
            $phone = trim($_POST['phone']);
            $apartment_number = intval($_POST['apartment_number']);
            $apartment_floor = trim($_POST['apartment_floor']);
            $apartment_type = trim($_POST['apartment_type']);
            
            // Verify this resident exists
            $stmt = $conn->prepare("
                SELECT ap.id_apartment
                FROM apartment ap
                WHERE ap.id_member = ?
            ");
            $stmt->execute([$member_id]);
            $apartment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$apartment) {
                throw new Exception("Résident non trouvé.");
            }
            
            $conn->beginTransaction();
            
            // Update member
            $stmt = $conn->prepare("
                UPDATE member 
                SET full_name = ?, phone = ? 
                WHERE id_member = ?
            ");
            $stmt->execute([$full_name, $phone, $member_id]);
            
            // Update apartment
            $stmt = $conn->prepare("
                UPDATE apartment 
                SET type = ?, floor = ?, number = ?
                WHERE id_apartment = ?
            ");
            $stmt->execute([$apartment_type, $apartment_floor, $apartment_number, $apartment['id_apartment']]);
            
            $conn->commit();
            
            $_SESSION['success'] = "Informations du résident mises à jour avec succès.";
            
        } elseif ($action === 'toggle_status') {
            $member_id = intval($_POST['member_id']);
            $new_status = $_POST['new_status'];
            
            $stmt = $conn->prepare("UPDATE member SET status = ? WHERE id_member = ?");
            $stmt->execute([$new_status, $member_id]);
            
            $status_text = $new_status === 'active' ? 'activé' : 'suspendu';
            $_SESSION['success'] = "Résident {$status_text} avec succès.";
            
        } elseif ($action === 'reset_password') {
            $member_id = intval($_POST['member_id']);
            $send_email = isset($_POST['send_email']);
            
            // Get resident info
            $stmt = $conn->prepare("
                SELECT m.email, m.full_name, r.name as building_name
                FROM member m
                JOIN apartment ap ON ap.id_member = m.id_member
                JOIN residence r ON r.id_residence = ap.id_residence
                WHERE m.id_member = ?
            ");
            $stmt->execute([$member_id]);
            $resident = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$resident) {
                throw new Exception("Résident non trouvé.");
            }
            
            // Generate new password
            $new_password = 'Resident' . rand(1000, 9999);
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE member SET password = ? WHERE id_member = ?");
            $stmt->execute([$hashed_password, $member_id]);
            
            // Send email if requested
            if ($send_email) {
                if (sendCredentialsEmail($resident['email'], $resident['full_name'], $resident['email'], $new_password, $resident['building_name'])) {
                    $_SESSION['success'] = "Mot de passe réinitialisé et envoyé par email.";
                } else {
                    $_SESSION['success'] = "Mot de passe réinitialisé: " . $new_password . " (l'email n'a pas pu être envoyé)";
                }
            } else {
                $_SESSION['success'] = "Mot de passe réinitialisé: " . $new_password;
            }
        }
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header('Location: residents.php');
    exit();
}

// Get filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$floor_filter = $_GET['floor'] ?? '';

// Get all residents
try {
    $where_conditions = ["m.role = 1"];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(m.full_name LIKE ? OR m.email LIKE ? OR ap.number LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if ($status_filter) {
        $where_conditions[] = "m.status = ?";
        $params[] = $status_filter;
    }
    
    if ($floor_filter) {
        $where_conditions[] = "ap.floor = ?";
        $params[] = $floor_filter;
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    $query = "
        SELECT 
            m.id_member,
            m.full_name,
            m.email,
            m.phone,
            m.status,
            m.date_created,
            ap.number as apartment_number,
            ap.floor,
            ap.type as apartment_type,
            r.name as building_name
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        JOIN residence r ON r.id_residence = ap.id_residence
        WHERE {$where_clause}
        ORDER BY ap.floor ASC, ap.number ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available floors for filter
    $stmt = $conn->prepare("
        SELECT DISTINCT ap.floor
        FROM apartment ap
        ORDER BY ap.floor ASC
    ");
    $stmt->execute();
    $floors = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get building info
    $stmt = $conn->prepare("
        SELECT r.name, r.address, c.city_name
        FROM residence r
        JOIN city c ON c.id_city = r.id_city
        LIMIT 1
    ");
    $stmt->execute();
    $building_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $stats = [
        'total_residents' => count($residents),
        'active_residents' => 0,
        'pending_residents' => 0,
        'total_floors' => count($floors)
    ];
    
    foreach ($residents as $resident) {
        if ($resident['status'] === 'active') $stats['active_residents']++;
        if ($resident['status'] === 'pending') $stats['pending_residents']++;
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors du chargement des résidents.";
    $residents = [];
    $floors = [];
    $building_info = null;
    $stats = ['total_residents' => 0, 'active_residents' => 0, 'pending_residents' => 0, 'total_floors' => 0];
}

$page_title = "Gestion des Résidents - Syndic Way";

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
            animation: slideInDown 0.3s ease;
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

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .quick-card.residents .quick-card-icon { background: #FFCB32; }
        .quick-card.active .quick-card-icon { background: #10b981; }
        .quick-card.pending .quick-card-icon { background: #f59e0b; }
        .quick-card.floors .quick-card-icon { background: #8b5cf6; }

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
            color: #FFCB32;
        }

        /* Table Header */
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            justify-content : end;
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
            background:rgb(246, 192, 31)b;
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

        .status-active {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-inactive {
            background: #f1f5f9;
            color: #64748b;
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

        .activity-icon.create { background: #10b981; }
        .activity-icon.update { background: #FFCB32; }
        .activity-icon.alert { background: #f59e0b; }

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

        /* Filters */
        .filters-section {
            background: white;
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
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

        .filter-group button {
            background: #FFCB32;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            margin-top: 20px;
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
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            padding: 24px 24px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }

        .modal-header h2 {
            color: #1e293b;
            font-size: 18px;
            font-weight: 600;
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
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .close:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .modal-body {
            padding: 0 24px 24px;
        }

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
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
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
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }

        .required {
            color: #ef4444;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #FFCB32;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 16px 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: 16px;
            height: 16px;
        }

        .help-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }

        .modal-actions {
            padding: 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: #FFCB32;
            color: white;
        }

        .btn-primary:hover {
            background:rgb(255, 230, 154);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #1e293b;
        }

        .empty-state p {
            margin-bottom: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .content-area {
                flex-direction: column;
            }
            
            .activity-panel {
                width: 100%;
            }
            
            .quick-access-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
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
            <?php require_once __DIR__ ."/../includes/navigation_syndic.php"?>


            <!-- Quick Access -->
            <div class="quick-access">
                <div class="quick-access-header">
                    <div class="quick-access-title">
                        Gestion des Résidents
                        <?php if ($building_info): ?>
                            - <?php echo htmlspecialchars($building_info['name']); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card residents">
                        <div class="quick-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="quick-card-title">Total Résidents</div>
                        <div class="quick-card-count"><?php echo $stats['total_residents']; ?></div>
                        <div class="quick-card-stats">Dans votre bâtiment</div>
                    </div>

                    <div class="quick-card active">
                        <div class="quick-card-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="quick-card-title">Résidents Actifs</div>
                        <div class="quick-card-count"><?php echo $stats['active_residents']; ?></div>
                        <div class="quick-card-stats">Comptes activés</div>
                    </div>

                    <div class="quick-card pending">
                        <div class="quick-card-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div class="quick-card-title">En Attente</div>
                        <div class="quick-card-count"><?php echo $stats['pending_residents']; ?></div>
                        <div class="quick-card-stats">Activations pendantes</div>
                    </div>

                    <div class="quick-card floors">
                        <div class="quick-card-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="quick-card-title">Étages</div>
                        <div class="quick-card-count"><?php echo $stats['total_floors']; ?></div>
                        <div class="quick-card-stats">Niveaux du bâtiment</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" id="filtersForm">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="search">Rechercher</label>
                            <input type="text" name="search" id="search" 
                                   placeholder="Nom, email ou appartement..." 
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
                            <label for="floor">Étage</label>
                            <select name="floor" id="floor">
                                <option value="">Tous</option>
                                <?php foreach ($floors as $floor): ?>
                                    <option value="<?php echo htmlspecialchars($floor); ?>" 
                                            <?php echo $floor_filter === $floor ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($floor); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit">
                                <i class="fas fa-search"></i>
                                Appliquer filtres
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div class="main-panel">
                    <!-- Breadcrumb -->
                    <div class="breadcrumb">
                        <a href="dashboard.php">Accueil</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <a href="#">Gestion Résidents</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Tous les Résidents</span>
                    </div>

                    <!-- Table Header -->
                    <div class="table-header">
                        <button class="add-btn" onclick="openCreateModal()">
                            <i class="fas fa-plus"></i>
                            Nouveau résident
                        </button>
                    </div>

                    <!-- Data Table -->
                    <table class="data-table">
                        <thead class="table-header-row">
                            <tr>
                                <th>Résident</th>
                                <th>Appartement</th>
                                <th>Contact</th>
                                <th>Statut</th>
                                <th>Inscrit</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($residents)): ?>
                            <tr class="table-row">
                                <td colspan="6" style="text-align: center; padding: 40px;">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <h3>Aucun résident trouvé</h3>
                                        <p>Commencez par ajouter vos premiers résidents</p>
                                        <button class="btn btn-primary" onclick="openCreateModal()">
                                            <i class="fas fa-plus"></i>
                                            Ajouter un résident
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($residents as $resident): ?>
                                <tr class="table-row">
                                    <td>
                                        <div class="file-item">
                                            <div class="table-icon" style="background: #FFCB32;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="file-info">
                                                <div class="file-name"><?php echo htmlspecialchars($resident['full_name']); ?></div>
                                                <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($resident['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;">Apt. <?php echo $resident['apartment_number']; ?></div>
                                        <div style="font-size: 12px; color: #64748b;">
                                            Étage <?php echo htmlspecialchars($resident['floor']); ?> • <?php echo htmlspecialchars($resident['apartment_type']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 14px;"><?php echo htmlspecialchars($resident['email']); ?></div>
                                        <?php if ($resident['phone']): ?>
                                            <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($resident['phone']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $resident['status']; ?>">
                                            <?php 
                                                $status_text = [
                                                    'active' => 'Actif',
                                                    'pending' => 'En attente',
                                                    'inactive' => 'Inactif'
                                                ];
                                                echo $status_text[$resident['status']] ?? $resident['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($resident['date_created'])); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 4px;">
                                            <button class="btn btn-sm btn-secondary" 
                                                    onclick="editResident(<?php echo htmlspecialchars(json_encode($resident)); ?>)"
                                                    title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <?php if ($resident['status'] === 'active'): ?>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick="toggleStatus(<?php echo $resident['id_member']; ?>, 'inactive')"
                                                        title="Suspendre">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="toggleStatus(<?php echo $resident['id_member']; ?>, 'active')"
                                                        title="Activer">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="btn btn-sm btn-warning" 
                                                    onclick="resetPassword(<?php echo $resident['id_member']; ?>, '<?php echo htmlspecialchars($resident['full_name']); ?>')"
                                                    title="Reset mot de passe">
                                                <i class="fas fa-key"></i>
                                            </button>
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


                    <?php 
                    $recent_residents = array_slice($residents, 0, 5);
                    if (!empty($recent_residents)):
                    ?>
                        <?php foreach ($recent_residents as $index => $resident): ?>
                        <div class="activity-item">
                            <div class="activity-icon create">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">Résident ajouté</div>
                                <div class="activity-time"><?php echo date('d/m/Y', strtotime($resident['date_created'])); ?></div>
                                <div class="activity-meta">
                                    <div class="sharing-avatar"><?php echo strtoupper(substr($resident['full_name'], 0, 1)); ?></div>
                                    <span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($resident['full_name']); ?></span>
                                    <div class="tag">Apt. <?php echo $resident['apartment_number']; ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #64748b;">
                            <i class="fas fa-clock" style="font-size: 32px; margin-bottom: 12px; opacity: 0.3;"></i>
                            <div style="font-size: 14px;">Aucune activité récente</div>
                            <div style="font-size: 12px; margin-top: 4px;">Les actions apparaîtront ici</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Resident Modal -->
    <div id="residentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">
                    <i class="fas fa-user-plus"></i>
                    <span id="modalTitleText">Nouveau résident</span>
                </h2>
                <button class="close" onclick="closeModal('residentModal')">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="residentForm" method="POST">
                    <input type="hidden" name="action" id="formAction" value="create_resident">
                    <input type="hidden" name="member_id" id="memberId">
                    
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-user"></i>
                            Informations personnelles
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name">
                                    Nom complet <span class="required">*</span>
                                </label>
                                <input type="text" name="full_name" id="full_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">
                                    Email <span class="required">*</span>
                                </label>
                                <input type="email" name="email" id="email" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone">Téléphone</label>
                            <input type="tel" name="phone" id="phone">
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-home"></i>
                            Informations de l'appartement
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="apartment_number">
                                    Numéro d'appartement <span class="required">*</span>
                                </label>
                                <input type="number" name="apartment_number" id="apartment_number" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="apartment_floor">
                                    Étage <span class="required">*</span>
                                </label>
                                <input type="text" name="apartment_floor" id="apartment_floor" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="apartment_type">
                                Type d'appartement <span class="required">*</span>
                            </label>
                            <select name="apartment_type" id="apartment_type" required>
                                <option value="">Choisir un type</option>
                                <option value="Studio">Studio</option>
                                <option value="T1">T1</option>
                                <option value="T2">T2</option>
                                <option value="T3">T3</option>
                                <option value="T4">T4</option>
                                <option value="T5">T5</option>
                                <option value="Duplex">Duplex</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-section" id="emailSection">
                        <div class="form-section-title">
                            <i class="fas fa-envelope"></i>
                            Options d'envoi
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" name="send_email" id="send_email" checked>
                            <label for="send_email">
                                Envoyer les identifiants de connexion par email
                            </label>
                        </div>
                        <div class="help-text">
                            Un mot de passe sera généré automatiquement et envoyé au résident par email.
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('residentModal')">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="submit" form="residentForm" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> <span id="submitText">Créer le résident</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-key"></i>
                    Réinitialiser le mot de passe
                </h2>
                <button class="close" onclick="closeModal('passwordModal')">&times;</button>
            </div>
            
            <div class="modal-body">
                <p>Voulez-vous réinitialiser le mot de passe de <strong id="residentName"></strong> ?</p>
                <p style="color: #64748b; font-size: 14px;">Un nouveau mot de passe sera généré automatiquement.</p>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="send_password_email" id="send_password_email" checked>
                    <label for="send_password_email">
                        Envoyer le nouveau mot de passe par email
                    </label>
                </div>

                <form id="passwordForm" method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="member_id" id="passwordMemberId">
                    <input type="hidden" name="send_email" id="passwordSendEmail" value="1">
                </form>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('passwordModal')">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button type="submit" form="passwordForm" class="btn btn-warning">
                    <i class="fas fa-key"></i> Réinitialiser
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden forms for actions -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="member_id" id="statusMemberId">
        <input type="hidden" name="new_status" id="newStatus">
    </form>

    <script>
        // Global variables
        let isEditMode = false;

        // Open create modal
        function openCreateModal() {
            isEditMode = false;
            document.getElementById('formAction').value = 'create_resident';
            document.getElementById('modalTitleText').textContent = 'Nouveau résident';
            document.getElementById('submitText').textContent = 'Créer le résident';
            document.getElementById('emailSection').style.display = 'block';
            
            // Reset form
            document.getElementById('residentForm').reset();
            document.getElementById('send_email').checked = true;
            document.getElementById('email').disabled = false;
            
            document.getElementById('residentModal').classList.add('show');
        }

        // Edit resident
        function editResident(resident) {
            isEditMode = true;
            document.getElementById('formAction').value = 'update_resident';
            document.getElementById('memberId').value = resident.id_member;
            document.getElementById('modalTitleText').textContent = 'Modifier le résident';
            document.getElementById('submitText').textContent = 'Mettre à jour';
            document.getElementById('emailSection').style.display = 'none';
            
            // Fill form with resident data
            document.getElementById('full_name').value = resident.full_name;
            document.getElementById('email').value = resident.email;
            document.getElementById('email').disabled = true; // Don't allow email change
            document.getElementById('phone').value = resident.phone || '';
            document.getElementById('apartment_number').value = resident.apartment_number;
            document.getElementById('apartment_floor').value = resident.floor;
            document.getElementById('apartment_type').value = resident.apartment_type;
            
            document.getElementById('residentModal').classList.add('show');
        }

        // Toggle resident status
        function toggleStatus(memberId, newStatus) {
            const statusText = newStatus === 'active' ? 'activer' : 'suspendre';
            if (confirm(`Êtes-vous sûr de vouloir ${statusText} ce résident ?`)) {
                document.getElementById('statusMemberId').value = memberId;
                document.getElementById('newStatus').value = newStatus;
                document.getElementById('statusForm').submit();
            }
        }

        // Reset password
        function resetPassword(memberId, residentName) {
            document.getElementById('residentName').textContent = residentName;
            document.getElementById('passwordMemberId').value = memberId;
            document.getElementById('passwordModal').classList.add('show');
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
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

        // Quick card clicks
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('click', function() {
                const cardType = this.classList[1];
                console.log(`Navigating to ${cardType} section`);
            });
        });

        // Table row hover effects
        document.querySelectorAll('.table-row').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8fafc';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });



        // Storage animation on load
        window.addEventListener('load', function() {
            const storageFill = document.querySelector('.storage-fill');
            const originalWidth = storageFill.style.width;
            storageFill.style.width = '0%';
            setTimeout(() => {
                storageFill.style.width = originalWidth;
            }, 500);
        });

        // Handle checkbox for password email
        document.addEventListener('DOMContentLoaded', function() {
            const sendPasswordCheckbox = document.getElementById('send_password_email');
            const passwordSendEmailHidden = document.getElementById('passwordSendEmail');
            
            if (sendPasswordCheckbox) {
                sendPasswordCheckbox.addEventListener('change', function() {
                    passwordSendEmailHidden.value = this.checked ? '1' : '0';
                });
            }

            // Auto-hide alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });

            // Close modals when clicking outside
            window.addEventListener('click', function(event) {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (event.target === modal) {
                        modal.classList.remove('show');
                    }
                });
            });

            // Form validation
            const residentForm = document.getElementById('residentForm');
            if (residentForm) {
                residentForm.addEventListener('submit', function(e) {
                    const apartmentNumber = document.getElementById('apartment_number').value;
                    const apartmentFloor = document.getElementById('apartment_floor').value;
                    
                    if (!apartmentNumber || apartmentNumber < 1) {
                        e.preventDefault();
                        alert('Veuillez entrer un numéro d\'appartement valide.');
                        return;
                    }
                    
                    if (!apartmentFloor.trim()) {
                        e.preventDefault();
                        alert('Veuillez entrer l\'étage de l\'appartement.');
                        return;
                    }

                    // Show loading state
                    const submitBtn = document.getElementById('submitBtn');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
                    
                    // Re-enable button after 10 seconds as fallback
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }, 10000);
                });
            }

            // Phone number formatting
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    // Remove non-digits
                    let value = e.target.value.replace(/\D/g, '');
                    
                    // Format as XX XX XX XX XX for Moroccan numbers
                    if (value.length > 0) {
                        if (value.startsWith('0')) {
                            value = value.replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5');
                        } else if (value.startsWith('212')) {
                            value = value.replace(/(\d{3})(\d{1})(\d{2})(\d{2})(\d{2})(\d{2})/, '+$1 $2 $3 $4 $5 $6');
                        }
                    }
                    
                    e.target.value = value;
                });
            }

            // Email validation
            const emailInput = document.getElementById('email');
            if (emailInput) {
                emailInput.addEventListener('blur', function(e) {
                    const email = e.target.value;
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    
                    if (email && !emailRegex.test(email)) {
                        e.target.style.borderColor = '#e53e3e';
                        alert('Veuillez entrer une adresse email valide.');
                    } else {
                        e.target.style.borderColor = '#e2e8f0';
                    }
                });
            }

            // Apartment number validation
            const apartmentInput = document.getElementById('apartment_number');
            if (apartmentInput) {
                apartmentInput.addEventListener('input', function(e) {
                    if (e.target.value < 1) {
                        e.target.value = 1;
                    }
                    if (e.target.value > 9999) {
                        e.target.value = 9999;
                    }
                });
            }

            // Real-time search
            const searchInput = document.getElementById('search');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function(e) {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        if (e.target.value.length >= 2 || e.target.value.length === 0) {
                            document.getElementById('filtersForm').submit();
                        }
                    }, 500);
                });
            }

            // Animate statistics
            const statNumbers = document.querySelectorAll('.quick-card-count');
            statNumbers.forEach(stat => {
                const target = parseInt(stat.textContent);
                if (!isNaN(target)) {
                    animateNumber(stat, target);
                }
            });

            function animateNumber(element, target) {
                let current = 0;
                const increment = target / 30;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    element.textContent = Math.floor(current);
                }, 50);
            }

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + N to create new resident
                if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                    e.preventDefault();
                    openCreateModal();
                }
                
                // Escape to close modals
                if (e.key === 'Escape') {
                    const openModals = document.querySelectorAll('.modal.show');
                    openModals.forEach(modal => {
                        modal.classList.remove('show');
                    });
                }
            });

            // Add ripple effect to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        background: rgba(255, 255, 255, 0.5);
                        border-radius: 50%;
                        transform: scale(0);
                        animation: ripple 0.6s ease-out;
                        pointer-events: none;
                    `;
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => ripple.remove(), 600);
                });
            });

            // Mobile responsive behavior
            function handleMobileView() {
                if (window.innerWidth <= 768) {
                    const sidebar = document.querySelector('.sidebar');
                    const activityPanel = document.querySelector('.activity-panel');
                    
                    // Create mobile toggle if it doesn't exist
                    if (!document.querySelector('.mobile-toggle')) {
                        const toggle = document.createElement('button');
                        toggle.innerHTML = '<i class="fas fa-bars"></i>';
                        toggle.className = 'mobile-toggle';
                        toggle.style.cssText = `
                            position: fixed;
                            top: 1rem;
                            left: 1rem;
                            z-index: 1001;
                            background: #FFCB32;
                            color: white;
                            border: none;
                            padding: 0.75rem;
                            border-radius: 8px;
                            font-size: 1.2rem;
                            cursor: pointer;
                            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                        `;
                        
                        toggle.addEventListener('click', () => {
                            const isVisible = sidebar.style.transform === 'translateX(0px)';
                            sidebar.style.transform = isVisible ? 'translateX(-100%)' : 'translateX(0px)';
                            sidebar.style.zIndex = '1002';
                        });
                        
                        document.body.appendChild(toggle);
                    }
                }
            }

            handleMobileView();
            window.addEventListener('resize', handleMobileView);
        });

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(2);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>