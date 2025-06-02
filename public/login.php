<?php
session_start();

// siting principal
define('APP_NAME', 'Syndic Way');

// connect on Database
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM admin WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $user['id_admin'];
        $_SESSION['success'] = "bonjour";
        header("Location: dashboard.php");
        exit;
    } else {
        $_SESSION['error'] = "Mot de passe ou email invalide";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/sections/signUp.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="auth-container">
        <div class="image">
            <img src="http://localhost/syndicplatform/public/images/full-shot-colleagues-working-office 2.png">
        </div>
        <div class="all-content">
        <div class="auth-form">
            <div class="auth-header">
                <h2><i class="fas fa-building"></i> <?php echo APP_NAME; ?></h2>
                <p>Login to your account</p>
            </div>

            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email:</label><br>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label><br>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div class="auth-footer">
                <p><a href="http://localhost/syndicplatform/">← Back to Home</a></p>
                <p>Don't have an account? <a href="http://localhost/syndicplatform/?page=subscriptions">Subscribe Now</a></p>
            </div>
        </div>
    </div>
</div>

</body>
</html>
