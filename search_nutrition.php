<?php
require_once 'db.php';
header('Content-Type: application/json');
$food = isset($_GET['food']) ? trim($_GET['food']) : '';
if (!$food) {
    echo json_encode(['found'=>false]);
    exit;
}
// 先查 nutrition_facts
$stmt = $db->prepare("SELECT kcal FROM nutrition_facts WHERE sample_name LIKE ? OR alias LIKE ? LIMIT 1");
$like = "%$food%";
$stmt->execute([$like, $like]);
$row = $stmt->fetch();
if ($row && $row['kcal'] > 0) {
    echo json_encode(['found'=>true, 'kcal'=>round($row['kcal'],1)]);
    exit;
}
// 查不到，呼叫 openai_api.php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'api/openai_api.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['food_name'=>$food]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);
$data = json_decode($result, true);
if ($data && isset($data['kcal']) && $data['kcal'] > 0) {
    echo json_encode(['openai'=>true, 'kcal'=>round($data['kcal'],1)]);
    exit;
}
echo json_encode(['found'=>false]); 