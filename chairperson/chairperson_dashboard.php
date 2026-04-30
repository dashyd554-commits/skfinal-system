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
    SELECT COUNT(*) 
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

/* ================= BUDGET ================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(budget_requested),0)
    FROM projects
    WHERE barangay_id = :bid AND created_by = :uid
");
$stmt->execute([':bid' => $barangay_id, ':uid' => $user_id]);
$totalBudgetRequested = $stmt->fetchColumn();

/* ================= ML API CALL ================= */
$ml_data = null;

$payload = json_encode([
    "barangay_id" => $barangay_id,
    "total" => $totalProposals,
    "approved" => $approved,
    "rejected" => $rejected,
    "pending" => $pending
]);

$ch = curl_init("https://skmanagementsys.onrender.com/predict");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 && $response) {
    $ml_data = json_decode($response, true);
}

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

.main{
    margin-left:190px;
    padding:20px;
    width:calc(100% - 200px);
}

h2{
    color:white;
    text-align:center;
    margin-bottom:20px;
}

.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:15px;
}

.card{
    background:rgba(255,255,255,0.55);
    backdrop-filter:blur(18px);
    border-radius:15px;
    padding:20px;
    text-align:center;
}

.card h3{margin:0;color:#555;}
.card h2{margin-top:10px;color:#1e3c72;}

.ml-box{
    background:rgba(255,255,255,0.7);
    padding:15px;
    border-radius:12px;
    margin-top:20px;
}

.chart-box{
    background:rgba(255,255,255,0.5);
    padding:20px;
    border-radius:15px;
    margin-top:20px;
}

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

<h2>👑 Chairperson Dashboard (AI Powered)</h2>

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
        <h3>Total Budget</h3>
        <h2>₱<?= number_format($totalBudgetRequested,2) ?></h2>
    </div>

</div>

<!-- ================= ML SECTION ================= -->
<div class="ml-box">

    <h3>🤖 AI / ML Analysis</h3>

    <?php if ($ml_data): ?>
        <p><b>Category:</b> <?= $ml_data['category'] ?></p>
        <p><b>Success Probability:</b> <?= round($ml_data['success_probability'] * 100,2) ?>%</p>
        <p><b>Budget Efficiency:</b> <?= $ml_data['budget_efficiency_score'] ?>%</p>
        <p><b>Recommendation:</b> <?= $ml_data['recommendation'] ?></p>
    <?php else: ?>
        <p style="color:red;">ML service unavailable. Showing basic analytics only.</p>
    <?php endif; ?>

</div>

<!-- ================= CHART ================= -->
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
    }
});
</script>

</body>
</html>