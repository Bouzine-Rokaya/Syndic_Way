<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'syndic2025';

try {
    $conn = new PDO("mysql:host=$host", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $conn->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->exec("USE $dbname");

    $sql = <<<SQL

-- Tables
CREATE TABLE IF NOT EXISTS subscription (
    id_subscription INT AUTO_INCREMENT PRIMARY KEY,
    name_subscription VARCHAR(100),
    price_subscription FLOAT,
    description TEXT,
    duration_months INT NOT NULL DEFAULT 12,
    max_residents INT DEFAULT 50,
    max_apartments INT DEFAULT 100,
    is_active TINYINT DEFAULT 1
);

CREATE TABLE IF NOT EXISTS admin (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100),
    password VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS member (
    id_member INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100),
    email VARCHAR(100),
    password VARCHAR(255),
    phone VARCHAR(20),
    role INT,
    status VARCHAR(50),
    date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_member_subscription (
    id_admin INT NOT NULL,
    id_member INT NOT NULL,
    id_subscription INT NOT NULL,
    date_payment DATETIME,
    amount FLOAT,
    PRIMARY KEY (id_admin, id_member, id_subscription)
);

CREATE TABLE IF NOT EXISTS admin_member_link (
    id_admin INT NOT NULL,
    id_member INT NOT NULL,
    date_created DATETIME,
    PRIMARY KEY (id_admin, id_member)
);

CREATE TABLE IF NOT EXISTS member_messages (
    id_sender INT NOT NULL,
    id_receiver INT NOT NULL,
    date_message DATETIME,
    PRIMARY KEY (id_sender, id_receiver)
);

CREATE TABLE IF NOT EXISTS member_payments (
    id_payer INT NOT NULL,
    id_receiver INT NOT NULL,
    date_payment DATETIME,
    month_paid DATE,
    PRIMARY KEY (id_payer, id_receiver)
);

CREATE TABLE IF NOT EXISTS member_announcements (
    id_poster INT NOT NULL,
    id_receiver INT NOT NULL,
    date_announcement DATETIME,
    PRIMARY KEY (id_poster, id_receiver)
);

CREATE TABLE IF NOT EXISTS member_notifications (
    id_sender INT NOT NULL,
    id_receiver INT NOT NULL,
    date_notification DATETIME,
    PRIMARY KEY (id_sender, id_receiver)
);

CREATE TABLE IF NOT EXISTS city (
    id_city INT AUTO_INCREMENT PRIMARY KEY,
    city_name VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS residence (
    id_residence INT AUTO_INCREMENT PRIMARY KEY,
    id_city INT NOT NULL,
    name VARCHAR(100),
    address VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS apartment (
    id_apartment INT AUTO_INCREMENT PRIMARY KEY,
    id_residence INT NOT NULL,
    id_member INT NOT NULL,
    type VARCHAR(50),
    floor VARCHAR(10),
    number INT
);

-- Constraints
ALTER TABLE admin_member_subscription
    ADD CONSTRAINT fk_admin_subscription FOREIGN KEY (id_subscription)
    REFERENCES subscription (id_subscription)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE admin_member_subscription
    ADD CONSTRAINT fk_admin_member_admin FOREIGN KEY (id_admin)
    REFERENCES admin (id_admin)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE admin_member_subscription
    ADD CONSTRAINT fk_admin_member_member FOREIGN KEY (id_member)
    REFERENCES member (id_member)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE admin_member_link
    ADD CONSTRAINT fk_link_admin FOREIGN KEY (id_admin)
    REFERENCES admin (id_admin)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE admin_member_link
    ADD CONSTRAINT fk_link_member FOREIGN KEY (id_member)
    REFERENCES member (id_member)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE member_messages
    ADD CONSTRAINT fk_msg_sender FOREIGN KEY (id_sender)
    REFERENCES member (id_member)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE member_messages
    ADD CONSTRAINT fk_msg_receiver FOREIGN KEY (id_receiver)
    REFERENCES member (id_member)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE member_payments
    ADD CONSTRAINT fk_pay_sender FOREIGN KEY (id_payer)
    REFERENCES member (id_member)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE member_payments
    ADD CONSTRAINT fk_pay_receiver FOREIGN KEY (id_receiver)
    REFERENCES member (id_member)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE member_announcements
    ADD CONSTRAINT fk_announce_poster FOREIGN KEY (id_poster)
    REFERENCES member (id_member)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE member_announcements
    ADD CONSTRAINT fk_announce_receiver FOREIGN KEY (id_receiver)
    REFERENCES member (id_member)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE member_notifications
    ADD CONSTRAINT fk_note_sender FOREIGN KEY (id_sender)
    REFERENCES member (id_member)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE member_notifications
    ADD CONSTRAINT fk_note_receiver FOREIGN KEY (id_receiver)
    REFERENCES member (id_member)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE apartment
    ADD CONSTRAINT fk_apartment_member FOREIGN KEY (id_member)
    REFERENCES member (id_member)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE apartment
    ADD CONSTRAINT fk_apartment_residence FOREIGN KEY (id_residence)
    REFERENCES residence (id_residence)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE residence
    ADD CONSTRAINT fk_residence_city FOREIGN KEY (id_city)
    REFERENCES city (id_city)
    ON DELETE RESTRICT ON UPDATE RESTRICT;

-- Insert sample subscriptions
INSERT INTO subscription (name_subscription, price_subscription, description, duration_months, max_residents, max_apartments)
VALUES 
('Forfait Basique', 50, 'Parfait pour les petits immeubles', 12, 20, 20),
('Forfait Professionnel', 100, 'Idéal pour les immeubles de taille moyenne', 12, 50, 100),
('Forfait Entreprise', 200, 'Pour les grands immeubles avec besoins avancés', 12, 100, 200);

SQL;

$conn->exec($sql);


//creat admin
$name = "admin";
$email = "admin@syndic.ma";
$plain_password = "admin123"; 
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO admin (name, email, password) VALUES (?, ?, ?)");
$stmt->execute([$name, $email, $hashed_password]);


    echo "✅ Base de données et tables créées avec succès.";
} catch (PDOException $e) {
    die("❌ Erreur: " . $e->getMessage());
}
?>
