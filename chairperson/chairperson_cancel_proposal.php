<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'chairman') {
    header("Location: ../index.php");
    exit();
}

$id = $_GET['id'] ?? null;
$barangay_id = $_SESSION['user']['barangay_id'];

if ($id) {

    $stmt = $conn->prepare("
        UPDATE projects
        SET status = 'cancelled'
        WHERE id = ? AND barangay_id = ?
    ");

    $stmt->execute([$id, $barangay_id]);
}

header("Location: chairperson_status.php");
exit();
?>