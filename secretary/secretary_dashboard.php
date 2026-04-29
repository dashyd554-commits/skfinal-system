<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= COUNTS ================= */
$stmt = $conn->prepare("SELECT COUNT(*) FROM proposals WHERE barangay_id=? AND status='pending_secretary'");
$stmt->execute([$barangay_id]);
$pending = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM proposals WHERE barangay_id=? AND status='pending_treasurer'");
$stmt->execute([$barangay_id]);
$approved = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM proposals WHERE barangay_id=? AND status='rejected'");
$stmt->execute([$barangay_id]);
$rejected = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html>
<head>
<title>Secretary Dashboard</title>

<link rel="stylesheet" href="../assets/style.css">

<style>
.grid {
    display:grid;
    grid-template-columns: repeat(3,1fr);
    gap:15px;
}

.card {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(20px);
    padding:20px;
    border-radius:15px;
    text-align:center;
}

@media(max-width:768px){
    .grid{grid-template-columns:1fr;}
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>🗳 Secretary Dashboard</h2>

<div class="grid">

<div class="card">
<h3>Pending Proposals</h3>
<h2><?= $pending ?></h2>
</div>

<div class="card">
<h3>Sent to Treasurer</h3>
<h2><?= $approved ?></h2>
</div>

<div class="card">
<h3>Rejected</h3>
<h2><?= $rejected ?></h2>
</div>

</div>

</div>

</body>
</html>