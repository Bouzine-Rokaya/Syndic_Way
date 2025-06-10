<?php
 require_once __DIR__ . '/../config.php';
 try{
     $query = "SELECT * FROM subscription WHERE is_active = 1 ORDER BY price_subscription ASC";
     $stmt = $conn->prepare($query);
     $stmt->execute();
     $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
 }catch(PDOException $e) {
     error_log($e->getMessage());
     die("Connection failed.");
 }


  //Inclure la vue (le HTML)
 require_once __DIR__ . '/../views/subscriptions.php';
?>
