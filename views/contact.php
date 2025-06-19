<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <title>Syndic Way</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="http://localhost/syndicplatform/css/sections/contact.css">
  <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
  <link href="https://fonts.googleapis.com/css?family=Karantina:400|Inter:800,400,700,600|Poppins:600" rel="stylesheet">
  <link href="contact.css" rel="stylesheet" />
  <style>
    .hero {
      background-image: url('../assets/image.png');
    }
  </style>
</head>

<body>
  <div class="container">
    <!-- Header Section -->
    <?php require_once __DIR__ . '/../public/header.php'; ?>


      <!-- Hero Section -->
      <section class="hero">
        <div class="hero-content">
          <h1 class="hero-title">
            <span class="yellow" style="color:FFCB32 ;">syndic</span>
            <span class="yellow-light" style="color:FFCB32 ;">-</span>
            <span class="yellow" style="color:FFCB32 ;">way</span>
          </h1>
        </div>
      </section>

      <!-- Services Section -->
      <section class="services">
        <div class="services-header">
          <div class="badge">Contacts</div>
          <h2>contactez notre équipe</h2>
        </div>

        <div class="services-grid">
          <div class="card image-card">
            <img src="../assets/vertical-shot-modern-table-nice-living-room 1.png" alt="Modern living room" />
          </div>

          <div class="card contact-card">
            <div class="icon">
              <img src="../assets/chat.svg-fill.png" alt="Chat" />
            </div>
            <h3>Support Team</h3>
            <a href="#" class="contact-link">Contact us</a>
          </div>

          <div class="card contact-card">
            <div class="icon">
              <img src="../assets/case-image.png" alt="Case" />
            </div>
            <h3>Policy & Government Relations</h3>
            <a href="mailto:gr@indrive.com" class="contact-link">gr@indrive.com</a>
          </div>

          <div class="card contact-card">
            <div class="icon">
              <div class="vector-icon">
                <img src="../assets/partnership-image.png" alt="Clip path" class="vector-inner" />
              </div>
            </div>
            <h3>Partnerships team</h3>
            <a href="#" class="contact-link">Contact us</a>
          </div>

          <div class="card contact-card">
            <div class="icon">
              <img src="../assets/sound-image.png" alt="Sound" />
            </div>
            <h3>Collaboration and Advertising</h3>
            <a href="mailto:marketing@indrive.com" class="contact-link">marketing@indrive.com</a>
          </div>

          <div class="card contact-card security-card">
            <div class="icon">
              <img src="../assets/bug.svg-fill.png" alt="Bug" />
            </div>
            <h3>Cyber Security</h3>
            <p>We accept vulnerability reports through</p>
            <a href="#" class="contact-link">the<br />HackerOne platform</a>
          </div>

          <div class="card image-card wide">
            <img src="../assets/Background+Shadow.png" alt="Group coworkers" />
          </div>
        </div>
      </section>

       <!-- Footer -->
      <?php require_once __DIR__ . '/../public/footer.php'; ?>
</html>