<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/EmailService.php';

// Require syndic login
requireRole(ROLE_SYNDIC);

$emailService = new EmailService();
$page_title = "Messages";

// Get syndic information
try {
    $stmt = $conn->prepare("
        SELECT m.*, r.name as building_name, r.address, c.city_name
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        JOIN residence r ON r.id_residence = ap.id_residence
        JOIN city c ON c.id_city = r.id_city
        WHERE m.id_member = ? AND m.role = ?
    ");
    $stmt->execute([$_SESSION['user_id'], ROLE_SYNDIC]);
    $syndic_info = $stmt->fetch();

    if (!$syndic_info) {
        setFlashMessage('error', 'Informations du syndic introuvables.');
        header('Location: index.php');
        exit();
    }

} catch(PDOException $e) {
    error_log("Database error in messages.php: " . $e->getMessage());
    setFlashMessage('error', 'Une erreur s\'est produite.');
    header('Location: index.php');
    exit();
}

// Get residents in the same building
try {
    $stmt = $conn->prepare("
        SELECT m.*, ap.number as apartment_number, ap.floor
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        JOIN residence r ON r.id_residence = ap.id_residence
        JOIN apartment syndic_apt ON syndic_apt.id_residence = r.id_residence
        WHERE syndic_apt.id_member = ? AND m.role = ? AND m.id_member != ?
        ORDER BY ap.number
    ");
    $stmt->execute([$_SESSION['user_id'], ROLE_RESIDENT, $_SESSION['user_id']]);
    $residents = $stmt->fetchAll();

} catch(PDOException $e) {
    error_log("Database error getting residents: " . $e->getMessage());
    $residents = [];
}

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_message') {
        $recipient_ids = $_POST['recipients'] ?? [];
        $subject = sanitizeInput($_POST['subject'] ?? '');
        $message = sanitizeInput($_POST['message'] ?? '');
        $priority = sanitizeInput($_POST['priority'] ?? 'normal');
        
        // Validation
        $errors = [];
        
        if (empty($recipient_ids)) {
            $errors[] = 'Veuillez sélectionner au moins un destinataire.';
        }
        
        if (empty($subject)) {
            $errors[] = 'Le sujet est obligatoire.';
        }
        
        if (empty($message)) {
            $errors[] = 'Le message est obligatoire.';
        }
        
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'])) {
            $priority = 'normal';
        }
        
        if (empty($errors)) {
            $successCount = 0;
            $failureCount = 0;
            
            foreach ($recipient_ids as $recipient_id) {
                // Find recipient info
                $recipient = null;
                foreach ($residents as $resident) {
                    if ($resident['id_member'] == $recipient_id) {
                        $recipient = $resident;
                        break;
                    }
                }
                
                if ($recipient) {
                    $success = $emailService->sendSyndicMessage($syndic_info, $recipient, $subject, $message, $priority);
                    if ($success) {
                        $successCount++;
                    } else {
                        $failureCount++;
                    }
                }
            }
            
            logUserActivity('MESSAGE_SENT', [
                'subject' => $subject,
                'recipients_count' => count($recipient_ids),
                'successful' => $successCount,
                'failed' => $failureCount,
                'priority' => $priority
            ]);
            
            if ($failureCount === 0) {
                setFlashMessage('success', "Message envoyé avec succès à {$successCount} destinataire(s).");
            } else {
                setFlashMessage('warning', "Message envoyé à {$successCount} destinataire(s). {$failureCount} échec(s).");
            }
        } else {
            foreach ($errors as $error) {
                setFlashMessage('error', $error);
            }
        }
        
        header('Location: messages.php');
        exit();
    }
    
    if ($action === 'send_announcement') {
        $title = sanitizeInput($_POST['announcement_title'] ?? '');
        $content = sanitizeInput($_POST['announcement_content'] ?? '');
        $priority = sanitizeInput($_POST['announcement_priority'] ?? 'normal');
        $send_to_all = isset($_POST['send_to_all']);
        
        // Validation
        $errors = [];
        
        if (empty($title)) {
            $errors[] = 'Le titre de l\'annonce est obligatoire.';
        }
        
        if (empty($content)) {
            $errors[] = 'Le contenu de l\'annonce est obligatoire.';
        }
        
        if (empty($errors)) {
            $recipients_for_announcement = $send_to_all ? $residents : [];
            
            if (!empty($recipients_for_announcement)) {
                $result = $emailService->sendAnnouncement($syndic_info, $recipients_for_announcement, $title, $content, $priority);
                
                logUserActivity('ANNOUNCEMENT_SENT', [
                    'title' => $title,
                    'recipients_count' => $result['total'],'successful' => $result['success'],
                    'failed' => $result['failed'],
                    'priority' => $priority
                ]);
                
                if ($result['failed'] === 0) {
                    setFlashMessage('success', "Annonce envoyée avec succès à {$result['success']} destinataire(s).");
                } else {
                    setFlashMessage('warning', "Annonce envoyée à {$result['success']} destinataire(s). {$result['failed']} échec(s).");
                }
            } else {
                setFlashMessage('error', 'Aucun destinataire trouvé pour l\'annonce.');
            }
        } else {
            foreach ($errors as $error) {
                setFlashMessage('error', $error);
            }
        }
        
        header('Location: messages.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-brand">
            <h2><i class="fas fa-user-tie"></i> Espace Syndic</h2>
        </div>
        <div class="nav-user">
            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($syndic_info['full_name']); ?></span>
            <a href="../public/logout.php" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <div class="header-content">
                <h1><i class="fas fa-comments"></i> Gestion des messages</h1>
                <p>Communiquez avec les résidents de votre immeuble</p>
            </div>
            <div class="header-actions">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        <div class="flash-messages">
            <?php echo displayFlashMessages(); ?>
        </div>

        <!-- Message Tabs -->
        <div class="tabs">
            <button class="tab-button active" onclick="showTab('individual')">
                <i class="fas fa-user"></i> Message individuel
            </button>
            <button class="tab-button" onclick="showTab('announcement')">
                <i class="fas fa-bullhorn"></i> Annonce générale
            </button>
            <button class="tab-button" onclick="showTab('residents')">
                <i class="fas fa-users"></i> Liste des résidents
            </button>
        </div>

        <!-- Individual Message Tab -->
        <div class="tab-content active" id="individual">
            <div class="form-container">
                <div class="form-header">
                    <h3><i class="fas fa-envelope"></i> Envoyer un message individuel</h3>
                </div>
                
                <form method="POST" class="message-form" id="individualMessageForm">
                    <input type="hidden" name="action" value="send_message">
                    
                    <div class="form-group">
                        <label for="recipients" class="required">Destinataires</label>
                        <div class="recipients-grid">
                            <?php foreach ($residents as $resident): ?>
                                <label class="resident-checkbox">
                                    <input type="checkbox" name="recipients[]" value="<?php echo $resident['id_member']; ?>">
                                    <div class="resident-info">
                                        <div class="resident-name"><?php echo htmlspecialchars($resident['full_name']); ?></div>
                                        <div class="resident-apartment">Apt. <?php echo htmlspecialchars($resident['apartment_number']); ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="recipients-actions">
                            <button type="button" onclick="selectAllResidents()" class="btn btn-sm btn-secondary">
                                <i class="fas fa-check-square"></i> Tout sélectionner
                            </button>
                            <button type="button" onclick="deselectAllResidents()" class="btn btn-sm btn-secondary">
                                <i class="fas fa-square"></i> Tout désélectionner
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="subject" class="required">Sujet</label>
                            <input 
                                type="text" 
                                name="subject" 
                                id="subject" 
                                placeholder="Objet du message"
                                required
                                maxlength="200"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="priority">Priorité</label>
                            <select name="priority" id="priority">
                                <option value="low">🟢 Faible</option>
                                <option value="normal" selected>🔵 Normal</option>
                                <option value="high">🟠 Élevée</option>
                                <option value="urgent">🔴 Urgente</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message" class="required">Message</label>
                        <textarea 
                            name="message" 
                            id="message" 
                            rows="8"
                            placeholder="Rédigez votre message..."
                            required
                            maxlength="2000"
                        ></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Envoyer le message
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Réinitialiser
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Announcement Tab -->
        <div class="tab-content" id="announcement">
            <div class="form-container">
                <div class="form-header">
                    <h3><i class="fas fa-bullhorn"></i> Créer une annonce générale</h3>
                </div>
                
                <form method="POST" class="message-form" id="announcementForm">
                    <input type="hidden" name="action" value="send_announcement">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="announcement_title" class="required">Titre de l'annonce</label>
                            <input 
                                type="text" 
                                name="announcement_title" 
                                id="announcement_title" 
                                placeholder="Titre de votre annonce"
                                required
                                maxlength="200"
                            >
                        </div>
                        
                        <div class="form-group">
                            <label for="announcement_priority">Priorité</label>
                            <select name="announcement_priority" id="announcement_priority">
                                <option value="low">🟢 Faible</option>
                                <option value="normal" selected>🔵 Normal</option>
                                <option value="high">🟠 Élevée</option>
                                <option value="urgent">🔴 Urgente</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="announcement_content" class="required">Contenu de l'annonce</label>
                        <textarea 
                            name="announcement_content" 
                            id="announcement_content" 
                            rows="10"
                            placeholder="Rédigez votre annonce..."
                            required
                            maxlength="3000"
                        ></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="send_to_all" checked>
                            <span class="checkmark"></span>
                            Envoyer à tous les résidents (<?php echo count($residents); ?> destinataires)
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-bullhorn"></i> Envoyer l'annonce
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Réinitialiser
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Residents List Tab -->
        <div class="tab-content" id="residents">
            <div class="residents-container">
                <div class="residents-header">
                    <h3><i class="fas fa-users"></i> Liste des résidents</h3>
                    <div class="residents-count">
                        <span class="count"><?php echo count($residents); ?></span> résidents
                    </div>
                </div>
                
                <?php if (empty($residents)): ?>
                    <div class="no-data">
                        <i class="fas fa-users"></i>
                        <h4>Aucun résident trouvé</h4>
                        <p>Il n'y a actuellement aucun résident enregistré dans votre immeuble.</p>
                    </div>
                <?php else: ?>
                    <div class="residents-grid">
                        <?php foreach ($residents as $resident): ?>
                            <div class="resident-card">
                                <div class="resident-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="resident-details">
                                    <h4><?php echo htmlspecialchars($resident['full_name']); ?></h4>
                                    <p class="apartment">
                                        <i class="fas fa-home"></i> 
                                        Appartement <?php echo htmlspecialchars($resident['apartment_number']); ?>
                                        <?php if ($resident['floor']): ?>
                                            - Étage <?php echo htmlspecialchars($resident['floor']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <p class="email">
                                        <i class="fas fa-envelope"></i> 
                                        <?php echo htmlspecialchars($resident['email']); ?>
                                    </p>
                                    <?php if ($resident['phone']): ?>
                                        <p class="phone">
                                            <i class="fas fa-phone"></i> 
                                            <?php echo htmlspecialchars($resident['phone']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="resident-actions">
                                    <button 
                                        class="btn btn-sm btn-primary" 
                                        onclick="sendQuickMessage('<?php echo $resident['id_member']; ?>', '<?php echo htmlspecialchars($resident['full_name']); ?>')"
                                    >
                                        <i class="fas fa-envelope"></i> Message
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Message Modal -->
    <div id="quickMessageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-envelope"></i> Message rapide</h3>
                <button class="close-modal" onclick="closeQuickMessage()">&times;</button>
            </div>
            <form method="POST" id="quickMessageForm">
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="recipients[]" id="quickRecipientId">
                
                <div class="modal-body">
                    <p>Destinataire: <strong id="quickRecipientName"></strong></p>
                    
                    <div class="form-group">
                        <label for="quickSubject" class="required">Sujet</label>
                        <input 
                            type="text" 
                            name="subject" 
                            id="quickSubject" 
                            placeholder="Objet du message"
                            required
                            maxlength="200"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="quickPriority">Priorité</label>
                        <select name="priority" id="quickPriority">
                            <option value="low">🟢 Faible</option>
                            <option value="normal" selected>🔵 Normal</option>
                            <option value="high">🟠 Élevée</option>
                            <option value="urgent">🔴 Urgente</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="quickMessage" class="required">Message</label>
                        <textarea 
                            name="message" 
                            id="quickMessage" 
                            rows="6"
                            placeholder="Rédigez votre message..."
                            required
                            maxlength="2000"
                        ></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Envoyer
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeQuickMessage()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../public/js/main.js"></script>
    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => button.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to selected tab button
            event.target.classList.add('active');
        }
        
        // Resident selection functions
        function selectAllResidents() {
            const checkboxes = document.querySelectorAll('input[name="recipients[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = true);
        }
        
        function deselectAllResidents() {
            const checkboxes = document.querySelectorAll('input[name="recipients[]"]');
            checkboxes.forEach(checkbox => checkbox.checked = false);
        }
        
        // Quick message modal
        function sendQuickMessage(residentId, residentName) {
            document.getElementById('quickRecipientId').value = residentId;
            document.getElementById('quickRecipientName').textContent = residentName;
            document.getElementById('quickMessageModal').style.display = 'block';
        }
        
        function closeQuickMessage() {
            document.getElementById('quickMessageModal').style.display = 'none';
            document.getElementById('quickMessageForm').reset();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('quickMessageModal');
            if (event.target === modal) {
                closeQuickMessage();
            }
        }
        
        // Form validation
        document.getElementById('individualMessageForm').addEventListener('submit', function(e) {
            const selectedRecipients = document.querySelectorAll('input[name="recipients[]"]:checked');
            
            if (selectedRecipients.length === 0) {
                e.preventDefault();
                alert('Veuillez sélectionner au moins un destinataire.');
                return false;
            }
        });
        
        // Character counters
        function addCharacterCounter(elementId, maxLength) {
            const element = document.getElementById(elementId);
            if (element) {
                element.addEventListener('input', function() {
                    updateCharCounter(this, maxLength);
                });
            }
        }
        
        function updateCharCounter(element, maxLength) {
            const current = element.value.length;
            const remaining = maxLength - current;
            
            let counter = element.parentNode.querySelector('.char-counter');
            if (!counter) {
                counter = document.createElement('small');
                counter.className = 'char-counter form-text';
                element.parentNode.appendChild(counter);
            }
            
            counter.textContent = `${current}/${maxLength} caractères`;
            counter.style.color = remaining < 50 ? '#dc3545' : '#6c757d';
        }
        
        // Add character counters
        addCharacterCounter('subject', 200);
        addCharacterCounter('message', 2000);
        addCharacterCounter('announcement_title', 200);
        addCharacterCounter('announcement_content', 3000);
        addCharacterCounter('quickSubject', 200);
        addCharacterCounter('quickMessage', 2000);
    </script>
</body>
</html>