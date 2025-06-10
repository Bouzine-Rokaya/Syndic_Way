<?php
// session_start();

// // Destroy all session data
// session_destroy();

// // Redirect to login page
// header('Location: login.php'); 
// exit();
?>
<?php
session_start();

// Store user info for logging before destroying session
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Unknown';
$user_role = $_SESSION['user_role'] ?? 'unknown';

// Log the logout action (optional - you can log this to database)
if ($user_id) {
    $logout_time = date('Y-m-d H:i:s');
    error_log("User logout: ID={$user_id}, Name={$user_name}, Role={$user_role}, Time={$logout_time}");
    
    // Optional: Log to database
    try {
        require_once __DIR__ . '/../config.php';
        
        // Create a simple logging table if it doesn't exist
        $stmt = $conn->prepare("
            CREATE TABLE IF NOT EXISTS user_activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                user_name VARCHAR(100),
                action VARCHAR(50),
                ip_address VARCHAR(45),
                user_agent TEXT,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $stmt->execute();
        
        // Log the logout
        $stmt = $conn->prepare("
            INSERT INTO user_activity_log (user_id, user_name, action, ip_address, user_agent) 
            VALUES (?, ?, 'logout', ?, ?)
        ");
        $stmt->execute([
            $user_id, 
            $user_name, 
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
    } catch (Exception $e) {
        // Log error but don't stop logout process
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Delete the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any remember me cookies (if you implement this feature)
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
    unset($_COOKIE['remember_token']);
}

// Prevent caching of this page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$page_title = "Déconnexion - Syndic Way";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color), var(--color-yellow));
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated background elements */
        .bg-animation {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        .floating-shape {
            position: absolute;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .floating-shape:nth-child(1) {
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-shape:nth-child(2) {
            top: 20%;
            right: 10%;
            animation-delay: 2s;
        }

        .floating-shape:nth-child(3) {
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }

        .floating-shape:nth-child(4) {
            bottom: 10%;
            right: 20%;
            animation-delay: 1s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }

        .logout-container {
            background: var(--color-white);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            padding: 3rem 2rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
            position: relative;
            z-index: 2;
            animation: slideInUp 0.6s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .logout-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--color-yellow), var(--primary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            animation: pulse 2s ease-in-out infinite;
            position: relative;
            overflow: hidden;
        }

        .logout-icon::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shine 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(244, 185, 66, 0.7);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 0 20px rgba(244, 185, 66, 0);
            }
        }

        @keyframes shine {
            0% { transform: translateX(-200%) translateY(-200%) rotate(45deg); }
            50% { transform: translateX(200%) translateY(200%) rotate(45deg); }
            100% { transform: translateX(-200%) translateY(-200%) rotate(45deg); }
        }

        .logout-icon i {
            font-size: 3rem;
            color: var(--color-white);
            position: relative;
            z-index: 1;
        }

        .logout-content h1 {
            color: var(--color-dark-grey);
            margin-bottom: 1rem;
            font-size: 2rem;
            font-weight: 700;
        }

        .logout-content p {
            color: var(--color-grey);
            margin-bottom: 2rem;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .logout-user-info {
            background: var(--color-light-grey);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .logout-user-info i {
            color: var(--color-yellow);
            font-size: 1.5rem;
        }

        .user-details {
            text-align: left;
        }

        .user-details .name {
            font-weight: 700;
            color: var(--color-dark-grey);
            margin-bottom: 0.25rem;
        }

        .user-details .role {
            color: var(--color-grey);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .logout-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transition: all 0.3s ease;
            transform: translate(-50%, -50%);
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--color-yellow), var(--primary-color));
            color: var(--color-white);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--color-grey), var(--color-dark-grey));
            color: var(--color-white);
        }

        .logout-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--color-light-grey);
            color: var(--color-grey);
            font-size: 0.9rem;
        }

        .logout-footer a {
            color: var(--color-yellow);
            text-decoration: none;
            font-weight: 600;
        }

        .logout-footer a:hover {
            text-decoration: underline;
        }

        .progress-bar {
            width: 100%;
            height: 4px;
            background: var(--color-light-grey);
            border-radius: 2px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, var(--color-yellow), var(--primary-color));
            width: 0%;
            animation: fillProgress 3s ease-out forwards;
        }

        @keyframes fillProgress {
            to { width: 100%; }
        }

        .logout-timer {
            color: var(--color-grey);
            font-size: 0.9rem;
            margin-top: 1rem;
        }

        .security-notice {
            background: rgba(244, 185, 66, 0.1);
            border: 1px solid var(--color-yellow);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .security-notice i {
            color: var(--color-yellow);
            font-size: 1.5rem;
        }

        .security-notice-text {
            text-align: left;
            color: var(--color-dark-grey);
            font-size: 0.9rem;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .logout-container {
                margin: 1rem;
                padding: 2rem 1.5rem;
            }

            .logout-content h1 {
                font-size: 1.5rem;
            }

            .logout-user-info {
                flex-direction: column;
                text-align: center;
            }

            .user-details {
                text-align: center;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            body {
                background: linear-gradient(135deg, #1a202c, #2d3748);
            }
            
            .logout-container {
                background: #2d3748;
                color: #e2e8f0;
            }
            
            .logout-content h1 {
                color: #e2e8f0;
            }
            
            .logout-user-info {
                background: #4a5568;
            }
            
            .user-details .name {
                color: #e2e8f0;
            }
        }

        /* Additional animations */
        .fade-in {
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .bounce-in {
            animation: bounceIn 0.6s ease-out;
        }

        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
</head>

<body>
    <!-- Animated background -->
    <div class="bg-animation">
        <div class="floating-shape">
            <i class="fas fa-building" style="font-size: 4rem; color: var(--color-white);"></i>
        </div>
        <div class="floating-shape">
            <i class="fas fa-users" style="font-size: 3rem; color: var(--color-white);"></i>
        </div>
        <div class="floating-shape">
            <i class="fas fa-chart-bar" style="font-size: 3.5rem; color: var(--color-white);"></i>
        </div>
        <div class="floating-shape">
            <i class="fas fa-cog" style="font-size: 2.5rem; color: var(--color-white);"></i>
        </div>
    </div>

    <!-- Main logout container -->
    <div class="logout-container">
        <div class="logout-icon bounce-in">
            <i class="fas fa-sign-out-alt"></i>
        </div>

        <div class="logout-content fade-in">
            <h1>Déconnexion Réussie</h1>
            <p>Vous avez été déconnecté avec succès de votre session administrateur.</p>

            <?php if ($user_name !== 'Unknown'): ?>
            <div class="logout-user-info">
                <i class="fas fa-user-circle"></i>
                <div class="user-details">
                    <div class="name"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="role"><?php echo ucfirst($user_role); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="security-notice">
                <i class="fas fa-shield-check"></i>
                <div class="security-notice-text">
                    <strong>Sécurité:</strong> Votre session a été fermée de manière sécurisée. 
                    Toutes les données temporaires ont été effacées.
                </div>
            </div>

            <div class="logout-actions">
                <a href="../public/login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Se Reconnecter
                </a>
                
                <a href="../public/index.php" class="btn btn-secondary">
                    <i class="fas fa-home"></i>
                    Retour à l'Accueil
                </a>
            </div>

            <div class="logout-timer">
                Redirection automatique dans <span id="countdown">10</span> secondes
            </div>
            
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>

            <div class="logout-footer">
                Session fermée le <?php echo date('d/m/Y à H:i:s'); ?><br>
                <a href="mailto:support@syndicway.com">Besoin d'aide ?</a>
            </div>
        </div>
    </div>

    <script>
        // Countdown timer for automatic redirect
        let countdown = 10;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = '../public/login.php';
            }
        }, 1000);

        // Allow user to cancel automatic redirect
        document.addEventListener('click', () => {
            clearInterval(timer);
            countdownElement.textContent = 'Annulé';
        });

        document.addEventListener('keydown', () => {
            clearInterval(timer);
            countdownElement.textContent = 'Annulé';
        });

        // Enhanced animations
        document.addEventListener('DOMContentLoaded', function() {
            // Stagger animations for child elements
            const elements = document.querySelectorAll('.logout-content > *');
            elements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
                element.classList.add('fade-in');
            });

            // Add hover effects to buttons
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.05)';
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Add click ripple effect
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        width: ${size}px;
                        height: ${size}px;
                        background: rgba(255, 255, 255, 0.3);
                        border-radius: 50%;
                        left: ${x}px;
                        top: ${y}px;
                        animation: ripple 0.6s ease-out;
                        pointer-events: none;
                    `;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => ripple.remove(), 600);
                });
            });
        });

        // Security features
        function clearBrowserData() {
            // Clear localStorage
            if (typeof(Storage) !== "undefined") {
                localStorage.clear();
                sessionStorage.clear();
            }
            
            // Clear any cached data
            if ('caches' in window) {
                caches.keys().then(names => {
                    names.forEach(name => {
                        caches.delete(name);
                    });
                });
            }
        }

        // Execute security cleanup
        clearBrowserData();

        // Prevent back button to access admin areas
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };

        // Disable context menu and certain keyboard shortcuts
        document.addEventListener('contextmenu', e => e.preventDefault());
        
        document.addEventListener('keydown', function(e) {
            // Disable F12, Ctrl+Shift+I, Ctrl+U
            if (e.key === 'F12' || 
                (e.ctrlKey && e.shiftKey && e.key === 'I') ||
                (e.ctrlKey && e.key === 'u')) {
                e.preventDefault();
            }
        });

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                0% {
                    transform: scale(0);
                    opacity: 1;
                }
                100% {
                    transform: scale(2);
                    opacity: 0;
                }
            }
            
            .btn {
                position: relative;
                overflow: hidden;
            }
        `;
        document.head.appendChild(style);

        // Enhanced security logging (client-side)
        function logSecurityEvent(event) {
            const securityLog = {
                timestamp: new Date().toISOString(),
                event: event,
                userAgent: navigator.userAgent,
                url: window.location.href,
                sessionEnd: true
            };
            
            // Send to server if needed
            console.log('Security Event:', securityLog);
        }

        // Log logout completion
        logSecurityEvent('admin_logout_completed');

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
            if (loadTime > 0) {
                console.log('Logout page load time:', loadTime + 'ms');
            }
        });

        // Accessibility improvements
        document.addEventListener('keydown', function(e) {
            // Enter key on buttons
            if (e.key === 'Enter' && e.target.classList.contains('btn')) {
                e.target.click();
            }
            
            // Escape key to go to login
            if (e.key === 'Escape') {
                window.location.href = '../public/login.php';
            }
        });

        // Focus management for accessibility
        document.querySelector('.btn-primary').focus();

        // Notification API (if supported)
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Syndic Way', {
                body: 'Vous avez été déconnecté avec succès',
                icon: '/favicon.ico',
                tag: 'logout'
            });
        }

        // Enhanced mobile experience
        if ('ontouchstart' in window) {
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.95)';
                });
                
                btn.addEventListener('touchend', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        }

        // Dark mode detection and application
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.body.classList.add('dark-mode');
        }

        // Listen for changes in color scheme preference
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (e.matches) {
                document.body.classList.add('dark-mode');
            } else {
                document.body.classList.remove('dark-mode');
            }
        });
    </script>
</body>
</html>
