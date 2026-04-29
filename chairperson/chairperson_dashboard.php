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

/* ================= INITIAL VALUES ================= */
$totalParticipants = 0;
$totalProjects = count($activities);
$budgetUsed = 0;

$labels = [];
$data = [];

/* ================= TOP / LOWEST ================= */
$topActivity = "N/A";
$topActivityParticipants = 0;

$lowestActivity = "N/A";
$lowestActivityParticipants = PHP_INT_MAX;

/* ================= LOOP ================= */
foreach ($activities as $a) {

    $labels[] = $a['title'];
    $data[] = $a['participants'];

    $totalParticipants += $a['participants'];
    $budgetUsed += $a['allocated_budget'];

    // TOP
    if ($a['participants'] > $topActivityParticipants) {
        $topActivityParticipants = $a['participants'];
        $topActivity = $a['title'];
    }

    // LOWEST
    if ($a['participants'] < $lowestActivityParticipants) {
        $lowestActivityParticipants = $a['participants'];
        $lowestActivity = $a['title'];
    }
}

if ($lowestActivityParticipants == PHP_INT_MAX) {
    $lowestActivityParticipants = 0;
}

/* ================= REMAINING ================= */
$remainingBudget = $totalBudget - $budgetUsed;

/* ================= ML SCORE ================= */
$efficiency = ($budgetUsed > 0) ? ($totalParticipants / $budgetUsed) : 0;
$budgetRatio = ($totalBudget > 0) ? ($budgetUsed / $totalBudget) : 0;

$mlScore = min(100, round(
    ($efficiency * 50) +
    ($budgetRatio * 30) +
    ($totalProjects * 5)
, 2));

/* ================= ML CONCLUSION ================= */
if ($mlScore >= 70) {

    $budgetIncreaseRate = 0.20;
    $recommendation = "High performance detected. Increase annual budget for expansion.";
    $nextSteps = "Scale successful programs and expand youth development projects.";
    $priorityAction = "Focus on sports, leadership, and livelihood programs.";

} elseif ($mlScore >= 40) {

    $budgetIncreaseRate = 0.10;
    $recommendation = "Moderate performance. Slight budget increase recommended.";
    $nextSteps = "Improve engagement and strengthen participation.";
    $priorityAction = "Enhance awareness campaigns and structured training.";

} else {

    $budgetIncreaseRate = 0.00;
    $recommendation = "Low performance detected. Budget increase NOT recommended.";
    $nextSteps = "Revise programs before increasing funding.";
    $priorityAction = "Conduct consultation and redesign activities.";
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
.main{margin-left:220px;padding:20px}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px}
.card{text-align:center;padding:20px}
.glass{padding:20px;margin-top:20px;background:white;border-radius:10px}
table{width:100%;border-collapse:collapse;background:white}
th{background:#0d6efd;color:white;padding:10px}
td{padding:10px;border-bottom:1px solid #ddd;text-align:center}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>🤖 Chairman AI Dashboard</h2>
</div>

<!-- KPI GRID -->
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
        <small><?= $topActivityParticipants ?> participants</small>
    </div>

    <div class="glass card">
        <h3>⚠ Lowest Activity</h3>
        <h2><?= htmlspecialchars($lowestActivity) ?></h2>
        <small><?= $lowestActivityParticipants ?> participants</small>
    </div>

</div>

<!-- CHART -->
<div class="glass">
    <h3>📊 Activity Participation</h3>
    <canvas id="chart"></canvas>
</div>

<!-- ML INSIGHT -->
<div class="glass">
    <h3>🤖 ML Insight</h3>
    <p><?= $recommendation ?></p>
</div>

<!-- ML CONCLUSION (NEW) -->
<div class="glass">
    <h3>🧠 Machine Learning Conclusion</h3>

    <p><b>📌 Recommendation:</b><br>
        <?= $recommendation ?>
    </p>

    <p><b>🚀 Next Steps:</b><br>
        <?= $nextSteps ?>
    </p>

    <p><b>⚠ Priority Action:</b><br>
        <?= $priorityAction ?>
    </p>

    <hr>

    <p><b>📈 Suggested Budget Increase:</b>
        <?= ($budgetIncreaseRate * 100) ?>%
    </p>

    <p><b>💰 Next Year Budget Estimate:</b>
        ₱ <?= number_format($nextYearBudget,2) ?>
    </p>
</div>

</div>

<!-- CHART SCRIPT -->
<script>
new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Participants',
            data: <?= json_encode($data) ?>
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

</body>
</html>