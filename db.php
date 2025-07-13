<?php
// db.php
$host = 'localhost';
$dbname = 'foodmanager';
$user = 'root';
$pass = '';
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
];
try {
    $db = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass, $options);
} catch (PDOException $e) {
    die('資料庫連線失敗: ' . $e->getMessage());
} 