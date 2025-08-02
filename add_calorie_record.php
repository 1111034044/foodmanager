<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['uId'])) exit('no user');
$uId = $_SESSION['uId'];
$food = trim($_POST['food_name'] ?? '');
$cal = floatval($_POST['calorie'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 1);
$source = $_POST['source'] ?? 'manual';

// 新增營養素欄位
$protein = floatval($_POST['protein'] ?? 0);
$fat = floatval($_POST['fat'] ?? 0);
$carb = floatval($_POST['carb'] ?? 0);
$fiber = floatval($_POST['fiber'] ?? 0);
$vitamin = $_POST['vitamin'] ?? '';
$mineral = $_POST['mineral'] ?? '';

if (!$food || !$cal) exit('no data');
$today = date('Y-m-d');

$stmt = $db->prepare("INSERT INTO calorie_records (user_id, record_date, food_name, quantity, calorie, protein, fat, carb, fiber, vitamin, mineral, source, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$stmt->execute([$uId, $today, $food, $quantity, $cal, $protein, $fat, $carb, $fiber, $vitamin, $mineral, $source]);
echo 'ok';
?> 