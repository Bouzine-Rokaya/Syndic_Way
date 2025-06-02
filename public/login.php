<?php
session_start();

// connect on Database
require_once __DIR__ . '/../config.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // 1. Check Admin
    $stmt = $conn->prepare("SELECT * FROM admin WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['user_id'] = $admin['id_admin'];
        $_SESSION['user_role'] = 'admin';
        header("Location: http://localhost/syndicplatform/admin/dashboard.php");
        exit;
    }

    // 2. Check Member (Syndic or Resident)
    $stmt = $conn->prepare("SELECT * FROM member WHERE email = ?");
    $stmt->execute([$email]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($member && password_verify($password, $member['password'])) {
        $_SESSION['user_id'] = $member['id_member'];

        if ($member['email'] === $email) {
            $_SESSION['user_role'] = 'syndic';
            header("Location: http://localhost/syndicplatform/syndic/dashboard.php");
        } else {
            $_SESSION['user_role'] = 'resident';
            header("Location: http://localhost/syndicplatform/resident/dashboard.php");
        }
        exit;
    }
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


            <?php
                if (isset($_SESSION['error'])) {
                    echo "<p style='color:red'>" . $_SESSION['error'] . "</p>";
                    unset($_SESSION['error']);
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
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>

            <div class="auth-footer">
                <p><a href="http://localhost/syndicplatform/public/">← Retour à l'accueil</a></p>
                <p>Vous n'avez pas de compte ? <a href="http://localhost/syndicplatform/public/">Abonnez-vous maintenant</a></p>
            </div>
        </div>
    </div>
</div>

</body>
</html>
