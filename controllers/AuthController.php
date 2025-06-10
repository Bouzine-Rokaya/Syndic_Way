<?php
$plan_id = $_GET['plan'] ?? null;

if (!$plan_id) {
    header('Location: ?page=subscriptions');
    exit();
}

$stmt = $conn->prepare("SELECT * FROM subscription WHERE id_subscription = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan) {
    $_SESSION['error'] = 'Forfait invalide.';
    header('Location: ?page=subscriptions');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les champs
    $name = trim($_POST['syndic_name'] ?? '');
    $email = trim($_POST['syndic_email'] ?? '');
    $phone = trim($_POST['syndic_phone'] ?? '');
    $company = trim($_POST['company_name'] ?? '');
    $city = trim($_POST['company_city'] ?? '');
    $address = trim($_POST['company_address'] ?? '');

    $errors = [];

    if (empty($name)) $errors[] = "Nom complet requis.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide.";
    if (empty($phone)) $errors[] = "Téléphone requis.";
    if (empty($company)) $errors[] = "Nom de la société requis.";
    if (empty($city)) $errors[] = "Ville requise.";

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        header("Location: ?page=purchase&plan=$plan_id");
        exit();
    }

    // Vérifier si l'email existe déjà
    $stmt = $conn->prepare("SELECT COUNT(*) FROM member WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['error'] = "Un compte avec cet email existe déjà.";
        header("Location: ?page=purchase&plan=$plan_id");
        exit();
    }

    try {
        $conn->beginTransaction();

        // 1. Insérer ou récupérer la ville
        $stmt = $conn->prepare("SELECT id_city FROM city WHERE city_name = ?");
        $stmt->execute([$city]);
        $city_id = $stmt->fetchColumn();
        
        if (!$city_id) {
            $stmt = $conn->prepare("INSERT INTO city (city_name) VALUES (?)");
            $stmt->execute([$city]);
            $city_id = $conn->lastInsertId();
        }

        // 2. Insérer la résidence
        $stmt = $conn->prepare("INSERT INTO residence (id_city, name, address) VALUES (?, ?, ?)");
        $stmt->execute([$city_id, $company, $address]);
        $residence_id = $conn->lastInsertId();

        // 3. Insérer le membre
        $default_password = password_hash('default_pass123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO member (full_name, email, password, phone, role, status)
                                VALUES (?, ?, ?, ?, 2, 'pending')");
        $stmt->execute([$name, $email, $default_password, $phone]);
        $member_id = $conn->lastInsertId();

        // 4. Créer un appartement par défaut pour ce membre
        $stmt = $conn->prepare("INSERT INTO apartment (id_residence, id_member, type, floor, number) 
                               VALUES (?, ?, 'Standard', '1', 1)");
        $stmt->execute([$residence_id, $member_id]);

        // 5. Lier à admin (id=1 par défaut)
        $stmt = $conn->prepare("INSERT INTO admin_member_link (id_admin, id_member, date_created)
                                VALUES (1, ?, NOW())");
        $stmt->execute([$member_id]);

        // 6. Enregistrer l'abonnement
        $stmt = $conn->prepare("INSERT INTO admin_member_subscription 
                               (id_admin, id_member, id_subscription, date_payment, amount)
                               VALUES (1, ?, ?, NOW(), ?)");
        $stmt->execute([$member_id, $plan_id, $plan['price_subscription']]);

        $conn->commit();

        $_SESSION['success'] = "Achat réussi ! Vous recevrez vos identifiants sous 24h.";
        header("Location: ?page=purchase-success&id=" . $member_id);
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Erreur lors de l'achat : " . $e->getMessage();
        header("Location: ?page=purchase&plan=$plan_id");
        exit();
    }
}