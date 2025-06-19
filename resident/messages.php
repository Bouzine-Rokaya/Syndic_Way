<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'resident') {
    header('Location: ../public/login.php');
    exit();
}

$current_user = [
    'id' => $_SESSION['user_id'],
    'role' => $_SESSION['user_role'],
    'name' => $_SESSION['user_name'] ?? 'Résident',
    'email' => $_SESSION['user_email'] ?? ''
];



// Get resident information
try {
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
    $stmt->execute([$_SESSION['user_id']]);
    $resident_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get syndic information for this building
    if ($resident_info) {
        $stmt = $conn->prepare("
            SELECT m.id_member, m.full_name as syndic_name, m.email as syndic_email, m.phone as syndic_phone
            FROM member m
            JOIN apartment ap ON ap.id_member = m.id_member
            WHERE ap.id_residence = ? AND m.role = 2
            LIMIT 1
        ");
        $stmt->execute([$resident_info['id_residence']]);
        $syndic_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $syndic_info = null;
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    $resident_info = null;
    $syndic_info = null;
}

// Handle sending message to syndic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $subject = trim($_POST['subject'] ?? '');
        $content = trim($_POST['content']);
        
        if (empty($content)) {
            throw new Exception("Le contenu du message est requis.");
        }
        
        if (!$resident_info) {
            throw new Exception("Informations du résident non trouvées.");
        }
        
        if (!$syndic_info) {
            throw new Exception("Aucun syndic trouvé pour votre immeuble.");
        }

        // Insert message
        $stmt = $conn->prepare("
            INSERT INTO member_messages (id_sender, id_receiver, date_message, subject, content)
            VALUES (?, ?, NOW(), ?, ?)
        ");
        
        $result = $stmt->execute([
            $_SESSION['user_id'],
            $syndic_info['id_member'],
            $subject,
            $content
        ]);

        if ($result) {
            $_SESSION['success'] = "Message envoyé avec succès!";
        } else {
            throw new Exception("Échec de l'envoi du message.");
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
        error_log("Message sending error: " . $e->getMessage());
    }
}

// Get conversation messages with syndic
try {
    $conversation_messages = [];
    
    if ($syndic_info && $resident_info) {
        $stmt = $conn->prepare("
            SELECT mm.*, 
            CASE 
                WHEN mm.id_sender = ? THEN 'sent'
                ELSE 'received'
            END as message_type,
            DATE_FORMAT(mm.date_message, '%d/%m/%Y à %H:%i') as formatted_date,
            sender.full_name as sender_name
            FROM member_messages mm
            JOIN member sender ON sender.id_member = mm.id_sender
            WHERE (mm.id_sender = ? AND mm.id_receiver = ?) 
            OR (mm.id_sender = ? AND mm.id_receiver = ?)
            ORDER BY mm.date_message DESC
            LIMIT 50
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['user_id'],
            $syndic_info['id_member'],
            $syndic_info['id_member'],
            $_SESSION['user_id']
        ]);

        $conversation_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calculate statistics
    $total_messages = count($conversation_messages);
    $sent_messages = count(array_filter($conversation_messages, function ($msg) {
        return $msg['message_type'] === 'sent';
    }));
    $received_messages = $total_messages - $sent_messages;

} catch (PDOException $e) {
    error_log("Message retrieval error: " . $e->getMessage());
    $conversation_messages = [];
    $total_messages = 0;
    $sent_messages = 0;
    $received_messages = 0;
}

$page_title = "Messages - Syndic Way";
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
/* Sidebar */
.sidebar {
    width: 240px;
    background: white;
    border-right: 1px solid #e2e8f0;
    padding: 20px 0;
    display: flex;
    flex-direction: column;
}
.logo {
    padding: 0 20px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 20px;
}
.logo-icon {
    width: 32px;
    height: 32px;
    background: #FFCB32; /* Changed to #FFCB32 */
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}
.logo-text {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}
.nav-section {
    padding: 0 20px;
    margin-bottom: 30px;
}
.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    color: #64748b;
    text-decoration: none;
    margin-bottom: 4px;
    transition: all 0.2s;
    font-size: 14px;
}
.nav-item:hover,
.nav-item.active {
    background: #f1f5f9;
    color: #FFCB32; /* Changed to #FFCB32 */
}
.nav-item i {
    width: 16px;
    text-align: center;
}
.user-info {
    margin-top: auto;
    padding: 0 20px;
    text-align: center;
}
.user-avatar {
    width: 50px;
    height: 50px;
    background: #FFCB32; /* Changed to #FFCB32 */
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    font-weight: 500;
    margin: 0 auto 10px;
}
.user-name {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}
.user-role {
    font-size: 12px;
    color: #64748b;
}
/* Main Content */
.main-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}
/* Header */
.header {
    background: white;
    border-bottom: 1px solid #e2e8f0;
    padding: 0 24px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.header-nav {
    display: flex;
    align-items: center;
    gap: 24px;
}
.header-nav a {
    color: #64748b;
    text-decoration: none;
    font-size: 14px;
    padding: 8px 12px;
    border-radius: 6px;
    transition: all 0.2s;
}
.header-nav a.active {
    color: #FFCB32; /* Changed to #FFCB32 */
    background: #FFF0C3; /* Changed to #FFF0C3 */
}
.header-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}
.header-user-avatar {
    width: 32px;
    height: 32px;
    background: #FFCB32; /* Changed to #FFCB32 */
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 14px;
    font-weight: 500;
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
.quick-card.total .quick-card-icon { background: #FFCB32; } /* Changed to #FFCB32 */
.quick-card.sent .quick-card-icon { background: #10b981; }
.quick-card.received .quick-card-icon { background: #f59e0b; }
.quick-card.contact .quick-card-icon { background: #8b5cf6; }
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
/* Syndic Info Card */
.syndic-info-card {
    background: #FFCB32; /* Changed to #FFCB32 and #FFF0C3 */
    color: white;
    padding: 20px;
    margin: 24px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.syndic-avatar-large {
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.syndic-details h4 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 4px;
}
.syndic-details p {
    opacity: 0.9;
    font-size: 14px;
}
.new-message-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    margin-left: auto;
}
.new-message-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}
/* Content Area */
.content-area {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.main-panel {
    flex: 1;
    padding: 24px;
    display: flex;
    flex-direction: column;
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
    color: #FFCB32; /* Changed to #FFCB32 */
}
/* Messages Interface */
.messages-container {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.messages-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.messages-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}
/* Messages List */
.messages-list {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
    min-height: 300px;
}
.message-bubble {
    max-width: 70%;
    margin-bottom: 16px;
    padding: 12px 16px;
    border-radius: 12px;
    word-wrap: break-word;
}
.message-bubble.sent {
    background: #FFCB32; /* Changed to #FFCB32 */
    color: white;
    margin-left: auto;
    border-bottom-right-radius: 4px;
}
.message-bubble.received {
    background: #f1f5f9;
    color: #1e293b;
    border-bottom-left-radius: 4px;
}
.message-subject {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}
.message-bubble.received .message-subject {
    border-bottom-color: #e2e8f0;
}
.message-content {
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 8px;
}
.message-time {
    font-size: 11px;
    opacity: 0.7;
    text-align: right;
}
/* Message Input */
.message-input {
    border-top: 1px solid #e2e8f0;
    padding: 24px;
    background: #f8fafc;
}
.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 6px;
}
.form-group input,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.2s;
}
.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #FFCB32; /* Changed to #FFCB32 */
    box-shadow: 0 0 0 3px rgba(255, 203, 50, 0.1); /* Changed to #FFCB32 */
}
.form-group textarea {
    resize: vertical;
    min-height: 100px;
}
.char-counter {
    font-size: 12px;
    color: #64748b;
    text-align: right;
    margin-top: 4px;
}
.message-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.btn-primary {
    background: #FFCB32; /* Changed to #FFCB32 */
    color: white;
}
.btn-primary:hover {
    background: #f59e0b;
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
    font-weight: 600;
    margin-bottom: 8px;
    color: #1e293b;
}
.empty-state p {
    font-size: 14px;
    line-height: 1.5;
}
/* Message Templates */
.message-templates {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.template-btn {
    background: #FFF0C3; /* Changed to #FFF0C3 */
    border: 1px solid #FFCB32; /* Changed to #FFCB32 */
    color: #FFCB32; /* Changed to #FFCB32 */
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s;
}
.template-btn:hover {
    background: #FFCB32; /* Changed to #FFCB32 */
    color: white; /* Changed to white */
}
/* Responsive */
@media (max-width: 768px) {
    .container {
        flex-direction: column;
    }
    .sidebar {
        width: 100%;
        height: auto;
    }
    .quick-access-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .message-bubble {
        max-width: 90%;
    }
    .syndic-info-card {
        flex-direction: column;
        text-align: center;
    }
}
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <div class="logo-icon">SW</div>
                <div class="logo-text">Syndic Way</div>
            </div>

            <div class="nav-section">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-th-large"></i>
                    Tableau de Bord
                </a>
                <a href="payments.php" class="nav-item">
                    <i class="fas fa-credit-card"></i>
                    Paiements
                </a>
                <a href="messages.php" class="nav-item active">
                    <i class="fas fa-envelope"></i>
                    Messages
                </a>
                <a href="announcements.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    Annonces
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-cog"></i>
                    Mon Profil
                </a>
                <a href="../public/login.php?logout=1" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </div>

            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($current_user['name'], 0, 1)); ?></div>
                <div class="user-name"><?php echo htmlspecialchars($current_user['name']); ?></div>
                <div class="user-role">Résident</div>
                <?php if ($resident_info): ?>
                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
                        Apt. <?php echo $resident_info['number']; ?> - <?php echo htmlspecialchars($resident_info['building_name']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

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
            <div class="header">
                <div class="header-nav">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="payments.php">Paiements</a>
                    <a href="messages.php" class="active">Messages</a>
                    <a href="announcements.php">Annonces</a>
                </div>

                <div class="header-actions">
                    <i class="fas fa-bell" style="color: #64748b; cursor: pointer;"></i>
                    <div class="header-user-avatar"><?php echo strtoupper(substr($current_user['name'], 0, 1)); ?></div>
                </div>
            </div>

            <!-- Syndic Info Card -->
            <?php if ($syndic_info): ?>
                <div class="syndic-info-card">
                    <div class="syndic-avatar-large">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="syndic-details">
                        <h4><?php echo htmlspecialchars($syndic_info['syndic_name']); ?></h4>
                        <p>Votre Syndic</p>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($syndic_info['syndic_email']); ?></p>
                        <?php if ($syndic_info['syndic_phone']): ?>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($syndic_info['syndic_phone']); ?></p>
                        <?php endif; ?>
                    </div>
                    <button class="new-message-btn" onclick="focusMessageInput()">
                        <i class="fas fa-paper-plane"></i>
                        Nouveau Message
                    </button>
                </div>
            <?php endif; ?>

            <!-- Quick Access -->
            <div class="quick-access">
                <div class="quick-access-header">
                    <div class="quick-access-title">Aperçu Messages</div>
                    <i class="fas fa-ellipsis-h" style="color: #64748b; cursor: pointer;"></i>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card total">
                        <div class="quick-card-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="quick-card-title">Total Messages</div>
                        <div class="quick-card-count"><?php echo $total_messages; ?></div>
                        <div class="quick-card-stats">Échanges</div>
                    </div>

                    <div class="quick-card sent">
                        <div class="quick-card-icon">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="quick-card-title">Messages Envoyés</div>
                        <div class="quick-card-count"><?php echo $sent_messages; ?></div>
                        <div class="quick-card-stats">Au syndic</div>
                    </div>

                    <div class="quick-card received">
                        <div class="quick-card-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <div class="quick-card-title">Messages Reçus</div>
                        <div class="quick-card-count"><?php echo $received_messages; ?></div>
                        <div class="quick-card-stats">Du syndic</div>
                    </div>

                    <div class="quick-card contact" onclick="focusMessageInput()">
                        <div class="quick-card-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="quick-card-title">Contact Syndic</div>
                        <div class="quick-card-count"><?php echo $syndic_info ? '1' : '0'; ?></div>
                        <div class="quick-card-stats"><?php echo $syndic_info ? 'Disponible' : 'Non trouvé'; ?></div>
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
                        <a href="messages.php">Messages</a>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        <span>Conversation avec le Syndic</span>
                    </div>

                    <!-- Messages Container -->
                    <div class="messages-container">
                        <div class="messages-header">
                            <h3>
                                <i class="fas fa-comments"></i>
                                Conversation avec <?php echo $syndic_info ? htmlspecialchars($syndic_info['syndic_name']) : 'le Syndic'; ?>
                            </h3>
                            <?php if ($syndic_info): ?>
                                <button class="new-message-btn" onclick="focusMessageInput()">
                                    <i class="fas fa-plus"></i>
                                    Nouveau
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="messages-list" id="messagesList">
                            <?php if (!empty($conversation_messages)): ?>
                                <?php foreach (array_reverse($conversation_messages) as $message): ?>
                                    <div class="message-bubble <?php echo $message['message_type']; ?>">
                                        <?php if ($message['subject']): ?>
                                            <div class="message-subject">
                                                <?php echo htmlspecialchars($message['subject']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="message-content">
                                            <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                                        </div>
                                        <div class="message-time">
                                            <?php echo $message['formatted_date']; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-comments"></i>
                                    <h3>Aucune conversation</h3>
                                    <p>Commencez une conversation avec votre syndic en envoyant votre premier message!</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($syndic_info): ?>
                            <div class="message-input">
                                <form method="POST" class="message-input-form" id="messageForm">
                                    <div class="message-templates">
                                        <button type="button" class="template-btn" onclick="insertTemplate('maintenance')">
                                            <i class="fas fa-wrench"></i> Maintenance
                                        </button>
                                        <button type="button" class="template-btn" onclick="insertTemplate('noise')">
                                            <i class="fas fa-volume-up"></i> Nuisance
                                        </button>
                                        <button type="button" class="template-btn" onclick="insertTemplate('payment')">
                                            <i class="fas fa-euro-sign"></i> Charges
                                        </button>
                                        <button type="button" class="template-btn" onclick="insertTemplate('general')">
                                            <i class="fas fa-question"></i> Question
                                        </button>
                                        <button type="button" class="template-btn" onclick="insertTemplate('emergency')">
                                            <i class="fas fa-exclamation-triangle"></i> Urgent
                                        </button>
                                    </div>

                                    <div class="form-group">
                                        <label for="subject">Objet (optionnel)</label>
                                        <input type="text" name="subject" id="subject" placeholder="Objet de votre message...">
                                    </div>

                                    <div class="form-group">
                                        <label for="content">Votre message *</label>
                                        <textarea name="content" id="content" rows="4" required placeholder="Tapez votre message ici..."></textarea>
                                        <div class="char-counter" id="charCounter">0 caractères</div>
                                    </div>

                                    <div class="message-actions">
                                        <div style="font-size: 12px; color: #64748b;">
                                            <i class="fas fa-info-circle"></i>
                                            Soyez clair et précis dans votre demande
                                        </div>
                                        <button type="submit" name="send_message" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i>
                                            Envoyer
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-times"></i>
                                <h3>Syndic non trouvé</h3>
                                <p>Aucun syndic n'est configuré pour votre résidence. Contactez l'administrateur.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Focus message input
        function focusMessageInput() {
            const contentField = document.getElementById('content');
            if (contentField) {
                contentField.focus();
                contentField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        // Insert message templates
        function insertTemplate(type) {
            const content = document.getElementById('content');
            const subject = document.getElementById('subject');

            if (!content || !subject) return;

            const templates = {
                'maintenance': {
                    subject: 'Demande de maintenance',
                    content: 'Bonjour,\n\nJe souhaite signaler un problème de maintenance dans mon appartement :\n\n[Décrivez le problème]\n\nMerci de votre attention.\n\nCordialement,'
                },
                'noise': {
                    subject: 'Problème de nuisance sonore',
                    content: 'Bonjour,\n\nJe souhaite signaler des nuisances sonores :\n\n[Décrivez le problème et les horaires]\n\nMerci de votre intervention.\n\nCordialement,'
                },
                'payment': {
                    subject: 'Question concernant les charges',
                    content: 'Bonjour,\n\nJ\'ai une question concernant :\n\n[Précisez votre question sur les charges/paiements]\n\nMerci pour votre réponse.\n\nCordialement,'
                },
                'general': {
                    subject: 'Demande d\'information',
                    content: 'Bonjour,\n\nJe souhaiterais obtenir des informations concernant :\n\n[Précisez votre demande]\n\nMerci par avance.\n\nCordialement,'
                },
                'emergency': {
                    subject: 'URGENT - Intervention nécessaire',
                    content: 'Bonjour,\n\nURGENT - Je signale un problème nécessitant une intervention rapide :\n\n[Décrivez l\'urgence]\n\nMerci de me contacter rapidement.\n\nCordialement,'
                }
            };

            if (templates[type]) {
                subject.value = templates[type].subject;
                content.value = templates[type].content;
                updateCharCounter();
                content.focus();

                // Add visual feedback
                const buttons = document.querySelectorAll('.template-btn');
                buttons.forEach(btn => btn.style.background = '#eff6ff');
                event.target.style.background = '#dbeafe';
                setTimeout(() => {
                    event.target.style.background = '#eff6ff';
                }, 1000);
            }
        }

        // Character counter
        function updateCharCounter() {
            const content = document.getElementById('content');
            const counter = document.getElementById('charCounter');

            if (!content || !counter) return;

            const length = content.value.length;
            counter.textContent = `${length} caractères`;

            if (length > 1000) {
                counter.style.color = '#ef4444';
            } else if (length > 500) {
                counter.style.color = '#f59e0b';
            } else {
                counter.style.color = '#64748b';
            }
        }

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

            // Auto-scroll to bottom of messages
            const messagesList = document.getElementById('messagesList');
            if (messagesList && messagesList.children.length > 0) {
                messagesList.scrollTop = messagesList.scrollHeight;
            }

            // Initialize character counter
            updateCharCounter();

            // Add event listeners
            const contentField = document.getElementById('content');
            if (contentField) {
                contentField.addEventListener('input', updateCharCounter);

                // Auto-resize textarea
                contentField.addEventListener('input', function () {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 200) + 'px';
                });
            }

            // Animate statistics
            document.querySelectorAll('.quick-card-count').forEach((counter, index) => {
                const target = parseInt(counter.textContent);
                counter.textContent = '0';
                setTimeout(() => {
                    animateCounter(counter, target);
                }, index * 200);
            });
        });

        // Animate counter
        function animateCounter(element, target) {
            let current = 0;
            const increment = Math.max(1, target / 20);
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current);
            }, 50);
        }

        // Form validation and submission
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', function (e) {
                const content = document.getElementById('content');
                if (!content || !content.value.trim()) {
                    e.preventDefault();
                    alert('Veuillez saisir un message avant d\'envoyer.');
                    return;
                }

                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';

                    // Re-enable after 5 seconds (fallback)
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }, 5000);
                }
            });
        }

        // Quick card interactions
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('mouseenter', function () {
                this.style.transform = 'translateY(-2px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function () {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function (event) {
            // Ctrl/Cmd + Enter to send message
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                event.preventDefault();
                const form = document.getElementById('messageForm');
                if (form) {
                    form.submit();
                }
            }

            // Ctrl/Cmd + M to focus message input
            if ((event.ctrlKey || event.metaKey) && event.key === 'm') {
                event.preventDefault();
                focusMessageInput();
            }
        });

        // Smooth scroll for messages
        function scrollToBottom() {
            const messagesList = document.getElementById('messagesList');
            if (messagesList) {
                messagesList.scrollTo({
                    top: messagesList.scrollHeight,
                    behavior: 'smooth'
                });
            }
        }

        // Enhanced template button interactions
        document.querySelectorAll('.template-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                // Add ripple effect
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
        });

        // Navigation active state management
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (!this.href.includes('login.php')) {
                    document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });

        // Header navigation active state
        document.querySelectorAll('.header-nav a').forEach(item => {
            item.addEventListener('click', function(e) {
                if (!this.href) {
                    e.preventDefault();
                }
                document.querySelectorAll('.header-nav a').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Real-time message status (placeholder for future implementation)
        function checkNewMessages() {
            // This would be implemented with AJAX to check for new messages
            console.log('Checking for new messages...');
        }

        // Set up periodic check for new messages (every 30 seconds)
        setInterval(checkNewMessages, 30000);

        // Enhanced visual feedback for form interactions
        document.querySelectorAll('input, textarea').forEach(field => {
            field.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.01)';
            });
            
            field.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });

        // Debug information (can be removed in production)
        console.log('Messages page loaded successfully');
        console.log('Syndic Info:', <?php echo json_encode($syndic_info); ?>);
        console.log('Total Messages:', <?php echo $total_messages; ?>);
        console.log('User Role:', '<?php echo $_SESSION['user_role'] ?? 'null'; ?>');
    </script>
</body>
</html>