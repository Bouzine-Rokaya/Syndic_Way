<!-- Sidebar -->
 <?php
$current_page = basename($_SERVER['PHP_SELF']); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            border-radius: 2px;
            transition: width 0.5s ease;
        }

        .storage-text {
            font-size: 12px;
            color: #64748b;
        }



        
    </style>
</head>
<body>
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
               
                <a href="syndic-accounts.php" class="nav-item <?= ($current_page == 'syndic-accounts.php') ? 'active' : '' ?>">
                    <i class="fas fa-building"></i>
                    Comptes Syndic
                </a>
                 <a href="subscriptions.php" class="nav-item <?= ($current_page == 'subscriptions.php') ? 'active' : '' ?>">
                    <i class="fas fa-tags"></i>
                    Abonnements
                </a>
                <a href="users.php" class="nav-item <?= ($current_page == 'users.php') ? 'active' : '' ?>">
                    <i class="fas fa-users"></i>
                    Utilisateurs
                </a>
                <a href="purchases.php" class="nav-item <?= ($current_page == 'purchases.php') ? 'active' : '' ?>">
                    <i class="fas fa-shopping-cart"></i>
                    Achats
                </a>
                <a href="reports.php" class="nav-item <?= ($current_page == 'reports.php') ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i>
                    Rapports
                </a>
                <a href="settings.php" class="nav-item <?= ($current_page == 'settings.php') ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i>
                    Paramètres
                </a>
                <a href="../public/login.php?logout=1" class="nav-item">
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
</body>
</html>
        