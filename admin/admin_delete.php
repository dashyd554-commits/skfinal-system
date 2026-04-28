<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Invalid request");
}

/* ================= GET USER FIRST (FOR LOGGING) ================= */
$stmt = $conn->prepare("
    SELECT username, role, barangay_id 
    FROM users 
    WHERE id = ?
");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

/* ================= DELETE USER ================= */
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$id]);

/* ================= INSERT AUDIT LOG ================= */
$log = "Deleted user account: {$user['username']} ({$user['role']})";

$stmt = $conn->prepare("
    INSERT INTO audit_logs (action_done, username, role, barangay_id)
    VALUES (?, ?, ?, ?)
");

$stmt->execute([
    $log,
    $_SESSION['admin']['username'],
    'admin',
    $user['barangay_id']
]);

/* ================= REDIRECT ================= */
header("Location: users.php");
exit();
?>