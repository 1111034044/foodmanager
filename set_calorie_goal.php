<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['uId'])) exit('no user');
$uId = $_SESSION['uId'];
$goal = intval($_POST['calorie_goal'] ?? 0);
$gender = $_POST['gender'] ?? null;
$age = $_POST['age'] ?? null;
$height = $_POST['height'] ?? null;
$weight = $_POST['weight'] ?? null;
if (!$goal) exit('no goal');
$stmt = $db->prepare("INSERT INTO user_calorie_goal (user_id, calorie_goal, gender, age, height, weight, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
$stmt->execute([$uId, $goal, $gender, $age, $height, $weight]);
echo 'ok'; 