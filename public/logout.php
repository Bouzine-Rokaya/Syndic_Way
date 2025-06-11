<?php
session_start();

// Log the logout action (optional)
if (isset($_SESSION['user_name'])) {
    error_log("User logout: " . $_SESSION['user_name'] . " at " . date('Y-m-d H:i:s'));
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Set a success message for the login page
session_start();
$_SESSION['success'] = "Vous avez été déconnecté avec succès.";

// Redirect to login page
header('Location: http://localhost/syndicplatform/public/login.php');
exit();
?>