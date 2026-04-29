<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'treasurer') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$treasurer_id = $_SESSION['user']['id'];

/* ================= CURRENT BUDGET ================= */
$stmt = $conn->prepare("
    SELECT * FROM budgets
    WHERE barangay_id = :bid
    ORDER BY year DESC LIMIT 1
");
$stmt->execute([':bid' => $barangay_id]);
$budget = $stmt->fetch(PDO::FETCH_ASSOC);

$total_budget = $budget['annual_budget'] ?? 0;
$used_budget = $budget['budget_used'] ?? 0;
$remaining_budget = $budget['remaining_budget'] ?? ($total_budget - $used_budget);

/* ================= APPROVED DISBURSEMENTS ================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) as total
    FROM budget_transactions
    WHERE barangay_id = :bid
");
$stmt->execute([':bid' => $barangay_id]);
$spent = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

/* ================= PENDING PROJECTS ================= */
$stmt = $conn->prepare("
    SELECT * FROM projects
    WHERE barangay_id = :bid
    AND status = 'pending_treasurer'
");
$stmt->execute([':bid' => $barangay_id]);
$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= REJECTED COUNT ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM projects
    WHERE barangay_id = :bid
    AND status = 'rejected'
");
$stmt->execute([':bid' => $barangay_id]);
$rejected = $stmt->fetchColumn();

?>

<!DOCTYPE html>
<html>
<head>
<title>Treasurer Dashboard</title>
<link rel="stylesheet" href="../assets/style.css">

<style>
.card { padding:20px; background:white; margin:10px; border-radius:10px; }
.grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>💰 Treasurer Dashboard</h2>

<div class="grid">

<div class="card">
<h3>Annual Budget</h3>
<p>₱ <?= number_format($total_budget) ?></p>
</div>

<div class="card">
<h3>Remaining Budget</h3>
<p>₱ <?= number_format($remaining_budget) ?></p>
</div>

<div class="card">
<h3>Total Spent</h3>
<p>₱ <?= number_format($spent) ?></p>
</div>

<div class="card">
<h3>Rejected Proposals</h3>
<p><?= $rejected ?></p>
</div>

</div>

<h3>📌 Pending Approval</h3>

<?php foreach($pending as $p){ ?>
<div class="card">
    <b><?= htmlspecialchars($p['name']) ?></b><br>
    Budget: ₱<?= number_format($p['budget_requested']) ?>
</div>
<?php } ?>

</div>
</body>
</html>