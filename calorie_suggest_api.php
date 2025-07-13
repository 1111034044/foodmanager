<?php
header('Content-Type: application/json');
$gender = $_GET['gender'] ?? '';
$age = intval($_GET['age'] ?? 0);
$height = intval($_GET['height'] ?? 0);
$weight = intval($_GET['weight'] ?? 0);
if (!$gender || !$age || !$height || !$weight) {
    echo json_encode(['suggest'=>null]);
    exit;
}
if ($gender === 'male') {
    $bmr = 66 + 13.7 * $weight + 5 * $height - 6.8 * $age;
} else if ($gender === 'female') {
    $bmr = 655 + 9.6 * $weight + 1.8 * $height - 4.7 * $age;
} else {
    $bmr = 500 + 11.5 * $weight + 3.4 * $height - 5.7 * $age;
}
$suggest = round($bmr * 1.4); // 乘以輕度活動量
$suggest = max(1000, min(5000, $suggest));
echo json_encode(['suggest'=>$suggest]); 