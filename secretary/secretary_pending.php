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
    WHERE barangay_id=? AND status='pending_secretary'
    ORDER BY created_at DESC
");
$stmt->execute([$barangay_id]);

$proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Pending Proposals</title>

<link rel="stylesheet" href="../assets/style.css">

<style>
.card {
    background:white;
    padding:15px;
    margin-bottom:10px;
    border-radius:10px;
}
a.btn {
    padding:8px 12px;
    background:#0d6efd;
    color:white;
    text-decoration:none;
    border-radius:6px;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>📌 Pending Proposals</h2>

<?php foreach ($proposals as $p) { ?>

<div class="card">
    <h3><?= htmlspecialchars($p['title']) ?></h3>
    <p>Budget: ₱<?= number_format($p['proposed_budget']) ?></p>
    <p>Status: <?= $p['status'] ?></p>

    <a class="btn" href="secretary_vote.php?id=<?= $p['id'] ?>">
        Vote Council
    </a>
</div>

<?php } ?>

</div>

</body>
</html>