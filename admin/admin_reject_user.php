<?php
session_start();
include '../config/db.php';
require '../config/mail.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) die("Invalid request");

/* ================= GET USER INFO FIRST ================= */
$stmt = $conn->prepare("
    SELECT u.*, b.barangay_name
    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.id = ?
");
$stmt->execute([$id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    die("User not found");
}

/* ================= REJECT USER ================= */
$stmt = $conn->prepare("
    UPDATE users 
    SET status = 'rejected',
        approved_by_admin = 0
    WHERE id = ?
");
$stmt->execute([$id]);

/* ================= SEND REJECTION EMAIL ================= */
if (!empty($u['email'])) {
    sendEmail(
        $u['email'],
        "SK Account Registration Rejected",
        "
        <h3>Hello {$u['fullname']},</h3>
        <p>We regret to inform you that your SK Officer account registration for <b>{$u['barangay_name']}</b> has not been approved by the administrator.</p>

        <p>Possible reasons may include:</p>
        <ul>
            <li>Information provided is incomplete or invalid</li>
            <li>Barangay role slot is already occupied</li>
            <li>Age requirement did not meet SK policy</li>
            <li>Administrative verification issue</li>
        </ul>

        <p>You may contact the SK administrator for clarification or submit a new registration if allowed.</p>

        <br>
        <p>— SK Decision Support System</p>
        "
    );
}

/* ================= AUDIT LOG ================= */
$log = "Rejected {$u['role']} account ({$u['username']})";

$stmt = $conn->prepare("
    INSERT INTO audit_logs
    (username, barangay_name, action_type, table_name, description)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $_SESSION['admin']['username'] ?? 'admin',
    $u['barangay_name'] ?? 'N/A',
    'REJECT',
    'users',
    $log
]);

/* ================= REDIRECT ================= */
header("Location: admin_pending.php");
exit();
?>