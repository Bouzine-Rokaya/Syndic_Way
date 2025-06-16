<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Document</title>
    <style>
        /* Sidebar */
        .sidebar {
            width: 240px;
            background: white;
            border-right: 1px solid #e2e8f0;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
        }

        .logo {
            padding: 0 20px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: #FFCB32;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .logo-text {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }

        .nav-section {
            padding: 0 20px;
            margin-bottom: 30px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            color: #64748b;
            text-decoration: none;
            margin-bottom: 4px;
            transition: all 0.2s;
            font-size: 14px;
        }

        .nav-item:hover,
        .nav-item.active {
            background: #f1f5f9;
            color: #FFCB32;
        }

        .nav-item i {
            width: 16px;
            text-align: center;
        }

        .storage-info {
            margin-top: auto;
            padding: 0 20px;
        }

        .storage-bar {
            width: 100%;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
            margin: 10px 0;
        }

        .storage-fill {
            height: 100%;
            background: #FFCB32;
            width:
                <?php echo min(($stats['total_residents'] / 50) * 100, 100); ?>
                %;
            border-radius: 2px;
            transition: width 0.5s ease;
        }

        .storage-text {
            font-size: 12px;
            color: #64748b;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <div class="logo-icon">SW</div>
            <div class="logo-text">Syndic Way</div>
        </div>

        <div class="nav-section">
            <a href="dashboard.php" class="nav-item <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i>
                Tableau de Bord
            </a>
            <a href="residents.php" class="nav-item <?= ($current_page == 'residents.php') ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                Résidents
            </a>
            <a href="apartments.php" class="nav-item <?= ($current_page == 'apartments.php') ? 'active' : '' ?>">
                <i class="fas fa-home"></i>
                Appartements
            </a>
            <a href="payments.php" class="nav-item <?= ($current_page == 'payments.php') ? 'active' : '' ?>">
                <i class="fas fa-credit-card"></i>
                Paiements
            </a>
            <a href="messages.php" class="nav-item <?= ($current_page == 'messages.php') ? 'active' : '' ?>">
                <i class="fas fa-envelope"></i>
                Messages
            </a>
            <a href="announcements.php" class="nav-item <?= ($current_page == 'announcements.php') ? 'active' : '' ?>">
                <i class="fas fa-bullhorn"></i>
                Annonces
            </a>
            <a href="reports.php" class="nav-item <?= ($current_page == 'reports.php') ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                Rapports
            </a>
            <a href="../public/login.php?logout=1" class="nav-item ">
                <i class="fas fa-sign-out-alt"></i>
                Déconnexion
            </a>
        </div>

        <div class="storage-info">
            <div class="storage-bar">
                <div class="storage-fill"></div>
            </div>
        </div>
    </div>


    <script>
        // Navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function (e) {
                if (!this.href.includes('login.php') && !this.href.includes('.php')) {
                    e.preventDefault();
                    document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                }
            });
        });
    </script>
</body>

</html>