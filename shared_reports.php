<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= APPROVED PROJECTS ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM projects
    WHERE barangay_id = :bid AND status = 'approved'
");
$stmt->execute([':bid'=>$barangay_id]);
$approved = $stmt->fetchColumn();

/* ================= REJECTED PROJECTS ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM projects
    WHERE barangay_id = :bid AND status = 'rejected'
");
$stmt->execute([':bid'=>$barangay_id]);
$rejected = $stmt->fetchColumn();

/* ================= BUDGET UTILIZATION ================= */
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(annual_budget),0) as budget,
        COALESCE(SUM(budget_used),0) as used
    FROM budgets
    WHERE barangay_id = :bid
");
$stmt->execute([':bid'=>$barangay_id]);
$budget = $stmt->fetch(PDO::FETCH_ASSOC);

$budget_total = $budget['budget'];
$budget_used = $budget['used'];
$utilization = ($budget_total > 0) ? ($budget_used / $budget_total) * 100 : 0;

/* ================= PARTICIPATION ================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(participants),0)
    FROM activities
    WHERE barangay_id = :bid
");
$stmt->execute([':bid'=>$barangay_id]);
$participants = $stmt->fetchColumn();

/* ================= PROJECT TYPE ANALYSIS ================= */
$stmt = $conn->prepare("
    SELECT name, COUNT(*) as total
    FROM projects
    WHERE barangay_id = :bid
    GROUP BY name
    ORDER BY total DESC
    LIMIT 5
");
$stmt->execute([':bid'=>$barangay_id]);
$topProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Shared Reports</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.card {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(500px);
    padding: 20px;
    margin-bottom: 15px;
    border-radius: 15px;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>📊 Shared Reports (All Officials)</h2>

<div class="card">
    <h3>📌 Project Status</h3>
    <p>Approved Projects: <?= $approved ?></p>
    <p>Rejected Projects: <?= $rejected ?></p>
</div>

<div class="card">
    <h3>💰 Budget Utilization</h3>
    <p>Total Budget: ₱<?= number_format($budget_total) ?></p>
    <p>Used: ₱<?= number_format($budget_used) ?></p>
    <p>Utilization: <?= round($utilization,2) ?>%</p>
</div>

<div class="card">
    <h3>👥 Participation</h3>
    <p>Total Participants: <?= $participants ?></p>
</div>

<div class="card">
    <h3>🏆 Top Project Types</h3>

    <?php foreach($topProjects as $p){ ?>
        <p><?= $p['name'] ?> - <?= $p['total'] ?></p>
    <?php } ?>
</div>

</div>

</body>
</html>