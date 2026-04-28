<?php
session_start();
include '../config/db.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

/* ================= VALIDATION ================= */
if (!$username || !$password) {
    die("Please fill in all fields");
}

/* ================= FETCH ADMIN FROM DB ================= */
$stmt = $conn->prepare("
    SELECT * FROM users 
    WHERE username = ? 
    AND role = 'admin'
    LIMIT 1
");
$stmt->execute([$username]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= VERIFY ADMIN ================= */
if (!$admin) {
    die("Admin not found");
}

if (!password_verify($password, $admin['password'])) {
    die("Invalid password");
}

/* ================= SESSION ================= */
$_SESSION['admin'] = [
    'id' => $admin['id'],
    'username' => $admin['username'],
    'role' => $admin['role']
];

/* ================= AUDIT LOG ================= */
$stmt = $conn->prepare("
    INSERT INTO audit_logs (action_done, username, role, barangay_id)
    VALUES (?, ?, ?, ?)
");

$stmt->execute([
    "Admin logged in",
    $admin['username'],
    'admin',
    $admin['barangay_id'] ?? null
]);

/* ================= REDIRECT ================= */
header("Location: dashboard.php");
exit();
?>