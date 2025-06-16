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
        if ($action === 'create_announcement') {
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $priority = $_POST['priority'] ?? 'normal';
            $target_audience = $_POST['target_audience'] ?? 'all';
            $specific_residents = $_POST['specific_residents'] ?? [];

            if (empty($title) || empty($content)) {
                throw new Exception("Title and content are required.");
            }

            // Get target residents
            $target_residents = [];

            if ($target_audience === 'all') {
                $stmt = $conn->prepare("
                    SELECT m.id_member
                    FROM member m
                    WHERE m.role = 1 AND m.status = 'active'
                ");
                $stmt->execute();
                $target_residents = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($target_audience === 'specific' && !empty($specific_residents)) {
                $target_residents = $specific_residents;
            }

            if (empty($target_residents)) {
                throw new Exception("No recipients found.");
            }

            $conn->beginTransaction();

            $announcement_date = date('Y-m-d H:i:s');

            // Create announcement for each target resident
            foreach ($target_residents as $resident_id) {
                $stmt = $conn->prepare("
                    INSERT INTO member_announcements (id_poster, id_receiver, date_announcement, title, content, Priority) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$current_user['id'], $resident_id, $announcement_date, $title, $content, $priority]);
            }

            $conn->commit();
            $_SESSION['success'] = "Announcement published successfully to " . count($target_residents) . " resident(s).";

        } elseif ($action === 'delete_announcement') {
            $announcement_date = $_POST['announcement_date'];

            $stmt = $conn->prepare("
                DELETE FROM member_announcements 
                WHERE id_poster = ? AND date_announcement = ?
            ");
            $stmt->execute([$current_user['id'], $announcement_date]);

            $_SESSION['success'] = "Announcement deleted successfully.";
        }

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }

    header('Location: announcements.php');
    exit();
}

// Get data for dashboard
try {
    // Get all residents for announcement targeting
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

    // Get published announcements
    $stmt = $conn->prepare("
        SELECT 
            ma.date_announcement,
            ma.title,
            ma.content,
            ma.Priority,
            COUNT(ma.id_receiver) as recipient_count,
            GROUP_CONCAT(m.full_name SEPARATOR ', ') as recipients
        FROM member_announcements ma
        JOIN member m ON ma.id_receiver = m.id_member
        WHERE ma.id_poster = ?
        GROUP BY ma.date_announcement, ma.title, ma.content, ma.Priority
        ORDER BY ma.date_announcement DESC
        LIMIT 20
    ");
    $stmt->execute([$current_user['id']]);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $total_announcements = count($announcements);
    $total_residents = count($residents);
    $active_residents = count(array_filter($residents, function ($r) {
        return $r['status'] === 'active'; }));
    $this_month_announcements = count(array_filter($announcements, function ($ann) {
        return date('Y-m', strtotime($ann['date_announcement'])) === date('Y-m');
    }));

} catch (PDOException $e) {
    $_SESSION['error'] = "Error loading data: " . $e->getMessage();
    $residents = [];
    $announcements = [];
    $total_announcements = 0;
    $total_residents = 0;
    $active_residents = 0;
    $this_month_announcements = 0;
}

$page_title = "Announcements Management - Syndic Way";
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

        .quick-card.announcements .quick-card-icon {
            background: #FFCB32;
        }

        .quick-card.urgent .quick-card-icon {
            background: #ef4444;
        }

        .quick-card.residents .quick-card-icon {
            background: #f59e0b;
        }

        .quick-card.month .quick-card-icon {
            background: #8b5cf6;
        }

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

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
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

        /* Priority Badges */
        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .priority-urgent {
            background: #fee2e2;
            color: #991b1b;
        }

        .priority-high {
            background: #fef3c7;
            color: #d97706;
        }

        .priority-normal {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .priority-low {
            background: #f3f4f6;
            color: #6b7280;
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
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
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

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
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

        /* Checkbox Group */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            max-height: 150px;
            overflow-y: auto;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 12px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
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

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <?php require_once __DIR__ . "/../includes/sidebar_syndic.php" ?>


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
            <?php require_once __DIR__ ."/../includes/navigation_syndic.php"?>


            <!-- Quick Access -->
            <div class="quick-access">
                <div class="quick-access-header">
                    <div class="quick-access-title">Actions Rapides</div>
                    <button class="add-btn" onclick="openCreateModal()">
                        <i class="fas fa-plus"></i>
                        Nouvelle Annonce
                    </button>
                </div>

                <div class="quick-access-grid">
                    <div class="quick-card announcements" onclick="openCreateModal('general')">
                        <div class="quick-card-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="quick-card-title">Total Annonces</div>
                        <div class="quick-card-count"><?php echo $total_announcements; ?></div>
                        <div class="quick-card-stats">Publiées</div>
                    </div>

                    <div class="quick-card urgent" onclick="openCreateModal('urgent')">
                        <div class="quick-card-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="quick-card-title">Ce Mois</div>
                        <div class="quick-card-count"><?php echo $this_month_announcements; ?></div>
                        <div class="quick-card-stats">Nouvelles annonces</div>
                    </div>

                    <div class="quick-card residents" onclick="openCreateModal('maintenance')">
                        <div class="quick-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="quick-card-title">Résidents</div>
                        <div class="quick-card-count"><?php echo $active_residents; ?></div>
                        <div class="quick-card-stats">Actifs</div>
                    </div>

                    <div class="quick-card month" onclick="openCreateModal('meeting')">
                        <div class="quick-card-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="quick-card-title">Total Résidents</div>
                        <div class="quick-card-count"><?php echo $total_residents; ?></div>
                        <div class="quick-card-stats">Gérés</div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Breadcrumb -->
                <div class="breadcrumb">
                    <a href="dashboard.php">Accueil</a>
                    <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                    <a href="#">Gestion Syndic</a>
                    <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                    <span>Annonces</span>
                </div>

                <!-- Data Table -->
                <table class="data-table">
                    <thead class="table-header-row">
                        <tr>
                            <th>Annonce</th>
                            <th>Priorité</th>
                            <th>Destinataires</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($announcements)): ?>
                            <tr class="table-row">
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fas fa-bullhorn"></i>
                                        <h3>Aucune annonce trouvée</h3>
                                        <p>Créez votre première annonce pour commencer</p>
                                        <button class="btn btn-primary" onclick="openCreateModal()">
                                            <i class="fas fa-plus"></i> Nouvelle Annonce
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($announcements as $announcement): ?>
                                <tr class="table-row">
                                    <td>
                                        <div class="file-item">
                                            <div class="table-icon" style="background: #FFCB32;">
                                                <i class="fas fa-bullhorn"></i>
                                            </div>
                                            <div class="file-info">
                                                <div class="file-name"><?php echo htmlspecialchars($announcement['title']); ?>
                                                </div>
                                                <div style="font-size: 12px; color: #64748b;">
                                                    <?php echo htmlspecialchars(substr($announcement['content'], 0, 100)) . '...'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="priority-badge priority-<?php echo $announcement['Priority']; ?>">
                                            <?php echo ucfirst($announcement['Priority']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $announcement['recipient_count']; ?> résidents</td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($announcement['date_announcement'])); ?></td>
                                    <td>
                                        <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 12px;"
                                            onclick="viewRecipients('<?php echo htmlspecialchars($announcement['recipients']); ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-danger" style="padding: 4px 8px; font-size: 12px;"
                                            onclick="deleteAnnouncement('<?php echo $announcement['date_announcement']; ?>')">
                                            <i class="fas fa-trash"></i>
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

    <!-- Create Announcement Modal -->
    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">
                    <i class="fas fa-plus"></i>
                    <span id="modalTitleText">Nouvelle annonce</span>
                </h2>
                <button class="close" onclick="closeModal('announcementModal')">&times;</button>
            </div>

            <form id="announcementForm" method="POST">
                <input type="hidden" name="action" value="create_announcement">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="title">
                            Titre de l'annonce <span class="required">*</span>
                        </label>
                        <input type="text" name="title" id="title" required
                            placeholder="Ex: Maintenance ascenseur, Assemblée générale...">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="priority">Priorité</label>
                            <select name="priority" id="priority">
                                <option value="low">Faible</option>
                                <option value="normal" selected>Normale</option>
                                <option value="high">Haute</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="target_audience">Destinataires</label>
                            <select name="target_audience" id="target_audience" onchange="toggleSpecificResidents()">
                                <option value="all">Tous les résidents (<?php echo $total_residents; ?>)</option>
                                <option value="specific">Résidents spécifiques</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" id="specificResidentsGroup" style="display: none;">
                        <label for="specific_residents">
                            Sélectionner les résidents
                        </label>
                        <div class="checkbox-group">
                            <?php foreach ($residents as $resident): ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" name="specific_residents[]"
                                        value="<?php echo $resident['id_member']; ?>"
                                        id="resident_<?php echo $resident['id_member']; ?>">
                                    <label for="resident_<?php echo $resident['id_member']; ?>">
                                        <?php echo htmlspecialchars($resident['full_name']); ?>
                                        <?php if ($resident['apartment_number']): ?>
                                            (Apt. <?php echo $resident['apartment_number']; ?>)
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="content">
                            Contenu de l'annonce <span class="required">*</span>
                        </label>
                        <textarea name="content" id="content" rows="6" required
                            placeholder="Rédigez votre annonce ici..."></textarea>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('announcementModal')">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-bullhorn"></i> Publier l'annonce
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Recipients Modal -->
    <div id="recipientsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>
                    <i class="fas fa-users"></i>
                    Destinataires de l'annonce
                </h2>
                <button class="close" onclick="closeModal('recipientsModal')">&times;</button>
            </div>

            <div class="modal-body">
                <div id="recipientsList"></div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('recipientsModal')">
                    <i class="fas fa-times"></i> Fermer
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden forms for actions -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_announcement">
        <input type="hidden" name="announcement_date" id="deleteAnnouncementDate">
    </form>

    <script>
        // Toggle specific residents selection
        function toggleSpecificResidents() {
            const select = document.getElementById('target_audience');
            const group = document.getElementById('specificResidentsGroup');

            if (select.value === 'specific') {
                group.style.display = 'block';
            } else {
                group.style.display = 'none';
                // Uncheck all checkboxes
                const checkboxes = group.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach(cb => cb.checked = false);
            }
        }

        // Modal functions
        function openCreateModal(type = 'general') {
            const modal = document.getElementById('announcementModal');
            const titleInput = document.getElementById('title');
            const contentInput = document.getElementById('content');
            const prioritySelect = document.getElementById('priority');

            // Pre-fill based on type
            const templates = {
                general: {
                    title: 'Information générale',
                    content: 'Chers résidents,\n\nNous souhaitons vous informer...\n\nCordialement,\nLa Gérance',
                    priority: 'normal'
                },
                urgent: {
                    title: 'URGENT - Information importante',
                    content: 'URGENT - Chers résidents,\n\nNous vous informons en urgence...\n\nCordialement,\nLa Gérance',
                    priority: 'urgent'
                },
                maintenance: {
                    title: 'Travaux de maintenance programmés',
                    content: 'Chers résidents,\n\nDes travaux de maintenance sont programmés...\n\nMerci de votre compréhension,\nLa Gérance',
                    priority: 'high'
                },
                meeting: {
                    title: 'Convocation - Assemblée Générale',
                    content: 'Chers copropriétaires,\n\nVous êtes convoqués à l\'assemblée générale...\n\nCordialement,\nLe Syndic',
                    priority: 'high'
                }
            };

            if (templates[type]) {
                titleInput.value = templates[type].title;
                contentInput.value = templates[type].content;
                prioritySelect.value = templates[type].priority;
            }

            modal.classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function viewRecipients(recipients) {
            const recipientsArray = recipients.split(', ');
            let message = "Liste des destinataires:\n\n";

            recipientsArray.forEach((recipient, index) => {
                message += `• ${recipient}\n`;
            });

            alert(message);
        }


        function deleteAnnouncement(announcementDate) {
            if (confirm('Voulez-vous supprimer cette annonce ? Cette action est irréversible.')) {
                document.getElementById('deleteAnnouncementDate').value = announcementDate;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modals when clicking outside
        window.onclick = function (event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
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
        });

        // Form validation
        document.getElementById('announcementForm').addEventListener('submit', function (e) {
            const title = document.getElementById('title').value.trim();
            const content = document.getElementById('content').value.trim();
            const targetAudience = document.getElementById('target_audience').value;

            if (!title || !content) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires.');
                return;
            }

            if (targetAudience === 'specific') {
                const checkedBoxes = document.querySelectorAll('input[name="specific_residents[]"]:checked');
                if (checkedBoxes.length === 0) {
                    e.preventDefault();
                    alert('Veuillez sélectionner au moins un résident.');
                    return;
                }
            }
        });


        // Quick card interactions
        document.querySelectorAll('.quick-card').forEach(card => {
            card.addEventListener('mouseenter', function () {
                this.style.transform = 'translateY(-2px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function () {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Auto-resize textarea
        document.getElementById('content').addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });

        // Character counter
        const contentTextarea = document.getElementById('content');
        const charCounter = document.createElement('div');
        charCounter.style.cssText = 'text-align: right; font-size: 12px; color: #64748b; margin-top: 5px;';
        contentTextarea.parentNode.appendChild(charCounter);

        function updateCharCount() {
            const length = contentTextarea.value.length;
            charCounter.textContent = `${length} caractères`;
            if (length > 500) {
                charCounter.style.color = '#f59e0b';
            } else if (length > 1000) {
                charCounter.style.color = '#ef4444';
            } else {
                charCounter.style.color = '#64748b';
            }
        }

        contentTextarea.addEventListener('input', updateCharCount);
        updateCharCount();
    </script>
</body>

</html>