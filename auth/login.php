<?php
session_start();
include '../config/db.php';

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

/* EMPTY CHECK */
if (empty($username) || empty($password)) {
    die("Please enter username and password.");
}

/* ================= GET USER WITH BARANGAY ================= */
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

if ($user['status'] !== 'approved') {
    die("Account not approved by admin.");
}

if (!password_verify($password, $user['password'])) {
    die("Invalid password");
}

/* ================= SAVE SESSION ================= */
$_SESSION['user'] = [
    'id' => $user['id'],
    'username' => $user['username'],
    'role' => $user['role'],
    'barangay_id' => $user['barangay_id'],
    'barangay_name' => $user['barangay_name']
];

/* ================= REDIRECT BY ROLE ================= */
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