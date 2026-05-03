<?php
session_start();
include '../config/db.php';
require '../config/mail.php';

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

/* ================= SEND EMAIL BEFORE DELETE ================= */
if (!empty($user['email'])) {
    sendEmail(
        $user['email'],
        "SK Account Removed",
        "
        <h3>Hello {$user['fullname']},</h3>
        <p>Your SK Officer account has been removed from the SK Decision Support System by the administrator.</p>

        <p>If you believe this action was made in error, please contact the system administrator.</p>

        <br>
        <p>— SK Decision Support System</p>
        "
    );
}

/* ================= DELETE USER ================= */
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$id]);

/* ================= AUDIT LOG ================= */
$log = "Deleted user account: {$user['username']} ({$user['role']})";

$stmt = $conn->prepare("
    INSERT INTO audit_logs
    (username, barangay_name, action_type, table_name, description)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $_SESSION['admin']['username'] ?? 'admin',
    $user['barangay_name'] ?? 'N/A',
    'DELETE',
    'users',
    $log
]);

/* ================= REDIRECT ================= */
header("Location: admin_users.php");
exit();
?>