<?php
$dsn = "mysql:host=localhost;dbname=syndic2025;charset=utf8mb4";
$user = "root";
$pass = "";

try {
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES 'utf8mb4'");
} catch(PDOException $e) {
    error_log($e->getMessage());
    die("Connection failed.");
}
?>
