<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['uId'])) exit('no user');
$uId = $_SESSION['uId'];
$id = intval($_POST['id'] ?? 0);
if (!$id) exit('no id');
$stmt = $db->prepare("DELETE FROM calorie_records WHERE id=? AND user_id=?");
$stmt->execute([$id, $uId]);
echo 'ok'; 