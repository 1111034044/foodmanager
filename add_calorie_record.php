<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['uId'])) exit('no user');
$uId = $_SESSION['uId'];
$food = trim($_POST['food_name'] ?? '');
$cal = floatval($_POST['calorie'] ?? 0);
$source = $_POST['source'] ?? 'manual';
if (!$food || !$cal) exit('no data');
$today = date('Y-m-d');
$stmt = $db->prepare("INSERT INTO calorie_records (user_id, record_date, food_name, calorie, source, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
$stmt->execute([$uId, $today, $food, $cal, $source]);
echo 'ok'; 