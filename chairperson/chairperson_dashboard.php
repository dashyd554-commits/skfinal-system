<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairperson') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user']['id'];
$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= TOTALS ================= */
$stmt = $conn->prepare("SELECT COUNT(*) FROM proposals WHERE created_by = ?");
$stmt->execute([$user_id]);
$total = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM proposals WHERE created_by = ? AND status = 'approved'");
$stmt->execute([$user_id]);
$approved = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM proposals WHERE created_by = ? AND status LIKE '%rejected%'");
$stmt->execute([$user_id]);
$rejected = $stmt->fetchColumn();

/* ================= AI CONFIDENCE ================= */
$confidence = ($total > 0) ? round(($approved / $total) * 100, 2) : 0;

/* ================= TREND DATA ================= */
$stmt = $conn->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as total
    FROM proposals
    WHERE created_by = ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([$user_id]);

$labels = [];
$data = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $labels[] = $row['date'];
    $data[] = $row['total'];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Chairperson Dashboard</title>

<link rel="stylesheet" href="../assets/style.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.grid {
    display:grid;
    grid-template-columns: repeat(4,1fr);
    gap:15px;
}

.card {
    background:rgba(255,255,255,0.2);
    backdrop-filter: blur(20px);
    padding:20px;
    border-radius:15px;
    text-align:center;
}

.glass {
    background:rgba(255,255,255,0.2);
    backdrop-filter: blur(20px);
    padding:20px;
    border-radius:15px;
}

@media(max-width:768px){
    .grid{grid-template-columns:1fr;}
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
<h2><?= $total ?></h2>
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
<h3>AI Confidence</h3>
<h2><?= $confidence ?>%</h2>
</div>

</div>

<div class="glass" style="margin-top:20px;">
<h3>📊 Proposal Trend</h3>
<canvas id="chart"></canvas>
</div>

</div>

<script>
new Chart(document.getElementById('chart'), {
    type:'line',
    data:{
        labels: <?= json_encode($labels) ?>,
        datasets:[{
            label:'Proposals',
            data: <?= json_encode($data) ?>
        }]
    }
});
</script>

</body>
</html>