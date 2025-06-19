<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Syndic-Way - Témoignages Clients</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&family=Poppins:wght@700&family=Karantina:wght@400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/sections/aboutUs.css">
        <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">

</head>
<body>
    <!-- Header -->
    <?php require_once __DIR__ . '/../public/header.php'; ?>




    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <div class="hero-text">
                        <h1 class="hero-title">
                            Ils ont testé,<br>
                            ils ont adoré,
                        </h1>
                        <h2 class="hero-subtitle">
                            et ils vous en parlent
                        </h2>
                        <p class="hero-description">
                            Découvrez les témoignages écrits et vidéos de nos<br>
                            clients, et toutes les thématiques sur lesquelles<br>
                            <span class="highlight">Syndic-way</span> a pu les accompagner
                        </p>
                    </div>
                    <div class="hero-image">
                        <img src="../assets/full-shot-colleagues-working-office-1.png" alt="Équipe au travail" class="hero-img">
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="stats">
            <div class="container">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value">
                            4.4<span class="stat-sub">/5</span>
                            <svg class="star-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                        </div>
                        <div class="stat-description">
                            <strong>+ 1000</strong><br>
                            avis Google
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">48h</div>
                        <div class="stat-description">
                            <strong>Temps moyen de réponses</strong><br>
                            de notre équipe client à vos demandes
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">10 000</div>
                        <div class="stat-description">
                            <strong>copropriétés clientes</strong><br>
                            en Maroc
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- About Us Section -->
        <section class="about-us">
            <div class="container">
                <h2 class="section-title">
                    Pourquoi nos clients<br>
                    nous adorent ?
                </h2>
                
                <div class="tabs">
                    <button class="tab-btn active" data-tab="syndic">Syndic de copropriété</button>
                    <button class="tab-btn" data-tab="gestion">Gestion locative</button>
                </div>
                
                <div class="testimonials-carousel">
                    <div class="testimonials-container" id="testimonialsContainer">
                        <!-- Testimonials will be populated by JavaScript -->
                    </div>
                    
                    <div class="carousel-controls">
                        <button class="carousel-btn carousel-prev" id="carouselPrev">
                            <img src="../assets/675c60675a947f1a9ac49b94-slider-arrow-left-svg.svg" alt="Previous">
                        </button>
                        <div class="carousel-progress">
                            <div class="progress-bar"></div>
                        </div>
                        <button class="carousel-btn carousel-next" id="carouselNext">
                            <img src="../assets/675c6067e7891eed70d783c4-slider-arrow-right-svg.svg" alt="Next">
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Service Promise Section -->
        <section class="service-promise">
            <div class="container">
                <h2 class="service-promise-title">Notre promesse de service</h2>
                <div class="service-cards">
                    <div class="service-card">
                        <img src="../assets/vector.svg" alt="Réactivité" class="service-icon">
                        <h3 class="service-card-title">Réactivité</h3>
                        <p class="service-card-description">
                            Nous vous assurons une réponse en 48h ouvrées, qu'importe votre question ou votre problématique.
                        </p>
                    </div>
                    <div class="service-card">
                        <img src="../assets/vector.svg" alt="Transparence" class="service-icon">
                        <h3 class="service-card-title">Transparence</h3>
                        <p class="service-card-description">
                            Suivez en temps réel les dossiers de la vie de votre immeuble et de vos biens en location (compte, budget, sinistres…) grâce à notre plateforme et à nos applications mobiles.
                        </p>
                    </div>
                    <div class="service-card">
                        <img src="../assets/vector.svg" alt="Flexibilité" class="service-icon">
                        <h3 class="service-card-title">Flexibilité</h3>
                        <p class="service-card-description">
                            Nous avons créé différentes offres pour que vous puissiez choisir celle qui correspond le plus aux besoins de votre immeuble ou de vos biens en location.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact CTA Section -->
        <section class="contact-cta">
            <div class="container">
                <div class="cta-content">
                    <div class="cta-text">
                        <h3 class="cta-title">Syndic-Way vous intéresse ?</h3>
                        <p class="cta-description">
                            Nos experts sont à votre disposition pour discuter de vos problématiques et vous proposer un accompagnement sur-mesure.
                        </p>
                        <a href="mailto:contact@syndic-way.com" class="btn btn-cta">Nous contacter</a>
                    </div>
                    <div class="cta-image">
                        <img src="../assets/medium-shot-young-friends-hostel-1.png" alt="Équipe Syndic-Way">
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <?php require_once __DIR__ . '/../public/footer.php'; ?>

    <script src="http://localhost/syndicplatform/js/sections/aboutUs.js"></script>
</body>
</html>