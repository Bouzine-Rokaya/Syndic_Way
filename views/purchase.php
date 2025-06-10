<?php
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../email_config.php';

$page_title = "Complete Your Purchase - Syndic Way";

$plan_id = $_GET['plan'] ?? null;

if (!$plan_id) {
    header('Location: ?page=subscriptions');
    exit();
}

$stmt = $conn->prepare("SELECT * FROM subscription WHERE id_subscription = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan) {
    $_SESSION['error'] = 'Forfait invalide.';
    header('Location: ?page=subscriptions');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les champs
    $name = trim($_POST['syndic_name'] ?? '');
    $email = trim($_POST['syndic_email'] ?? '');
    $phone = trim($_POST['syndic_phone'] ?? '');
    $company = trim($_POST['company_name'] ?? '');
    $city = trim($_POST['company_city'] ?? '');
    $address = trim($_POST['company_address'] ?? '');

    $errors = [];

    if (empty($name)) $errors[] = "Nom complet requis.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide.";
    if (empty($phone)) $errors[] = "Téléphone requis.";
    if (empty($company)) $errors[] = "Nom de la société requis.";
    if (empty($city)) $errors[] = "Ville requise.";

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        header("Location: ?page=purchase&plan=$plan_id");
        exit();
    }

    // Vérifier si l'email existe déjà
    $stmt = $conn->prepare("SELECT COUNT(*) FROM member WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['error'] = "Un compte avec cet email existe déjà.";
        header("Location: ?page=purchase&plan=$plan_id");
        exit();
    }

    try {
        $conn->beginTransaction();

        // Generate random password using the function from email_config.php
        $random_password = generateRandomPassword(12);
        $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);

        // 1. Insérer ou récupérer la ville
        $stmt = $conn->prepare("SELECT id_city FROM city WHERE city_name = ?");
        $stmt->execute([$city]);
        $city_id = $stmt->fetchColumn();
        
        if (!$city_id) {
            $stmt = $conn->prepare("INSERT INTO city (city_name) VALUES (?)");
            $stmt->execute([$city]);
            $city_id = $conn->lastInsertId();
        }

        // 2. Insérer la résidence
        $stmt = $conn->prepare("INSERT INTO residence (id_city, name, address) VALUES (?, ?, ?)");
        $stmt->execute([$city_id, $company, $address]);
        $residence_id = $conn->lastInsertId();

        // 3. Insérer le membre avec le mot de passe généré
        $stmt = $conn->prepare("INSERT INTO member (full_name, email, password, phone, role, status)
                                VALUES (?, ?, ?, ?, 2, 'active')");
        $stmt->execute([$name, $email, $hashed_password, $phone]);
        $member_id = $conn->lastInsertId();

        // 4. Créer un appartement par défaut pour ce membre
        $stmt = $conn->prepare("INSERT INTO apartment (id_residence, id_member, type, floor, number) 
                               VALUES (?, ?, 'Standard', '1', 1)");
        $stmt->execute([$residence_id, $member_id]);

        // 5. Lier à admin (id=1 par défaut)
        $stmt = $conn->prepare("INSERT INTO admin_member_link (id_admin, id_member, date_created)
                                VALUES (1, ?, NOW())");
        $stmt->execute([$member_id]);

        // 6. Enregistrer l'abonnement
        $stmt = $conn->prepare("INSERT INTO admin_member_subscription 
                               (id_admin, id_member, id_subscription, date_payment, amount)
                               VALUES (1, ?, ?, NOW(), ?)");
        $stmt->execute([$member_id, $plan_id, $plan['price_subscription']]);

        $conn->commit();

        // Send email with password using the function from email_config.php
        $email_sent = sendPasswordEmail($email, $name, $random_password, $plan['name_subscription']);
        
        // TESTING: Log the password (REMOVE IN PRODUCTION!)
        error_log("TESTING - PASSWORD SENT TO {$email}: {$random_password}");
        
        // TESTING: Store password in session for display on success page
        $_SESSION['temp_password'] = $random_password;
        $_SESSION['temp_email'] = $email;
        $_SESSION['temp_name'] = $name;
        
        // Optional: Send admin notification
        notifyAdminNewSubscription($name, $email, $plan['name_subscription'], $plan['price_subscription']);
        
        // Log email activity
        logEmailActivity($email, 'welcome_email', $email_sent ? 'success' : 'failed');
        
        if ($email_sent) {
            $_SESSION['success'] = "Achat réussi ! Vos identifiants ont été envoyés par email.";
            error_log("EMAIL SENT SUCCESSFULLY TO: {$email}");
        } else {
            $_SESSION['success'] = "Achat réussi ! Vos identifiants vous seront envoyés sous peu.";
            error_log("EMAIL FAILED FOR {$email} - PASSWORD WAS: {$random_password}");
        }
        
        header("Location: ?page=purchase-success&id=" . $member_id);
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Erreur lors de l'achat : " . $e->getMessage();
        error_log("PURCHASE ERROR: " . $e->getMessage());
        header("Location: ?page=purchase&plan=$plan_id");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/sections/purchase.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="landing-nav">
        <div class="container">
            <div class="nav-brand">
                <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/syndic-way-3l3Tx2e1PrjISxRnMx9Sk4ut1e4AQ1.png" alt="Syndic-Way" class="logo-img">
            </div>
            <div class="nav-links">
                <a href="?page=home">Accueil</a>
                <a href="?page=subscriptions">Tarification</a>
                <a href="#">À propos</a>
                <a href="#">Nos services</a>
                <a href="#">Contact</a>
            </div>
            <a href="login.php" class="btn-login">Connexion</a>
        </div>
    </nav>

    <!-- Purchase Form Section -->
    <section class="purchase-form">
        <div class="container">
            <div class="purchase-content">
                <div class="purchase-header">
                    <h1>Finalisez votre achat</h1>
                    <p>Vous êtes sur le point de commencer à gérer votre immeuble plus efficacement !</p>
                </div>

                <div class="purchase-wrapper">
                    <!-- Selected Plan Summary -->
                    <div class="plan-summary">
                        <h3>Forfait sélectionné</h3>
                        <div class="selected-plan-card">
                            <h4><?php echo htmlspecialchars($plan['name_subscription']); ?></h4>
                            <div class="plan-price">
                                <span class="amount"><?php echo number_format($plan['price_subscription'], 0); ?></span>
                                <span class="currency">DH</span>
                                <span class="period">/Mois</span>
                            </div>
                            <ul class="plan-benefits">
                                <li><i class="fas fa-check"></i> Jusqu'à <?php echo (int)$plan['max_residents']; ?> résidents</li>
                                <li><i class="fas fa-check"></i> Jusqu'à <?php echo (int)$plan['max_apartments']; ?> appartements</li>
                                <li><i class="fas fa-check"></i> Gestion de la maintenance</li>
                                <li><i class="fas fa-check"></i> Système de facturation et de gestion des paiements</li>
                                <?php if($plan['name_subscription'] !== 'Forfait Basique'): ?>
                                <li><i class="fas fa-check"></i> Rapports avancés</li>
                                <li><i class="fas fa-check"></i> Notifications par e-mail</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Purchase Form -->
                     <div class="form-section">
                        <?php if(isset($_SESSION['error'])): ?>
                        <div class="alert alert-error">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        </div>
                        <?php endif; ?>

                        <?php if(isset($_SESSION['errors'])): ?>
                        <div class="alert alert-error">
                            <ul>
                                <?php foreach($_SESSION['errors'] as $error): ?>
                                <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php unset($_SESSION['errors']); ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST" id="purchase-form">
                            <div class="form-section-header">
                                <h3><i class="fas fa-user"></i> Informations de contact</h3>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="syndic_name">Nom complet *</label>
                                    <input type="text" id="syndic_name" name="syndic_name" 
                                           value="<?php echo htmlspecialchars($_POST['syndic_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="syndic_email">Adresse e-mail *</label>
                                    <input type="email" id="syndic_email" name="syndic_email" 
                                           value="<?php echo htmlspecialchars($_POST['syndic_email'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="syndic_phone">Numéro de téléphone *</label>
                                <input type="tel" id="syndic_phone" name="syndic_phone" 
                                       value="<?php echo htmlspecialchars($_POST['syndic_phone'] ?? ''); ?>" required>
                            </div>

                            <div class="form-section-header">
                                <h3><i class="fas fa-building"></i>  Informations sur l'entreprise / le bâtiment</h3>
                            </div>

                            <div class="form-group">
                                <label for="company_name">Nom de l'entreprise / du bâtiment *</label>
                                <input type="text" id="company_name" name="company_name" 
                                       value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="company_city">Ville *</label>
                                <input type="text" id="company_city" name="company_city" 
                                       value="<?php echo htmlspecialchars($_POST['company_city'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="company_address">Adresse du bâtiment</label>
                                <textarea id="company_address" name="company_address" rows="3"><?php echo htmlspecialchars($_POST['company_address'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-section-header">
                                <h3><i class="fas fa-credit-card"></i> Récapitulatif du paiement</h3>
                            </div>

                            <div class="payment-summary">
                                <div class="summary-row">
                                    <span>Forfait : <?php echo htmlspecialchars($plan['name_subscription']); ?></span>
                                    <span><?php echo number_format($plan['price_subscription'], 0, '', ' '); ?> DH / Mois</span>                                </div>
                                <div class="summary-row">
                                    <span>Frais d'installation : </span>
                                    <span class="free">GRATUIT</span>
                                </div>
                                <div class="summary-row total">
                                    <span><strong>Total mensuel :</strong></span>
                                    <span><strong><?php echo number_format($plan['price_subscription'], 2); ?>DH</strong></span>
                                </div>
                            </div>

                            <div class="terms-section">
                                <label class="checkbox-label">
                                    <input type="checkbox" required>
                                    J'accepte les <a href="#"> Conditions Générales</a>
                                </label>
                            </div>

                            <div class="form-actions">
                                <a href="?page=subscriptions" class="btn btn-secondary">← Back to Plans</a>
                                <button type="submit" class="btn btn-primary btn-large">
                                    <i class="fas fa-credit-card"></i> Complete Purchase
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Security & Trust Section -->
    <section class="trust-section">
        <div class="container">
            <div class="trust-items">
                <div class="trust-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure Payment</span>
                </div>
                <div class="trust-item">
                    <i class="fas fa-clock"></i>
                    <span>24/7 Support</span>
                </div>
                <div class="trust-item">
                    <i class="fas fa-undo"></i>
                    <span>30-Day Money Back</span>
                </div>
            </div>
        </div>
    </section>
                    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3></h3>
                    <p>Solution moderne de gestion de syndic pour l'ère numérique.</p>
                </div>
                <div class="footer-section">
                    <h4>Fonctionnalités</h4>
                    <ul>
                        <li><a href="#">Gestion des résidents</a></li>
                        <li><a href="#">Suivi de la maintenance</a></li>
                        <li><a href="#">Système de facturation</a></li>
                        <li><a href="#">Rapports</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">Centre d'aide</a></li>
                        <li><a href="#">Contactez-nous</a></li>
                        <li><a href="#">Documentation</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p> <?php echo date('Y'); ?> Syndic Way . Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script src="js/sections/subscriptions.js"></script>
</body>
</html>