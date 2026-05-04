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
    WHERE barangay_id = :bid
    AND created_by = :uid
    AND status != 'cancelled'
");
$stmt->execute([':bid' => $barangay_id, ':uid' => $user_id]);
$totalProposals = $stmt->fetchColumn();

/* ================= APPROVED ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM projects
    WHERE barangay_id = :bid
    AND created_by = :uid
    AND status = 'approved'
");
$stmt->execute([':bid' => $barangay_id, ':uid' => $user_id]);
$approved = $stmt->fetchColumn();

/* ================= REJECTED ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM projects
    WHERE barangay_id = :bid
    AND created_by = :uid
    AND status = 'rejected'
");
$stmt->execute([':bid' => $barangay_id, ':uid' => $user_id]);
$rejected = $stmt->fetchColumn();

/* ================= PENDING ================= */
$pending = $totalProposals - ($approved + $rejected);
if($pending < 0){ $pending = 0; }

/* ================= BUDGET INFO ================= */
$stmt = $conn->prepare("
    SELECT total_amount, remaining_budget
    FROM budgets
    WHERE barangay_id = :bid
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([':bid' => $barangay_id]);
$budgetInfo = $stmt->fetch(PDO::FETCH_ASSOC);

$totalAnnualBudget = $budgetInfo['total_amount'] ?? 0;
$remainingBudget   = $budgetInfo['remaining_budget'] ?? 0;

/* ================= USED BUDGET ================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM budget_transactions
    WHERE barangay_id = :bid
");
$stmt->execute([':bid' => $barangay_id]);
$totalUsedBudget = $stmt->fetchColumn();

/* ================= ML API CALL ================= */
$url = "https://skfinal-system.onrender.com/predict";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

/* ================= SAFE AI DEFAULTS ================= */
$ml_online = false;
$mean_score = 0;
$category = "No Data";
$success_probability = 0;
$budget_efficiency = 0;
$recommendation = "No recommendation available";

/* ================= PARSE AI ================= */
if ($response && $http_code == 200) {
    $decoded = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        $mean_score = floatval($decoded['mean_score'] ?? 0);
        $budget_efficiency = round($mean_score,2);

        if ($mean_score >= 70) {
            $category = "High Performance";
            $success_probability = 0.85;
            $recommendation = "Barangay operations are performing strongly. Maintain and expand approved youth programs.";
        }
        elseif ($mean_score >= 40) {
            $category = "Moderate Performance";
            $success_probability = 0.60;
            $recommendation = "Barangay has stable project execution. Improve proposal quality and participation rate.";
        }
        elseif ($mean_score > 0) {
            $category = "Low Performance";
            $success_probability = 0.30;
            $recommendation = "Barangay needs stronger planning, approval management, and budget optimization.";
        }

        $ml_online = true;
    }
}

/* ================= TREND ================= */
$stmt = $conn->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as total
    FROM projects
    WHERE barangay_id = :bid
    AND created_by = :uid
    AND status != 'cancelled'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$stmt->execute([':bid' => $barangay_id, ':uid' => $user_id]);
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
<link rel="stylesheet" href="../assets/sbstyle.css">
<link rel="stylesheet" href="../assets/style.css">

<style>
body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    font-family:Arial;
}

.main{
    margin-left:190px;
    padding:20px;
    width:calc(100% - 210px);
}

h2{
    color:white;
    text-align:center;
    margin-bottom:20px;
}

.grid{
    display:flex;
    flex-direction:column;
    gap:15px;
}

.row{
    display:flex;
    gap:15px;
}

.row.top .card{
    flex:1;
}

.row.bottom{
    justify-content:center;
}

.row.bottom .card{
    width:220px;
}

.card,.ml-box,.chart-box{
    background:rgba(255,255,255,0.18);
    backdrop-filter:blur(18px);
    border-radius:15px;
    padding:20px;
    color:white;
    text-align:center;
    box-shadow:0 8px 25px rgba(0,0,0,0.25);
    margin-bottom:20px;
}

.card h2{
    color:#1e3c72;
}

.ml-box,.chart-box{
    text-align:left;
}

@media(max-width:768px){
    .main{
        margin-left:0;
        width:100%;
        padding:10px;
    }

    .row{
        flex-direction:column;
    }

    .row.bottom .card{
        width:100%;
    }
}
</style>
</head>

<body>

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

    <?php if ($ml_online): ?>
        <p><b>Category:</b> <?= htmlspecialchars($category) ?></p>
        <p><b>Success Probability:</b> <?= round($success_probability * 100,2) ?>%</p>
        <p><b>Budget Efficiency:</b> <?= $budget_efficiency ?>%</p>
        <p><b>Recommendation:</b> <?= htmlspecialchars($recommendation) ?></p>
    <?php else: ?>
        <p style="color:#ffcccc;">AI service unavailable.</p>
        <small>HTTP CODE: <?= $http_code ?> | CURL ERROR: <?= $curl_error ?></small>
    <?php endif; ?>
</div>

<div class="chart-box">
    <h3>📊 Proposal Submission Trend</h3>
    <canvas id="chart"></canvas>
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
        responsive:true
    }
});
</script>

</body>
</html>