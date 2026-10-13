<?php
$host = "localhost";
$db_name = "login_system";
$db_user = "root";
$db_pass = "";

try {
    $connection = new PDO(
        "mysql:host=$host;charset=utf8mb4",
        $db_user,
        $db_pass
    );
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connection->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new PDO(
        "mysql:host=$host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        gmail VARCHAR(150) NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('student','staff','admin') NOT NULL DEFAULT 'student',
        full_name VARCHAR(150) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_users_gmail (gmail)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $defaultStaffEmail = 'staff@gmail.com';
    $defaultStaffPassword = 'staff123';
    $staffCheck = $pdo->prepare("SELECT id FROM users WHERE gmail = ?");
    $staffCheck->execute([$defaultStaffEmail]);

    if (!$staffCheck->fetch()) {
        $staffInsert = $pdo->prepare("INSERT INTO users (gmail, password, role, full_name) VALUES (?, ?, 'staff', 'Staff User')");
        $staffInsert->execute([$defaultStaffEmail, md5($defaultStaffPassword)]);
    }
} catch (PDOException $e) {
    die("Hindi kumonekta sa database: " . $e->getMessage());
}
?>