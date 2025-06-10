<?php
session_start();

// connect on Database
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $login_successful = false;

    // 1. Check Admin
    $stmt = $conn->prepare("SELECT * FROM admin WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['user_id'] = $admin['id_admin'];
        $_SESSION['user_role'] = 'admin';
        $_SESSION['user_name'] = $admin['name'];
        header("Location: http://localhost/syndicplatform/admin/dashboard.php");
        exit;
    }

    // 2. Check Member (Syndic or Resident)
    $stmt = $conn->prepare("SELECT * FROM member WHERE email = ?");
    $stmt->execute([$email]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($member && password_verify($password, $member['password'])) {
        $_SESSION['user_id'] = $member['id_member'];
        $_SESSION['user_name'] = $member['full_name'];

        // Determine role based on member's role field
        if ($member['role'] == 2) { // Assuming role 2 = syndic
            $_SESSION['user_role'] = 'syndic';
            header("Location: http://localhost/syndicplatform/syndic/dashboard.php");
        } else { // role 1 = resident
            $_SESSION['user_role'] = 'resident';
            header("Location: http://localhost/syndicplatform/resident/dashboard.php");
        }
        exit;
    }

    // If we reach here, login failed
    $_SESSION['error'] = "Email ou mot de passe incorrect.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login - Syndic Way</title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/sections/signUp.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Testing helper styles */
        .auto-filled {
            background-color: #d4edda !important;
            border: 2px solid #28a745 !important;
            transition: all 0.3s ease;
        }

        .testing-info {
            background: #e7f3ff;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
            font-size: 14px;
        }

        .testing-info strong {
            color: #007bff;
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <div class="image">
            <img src="http://localhost/syndicplatform/public/images/full-shot-colleagues-working-office 2.png">
        </div>
        <div class="all-content">
            <div class="auth-form">
                <div class="auth-header">
                    <h2><i class="fas fa-building"></i> Syndic Way</h2>
                    <p>Connexion à votre compte</p>
                </div>

                <!-- Testing info (only shows when auto-filled) -->
                <div id="testing-info" class="testing-info" style="display: none;">
                    <strong>🧪 Testing Mode:</strong> Login credentials have been auto-filled from the email viewer.
                </div>

                <?php
                if (isset($_SESSION['error'])) {
                    echo "<div class='alert alert-error'>" . $_SESSION['error'] . "</div>";
                    unset($_SESSION['error']);
                }

                if (isset($_SESSION['success'])) {
                    echo "<div class='alert alert-success'>" . $_SESSION['success'] . "</div>";
                    unset($_SESSION['success']);
                }
                ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Email :</label><br>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Mot de passe :</label><br>
                        <input type="password" id="password" name="password" required>
                        <button type="button" id="toggle-password" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggle-icon"></i>
                        </button>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">
                        <i class="fas fa-sign-in-alt"></i> Se connecter
                    </button>
                </form>

                <div class="auth-footer">
                    <p><a href="http://localhost/syndicplatform/public/">← Retour à l'accueil</a></p>
                    <p>Vous n'avez pas de compte ? <a href="http://localhost/syndicplatform/public/">Abonnez-vous
                            maintenant</a></p>

                    <!-- Testing links (only in development) -->
                    <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                        <p style="font-size: 12px; color: #666;">
                            <strong>For Testing:</strong>
                            <a href="../views/test_accounts.php" style="color: #007bff;">Test Accounts</a> |
                            <a href="../views/email_viewer.php" style="color: #007bff;">Email Viewer</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-fill functionality for testing
        document.addEventListener('DOMContentLoaded', function () {
            const autoEmail = sessionStorage.getItem('auto_fill_email');
            const autoPassword = sessionStorage.getItem('auto_fill_password');

            if (autoEmail && autoPassword) {
                const emailField = document.querySelector('#email');
                const passwordField = document.querySelector('#password');
                const testingInfo = document.querySelector('#testing-info');

                if (emailField && passwordField) {
                    // Fill the fields
                    emailField.value = autoEmail;
                    passwordField.value = autoPassword;

                    // Show testing info
                    if (testingInfo) {
                        testingInfo.style.display = 'block';
                    }

                    // Clear the session storage
                    sessionStorage.removeItem('auto_fill_email');
                    sessionStorage.removeItem('auto_fill_password');

                    // Add visual feedback
                    emailField.classList.add('auto-filled');
                    passwordField.classList.add('auto-filled');

                    // Remove highlighting after 3 seconds
                    setTimeout(() => {
                        emailField.classList.remove('auto-filled');
                        passwordField.classList.remove('auto-filled');
                    }, 3000);

                    // Optional: Auto-focus the submit button
                    setTimeout(() => {
                        document.querySelector('.btn-primary').focus();
                    }, 500);
                }
            }

            // Check for URL parameters (alternative method)
            const urlParams = new URLSearchParams(window.location.search);
            const emailParam = urlParams.get('email');
            const passwordParam = urlParams.get('password');

            if (emailParam && passwordParam) {
                document.querySelector('#email').value = decodeURIComponent(emailParam);
                document.querySelector('#password').value = decodeURIComponent(passwordParam);

                // Clean URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        // Password visibility toggle
        function togglePassword() {
            const passwordField = document.querySelector('#password');
            const toggleIcon = document.querySelector('#toggle-icon');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Quick test accounts (for development only)
        function fillTestAccount() {
            document.querySelector('#email').value = 'test@test.com';
            document.querySelector('#password').value = 'test123';
        }

        // Add keyboard shortcut for testing (Ctrl+Shift+T)
        document.addEventListener('keydown', function (e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'T') {
                fillTestAccount();
                e.preventDefault();
            }
        });
    </script>

    <style>
        /* Additional styles for password toggle */
        .form-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 63px;
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 16px;
        }

        .password-toggle:hover {
            color: #007bff;
        }

        /* Ensure password field has padding for the toggle button */
        #password {
            padding-right: 40px;
        }
    </style>
</body>

</html>