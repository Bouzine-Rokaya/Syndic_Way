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
        if ($action === 'send_message') {
            $receiver_id = intval($_POST['receiver_id']);
            $subject = trim($_POST['subject'] ?? '');
            $content = trim($_POST['content']);
            
            if (empty($content)) {
                throw new Exception("Message content is required.");
            }
            
            // Verify receiver exists
            $stmt = $conn->prepare("SELECT id_member, full_name FROM member WHERE id_member = ?");
            $stmt->execute([$receiver_id]);
            $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$receiver) {
                throw new Exception("Recipient not found.");
            }
            
            // Insert message
            $stmt = $conn->prepare("
                INSERT INTO member_messages (id_sender, id_receiver, date_message, subject, content) 
                VALUES (?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([$current_user['id'], $receiver_id, $subject, $content]);
            
            $_SESSION['success'] = "Message sent successfully to " . htmlspecialchars($receiver['full_name']) . ".";
            
        } elseif ($action === 'delete_message') {
            $message_id = intval($_POST['message_id']);
            
            $stmt = $conn->prepare("
                DELETE FROM member_messages 
                WHERE id_message = ? AND (id_sender = ? OR id_receiver = ?)
            ");
            $stmt->execute([$message_id, $current_user['id'], $current_user['id']]);
            
            $_SESSION['success'] = "Message deleted successfully.";
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header('Location: messages.php');
    exit();
}

// Get messages data
try {
    // Get all residents for messaging
    $stmt = $conn->prepare("
        SELECT 
            m.id_member,
            m.full_name,
            m.email,
            m.status,
            ap.number as apartment_number,
            ap.floor,
            ap.type
        FROM member m
        LEFT JOIN apartment ap ON ap.id_member = m.id_member
        WHERE m.role = 1
        ORDER BY ap.floor, ap.number, m.full_name ASC
    ");
    $stmt->execute();
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get received messages (from residents)
    $stmt = $conn->prepare("
        SELECT 
            mm.*,
            m.full_name as sender_name,
            m.email as sender_email,
            ap.number as sender_apartment,
            ap.floor as sender_floor,
            DATE_FORMAT(mm.date_message, '%d/%m/%Y à %H:%i') as formatted_date
        FROM member_messages mm
        JOIN member m ON mm.id_sender = m.id_member
        LEFT JOIN apartment ap ON ap.id_member = m.id_member
        WHERE mm.id_receiver = ?
        ORDER BY mm.date_message DESC
        LIMIT 50
    ");
    $stmt->execute([$current_user['id']]);
    $received_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sent messages (to residents)
    $stmt = $conn->prepare("
        SELECT 
            mm.*,
            m.full_name as receiver_name,
            m.email as receiver_email,
            ap.number as receiver_apartment,
            ap.floor as receiver_floor,
            DATE_FORMAT(mm.date_message, '%d/%m/%Y à %H:%i') as formatted_date
        FROM member_messages mm
        JOIN member m ON mm.id_receiver = m.id_member
        LEFT JOIN apartment ap ON ap.id_member = m.id_member
        WHERE mm.id_sender = ?
        ORDER BY mm.date_message DESC
        LIMIT 50
    ");
    $stmt->execute([$current_user['id']]);
    $sent_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get conversation threads
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            CASE 
                WHEN mm.id_sender = ? THEN mm.id_receiver 
                ELSE mm.id_sender 
            END as contact_id,
            CASE 
                WHEN mm.id_sender = ? THEN receiver.full_name 
                ELSE sender.full_name 
            END as contact_name,
            CASE 
                WHEN mm.id_sender = ? THEN receiver.email 
                ELSE sender.email 
            END as contact_email,
            CASE 
                WHEN mm.id_sender = ? THEN ap_receiver.number 
                ELSE ap_sender.number 
            END as contact_apartment,
            MAX(mm.date_message) as last_message_date,
            COUNT(*) as message_count
        FROM member_messages mm
        LEFT JOIN member sender ON sender.id_member = mm.id_sender
        LEFT JOIN member receiver ON receiver.id_member = mm.id_receiver
        LEFT JOIN apartment ap_sender ON ap_sender.id_member = sender.id_member
        LEFT JOIN apartment ap_receiver ON ap_receiver.id_member = receiver.id_member
        WHERE mm.id_sender = ? OR mm.id_receiver = ?
        GROUP BY contact_id, contact_name, contact_email, contact_apartment
        ORDER BY last_message_date DESC
    ");
    $stmt->execute([
        $current_user['id'], $current_user['id'], $current_user['id'], 
        $current_user['id'], $current_user['id'], $current_user['id']
    ]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $total_received = count($received_messages);
    $total_sent = count($sent_messages);
    $total_conversations = count($conversations);
    $total_residents = count($residents);
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error loading messages: " . $e->getMessage();
    $residents = [];
    $received_messages = [];
    $sent_messages = [];
    $conversations = [];
    $total_received = 0;
    $total_sent = 0;
    $total_conversations = 0;
    $total_residents = 0;
}

$page_title = "Messages Management - Syndic Way";
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

        .quick-card.received .quick-card-icon { background: #10b981; }
        .quick-card.sent .quick-card-icon { background: #FFCB32; }
        .quick-card.conversations .quick-card-icon { background: #f59e0b; }
        .quick-card.residents .quick-card-icon { background: #8b5cf6; }

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
            padding: 24px;
        }

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

        /* Tab Navigation */
        .tab-navigation {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .tab-button {
            padding: 12px 20px;
            background: none;
            border: none;
            color: #64748b;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .tab-button.active {
            color: #FFCB32;
            border-bottom-color: #FFCB32;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Message Table */
        .messages-table {
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

        .message-item {
            display: flex;
            align-items: center;
        }

        .message-icon {
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

        .message-info {
            flex: 1;
        }

        .message-subject {
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .message-preview {
            font-size: 12px;
            color: #64748b;
        }

        .message-received .message-icon { background: #10b981; }
        .message-sent .message-icon { background: #FFCB32; }

        /* Action Buttons */
        .btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #FFCB32;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
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
            background: #2563eb;
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
            background-color: rgba(0,0,0,0.5);
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
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .close {
            font-size: 24px;
            font-weight: bold;
            color: #64748b;
            cursor: pointer;
            border: none;
            background: none;
        }

        .close:hover {
            color: #1e293b;
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .required {
            color: #ef4444;
        }

        .modal-actions {
            padding: 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
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
            opacity: 0.3;
        }

        .empty-state h3 {
            margin-bottom: 8px;
            color: #374151;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .quick-access-grid {
                grid-template-columns: repeat(2, 1fr);
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
                    <div class="quick-access-title">Aperçu Messages</div>
                    <button class="add-btn" onclick="openComposeModal()">
                        <i class="fas fa-plus"></i>
                        Nouveau Message
                    </button>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card received" onclick="showTab('received')">
                        <div class="quick-card-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <div class="quick-card-title">Messages Reçus</div>
                        <div class="quick-card-count"><?php echo $total_received; ?></div>
                        <div class="quick-card-stats">De résidents</div>
                    </div>

                    <div class="quick-card sent" onclick="showTab('sent')">
                        <div class="quick-card-icon">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="quick-card-title">Messages Envoyés</div>
                        <div class="quick-card-count"><?php echo $total_sent; ?></div>
                        <div class="quick-card-stats">Aux résidents</div>
                    </div>

                    <div class="quick-card conversations" onclick="showTab('conversations')">
                        <div class="quick-card-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="quick-card-title">Conversations</div>
                        <div class="quick-card-count"><?php echo $total_conversations; ?></div>
                        <div class="quick-card-stats">Actives</div>
                    </div>

                    <div class="quick-card residents" onclick="openComposeModal()">
                        <div class="quick-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="quick-card-title">Résidents</div>
                        <div class="quick-card-count"><?php echo $total_residents; ?></div>
                        <div class="quick-card-stats">Contactables</div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Breadcrumb -->
                <div class="breadcrumb">
                    <a href="dashboard.php">Accueil</a>
                    <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                    <a href="#">Communication</a>
                    <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                    <span>Messages</span>
                </div>

                <!-- Tab Navigation -->
                <div class="tab-navigation">
                    <button class="tab-button active" onclick="showTab('received')">
                        <i class="fas fa-inbox"></i> Messages Reçus (<?php echo $total_received; ?>)
                    </button>
                    <button class="tab-button" onclick="showTab('sent')">
                        <i class="fas fa-paper-plane"></i> Messages Envoyés (<?php echo $total_sent; ?>)
                    </button>
                    <button class="tab-button" onclick="showTab('conversations')">
                        <i class="fas fa-comments"></i> Conversations (<?php echo $total_conversations; ?>)
                    </button>
                </div>

                <!-- Received Messages Tab -->
                <div id="tab-received" class="tab-content active">
                    <table class="messages-table">
                        <thead class="table-header-row">
                            <tr>
                                <th>Message</th>
                                <th>Expéditeur</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($received_messages)): ?>
                            <tr class="table-row">
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h3>Aucun message reçu</h3>
                                        <p>Vous n'avez reçu aucun message des résidents</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($received_messages as $message): ?>
                                <tr class="table-row">
                                    <td>
                                        <div class="message-item message-received">
                                            <div class="message-icon">
                                                <i class="fas fa-envelope"></i>
                                            </div>
                                            <div class="message-info">
                                                <div class="message-subject">
                                                    <?php echo htmlspecialchars($message['subject'] ?: 'Sans objet'); ?>
                                                </div>
                                                <div class="message-preview">
                                                    <?php echo htmlspecialchars(substr($message['content'], 0, 80)) . '...'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;">
                                            <?php echo htmlspecialchars($message['sender_name']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #64748b;">
                                            <?php if ($message['sender_apartment']): ?>
                                                Apt. <?php echo $message['sender_apartment']; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $message['formatted_date']; ?></td>
                                    <td>
                                        <button class="btn btn-primary" 
                                                onclick="replyToMessage(<?php echo $message['id_sender']; ?>, '<?php echo htmlspecialchars($message['sender_name']); ?>', '<?php echo htmlspecialchars($message['subject']); ?>')">
                                            <i class="fas fa-reply"></i> Répondre
                                        </button>
                                        <button class="btn btn-danger" 
                                                onclick="deleteMessage(<?php echo $message['id_message']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Sent Messages Tab -->
                <div id="tab-sent" class="tab-content">
                    <table class="messages-table">
                        <thead class="table-header-row">
                            <tr>
                                <th>Message</th>
                                <th>Destinataire</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sent_messages)): ?>
                            <tr class="table-row">
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="fas fa-paper-plane"></i>
                                        <h3>Aucun message envoyé</h3>
                                        <p>Commencez à communiquer avec vos résidents</p>
                                        <button class="btn btn-primary" onclick="openComposeModal()">
                                            <i class="fas fa-plus"></i> Nouveau Message
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($sent_messages as $message): ?>
                                <tr class="table-row">
                                    <td>
                                        <div class="message-item message-sent">
                                            <div class="message-icon">
                                                <i class="fas fa-paper-plane"></i>
                                            </div>
                                            <div class="message-info">
                                                <div class="message-subject">
                                                    <?php echo htmlspecialchars($message['subject'] ?: 'Sans objet'); ?>
                                                </div>
                                                <div class="message-preview">
                                                    <?php echo htmlspecialchars(substr($message['content'], 0, 80)) . '...'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;">
                                            <?php echo htmlspecialchars($message['receiver_name']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #64748b;">
                                            <?php if ($message['receiver_apartment']): ?>
                                                Apt. <?php echo $message['receiver_apartment']; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $message['formatted_date']; ?></td>
                                    <td>
                                        <button class="btn btn-secondary" 
                                                onclick="viewMessage('<?php echo htmlspecialchars($message['subject']); ?>', '<?php echo htmlspecialchars($message['content']); ?>')">
                                            <i class="fas fa-eye"></i> Voir
                                        </button>
                                        <button class="btn btn-danger" 
                                                onclick="deleteMessage(<?php echo $message['id_message']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Conversations Tab -->
                <div id="tab-conversations" class="tab-content">
                    <table class="messages-table">
                        <thead class="table-header-row">
                            <tr>
                                <th>Contact</th>
                                <th>Messages</th>
                                <th>Dernier message</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($conversations)): ?>
                            <tr class="table-row">
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="fas fa-comments"></i>
                                        <h3>Aucune conversation</h3>
                                        <p>Aucune conversation active avec les résidents</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($conversations as $conversation): ?>
                                <tr class="table-row">
                                    <td>
                                        <div class="message-item">
                                            <div class="message-icon" style="background: #f59e0b;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="message-info">
                                                <div class="message-subject">
                                                    <?php echo htmlspecialchars($conversation['contact_name']); ?>
                                                </div>
                                                <div class="message-preview">
                                                    <?php if ($conversation['contact_apartment']): ?>
                                                        Appartement <?php echo $conversation['contact_apartment']; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="background: #eff6ff; color: #1d4ed8; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500;">
                                            <?php echo $conversation['message_count']; ?> messages
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($conversation['last_message_date'])); ?></td>
                                    <td>
                                        <button class="btn btn-primary" 
                                                onclick="replyToMessage(<?php echo $conversation['contact_id']; ?>, '<?php echo htmlspecialchars($conversation['contact_name']); ?>', '')">
                                            <i class="fas fa-paper-plane"></i> Message
                                        </button>
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

    <!-- Compose Message Modal -->
    <div id="composeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-plus"></i>
                    <span id="composeTitle">Nouveau Message</span>
                </h2>
                <button class="close" onclick="closeComposeModal()">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="send_message">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="receiver_select">
                            Destinataire <span class="required">*</span>
                        </label>
                        <select name="receiver_id" id="receiver_select" required>
                            <option value="">Sélectionner un résident</option>
                            <?php foreach ($residents as $resident): ?>
                                <option value="<?php echo $resident['id_member']; ?>">
                                    <?php echo htmlspecialchars($resident['full_name']); ?>
                                    <?php if ($resident['apartment_number']): ?>
                                        - Apt. <?php echo $resident['apartment_number']; ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="subject">
                            Objet
                        </label>
                        <input type="text" name="subject" id="subject" placeholder="Objet du message...">
                    </div>

                    <div class="form-group">
                        <label for="content">
                            Message <span class="required">*</span>
                        </label>
                        <textarea name="content" id="content" rows="6" required 
                                  placeholder="Tapez votre message ici..."></textarea>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeComposeModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Envoyer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Message Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-eye"></i>
                    Message
                </h2>
                <button class="close" onclick="closeViewModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Objet:</label>
                    <div id="viewSubject" style="padding: 8px; background: #f8fafc; border-radius: 4px;"></div>
                </div>
                <div class="form-group">
                    <label>Contenu:</label>
                    <div id="viewContent" style="padding: 12px; background: #f8fafc; border-radius: 4px; white-space: pre-wrap;"></div>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">
                    <i class="fas fa-times"></i> Fermer
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden forms for actions -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_message">
        <input type="hidden" name="message_id" id="deleteMessageId">
    </form>

    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById('tab-' + tabName).classList.add('active');
            
            // Add active class to clicked tab button
            event.target.classList.add('active');
        }

        // Modal functions
        function openComposeModal() {
            document.getElementById('composeTitle').textContent = 'Nouveau Message';
            document.getElementById('receiver_select').value = '';
            document.getElementById('subject').value = '';
            document.getElementById('content').value = '';
            document.getElementById('composeModal').classList.add('show');
        }

        function closeComposeModal() {
            document.getElementById('composeModal').classList.remove('show');
        }

        function replyToMessage(receiverId, receiverName, originalSubject) {
            document.getElementById('composeTitle').textContent = 'Répondre à ' + receiverName;
            document.getElementById('receiver_select').value = receiverId;
            document.getElementById('subject').value = originalSubject ? 'Re: ' + originalSubject : '';
            document.getElementById('content').value = '';
            document.getElementById('composeModal').classList.add('show');
        }

        function viewMessage(subject, content) {
            document.getElementById('viewSubject').textContent = subject || 'Sans objet';
            document.getElementById('viewContent').textContent = content;
            document.getElementById('viewModal').classList.add('show');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('show');
        }

        function deleteMessage(messageId) {
            if (confirm('Voulez-vous supprimer ce message ?')) {
                document.getElementById('deleteMessageId').value = messageId;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
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
        });


        // Form validation
        document.querySelector('#composeModal form').addEventListener('submit', function(e) {
            const receiverId = document.getElementById('receiver_select').value;
            const content = document.getElementById('content').value.trim();
            
            if (!receiverId) {
                e.preventDefault();
                alert('Veuillez sélectionner un destinataire.');
                return;
            }
            
            if (!content) {
                e.preventDefault();
                alert('Veuillez saisir un message.');
                return;
            }
        });

        // Quick card interactions
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Auto-resize textarea
        document.getElementById('content').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    </script>
</body>
</html>