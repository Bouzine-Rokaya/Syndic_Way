<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is a resident
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'resident') {
    header('Location: http://localhost/syndicplatform/public/login.php');
    exit();
}

// Get resident information and building details
try {
    // Get resident details with apartment and building info
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
    $stmt = $conn->prepare("
        SELECT m.full_name as syndic_name, m.email as syndic_email, m.phone as syndic_phone
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        JOIN residence r ON r.id_residence = ap.id_residence
        WHERE r.id_residence = ? AND m.role = 2
        LIMIT 1
    ");
    $stmt->execute([$resident_info['id_residence'] ?? 0]);
    $syndic_info = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get total residents in building
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_residents
        FROM member m
        JOIN apartment ap ON ap.id_member = m.id_member
        WHERE ap.id_residence = ? AND m.role = 1
    ");
    $stmt->execute([$resident_info['id_residence'] ?? 0]);
    $total_residents = $stmt->fetch()['total_residents'] ?? 0;

    // Mock data for various metrics
    $my_charges = 750; // Monthly charges
    $payment_status = 'paid'; // paid, pending, overdue
    $last_payment_date = date('Y-m-d', strtotime('-15 days'));
    $next_payment_due = date('Y-m-d', strtotime('+15 days'));
    $pending_requests = 1; // Maintenance requests
    $unread_announcements = 2;

    // Get recent announcements (mock data)
    $recent_announcements = [
        [
            'id' => 1,
            'title' => 'Nettoyage des escaliers',
            'content' => 'Les escaliers seront nettoyés demain de 9h à 12h.',
            'date_posted' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'is_read' => false
        ],
        [
            'id' => 2,
            'title' => 'Assemblée générale',
            'content' => 'L\'assemblée générale aura lieu le 15 de ce mois.',
            'date_posted' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'is_read' => true
        ]
    ];

    // Get payment history (mock data)
    $payment_history = [
        [
            'month' => date('M Y', strtotime('-1 month')),
            'amount' => 750,
            'status' => 'paid',
            'payment_date' => date('Y-m-d', strtotime('-45 days'))
        ],
        [
            'month' => date('M Y', strtotime('-2 months')),
            'amount' => 750,
            'status' => 'paid',
            'payment_date' => date('Y-m-d', strtotime('-75 days'))
        ]
    ];

} catch(PDOException $e) {
    error_log($e->getMessage());
    $resident_info = null;
    $syndic_info = null;
    $total_residents = 0;
    $my_charges = 0;
    $payment_status = 'unknown';
    $pending_requests = 0;
    $unread_announcements = 0;
    $recent_announcements = [];
    $payment_history = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Dashboard - Syndic Way</title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/sections/dashboard.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .apartment-info-card {
            background: linear-gradient(135deg, var(--color-green), #20c997);
            color: var(--color-white);
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .apartment-info-card h2 {
            margin: 0 0 1rem 0;
            font-size: 1.8rem;
        }

        .apartment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .apartment-detail {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .apartment-detail i {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .payment-status-card {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid;
        }

        .payment-status-card.paid {
            background: rgba(40, 167, 69, 0.1);
            border-color: var(--color-green);
        }

        .payment-status-card.pending {
            background: rgba(244, 185, 66, 0.1);
            border-color: var(--color-yellow);
        }

        .payment-status-card.overdue {
            background: rgba(220, 53, 69, 0.1);
            border-color: #dc3545;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: var(--color-white);
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--color-green), #20c997);
        }

        .action-card:hover {
            transform: translateY(-5px);
            border-color: var(--color-green);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .syndic-contact-card {
            background: var(--color-white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .contact-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .contact-detail {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--color-light-grey);
            border-radius: 10px;
        }

        .two-column-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .announcements-section,
        .payment-history-section {
            background: var(--color-white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .section-header {
            background: linear-gradient(135deg, var(--color-green), #20c997);
            color: var(--color-white);
            padding: 1.5rem;
        }

        .section-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .announcement-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--color-light-grey);
            position: relative;
        }

        .announcement-item:last-child {
            border-bottom: none;
        }

        .announcement-item.unread {
            background: rgba(40, 167, 69, 0.05);
            border-left: 4px solid var(--color-green);
        }

        .payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--color-light-grey);
        }

        .payment-item:last-child {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .apartment-details {
                grid-template-columns: 1fr;
            }
            
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
            
            .two-column-layout {
                grid-template-columns: 1fr;
            }
            
            .contact-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-brand">
            <h2><i class="fas fa-home"></i> Espace Résident</h2>
        </div>
        <div class="nav-user">
            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Résident'); ?></span>
            <a href="http://localhost/syndicplatform/public/logout.php" class="btn btn-logout">
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
                    <li class="active">
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Tableau de bord
                        </a>
                    </li>
                    <li>
                        <a href="payments.php">
                            <i class="fas fa-credit-card"></i> Mes paiements
                        </a>
                    </li>
                    <li>
                        <a href="maintenance.php">
                            <i class="fas fa-tools"></i> Demandes maintenance
                            <?php if($pending_requests > 0): ?>
                                <span class="notifications-badge"><?php echo $pending_requests; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="announcements.php">
                            <i class="fas fa-bullhorn"></i> Annonces
                            <?php if($unread_announcements > 0): ?>
                                <span class="notifications-badge"><?php echo $unread_announcements; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li>
                        <a href="neighbors.php">
                            <i class="fas fa-users"></i> Voisinage
                        </a>
                    </li>
                    <li>
                        <a href="documents.php">
                            <i class="fas fa-file-alt"></i> Documents
                        </a>
                    </li>
                    <li>
                        <a href="contact.php">
                            <i class="fas fa-envelope"></i> Contact syndic
                        </a>
                    </li>
                    <li>
                        <a href="profile.php">
                            <i class="fas fa-user-cog"></i> Mon profil
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Apartment Info Card -->
            <?php if ($resident_info): ?>
                <div class="apartment-info-card">
                    <h2>Appartement <?php echo htmlspecialchars($resident_info['number']); ?></h2>
                    <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($resident_info['building_name'] ?? 'Mon Immeuble'); ?></p>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($resident_info['address'] ?? ''); ?>, <?php echo htmlspecialchars($resident_info['city_name'] ?? ''); ?></p>
                    
                    <div class="apartment-details">
                        <div class="apartment-detail">
                            <i class="fas fa-home"></i>
                            <h4>Appartement <?php echo $resident_info['number']; ?></h4>
                            <p>Étage <?php echo $resident_info['floor']; ?></p>
                        </div>
                        <div class="apartment-detail">
                            <i class="fas fa-door-open"></i>
                            <h4><?php echo htmlspecialchars($resident_info['type']); ?></h4>
                            <p>Type d'appartement</p>
                        </div>
                        <div class="apartment-detail">
                            <i class="fas fa-users"></i>
                            <h4><?php echo $total_residents; ?> résidents</h4>
                            <p>Dans l'immeuble</p>
                        </div>
                        <div class="apartment-detail">
                            <i class="fas fa-calendar"></i>
                            <h4><?php echo date('M Y', strtotime($resident_info['date_created'])); ?></h4>
                            <p>Résident depuis</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Payment Status -->
            <div class="payment-status-card <?php echo $payment_status; ?>">
                <h4>
                    <?php if ($payment_status === 'paid'): ?>
                        <i class="fas fa-check-circle"></i> Paiements à jour
                    <?php elseif ($payment_status === 'pending'): ?>
                        <i class="fas fa-clock"></i> Paiement en attente
                    <?php else: ?>
                        <i class="fas fa-exclamation-triangle"></i> Paiement en retard
                    <?php endif; ?>
                </h4>
                <p>
                    <?php if ($payment_status === 'paid'): ?>
                        Votre dernier paiement de <?php echo number_format($my_charges); ?> DH a été effectué le <?php echo date('d/m/Y', strtotime($last_payment_date)); ?>.
                        Prochain paiement prévu le <?php echo date('d/m/Y', strtotime($next_payment_due)); ?>.
                    <?php elseif ($payment_status === 'pending'): ?>
                        Votre paiement de <?php echo number_format($my_charges); ?> DH est attendu avant le <?php echo date('d/m/Y', strtotime($next_payment_due)); ?>.
                    <?php else: ?>
                        Votre paiement de <?php echo number_format($my_charges); ?> DH était dû le <?php echo date('d/m/Y', strtotime($next_payment_due)); ?>. Veuillez régulariser votre situation.
                    <?php endif; ?>
                </p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Charges mensuelles</h3>
                        <div class="stat-number"><?php echo number_format($my_charges); ?> DH</div>
                    </div>
                </div>

                <div class="stat-card <?php echo $payment_status !== 'paid' ? 'pending' : ''; ?>">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Statut paiement</h3>
                        <div class="stat-number">
                            <?php 
                                $status_text = [
                                    'paid' => 'À jour',
                                    'pending' => 'En attente',
                                    'overdue' => 'En retard'
                                ];
                                echo $status_text[$payment_status] ?? 'Inconnu';
                            ?>
                        </div>
                    </div>
                </div>

                <div class="stat-card <?php echo $pending_requests > 0 ? 'pending' : ''; ?>">
                    <div class="stat-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Demandes en cours</h3>
                        <div class="stat-number"><?php echo $pending_requests; ?></div>
                    </div>
                </div>

                <div class="stat-card <?php echo $unread_announcements > 0 ? 'pending' : ''; ?>">
                    <div class="stat-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Nouvelles annonces</h3>
                        <div class="stat-number"><?php echo $unread_announcements; ?></div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="content-section">
                <h2>Actions rapides</h2>
                <div class="quick-actions-grid">
                    <a href="payments.php?action=pay" class="action-card">
                        <i class="fas fa-credit-card"></i>
                        <h3>Payer mes charges</h3>
                        <p>Effectuer le paiement de mes charges mensuelles</p>
                    </a>

                    <a href="maintenance.php?action=new" class="action-card">
                        <i class="fas fa-wrench"></i>
                        <h3>Demande de maintenance</h3>
                        <p>Signaler un problème ou demander une intervention</p>
                    </a>

                    <a href="contact.php" class="action-card">
                        <i class="fas fa-envelope"></i>
                        <h3>Contacter le syndic</h3>
                        <p>Envoyer un message au syndic de l'immeuble</p>
                    </a>

                    <a href="documents.php" class="action-card">
                        <i class="fas fa-download"></i>
                        <h3>Mes documents</h3>
                        <p>Télécharger quittances et documents officiels</p>
                    </a>

                    <a href="neighbors.php" class="action-card">
                        <i class="fas fa-users"></i>
                        <h3>Annuaire des voisins</h3>
                        <p>Consulter les contacts des autres résidents</p>
                    </a>

                    <a href="profile.php" class="action-card">
                        <i class="fas fa-user-edit"></i>
                        <h3>Modifier mon profil</h3>
                        <p>Mettre à jour mes informations personnelles</p>
                    </a>
                </div>
            </div>

            <!-- Syndic Contact Card -->
            <?php if ($syndic_info): ?>
                <div class="syndic-contact-card">
                    <h3><i class="fas fa-user-tie"></i> Contact de votre syndic</h3>
                    <div class="contact-info">
                        <div class="contact-detail">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($syndic_info['syndic_name']); ?></span>
                        </div>
                        <div class="contact-detail">
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:<?php echo htmlspecialchars($syndic_info['syndic_email']); ?>">
                                <?php echo htmlspecialchars($syndic_info['syndic_email']); ?>
                            </a>
                        </div>
                        <?php if ($syndic_info['syndic_phone']): ?>
                            <div class="contact-detail">
                                <i class="fas fa-phone"></i>
                                <a href="tel:<?php echo htmlspecialchars($syndic_info['syndic_phone']); ?>">
                                    <?php echo htmlspecialchars($syndic_info['syndic_phone']); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="contact-detail">
                            <i class="fas fa-comments"></i>
                            <a href="contact.php">Envoyer un message</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Two Column Layout: Announcements and Payment History -->
            <div class="two-column-layout">
                <!-- Recent Announcements -->
                <div class="announcements-section">
                    <div class="section-header">
                        <h3>
                            <i class="fas fa-bullhorn"></i>
                            Dernières annonces
                        </h3>
                    </div>

                    <?php if (!empty($recent_announcements)): ?>
                        <?php foreach ($recent_announcements as $announcement): ?>
                            <div class="announcement-item <?php echo !$announcement['is_read'] ? 'unread' : ''; ?>">
                                <div class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                <div class="announcement-content"><?php echo htmlspecialchars($announcement['content']); ?></div>
                                <div class="announcement-date">
                                    <i class="fas fa-clock"></i> 
                                    <?php echo date('d/m/Y à H:i', strtotime($announcement['date_posted'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div style="padding: 1rem; text-align: center;">
                            <a href="announcements.php" class="btn btn-secondary">
                                <i class="fas fa-eye"></i> Voir toutes les annonces
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 2rem; text-align: center;">
                            <i class="fas fa-bullhorn" style="font-size: 2rem; color: var(--color-grey); margin-bottom: 1rem;"></i>
                            <h4>Aucune annonce</h4>
                            <p>Il n'y a pas d'annonces récentes.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Payment History -->
                <div class="payment-history-section">
                    <div class="section-header">
                        <h3>
                            <i class="fas fa-history"></i>
                            Historique des paiements
                        </h3>
                    </div>

                    <?php if (!empty($payment_history)): ?>
                        <?php foreach ($payment_history as $payment): ?>
                            <div class="payment-item">
                                <div class="payment-info">
                                    <div class="payment-month"><?php echo $payment['month']; ?></div>
                                    <div class="payment-date">Payé le <?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></div>
                                </div>
                                <div class="payment-amount"><?php echo number_format($payment['amount']); ?> DH</div>
                                <span class="payment-badge payment-<?php echo $payment['status']; ?>">
                                    <?php echo $payment['status'] === 'paid' ? 'Payé' : 'En attente'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <div style="padding: 1rem; text-align: center;">
                            <a href="payments.php" class="btn btn-secondary">
                                <i class="fas fa-eye"></i> Voir tout l'historique
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 2rem; text-align: center;">
                            <i class="fas fa-credit-card" style="font-size: 2rem; color: var(--color-grey); margin-bottom: 1rem;"></i>
                            <h4>Aucun paiement</h4>
                            <p>Votre historique de paiements apparaîtra ici.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });

            // Animate statistics counters
            document.querySelectorAll('.stat-number').forEach(counter => {
                const target = parseInt(counter.textContent.replace(/[^0-9]/g, ''));
                if (!isNaN(target)) {
                    animateCounter(counter, target);
                }
            });

            // Enhanced card hover effects
            document.querySelectorAll('.action-card').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Mark announcements as read when clicked
            document.querySelectorAll('.announcement-item.unread').forEach(item => {
                item.addEventListener('click', function() {
                    this.classList.remove('unread');
                    // Here you would typically send an AJAX request to mark as read
                });
            });
        });

        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                
                if (element.textContent.includes('DH')) {
                    element.textContent = Math.floor(current).toLocaleString() + ' DH';
                } else if (element.textContent.includes('À jour') || element.textContent.includes('En attente') || element.textContent.includes('En retard')) {
                    // Don't animate status text
                    return;
                } else {
                    element.textContent = Math.floor(current);
                }
            }, 20);
        }

        // Quick payment functionality
        function quickPay() {
            if (confirm('Confirmer le paiement de <?php echo number_format($my_charges); ?> DH pour les charges de ce mois ?')) {
                // Redirect to payment page
                window.location.href = 'payments.php?action=pay&amount=<?php echo $my_charges; ?>';
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // Alt + P for payments
            if (event.altKey && event.key === 'p') {
                event.preventDefault();
                window.location.href = 'payments.php';
            }
            
            // Alt + M for maintenance
            if (event.altKey && event.key === 'm') {
                event.preventDefault();
                window.location.href = 'maintenance.php';
            }
            
            // Alt + A for announcements
            if (event.altKey && event.key === 'a') {
                event.preventDefault();
                window.location.href = 'announcements.php';
            }
            
            // Alt + C for contact
            if (event.altKey && event.key === 'c') {
                event.preventDefault();
                window.location.href = 'contact.php';
            }
        });
    </script>
</body>
</html>