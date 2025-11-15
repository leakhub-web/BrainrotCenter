<?php
session_start();

$host = 'localhost';
$db = 'brainrotcenter';
$user = 'root'; // Change !
$pass = '';     // Change !

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch(PDOException $e) {
    die("Erreur DB: " . $e->getMessage());
}
?>
