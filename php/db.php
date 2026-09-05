<?php
// db.php — shared database connection
$host    = '127.0.0.1';
$db      = 'app_db';
$user    = 'root';
$pass    = '';           // set a real password outside of local dev
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // In production, log $e->getMessage() to a file instead of showing it.
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// Central place for the AI microservice's base URL, so every page
// that talks to it (recommend.php, chatbot.php, ai_proxy.php) agrees.
define('AI_SERVICE_URL', 'http://127.0.0.1:5000');
