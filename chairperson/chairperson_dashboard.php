<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= BUDGET ================= */
$stmt = $conn->prepare("
    SELECT total_amount 
    FROM budgets 
    WHERE barangay_id = :barangay_id
    ORDER BY year DESC
    LIMIT 1
");
$stmt->bindValue(':barangay_id', $barangay_id);
$stmt->execute();
$budgetData = $stmt->fetch(PDO::FETCH_ASSOC);

$totalBudget = $budgetData['total_amount'] ?? 0;

/* ================= ACTIVITIES ================= */
$stmt = $conn->prepare("
    SELECT title, participants, allocated_budget 
    FROM activities 
    WHERE barangay_id = :barangay_id
");
$stmt->bindValue(':barangay_id', $barangay_id);
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= INIT ================= */
$totalParticipants = 0;
$totalProjects = count($activities);
$budgetUsed = 0;

$labels = [];
$data = [];

$topActivity = "N/A";
$topCount = -1;

$lowestActivity = "N/A";
$lowestCount = PHP_INT_MAX;

/* ================= PROCESS DATA ================= */
foreach ($activities as $a) {

    $labels[] = $a['title'];
    $data[] = $a['participants'];

    $totalParticipants += (int)$a['participants'];
    $budgetUsed += (float)$a['allocated_budget'];

    // TOP
    if ($a['participants'] > $topCount) {
        $topCount = $a['participants'];
        $topActivity = $a['title'];
    }

    // LOWEST
    if ($a['participants'] < $lowestCount) {
        $lowestCount = $a['participants'];
        $lowestActivity = $a['title'];
    }
}

if ($lowestCount == PHP_INT_MAX) $lowestCount = 0;

/* ================= SAFE CALCULATION ================= */
$remainingBudget = max(0, $totalBudget - $budgetUsed);

/* normalize */
$efficiency = ($budgetUsed > 0)
    ? min(10, $totalParticipants / max(1, $budgetUsed))
    : 0;

$budgetRatio = ($totalBudget > 0)
    ? ($budgetUsed / $totalBudget)
    : 0;

/* ================= REALISTIC ML SCORE ================= */
$mlScore = (
    ($efficiency * 30) +
    ($budgetRatio * 40) +
    ($totalProjects * 2)
);

/* clamp 0–100 */
$mlScore = max(0, min(100, round($mlScore, 2)));

/* ================= FIXED BUDGET LOGIC ================= */
if ($mlScore >= 70) {
    $budgetIncreaseRate = 0.20;
    $recommendation = "High performance detected. Strong justification for budget increase.";
    $nextSteps = "Expand successful programs and scale youth engagement.";
    $priorityAction = "Invest in sports, leadership, and livelihood projects.";

} elseif ($mlScore >= 40) {
    $budgetIncreaseRate = 0.10;
    $recommendation = "Moderate performance. Controlled budget increase recommended.";
    $nextSteps = "Improve participation and strengthen activity execution.";
    $priorityAction = "Enhance outreach and structured training programs.";

} else {
    $budgetIncreaseRate = 0.05; // 🔥 FIX: never show 0 unless truly needed
    $recommendation = "Low performance. Small budget adjustment only.";
    $nextSteps = "Revise programs before scaling funding.";
    $priorityAction = "Conduct evaluation and redesign activities.";
}

$nextYearBudget = $totalBudget + ($totalBudget * $budgetIncreaseRate);
?>

<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chairman Dashboard</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
*{
    box-sizing:border-box;
}

body{
    margin:0;
    padding:0;
    overflow-x:hidden;
}

.main{
    margin-left:190px;
    padding:20px;
    width:calc(100% - 200px);
    overflow-x:hidden;
}

.grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    width:100%;
}

.card{
    text-align:center;
    padding:20px;
}

.glass {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(500px);
    border-radius: 15px;
    padding: 20px;
}

canvas{
    width:100% !important;
    max-width:100%;
}

@media(max-width:1200px){
    .grid{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:768px){
    .main{
        margin-left:70px;
        width:calc(100% - 80px);
    }

    .grid{
        grid-template-columns:1fr;
    }
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>🤖 Chairman AI Dashboard</h2>
</div>

<!-- GRID -->
<div class="grid">

    <div class="glass card">
        <h3>💰 Budget</h3>
        <h2>₱ <?= number_format($totalBudget,2) ?></h2>
    </div>

    <div class="glass card">
        <h3>📉 Budget Used</h3>
        <h2>₱ <?= number_format($budgetUsed,2) ?></h2>
    </div>

    <div class="glass card">
        <h3>📊 Remaining</h3>
        <h2>₱ <?= number_format($remainingBudget,2) ?></h2>
    </div>

    <div class="glass card">
        <h3>📁 Activities</h3>
        <h2><?= $totalProjects ?></h2>
    </div>

    <div class="glass card">
        <h3>👥 Participants</h3>
        <h2><?= $totalParticipants ?></h2>
    </div>

    <div class="glass card">
        <h3>🤖 ML Score</h3>
        <h2><?= $mlScore ?>%</h2>
    </div>

    <div class="glass card">
        <h3>🏆 Top Activity</h3>
        <h2><?= htmlspecialchars($topActivity) ?></h2>
    </div>

    <div class="glass card">
        <h3>⚠ Lowest Activity</h3>
        <h2><?= htmlspecialchars($lowestActivity) ?></h2>
    </div>

</div>

<div class="glass">
    <h3>📊 Real-Time Activity Participation Graph</h3>
    <canvas id="chart"></canvas>
</div>

<!-- INSIGHT -->
<div class="glass">
    <h3>🤖 AI Recommendation</h3>
    <p><?= $recommendation ?></p>
</div>

<!-- CONCLUSION -->
<div class="glass">
    <h3>🧠 ML Conclusion</h3>

    <p><b>Next Steps:</b> <?= $nextSteps ?></p>
    <p><b>Priority Action:</b> <?= $priorityAction ?></p>

    <hr>

    <p><b>📈 Budget Increase:</b> <?= ($budgetIncreaseRate * 100) ?>%</p>
    <p><b>💰 Next Year Budget:</b> ₱ <?= number_format($nextYearBudget,2) ?></p>
</div>

</div>

<script>
let myChart;

function loadLiveChart() {
    fetch("get_live_chart_data.php")
    .then(res => res.json())
    .then(result => {

        const labels = result.labels;
        const values = result.data;

        if (myChart) {
            myChart.data.labels = labels;
            myChart.data.datasets[0].data = values;
            myChart.update();
            return;
        }

        const ctx = document.getElementById('chart').getContext('2d');

        myChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Participants',
                    data: values,
                    borderWidth: 1
                }]
            },
            options: {
                responsive:true,
                animation:false,
                scales:{
                    y:{beginAtZero:true}
                }
            }
        });
    });
}

/* FIRST LOAD */
loadLiveChart();

/* AUTO UPDATE EVERY 3 SECONDS */
setInterval(loadLiveChart, 3000);
</script>

</body>
</html>