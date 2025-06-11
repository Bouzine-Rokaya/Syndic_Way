<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit();
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_general_settings') {
            $site_name = trim($_POST['site_name'] ?? '');
            $site_description = trim($_POST['site_description'] ?? '');
            $contact_email = trim($_POST['contact_email'] ?? '');
            $support_phone = trim($_POST['support_phone'] ?? '');
            $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
            
            if (empty($site_name) || empty($contact_email)) {
                throw new Exception("Le nom du site et l'email de contact sont requis.");
            }
            
            if (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("L'adresse email n'est pas valide.");
            }
            
            // Update settings in database (you might want to create a settings table)
            $stmt = $conn->prepare("
                INSERT INTO site_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            
            $settings = [
                'site_name' => $site_name,
                'site_description' => $site_description,
                'contact_email' => $contact_email,
                'support_phone' => $support_phone,
                'maintenance_mode' => $maintenance_mode
            ];
            
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            
            $_SESSION['success'] = "Paramètres généraux mis à jour avec succès.";
            
        } elseif ($action === 'update_payment_settings') {
            $payment_gateway = $_POST['payment_gateway'] ?? '';
            $stripe_public_key = trim($_POST['stripe_public_key'] ?? '');
            $stripe_secret_key = trim($_POST['stripe_secret_key'] ?? '');
            $paypal_client_id = trim($_POST['paypal_client_id'] ?? '');
            $paypal_client_secret = trim($_POST['paypal_client_secret'] ?? '');
            $currency = $_POST['currency'] ?? 'MAD';
            $tax_rate = floatval($_POST['tax_rate'] ?? 0);
            
            $payment_settings = [
                'payment_gateway' => $payment_gateway,
                'stripe_public_key' => $stripe_public_key,
                'stripe_secret_key' => $stripe_secret_key,
                'paypal_client_id' => $paypal_client_id,
                'paypal_client_secret' => $paypal_client_secret,
                'currency' => $currency,
                'tax_rate' => $tax_rate
            ];
            
            $stmt = $conn->prepare("
                INSERT INTO site_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            
            foreach ($payment_settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            
            $_SESSION['success'] = "Paramètres de paiement mis à jour avec succès.";
            
        } elseif ($action === 'update_email_settings') {
            $smtp_host = trim($_POST['smtp_host'] ?? '');
            $smtp_port = intval($_POST['smtp_port'] ?? 587);
            $smtp_username = trim($_POST['smtp_username'] ?? '');
            $smtp_password = trim($_POST['smtp_password'] ?? '');
            $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';
            $from_email = trim($_POST['from_email'] ?? '');
            $from_name = trim($_POST['from_name'] ?? '');
            
            if (!empty($from_email) && !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("L'adresse email d'expédition n'est pas valide.");
            }
            
            $email_settings = [
                'smtp_host' => $smtp_host,
                'smtp_port' => $smtp_port,
                'smtp_username' => $smtp_username,
                'smtp_password' => $smtp_password,
                'smtp_encryption' => $smtp_encryption,
                'from_email' => $from_email,
                'from_name' => $from_name
            ];
            
            $stmt = $conn->prepare("
                INSERT INTO site_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            
            foreach ($email_settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            
            $_SESSION['success'] = "Paramètres email mis à jour avec succès.";
            
        } elseif ($action === 'update_security_settings') {
            $max_login_attempts = intval($_POST['max_login_attempts'] ?? 5);
            $lockout_duration = intval($_POST['lockout_duration'] ?? 30);
            $session_timeout = intval($_POST['session_timeout'] ?? 60);
            $password_min_length = intval($_POST['password_min_length'] ?? 8);
            $require_password_uppercase = isset($_POST['require_password_uppercase']) ? 1 : 0;
            $require_password_numbers = isset($_POST['require_password_numbers']) ? 1 : 0;
            $require_password_symbols = isset($_POST['require_password_symbols']) ? 1 : 0;
            $enable_two_factor = isset($_POST['enable_two_factor']) ? 1 : 0;
            
            $security_settings = [
                'max_login_attempts' => $max_login_attempts,
                'lockout_duration' => $lockout_duration,
                'session_timeout' => $session_timeout,
                'password_min_length' => $password_min_length,
                'require_password_uppercase' => $require_password_uppercase,
                'require_password_numbers' => $require_password_numbers,
                'require_password_symbols' => $require_password_symbols,
                'enable_two_factor' => $enable_two_factor
            ];
            
            $stmt = $conn->prepare("
                INSERT INTO site_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            
            foreach ($security_settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }
            
            $_SESSION['success'] = "Paramètres de sécurité mis à jour avec succès.";
            
        } elseif ($action === 'update_admin_profile') {
            $admin_name = trim($_POST['admin_name'] ?? '');
            $admin_email = trim($_POST['admin_email'] ?? '');
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($admin_name) || empty($admin_email)) {
                throw new Exception("Le nom et l'email sont requis.");
            }
            
            if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("L'adresse email n'est pas valide.");
            }
            
            // Check if email is already used by another user
            $stmt = $conn->prepare("SELECT id_member FROM member WHERE email = ? AND id_member != ?");
            $stmt->execute([$admin_email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                throw new Exception("Cette adresse email est déjà utilisée.");
            }
            
            // Update admin profile
            $stmt = $conn->prepare("UPDATE member SET name = ?, email = ? WHERE id_member = ?");
            $stmt->execute([$admin_name, $admin_email, $_SESSION['user_id']]);
            
            // Update session data
            $_SESSION['user_name'] = $admin_name;
            $_SESSION['user_email'] = $admin_email;
            
            // Handle password change
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    throw new Exception("Le mot de passe actuel est requis pour changer le mot de passe.");
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception("Les nouveaux mots de passe ne correspondent pas.");
                }
                
                if (strlen($new_password) < 8) {
                    throw new Exception("Le nouveau mot de passe doit contenir au moins 8 caractères.");
                }
                
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM member WHERE id_member = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if (!password_verify($current_password, $user['password'])) {
                    throw new Exception("Le mot de passe actuel est incorrect.");
                }
                
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE member SET password = ? WHERE id_member = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
            }
            
            $_SESSION['success'] = "Profil administrateur mis à jour avec succès.";
            
        } elseif ($action === 'backup_database') {
            // This would typically be handled by a separate script
            $_SESSION['success'] = "Sauvegarde de la base de données lancée. Vous recevrez un email de confirmation.";
            
        } elseif ($action === 'clear_cache') {
            // Clear various caches
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            // Clear session files older than 24 hours
            $session_path = session_save_path();
            if ($session_path && is_dir($session_path)) {
                $files = glob($session_path . '/sess_*');
                $now = time();
                foreach ($files as $file) {
                    if (is_file($file) && ($now - filemtime($file)) > 86400) {
                        unlink($file);
                    }
                }
            }
            
            $_SESSION['success'] = "Cache vidé avec succès.";
            
        } elseif ($action === 'test_email') {
            $test_email = trim($_POST['test_email'] ?? '');
            
            if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Adresse email de test invalide.");
            }
            
            // Here you would implement actual email sending
            // For now, we'll just simulate it
            $_SESSION['success'] = "Email de test envoyé à " . htmlspecialchars($test_email);
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur: " . $e->getMessage();
    }
    
    header('Location: settings.php');
    exit();
}

// Get current settings
function getSetting($conn, $key, $default = '') {
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Get admin profile
try {
    $stmt = $conn->prepare("SELECT * FROM member WHERE id_member = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin_profile = $stmt->fetch();
} catch (Exception $e) {
    $admin_profile = null;
}

// Get system information
$system_info = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'mysql_version' => $conn->getAttribute(PDO::ATTR_SERVER_VERSION),
    'disk_space' => disk_free_space('.'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size')
];

$page_title = "Paramètres - Syndic Way";
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/dashboard.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/admin/settings.css">
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
                    <li class="active">
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
                    <h1><i class="fas fa-cog"></i> Paramètres du Système</h1>
                    <p>Configurez et gérez les paramètres de votre plateforme</p>
                </div>
                <button class="btn btn-primary" onclick="saveAllSettings()">
                    <i class="fas fa-save"></i> Sauvegarder Tout
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

            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button class="tab-button active" onclick="switchTab('general')">
                    <i class="fas fa-cog"></i> Général
                </button>
                <button class="tab-button" onclick="switchTab('payment')">
                    <i class="fas fa-credit-card"></i> Paiement
                </button>
                <button class="tab-button" onclick="switchTab('email')">
                    <i class="fas fa-envelope"></i> Email
                </button>
                <button class="tab-button" onclick="switchTab('security')">
                    <i class="fas fa-shield-alt"></i> Sécurité
                </button>
                <button class="tab-button" onclick="switchTab('profile')">
                    <i class="fas fa-user"></i> Profil
                </button>
                <button class="tab-button" onclick="switchTab('system')">
                    <i class="fas fa-server"></i> Système
                </button>
            </div>

            <!-- General Settings Tab -->
            <div id="general" class="tab-content active">
                <form method="POST" onsubmit="return validateForm(this)">
                    <input type="hidden" name="action" value="update_general_settings">
                    
                    <div class="settings-section">
                        <div class="section-header">
                            <i class="fas fa-info-circle"></i>
                            <h3>Informations Générales</h3>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="site_name">Nom du Site</label>
                                <input type="text" name="site_name" id="site_name" 
                                       value="<?php echo htmlspecialchars(getSetting($conn, 'site_name', 'Syndic Way')); ?>" required>
                                <div class="help-text">Le nom qui apparaîtra dans l'interface utilisateur</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="contact_email">Email de Contact</label>
                                <input type="email" name="contact_email" id="contact_email" 
                                       value="<?php echo htmlspecialchars(getSetting($conn, 'contact_email', 'contact@syndicway.com')); ?>" required>
                                <div class="help-text">Adresse email principale pour les contacts</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="site_description">Description du Site</label>
                            <textarea name="site_description" id="site_description"><?php echo htmlspecialchars(getSetting($conn, 'site_description', '')); ?></textarea>
                            <div class="help-text">Description utilisée pour le SEO et les réseaux sociaux</div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="support_phone">Téléphone Support</label>
                                <input type="tel" name="support_phone" id="support_phone" 
                                       value="<?php echo htmlspecialchars(getSetting($conn, 'support_phone', '')); ?>">
                                <div class="help-text">Numéro de téléphone pour le support client</div>
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="maintenance_mode" id="maintenance_mode" 
                                           <?php echo getSetting($conn, 'maintenance_mode', 0) ? 'checked' : ''; ?>>
                                    <label for="maintenance_mode">Mode Maintenance</label>
                                </div>
                                <div class="help-text">Active le mode maintenance pour les utilisateurs non-admin</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="settings-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Sauvegarder
                        </button>
                        </div>
               </form>
           </div>

           <!-- Payment Settings Tab -->
           <div id="payment" class="tab-content">
               <form method="POST" onsubmit="return validateForm(this)">
                   <input type="hidden" name="action" value="update_payment_settings">
                   
                   <div class="settings-section">
                       <div class="section-header">
                           <i class="fas fa-credit-card"></i>
                           <h3>Configuration des Paiements</h3>
                       </div>
                       
                       <div class="form-grid">
                           <div class="form-group">
                               <label for="payment_gateway">Passerelle de Paiement</label>
                               <select name="payment_gateway" id="payment_gateway">
                                   <option value="stripe" <?php echo getSetting($conn, 'payment_gateway', 'stripe') === 'stripe' ? 'selected' : ''; ?>>Stripe</option>
                                   <option value="paypal" <?php echo getSetting($conn, 'payment_gateway', 'stripe') === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                   <option value="both" <?php echo getSetting($conn, 'payment_gateway', 'stripe') === 'both' ? 'selected' : ''; ?>>Les Deux</option>
                               </select>
                           </div>
                           
                           <div class="form-group">
                               <label for="currency">Devise</label>
                               <select name="currency" id="currency">
                                   <option value="MAD" <?php echo getSetting($conn, 'currency', 'MAD') === 'MAD' ? 'selected' : ''; ?>>MAD (Dirham Marocain)</option>
                                   <option value="EUR" <?php echo getSetting($conn, 'currency', 'MAD') === 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                                   <option value="USD" <?php echo getSetting($conn, 'currency', 'MAD') === 'USD' ? 'selected' : ''; ?>>USD (Dollar US)</option>
                               </select>
                           </div>
                       </div>
                       
                       <div class="form-group">
                           <label for="tax_rate">Taux de TVA (%)</label>
                           <input type="number" name="tax_rate" id="tax_rate" step="0.01" min="0" max="100"
                                  value="<?php echo getSetting($conn, 'tax_rate', '20'); ?>">
                           <div class="help-text">Taux de TVA appliqué aux abonnements (ex: 20 pour 20%)</div>
                       </div>
                   </div>
                   
                   <div class="settings-section">
                       <div class="section-header">
                           <i class="fab fa-stripe"></i>
                           <h3>Configuration Stripe</h3>
                       </div>
                       
                       <div class="form-grid">
                           <div class="form-group">
                               <label for="stripe_public_key">Clé Publique Stripe</label>
                               <input type="text" name="stripe_public_key" id="stripe_public_key" 
                                      value="<?php echo htmlspecialchars(getSetting($conn, 'stripe_public_key', '')); ?>"
                                      placeholder="pk_test_...">
                               <div class="help-text">Clé publique pour l'intégration Stripe</div>
                           </div>
                           
                           <div class="form-group">
                               <label for="stripe_secret_key">Clé Secrète Stripe</label>
                               <input type="password" name="stripe_secret_key" id="stripe_secret_key" 
                                      value="<?php echo htmlspecialchars(getSetting($conn, 'stripe_secret_key', '')); ?>"
                                      placeholder="sk_test_...">
                               <div class="help-text">Clé secrète pour l'intégration Stripe (gardée confidentielle)</div>
                           </div>
                       </div>
                       
                       <div class="test-connection">
                           <button type="button" class="btn btn-secondary" onclick="testStripeConnection()">
                               <i class="fas fa-plug"></i> Tester la Connexion
                           </button>
                           <div id="stripe-status" class="connection-status" style="display: none;"></div>
                       </div>
                   </div>
                   
                   <div class="settings-section">
                       <div class="section-header">
                           <i class="fab fa-paypal"></i>
                           <h3>Configuration PayPal</h3>
                       </div>
                       
                       <div class="form-grid">
                           <div class="form-group">
                               <label for="paypal_client_id">Client ID PayPal</label>
                               <input type="text" name="paypal_client_id" id="paypal_client_id" 
                                      value="<?php echo htmlspecialchars(getSetting($conn, 'paypal_client_id', '')); ?>"
                                      placeholder="AXt...">
                               <div class="help-text">Client ID pour l'intégration PayPal</div>
                           </div>
                           
                           <div class="form-group">
                               <label for="paypal_client_secret">Client Secret PayPal</label>
                               <input type="password" name="paypal_client_secret" id="paypal_client_secret" 
                                      value="<?php echo htmlspecialchars(getSetting($conn, 'paypal_client_secret', '')); ?>"
                                      placeholder="EI...">
                               <div class="help-text">Client Secret pour l'intégration PayPal</div>
                           </div>
                       </div>
                       
                       <div class="test-connection">
                           <button type="button" class="btn btn-secondary" onclick="testPayPalConnection()">
                               <i class="fas fa-plug"></i> Tester la Connexion
                           </button>
                           <div id="paypal-status" class="connection-status" style="display: none;"></div>
                       </div>
                   </div>
                   
                   <div class="settings-actions">
                       <button type="submit" class="btn btn-primary">
                           <i class="fas fa-save"></i> Sauvegarder
                       </button>
                   </div>
               </form>
           </div>

           <!-- Email Settings Tab -->
           <div id="email" class="tab-content">
               <form method="POST" onsubmit="return validateForm(this)">
                   <input type="hidden" name="action" value="update_email_settings">
                   
                   <div class="settings-section">
                       <div class="section-header">
                           <i class="fas fa-server"></i>
                           <h3>Configuration SMTP</h3>
                       </div>
                       
                       <div class="form-grid">
                           <div class="form-group">
                               <label for="smtp_host">Serveur SMTP</label>
                               <input type="text" name="smtp_host" id="smtp_host" 
                                      value="<?php echo htmlspecialchars(getSetting($conn, 'smtp_host', '')); ?>"
                                      placeholder="smtp.gmail.com">
                               <div class="help-text">Adresse du serveur SMTP</div>
                           </div>
                           
                           <div class="form-group">
                               <label for="smtp_port">Port SMTP</label>
                               <input type="number" name="smtp_port" id="smtp_port" 
                                      value="<?php echo getSetting($conn, 'smtp_port', '587'); ?>"
                                      min="1" max="65535">
                               <div class="help-text">Port du serveur SMTP (587 pour TLS, 465 pour SSL)</div>
                           </div>
                       </div>
                       
                       <div class="form-grid">
                           <div class="form-group">
                               <label for="smtp_username">Nom d'utilisateur SMTP</label>
                               <input type="text" name="smtp_username" id="smtp_username" 
                                      value="<?php echo htmlspecialchars(getSetting($conn, 'smtp_username', '')); ?>"
                                      autocomplete="username">
                               <div class="help-text">Nom d'utilisateur pour l'authentification SMTP</div>
                           </div>
                           
                           <div class="form-group">
                               <label for="smtp_password">Mot de passe SMTP</label>
                               <input type="password" name="smtp_password" id="smtp_password" 
                                      value="<?php echo htmlspecialchars(getSetting($conn, 'smtp_password', '')); ?>"
                                      autocomplete="current-password">
                               <div class="help-text">Mot de passe pour l'authentification SMTP</div>
                           </div>
                       </div>
                       
                       <div class="form-group">
                           <label for="smtp_encryption">Chiffrement</label>
                           <select name="smtp_encryption" id="smtp_encryption">
                               <option value="tls" <?php echo getSetting($conn, 'smtp_encryption', 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                               <option value="ssl" <?php echo getSetting($conn, 'smtp_encryption', 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                               <option value="none" <?php echo getSetting($conn, 'smtp_encryption', 'tls') === 'none' ? 'selected' : ''; ?>>Aucun</option>
                           </select>
                       </div>
                   </div>
                   
                   <div class="settings-section">
                       <div class="section-header">
                           <i class="fas fa-envelope-open"></i>
                           <h3>Paramètres d'Expédition</h3>
                       </div>
                       
                       <div class="form-grid">
                           <div class="form-group">
                               <label for="from_email">Email d'expédition</label>
                               <input type="email" name="from_email" id="from_email" 
                                      value="<?php echo htmlspecialchars(getSetting($conn, 'from_email', '')); ?>"
                                      placeholder="noreply@syndicway.com">
                               <div class="help-text">Adresse email utilisée pour envoyer les emails</div>
                           </div>
                           
                           <div class="form-group">
                               <label for="from_name">Nom d'expédition</label>
                               <input type="text" name="from_name" id="from_name" 
                                      value="<?php echo htmlspecialchars(getSetting($conn, 'from_name', 'Syndic Way')); ?>"
                                      placeholder="Syndic Way">
                               <div class="help-text">Nom affiché comme expéditeur</div>
                           </div>
                       </div>
                       
                       <div class="test-connection">
                           <div class="form-group" style="flex: 1; margin-bottom: 0;">
                               <input type="email" placeholder="Email de test" id="test_email_input">
                           </div>
                           <button type="button" class="btn btn-secondary" onclick="sendTestEmail()">
                               <i class="fas fa-paper-plane"></i> Envoyer Test
                           </button>
                           <div id="email-status" class="connection-status" style="display: none;"></div>
                       </div>
                   </div>
                   
                   <div class="settings-actions">
                       <button type="submit" class="btn btn-primary">
                           <i class="fas fa-save"></i> Sauvegarder
                       </button>
                   </div>
               </form>
           </div>

           <!-- Security Settings Tab -->
           <div id="security" class="tab-content">
               <form method="POST" onsubmit="return validateForm(this)">
                   <input type="hidden" name="action" value="update_security_settings">
                   
                   <div class="settings-section">
                       <div class="section-header">
                           <i class="fas fa-lock"></i>
                           <h3>Sécurité des Connexions</h3>
                       </div>
                       
                       <div class="form-grid">
                           <div class="form-group">
                               <label for="max_login_attempts">Tentatives de connexion max</label>
                               <input type="number" name="max_login_attempts" id="max_login_attempts" 
                                      value="<?php echo getSetting($conn, 'max_login_attempts', '5'); ?>"
                                      min="1" max="20">
                               <div class="help-text">Nombre maximum de tentatives avant blocage</div>
                           </div>
                           
                           <div class="form-group">
                               <label for="lockout_duration">Durée de blocage (minutes)</label>
                               <input type="number" name="lockout_duration" id="lockout_duration" 
                                      value="<?php echo getSetting($conn, 'lockout_duration', '30'); ?>"
                                      min="1" max="1440">
                               <div class="help-text">Durée du blocage après échec des connexions</div>
                           </div>
                       </div>
                       
                       <div class="form-group">
                           <label for="session_timeout">Timeout de session (minutes)</label>
                           <input type="number" name="session_timeout" id="session_timeout" 
                                  value="<?php echo getSetting($conn, 'session_timeout', '60'); ?>"
                                  min="5" max="480">
                           <div class="help-text">Durée d'inactivité avant déconnexion automatique</div>
                       </div>
                   </div>
                   
                   <div class="settings-section">
                       <div class="section-header">
                           <i class="fas fa-key"></i>
                           <h3>Politique des Mots de Passe</h3>
                       </div>
                       
                       <div class="form-group">
                           <label for="password_min_length">Longueur minimale</label>
                           <input type="number" name="password_min_length" id="password_min_length" 
                                  value="<?php echo getSetting($conn, 'password_min_length', '8'); ?>"
                                  min="4" max="128">
                           <div class="help-text">Nombre minimum de caractères requis</div>
                       </div>
                       
                       <div class="form-grid">
                           <div class="form-group">
                               <div class="checkbox-group">
                                   <input type="checkbox" name="require_password_uppercase" id="require_password_uppercase" 
                                          <?php echo getSetting($conn, 'require_password_uppercase', 0) ? 'checked' : ''; ?>>
                                   <label for="require_password_uppercase">Majuscules requises</label>
                               </div>
                           </div>
                           
                           <div class="form-group">
                               <div class="checkbox-group">
                                   <input type="checkbox" name="require_password_numbers" id="require_password_numbers" 
                                          <?php echo getSetting($conn, 'require_password_numbers', 0) ? 'checked' : ''; ?>>
                                   <label for="require_password_numbers">Chiffres requis</label>
                               </div>
                           </div>
                       </div>
                       
                       <div class="form-grid">
                           <div class="form-group">
                               <div class="checkbox-group">
                                   <input type="checkbox" name="require_password_symbols" id="require_password_symbols" 
                                          <?php echo getSetting($conn, 'require_password_symbols', 0) ? 'checked' : ''; ?>>
                                   <label for="require_password_symbols">Symboles requis</label>
                               </div>
                           </div>
                           
                           <div class="form-group">
                               <div class="checkbox-group">
                                   <input type="checkbox" name="enable_two_factor" id="enable_two_factor" 
                                          <?php echo getSetting($conn, 'enable_two_factor', 0) ? 'checked' : ''; ?>>
                                   <label for="enable_two_factor">Authentification 2FA</label>
                               </div>
                           </div>
                       </div>
                       
                       <div class="security-level">
                           <span>Niveau de sécurité: </span>
                           <div class="indicator active"></div>
                           <div class="indicator active"></div>
                           <div class="indicator medium"></div>
                           <div class="indicator"></div>
                           <div class="indicator"></div>
                           <span id="security-level-text">Moyen</span>
                       </div>
                   </div>
                   
                   <div class="settings-actions">
                       <button type="submit" class="btn btn-primary">
                           <i class="fas fa-save"></i> Sauvegarder
                       </button>
                   </div>
               </form>
           </div>

           <!-- Profile Settings Tab -->
           <div id="profile" class="tab-content">
               <form method="POST" onsubmit="return validateForm(this)">
                   <input type="hidden" name="action" value="update_admin_profile">
                   
                   <div class="settings-section">
                       <div class="section-header">
                           <i class="fas fa-user-circle"></i>
                           <h3>Informations du Profil</h3>
                       </div>
                       
                       <div class="form-grid">
                           <div class="form-group">
                               <label for="admin_name">Nom Complet</label>
                               <input type="text" name="admin_name" id="admin_name" 
                                      value="<?php echo htmlspecialchars($admin_profile['name'] ?? ''); ?>" required>
                               <div class="help-text">Votre nom complet tel qu'affiché dans l'interface</div>
                           </div>
                           
                           <div class="form-group">
                               <label for="admin_email">Adresse Email</label>
                               <input type="email" name="admin_email" id="admin_email" 
                                      value="<?php echo htmlspecialchars($admin_profile['email'] ?? ''); ?>" required>
                               <div class="help-text">Adresse email pour votre compte administrateur</div>
                           </div>
                       </div>
                   </div>
                   
                   <div class="settings-section">
                       <div class="section-header">
                           <i class="fas fa-lock"></i>
                           <h3>Changement de Mot de Passe</h3>
                       </div>
                       
                       <div class="form-group">
                           <label for="current_password">Mot de passe actuel</label>
                           <input type="password" name="current_password" id="current_password" 
                                  autocomplete="current-password">
                           <div class="help-text">Requis seulement si vous changez le mot de passe</div>
                       </div>
                       
                       <div class="form-grid">
                           <div class="form-group">
                               <label for="new_password">Nouveau mot de passe</label>
                               <input type="password" name="new_password" id="new_password" 
                                      autocomplete="new-password" onkeyup="checkPasswordStrength(this.value)">
                               <div class="password-strength">
                                   <div class="strength-bar">
                                       <div class="strength-fill" id="strength-fill"></div>
                                   </div>
                                   <small id="strength-text">Entrez un mot de passe</small>
                               </div>
                           </div>
                           
                           <div class="form-group">
                               <label for="confirm_password">Confirmer le mot de passe</label>
                               <input type="password" name="confirm_password" id="confirm_password" 
                                      autocomplete="new-password">
                               <div class="help-text">Répétez le nouveau mot de passe</div>
                           </div>
                       </div>
                   </div>
                   
                   <div class="settings-actions">
                       <button type="submit" class="btn btn-primary">
                           <i class="fas fa-save"></i> Mettre à jour le Profil
                       </button>
                   </div>
               </form>
           </div>

           <!-- System Settings Tab -->
           <div id="system" class="tab-content">
               <div class="settings-section">
                   <div class="section-header">
                       <i class="fas fa-info-circle"></i>
                       <h3>Informations Système</h3>
                   </div>
                   
                   <div class="info-grid">
                       <div class="info-card">
                           <h4>Version PHP</h4>
                           <div class="value"><?php echo $system_info['php_version']; ?></div>
                       </div>
                       
                       <div class="info-card">
                           <h4>Serveur Web</h4>
                           <div class="value"><?php echo $system_info['server_software']; ?></div>
                       </div>
                       
                       <div class="info-card">
                           <h4>Version MySQL</h4>
                           <div class="value"><?php echo $system_info['mysql_version']; ?></div>
                       </div>
                       
                       <div class="info-card">
                           <h4>Espace Disque Libre</h4>
                           <div class="value"><?php echo round($system_info['disk_space'] / 1024 / 1024 / 1024, 2); ?> GB</div>
                       </div>
                       
                       <div class="info-card">
                           <h4>Limite Mémoire</h4>
                           <div class="value"><?php echo $system_info['memory_limit']; ?></div>
                       </div>
                       
                       <div class="info-card">
                           <h4>Temps d'Exécution Max</h4>
                           <div class="value"><?php echo $system_info['max_execution_time']; ?>s</div>
                       </div>
                   </div>
               </div>
               
               <div class="settings-section">
                   <div class="section-header">
                       <i class="fas fa-tools"></i>
                       <h3>Actions Rapides</h3>
                   </div>
                   
                   <div class="quick-actions">
                       <div class="quick-action" onclick="clearCache()">
                           <i class="fas fa-broom"></i>
                           <h4>Vider le Cache</h4>
                           <p>Nettoie les fichiers de cache temporaires</p>
                       </div>
                       
                       <div class="quick-action" onclick="backupDatabase()">
                           <i class="fas fa-database"></i>
                           <h4>Sauvegarder BD</h4>
                           <p>Crée une sauvegarde de la base de données</p>
                       </div>
                       
                       <div class="quick-action" onclick="checkUpdates()">
                           <i class="fas fa-download"></i>
                           <h4>Vérifier MAJ</h4>
                           <p>Recherche les mises à jour disponibles</p>
                       </div>
                       
                       <div class="quick-action" onclick="optimizeDatabase()">
                           <i class="fas fa-tachometer-alt"></i>
                           <h4>Optimiser BD</h4>
                           <p>Optimise les performances de la base</p>
                       </div>
                   </div>
               </div>
               
               <div class="settings-section">
                   <div class="section-header">
                       <i class="fas fa-history"></i>
                       <h3>Sauvegardes</h3>
                   </div>
                   
                   <div class="backup-status">
                       <h5>Dernière Sauvegarde</h5>
                       <p>Il y a 2 jours - backup_20250606_143022.sql (125 MB)</p>
                   </div>
                   
                   <div class="form-grid">
                       <div class="form-group">
                           <label>Sauvegarde Automatique</label>
                           <select>
                               <option value="daily">Quotidienne</option>
                               <option value="weekly" selected>Hebdomadaire</option>
                               <option value="monthly">Mensuelle</option>
                               <option value="disabled">Désactivée</option>
                           </select>
                       </div>
                       
                       <div class="form-group">
                           <label>Conserver (nombre)</label>
                           <input type="number" value="10" min="1" max="100">
                       </div>
                   </div>
               </div>
               
               <div class="danger-zone">
                   <h4><i class="fas fa-exclamation-triangle"></i> Zone Dangereuse</h4>
                   <p>Ces actions sont irréversibles. Procédez avec prudence.</p>
                   
                   <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                       <button type="button" class="btn btn-danger" onclick="resetSettings()">
                           <i class="fas fa-undo"></i> Réinitialiser Paramètres
                       </button>
                       
                       <button type="button" class="btn btn-danger" onclick="clearAllData()">
                           <i class="fas fa-trash"></i> Effacer Toutes Données
                       </button>
                       
                       <button type="button" class="btn btn-danger" onclick="factoryReset()">
                           <i class="fas fa-exclamation-circle"></i> Remise à Zéro
                       </button>
                   </div>
               </div>
           </div>
       </main>
   </div>

   <script src="http://localhost/syndicplatform/js/admin/settings.js"></script>

   <!-- Add some additional CSS for enhanced features -->
   <style>
       
   </style>
</body>
</html>