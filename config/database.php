<?php
$host = 'localhost';
$dbname = 'mubende_school_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if admin user exists, if not create it
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'admin@school.com'");
    $adminExists = $stmt->fetchColumn();
    
    if (!$adminExists) {
        // Create admin user with hashed password
        $hashedPassword = password_hash('mickymike', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (email, password, role) VALUES ('admin@school.com', '$hashedPassword', 'admin')");
    }
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>