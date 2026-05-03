<?php
session_start();
include '../config/db.php';

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

/* EMPTY CHECK */
if (empty($username) || empty($password)) {
    die("Please enter username and password.");
}

/* ================= GET USER ================= */
$stmt = $conn->prepare("
    SELECT u.*, b.barangay_name
    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.username = ?
");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= VALIDATION ================= */
if (!$user) {
    die("User not found");
}

/* 1. CHECK EMAIL VERIFIED (OTP STEP) */
if ($user['is_verified'] != 1) {
    die("Please verify your email first.");
}

/* 2. CHECK ADMIN APPROVAL */
if ($user['status'] !== 'approved') {
    die("Account not yet approved by admin.");
}

/* 3. PASSWORD CHECK */
if (!password_verify($password, $user['password'])) {
    die("Invalid password");
}

/* ================= SESSION ================= */
$_SESSION['user'] = [
    'id' => $user['id'],
    'username' => $user['username'],
    'role' => $user['role'],
    'barangay_id' => $user['barangay_id'],
    'barangay_name' => $user['barangay_name']
];

/* ================= REDIRECT ================= */
if ($user['role'] === 'chairman') {
    header("Location: ../chairperson/chairperson_dashboard.php");
    exit();
}

elseif ($user['role'] === 'secretary') {
    header("Location: ../secretary/secretary_dashboard.php");
    exit();
}

elseif ($user['role'] === 'treasurer') {
    header("Location: ../treasurer/treasurer_dashboard.php");
    exit();
}

elseif ($user['role'] === 'admin') {
    $_SESSION['admin'] = $_SESSION['user'];
    header("Location: ../admin/dashboard.php");
    exit();
}

else {
    die("Unknown role");
}
?>