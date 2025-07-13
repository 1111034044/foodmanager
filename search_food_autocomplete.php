<?php
require_once 'db.php';
$term = $_GET['term'] ?? '';
if (!$term) {
    echo json_encode([]);
    exit;
}
$stmt = $db->prepare("SELECT sample_name, kcal FROM nutrition_facts WHERE sample_name LIKE ? OR alias LIKE ? LIMIT 15");
$like = "%$term%";
$stmt->execute([$like, $like]);
$res = [];
while ($row = $stmt->fetch()) {
    $res[] = [
        'label' => $row['sample_name'],
        'value' => $row['sample_name'],
        'kcal' => $row['kcal']
    ];
}
echo json_encode($res); 