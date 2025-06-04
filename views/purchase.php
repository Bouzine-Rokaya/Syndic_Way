<?php
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../controllers/AuthController.php';

    $page_title = "Complete Your Purchase - Syndic Way";
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
                                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="syndic_email">Adresse e-mail *</label>
                                    <input type="email" id="syndic_email" name="syndic_email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="syndic_phone">Numéro de téléphone *</label>
                                <input type="tel" id="syndic_phone" name="syndic_phone" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                            </div>

                            <div class="form-section-header">
                                <h3><i class="fas fa-building"></i>  Informations sur l'entreprise / le bâtiment</h3>
                            </div>

                            <div class="form-group">
                                <label for="company_name">Nom de l'entreprise / du bâtiment *</label>
                                <input type="text" id="company_name" name="company_name" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="company_city">Ville *</label>
                                <input type="text" id="company_city" name="company_city" 
                                       value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="company_address">Adresse du bâtiment</label>
                                <textarea id="company_address" name="company_address" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
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