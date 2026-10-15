<?php
/**
 * db_connect.php
 * Koneksyon lang sa database ang trabaho nito. Walang ibang ginagawa.
 * (Ang paggawa ng tables ay nasa setup.sql, hindi na dito.)
 *
 * Pagkatapos i-require ito, may variable ka nang $pdo na pwedeng gamitin.
 */

$host   = 'localhost';
$dbName = 'login_system';
$dbUser = 'root';   // default ng XAMPP
$dbPass = '';       // default ng XAMPP (walang password)

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ipakita ang SQL errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // array na may pangalan ng column
        ]
    );
} catch (PDOException $e) {
    // The real reason is in the Apache error log (xampp/apache/logs/error.log)
    error_log('DB connection failed: ' . $e->getMessage());
    die('Unable to connect to the database. Is MySQL running in XAMPP, and have you created the required setup?');
}