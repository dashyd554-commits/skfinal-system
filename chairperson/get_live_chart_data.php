<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user'])) {
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

$stmt = $conn->prepare("
    SELECT title, participants
    FROM activities
    WHERE barangay_id = :barangay_id
    ORDER BY id ASC
");
$stmt->bindValue(':barangay_id', $barangay_id);
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$data = [];

foreach ($activities as $a) {
    $labels[] = $a['title'];
    $data[] = (int)$a['participants'];
}

echo json_encode([
    "labels" => $labels,
    "data" => $data
]);