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

/* ================= GET USER INFO FIRST ================= */
$stmt = $conn->prepare("
    SELECT u.username, u.role, b.barangay_name
    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.id = ?
");
$stmt->execute([$id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= SAFETY CHECK ================= */
if (!$u) {
    die("User not found");
}

/* ================= APPROVE USER ================= */
$stmt = $conn->prepare("
    UPDATE users
    SET status = 'approved'
    WHERE id = ?
");
$stmt->execute([$id]);

/* ================= AUDIT LOG (FIXED FOR YOUR DB) ================= */
$log = "Approved {$u['role']} account ({$u['username']})";

$stmt = $conn->prepare("
    INSERT INTO audit_logs 
    (username, barangay_name, action_type, table_name, description)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $_SESSION['admin']['username'] ?? 'admin',
    $u['barangay_name'] ?? 'N/A',
    'APPROVE',
    'users',
    $log
]);

/* ================= REDIRECT ================= */
header("Location: admin_pending.php");
exit();
?>