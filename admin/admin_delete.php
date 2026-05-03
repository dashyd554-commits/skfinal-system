<?php
session_start();
include '../config/db.php';

/* ================= SECURITY CHECK ================= */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'municipal_admin') {
    header("Location: ../index.php");
    exit();
}

/* ================= GET USER ID ================= */
$id = $_GET['id'] ?? null;

if (!$id) {
    die("Invalid request: missing user ID");
}

/* ================= FETCH USER ================= */
$stmt = $conn->prepare("
    SELECT u.*, b.barangay_name
    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.id = ?
");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

/* ================= DELETE USER ================= */
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$id]);

/* ================= AUDIT LOG ================= */
$adminUsername = $_SESSION['admin']['username'] ?? 'admin';

$log = "Deleted user account: {$user['username']} ({$user['role']})";

$stmt = $conn->prepare("
    INSERT INTO audit_logs
    (username, barangay_name, action_type, table_name, description, action_time)
    VALUES (?, ?, ?, ?, ?, NOW())
");

$stmt->execute([
    $adminUsername,
    $user['barangay_name'] ?? 'N/A',
    'DELETE',
    'users',
    $log
]);

/* ================= REDIRECT ================= */
header("Location: admin_official_information.php");
exit();
?>