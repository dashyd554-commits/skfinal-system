<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$user_id = $_SESSION['user']['id'];

/* ================= TOTAL PROPOSALS ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM projects
    WHERE barangay_id = :bid AND created_by = :uid
");
$stmt->execute([':bid' => $barangay_id, ':uid' => $user_id]);
$totalProposals = $stmt->fetchColumn();

/* ================= APPROVED ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM projects
    WHERE barangay_id = :bid AND created_by = :uid
    AND status = 'approved'
");
$stmt->execute([':bid' => $barangay_id, ':uid' => $user_id]);
$approved = $stmt->fetchColumn();

/* ================= REJECTED ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM projects
    WHERE barangay_id = :bid AND created_by = :uid
    AND status = 'rejected'
");
$stmt->execute([':bid' => $barangay_id, ':uid' => $user_id]);
$rejected = $stmt->fetchColumn();

/* ================= PENDING ================= */
$pending = $totalProposals - ($approved + $rejected);

/* ================= BUDGET REQUESTED ================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(budget_requested),0)
    FROM projects
    WHERE barangay_id = :bid AND created_by = :uid
");
$stmt->execute([':bid' => $barangay_id, ':uid' => $user_id]);
$totalBudgetRequested = $stmt->fetchColumn();

/* ================= ML SCORE ================= */
if ($totalProposals > 0) {
    $approvalRate = $approved / $totalProposals;
    $rejectionRate = $rejected / $totalProposals;

    $mlScore = ($approvalRate * 70) + ((1 - $rejectionRate) * 30);
} else {
    $mlScore = 0;
}

$mlScore = round(min(100, $mlScore), 2);

/* ================= TREND ================= */
$stmt = $conn->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as total
    FROM projects
    WHERE barangay_id = :bid AND created_by = :uid
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([':bid' => $barangay_id, ':uid' => $user_id]);
$trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$data = [];

foreach ($trend as $t) {
    $labels[] = $t['date'];
    $data[] = $t['total'];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Chairperson Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../assets/sbstyle.css">
<link rel="stylesheet" href="../assets/style.css">

<style>
body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
}

/* MAIN AREA */
.main{
    margin-left:190px;
    padding:20px;
    width:calc(100% - 200px);
}

/* HEADER */
h2{
    color:#1e3c72;
}

/* GRID FIXED */
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
    gap:15px;
    margin-bottom:20px;
}

/* CARD STYLE */
.card{
    background:rgba(255,255,255,0.55);
    backdrop-filter:blur(18px);
    border-radius:15px;
    padding:20px;
    text-align:center;
    box-shadow:0 8px 20px rgba(0,0,0,0.08);
    transition:0.2s;
}

.card:hover{
    transform:translateY(-3px);
}

.card h3{
    margin:0;
    font-size:14px;
    color:#555;
}

.card h2{
    margin-top:10px;
    color:#1e3c72;
}

/* CHART */
.chart-box{
    background:rgba(255,255,255,0.5);
    backdrop-filter:blur(18px);
    padding:20px;
    border-radius:15px;
    box-shadow:0 8px 20px rgba(0,0,0,0.08);
}

/* RESPONSIVE */
@media(max-width:768px){
    .main{
        margin-left:70px;
        width:calc(100% - 80px);
    }
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>👑 Chairperson Dashboard</h2>

<div class="grid">

    <div class="card">
        <h3>Total Proposals</h3>
        <h2><?= $totalProposals ?></h2>
    </div>

    <div class="card">
        <h3>Approved</h3>
        <h2><?= $approved ?></h2>
    </div>

    <div class="card">
        <h3>Rejected</h3>
        <h2><?= $rejected ?></h2>
    </div>

    <div class="card">
        <h3>Pending</h3>
        <h2><?= $pending ?></h2>
    </div>

    <div class="card">
        <h3>Total Budget Requested</h3>
        <h2>₱<?= number_format($totalBudgetRequested,2) ?></h2>
    </div>

    <div class="card">
        <h3>AI Confidence</h3>
        <h2><?= $mlScore ?>%</h2>
    </div>

</div>

<div class="chart-box">
    <h3>📊 Proposal Trend</h3>
    <canvas id="chart"></canvas>
</div>

</div>

<script>
new Chart(document.getElementById('chart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Proposals Over Time',
            data: <?= json_encode($data) ?>,
            borderWidth: 2,
            tension: 0.3
        }]
    },
    options: {
        responsive: true
    }
});
</script>

</body>
</html>