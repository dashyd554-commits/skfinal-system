<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$user_id     = $_SESSION['user']['id'];

/* ================= TOTAL PROPOSALS ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM projects
    WHERE barangay_id = :bid
    AND created_by = :uid
    AND status != 'cancelled'
");
$stmt->execute([':bid'=>$barangay_id, ':uid'=>$user_id]);
$totalProposals = (int)$stmt->fetchColumn();

/* ================= APPROVED ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM projects
    WHERE barangay_id = :bid
    AND created_by = :uid
    AND status = 'approved'
");
$stmt->execute([':bid'=>$barangay_id, ':uid'=>$user_id]);
$approved = (int)$stmt->fetchColumn();

/* ================= REJECTED ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM projects
    WHERE barangay_id = :bid
    AND created_by = :uid
    AND status = 'rejected'
");
$stmt->execute([':bid'=>$barangay_id, ':uid'=>$user_id]);
$rejected = (int)$stmt->fetchColumn();

$pending = max(0, $totalProposals - ($approved + $rejected));

/* ================= BUDGET ================= */
$stmt = $conn->prepare("
    SELECT total_amount, remaining_budget
    FROM budgets
    WHERE barangay_id = :bid
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([':bid'=>$barangay_id]);
$budgetInfo = $stmt->fetch(PDO::FETCH_ASSOC);

$totalAnnualBudget = (float)($budgetInfo['total_amount'] ?? 0);
$remainingBudget   = (float)($budgetInfo['remaining_budget'] ?? 0);

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM budget_transactions
    WHERE barangay_id = :bid
");
$stmt->execute([':bid'=>$barangay_id]);
$totalUsedBudget = (float)$stmt->fetchColumn();

/* ================= TREND ================= */
$stmt = $conn->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as total
    FROM projects
    WHERE barangay_id = :bid
    AND created_by = :uid
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([':bid'=>$barangay_id, ':uid'=>$user_id]);
$trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$data = [];

foreach($trend as $t){
    $labels[] = $t['date'];
    $data[] = $t['total'];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Chairperson Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../assets/style.css">

<style>
*{box-sizing:border-box;}

body{
    margin:0;
    font-family:Arial;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    height:100vh;
    overflow:hidden;
}

/* MAIN WRAPPER */
.wrapper{
    display:flex;
    height:100vh;
    overflow:hidden;
}

/* MAIN CONTENT */
.main{
    flex:1;
    display:flex;
    flex-direction:column;
    padding:15px;
    overflow:hidden;
}

/* TITLE */
h2{
    color:white;
    text-align:center;
    margin:5px 0 10px;
}

/* GRID TOP */
.grid{
    display:flex;
    flex-direction:column;
    gap:10px;
    flex-shrink:0;
}

/* ROWS */
.row{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

/* CARDS */
.card{
    background:rgba(255,255,255,0.18);
    backdrop-filter:blur(18px);
    border-radius:12px;
    padding:12px;
    color:white;
    text-align:center;
    flex:1;
    min-width:140px;
}

/* BOTTOM ROW */
.row.bottom{
    justify-content:center;
}

.row.bottom .card{
    width:200px;
    flex:none;
}

/* KPI TEXT */
.card h2{
    color:#1e3c72;
    margin:5px 0 0;
}

/* ML BOX */
.ml-box{
    margin-top:10px;
    padding:10px;
    background:rgba(255,255,255,0.15);
    border-radius:12px;
    color:white;
    flex-shrink:0;
}

/* CHART AREA TAKES REMAINING SPACE */
.chart-box{
    flex:1;
    margin-top:10px;
    background:rgba(255,255,255,0.15);
    border-radius:12px;
    padding:10px;
    display:flex;
    flex-direction:column;
    overflow:hidden;
}

/* CHART FIX */
.chart-wrapper{
    flex:1;
    position:relative;
}

canvas{
    width:100% !important;
    height:100% !important;
}

/* RESPONSIVE */
@media(max-width:768px){
    body{overflow:auto;}
    .wrapper{flex-direction:column;height:auto;}
    .main{height:auto;overflow:auto;}
    .row{flex-direction:column;}
    .row.bottom .card{width:100%;}
}
</style>
</head>

<body>

<div class="wrapper">
<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>👑 Chairperson Dashboard</h2>

<div class="grid">

    <div class="row top">
        <div class="card"><h3>Total Proposals</h3><h2><?= $totalProposals ?></h2></div>
        <div class="card"><h3>Approved</h3><h2><?= $approved ?></h2></div>
        <div class="card"><h3>Rejected</h3><h2><?= $rejected ?></h2></div>
        <div class="card"><h3>Pending</h3><h2><?= $pending ?></h2></div>
    </div>

    <div class="row bottom">
        <div class="card"><h3>Total Budget</h3><h2>₱<?= number_format($totalAnnualBudget,2) ?></h2></div>
        <div class="card"><h3>Used Budget</h3><h2>₱<?= number_format($totalUsedBudget,2) ?></h2></div>
        <div class="card"><h3>Remaining Budget</h3><h2>₱<?= number_format($remainingBudget,2) ?></h2></div>
    </div>

</div>

<div class="ml-box">
    <h3>🤖 AI / ML Analysis</h3>
    <p>System analysis available here.</p>
</div>

<div class="chart-box">
    <h3 style="color:white;margin:0 0 10px;">📊 Proposal Submission Trend</h3>
    <div class="chart-wrapper">
        <canvas id="chart"></canvas>
    </div>
</div>

</div>
</div>

<script>
new Chart(document.getElementById('chart'), {
    type:'line',
    data:{
        labels: <?= json_encode($labels) ?>,
        datasets:[{
            label:'Proposal Trend',
            data: <?= json_encode($data) ?>,
            borderWidth:2,
            tension:0.3,
            fill:false
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false
    }
});
</script>

</body>
</html>