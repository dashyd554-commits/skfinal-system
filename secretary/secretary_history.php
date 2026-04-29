<?php
session_start();
include '../config/db.php';

if ($_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

$stmt = $conn->prepare("
    SELECT *
    FROM proposals
    WHERE barangay_id=? AND status IN ('pending_treasurer','rejected')
    ORDER BY created_at DESC
");
$stmt->execute([$barangay_id]);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>History</title>

<link rel="stylesheet" href="../assets/style.css">

<style>
table {
    width:100%;
    background:white;
    border-collapse:collapse;
}

th {
    background:#6f42c1;
    color:white;
    padding:10px;
}

td {
    padding:10px;
    border-bottom:1px solid #ddd;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>📜 Voting History</h2>

<table>
<tr>
    <th>Title</th>
    <th>Yes</th>
    <th>No</th>
    <th>Status</th>
</tr>

<?php foreach ($data as $d) { ?>

<tr>
    <td><?= htmlspecialchars($d['title']) ?></td>
    <td><?= $d['vote_yes'] ?></td>
    <td><?= $d['vote_no'] ?></td>
    <td><?= $d['status'] ?></td>
</tr>

<?php } ?>

</table>

</div>

</body>
</html>