<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Syndic-Way - Gestion de Copropriété</title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/sections/service.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Poppins:wght@600&family=Karantina:wght@400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="service.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
            <?php require_once __DIR__ . '/../public/header.php'; ?>


        
       
    </header>

    <!-- Main Content -->
    <main class="main">
        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-background">
                <img src="../assets/vector.png" alt="" class="hero-vector">
                <img class="hero-image" src="../assets/diverse-business-people-dinner-party-1.png" alt="Équipe diverse" class="hero-image">
            </div>
            
            <div class="hero-content">
                <h1 class="hero-title">
                    <span class="brand-name">syndic-way</span>
                </h1>
                
                <p class="hero-subtitle">
                    Une application unique qui centralise tous vos services pour une
                    gestion simplifiée et efficace de votre copropriété
                </p>
            </div>
        </section>

        <!-- Services Section -->
        <section class="services">
            <div class="container">
                <div class="services-grid">
                    <!-- Service 1 -->
                    <div class="service-card">
                        <div class="service-image">
                            <img src="../assets/vector-37.svg" alt="" class="service-bg">
                            <img src="../assets/747527a4-98e7-4bcb-9aa8-f45798e3126a--1--2.png" alt="Nettoyage" class="service-img">
                        </div>
                        <h3 class="service-title">Maintien de la propreté de l'immeuble</h3>
                        <p class="service-description">
                            Des équipes spécialisées assurent le nettoyage régulier pour un cadre
                            de vie toujours agréable.
                        </p>
                    </div>

                    <!-- Service 2 -->
                    <div class="service-card">
                        <div class="service-image">
                            <img src="../assets/vector-37.svg" alt="" class="service-bg">
                            <img src="../assets/195fe522-620d-4075-8443-9d7e8ff6d098--1--2.png" alt="Réparation" class="service-img">
                        </div>
                        <h3 class="service-title">Entretien et réparation des pannes</h3>
                        <p class="service-description">
                            Signalez un problème, et nous faisons intervenir un technicien
                            qualifié rapidement, en toute transparence.
                        </p>
                    </div>

                    <!-- Service 3 -->
                    <div class="service-card">
                        <div class="service-image">
                            <img src="../assets/vector-37.svg" alt="" class="service-bg">
                            <img src="../assets/fea5206f-82de-45f1-8a56-4ca9b8fbd68d--1--2.png" alt="Juridique" class="service-img">
                        </div>
                        <h3 class="service-title">Gestion rapide des problèmes juridiques</h3>
                        <p class="service-description">
                            Des spécialistes gèrent vos démarches et litiges en toute
                            conformité.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="how-it-works">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">Comment ça marche ?</h2>
                    <img src="../assets/image.svg" alt="" class="section-decoration">
                </div>

                <div class="steps-container">
                    <div class="steps-content">
                        <div class="steps-list">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h3 class="step-title">Créez votre compte</h3>
                                    <p class="step-description">Inscrivez-vous en 2 minutes pour rejoindre votre immeuble.</p>
                                </div>
                            </div>

                            <div class="step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h3 class="step-title">Ajoutez votre résidence</h3>
                                    <p class="step-description">Remplissez les informations sur votre copropriété.</p>
                                </div>
                            </div>

                            <div class="step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h3 class="step-title">Consultez ou signalez</h3>
                                    <p class="step-description">
                                        Besoin de réparation ? Question sur le budget ? Faites votre demande directement depuis la plateforme.
                                    </p>
                                </div>
                            </div>

                            <div class="step">
                                <div class="step-number">4</div>
                                <div class="step-content">
                                    <h3 class="step-title">Suivez en direct</h3>
                                    <p class="step-description">
                                        Recevez des notifications à chaque étape. Transparence garantie.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <button class="btn btn-primary cta-button">Créer un compte</button>
                    </div>

                    <div class="steps-image">
                        <img src="../assets/fair-services-svg.svg" alt="" class="steps-bg">
                        <img src="../assets/person.png" alt="Personne" class="steps-person">
                    </div>
                </div>
            </div>
        </section>

        <!-- Service Promise Section -->
        <section class="service-promise">
            <div class="container">
                <h2 class="promise-title">Notre promesse de service</h2>
                
                <div class="promise-grid">
                    <div class="promise-card">
                        <img src="../assets/vector-1.svg" alt="" class="promise-icon">
                        <h3 class="promise-card-title">Réactivité</h3>
                        <p class="promise-description">
                            Nous vous assurons une réponse en 48h ouvrées, qu'importe votre question ou votre problématique.
                        </p>
                    </div>

                    <div class="promise-card">
                        <img src="../assets/vector-1.svg" alt="" class="promise-icon">
                        <h3 class="promise-card-title">Transparence</h3>
                        <p class="promise-description">
                            Suivez en temps réel les dossiers de la vie de votre immeuble et de vos biens en location (compte, budget, sinistres…) grâce à notre plateforme et à nos applications mobiles.
                        </p>
                    </div>

                    <div class="promise-card">
                        <img src="../assets/vector-1.svg" alt="" class="promise-icon">
                        <h3 class="promise-card-title">Flexibilité</h3>
                        <p class="promise-description">
                            Nous avons créé différentes offres pour que vous puissiez choisir celle qui correspond le plus aux besoins de votre immeuble ou de vos biens en location.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

     <!-- Footer -->
    <?php require_once __DIR__ . '/../public/footer.php'; ?>

    <script src="http://localhost/syndicplatform/js/sections/service.css"></script>
</body>
</html>