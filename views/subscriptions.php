<?php
session_start();
require_once __DIR__ . '/../config.php';

try {
    $query = "SELECT * FROM subscription WHERE is_active = 1 ORDER BY price_subscription ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Connection failed.");
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Découvrez les forfaits de gestion d'immeuble les plus adaptés à vos besoins avec Syndic Way.">
    <meta property="og:title" content="Tarification | Syndic-Way">
    <meta property="og:description" content="Commencez à gérer votre syndic plus efficacement dès aujourd'hui">

    <title>Tarification | Syndic-Way</title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/sections/subscriptions.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <!-- Header -->
    <?php require_once __DIR__ . '/../public/header.php'; ?>

    <!-- Pricing Section -->
    <section class="pricing">
        <div class="container">
            <div class="pricing-header">
                <h1>Choisissez le forfait idéal pour votre immeuble</h1>
                <p>Commencez à gérer votre syndic plus efficacement dès aujourd'hui</p>
            </div>

            <div class="pricing-grid">
                <?php if (!empty($plans)): ?>
                    <?php foreach ($plans as $plan): ?>
                        <div
                            class="pricing-card <?php echo $plan['name_subscription'] === 'Forfait Professionnel' ? 'featured' : ''; ?>">
                            <?php if ($plan['name_subscription'] === 'Forfait Professionnel'): ?>
                                <div class="popular-badge">Le plus populaire</div>
                            <?php endif; ?>

                            <div class="plan-header">
                                <h3><?php echo htmlspecialchars($plan['name_subscription']); ?></h3>
                                <div class="price">
                                    <span class="amount"><?php echo number_format($plan['price_subscription'], 0); ?></span>
                                    <span class="currency">DH</span>
                                    <span class="period">/Mois</span>
                                </div>
                                <p class="plan-description"><?php echo htmlspecialchars($plan['description']); ?></p>
                            </div>

                            <div class="plan-features">
                                <ul>
                                    <li><i class="fas fa-check"></i> Jusqu'à <?php echo (int) $plan['max_residents']; ?>
                                        résidents</li>
                                    <li><i class="fas fa-check"></i> Jusqu'à <?php echo (int) $plan['max_apartments']; ?>
                                        appartements</li>
                                    <li><i class="fas fa-check"></i> Gestion de la maintenance</li>
                                    <li><i class="fas fa-check"></i> Portail résident</li>
                                    <?php if ($plan['name_subscription'] !== 'Forfait Basique'): ?>
                                        <li><i class="fas fa-check"></i> Gestion financière</li>
                                        <li><i class="fas fa-check"></i> Rapports avancés</li>
                                    <?php endif; ?>
                                    <?php if ($plan['name_subscription'] === 'Forfait Entreprise'): ?>
                                        <li><i class="fas fa-check"></i> Support prioritaire</li>
                                        <li><i class="fas fa-check"></i> Fonctionnalités personnalisées</li>
                                        <li><i class="fas fa-check"></i> Accès à l'API</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <div class="plan-footer">
                                <a href="?page=purchase&plan=<?php echo $plan['id_subscription']; ?>"
                                    class="btn <?php echo $plan['name_subscription'] === 'Forfait Professionnel' ? 'btn-primary' : 'btn-secondary'; ?> btn-full">
                                    Commencer
                                </a>
                            </div>

                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Aucun forfait disponible pour le moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>


    <!-- FAQ Section -->
    <section class="faq">
        <div class="container">
            <h2>Foire Aux Questions</h2>
            <div class="faq-grid">
                <div class="faq-item">
                    <h3>Puis-je changer de forfait plus tard ?</h3>
                    <p>Oui, vous pouvez passer à un forfait supérieur ou inférieur à tout moment. Les changements seront
                        appliqués lors de votre prochain cycle de facturation.</p>
                </div>
                <div class="faq-item">
                    <h3>Y a-t-il des frais d'installation ?</h3>
                    <p>Non, tous nos forfaits incluent une configuration gratuite et un accompagnement pour un démarrage
                        rapide.</p>
                </div>
                <div class="faq-item">
                    <h3>Quels sont les moyens de paiement acceptés ?</h3>
                    <p>Nous acceptons toutes les principales cartes de crédit, les virements bancaires et les méthodes
                        de paiement en ligne.</p>
                </div>
                <div class="faq-item">
                    <h3>Mes données sont-elles sécurisées ?</h3>
                    <p>Absolument. Nous utilisons des mesures de sécurité de niveau entreprise pour protéger vos données
                        avec des sauvegardes régulières.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php require_once __DIR__ . '/../public/footer.php'; ?>



    <script src="http://localhost/syndicplatform/js/sections/subscriptions.js"></script>
</body>

</html>