<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_officials_information.php");
    exit();
}

$id = $_GET['id'];

/* UPDATE STATUS TO INACTIVE */
$stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
$stmt->execute([$id]);

/* OPTIONAL AUDIT LOG */
if(isset($_SESSION['admin'])){
    $log = $conn->prepare("
        INSERT INTO audit_logs (username, action_type, action_time)
        VALUES (?, ?, NOW())
    ");
    $log->execute(['Admin', 'Deactivated official account ID '.$id]);
}

header("Location: admin_officials_information.php");
exit();
?>