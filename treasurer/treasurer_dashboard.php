<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'treasurer') {
    header("Location: ../index.php");
    exit();
}

/* ================= BARANGAY ID ================= */
$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= PRESENT ANNUAL BUDGET (FILTERED) ================= */
$stmt = $conn->prepare("
    SELECT year, total_amount 
    FROM budgets 
    WHERE barangay_id = :barangay_id
    ORDER BY year DESC 
    LIMIT 1
");

$stmt->execute([
    ':barangay_id' => $barangay_id
]);

$current = $stmt->fetch(PDO::FETCH_ASSOC);

$currentYear = $current['year'] ?? 'N/A';
$currentBudget = $current['total_amount'] ?? 0;

/* ================= YEARLY DATA (FILTERED) ================= */
$stmt = $conn->prepare("
    SELECT year, total_amount 
    FROM budgets 
    WHERE barangay_id = :barangay_id
    ORDER BY year ASC
");

$stmt->execute([
    ':barangay_id' => $barangay_id
]);

$years = [];
$amounts = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $years[] = $row['year'];
    $amounts[] = $row['total_amount'];
}

/* ================= ML ANALYSIS ================= */
$trend = "stable";
$mlInsight = "Not enough data for prediction.";
$mlScore = 0;

if (count($amounts) >= 2) {

    $last = $amounts[count($amounts) - 1];
    $prev = $amounts[count($amounts) - 2];

    if ($last > $prev) {
        $trend = "up";
        $mlInsight = "Budget trend is increasing. Strong financial performance detected.";
        $mlScore = 85;
    } elseif ($last < $prev) {
        $trend = "down";
        $mlInsight = "Budget trend is decreasing. Review funding sources.";
        $mlScore = 40;
    } else {
        $trend = "stable";
        $mlInsight = "Budget is stable. Maintain current strategy.";
        $mlScore = 60;
    }
}

/* ================= FORECAST ================= */
$forecast = $currentBudget;

if ($trend == "up") {
    $forecast = $currentBudget * 1.10;
} elseif ($trend == "down") {
    $forecast = $currentBudget * 0.90;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Treasurer Dashboard</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.card {
    padding: 20px;
    text-align: center;
}

.badge {
    display:inline-block;
    padding:5px 10px;
    border-radius:8px;
    color:white;
    font-size:12px;
}

.up { background:green; }
.down { background:red; }
.stable { background:gray; }

@media (max-width: 768px) {
    .grid {
        grid-template-columns: 1fr;
    }
}

.glass {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(500px);
    border-radius: 15px;
    padding: 20px;
}
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>💰 Treasurer Dashboard (ML Enhanced)</h2>
    <p>Financial monitoring with predictive insights</p>
</div>

<!-- KPI CARDS -->
<div class="grid">

    <div class="glass card">
        <h3>📅 Present Year</h3>
        <h2><?= $currentYear ?></h2>
    </div>

    <div class="glass card">
        <h3>💰 Current Budget</h3>
        <h2>₱ <?= number_format($currentBudget) ?></h2>
    </div>

    <div class="glass card">
        <h3>📊 Trend</h3>
        <span class="badge <?= $trend ?>">
            <?= strtoupper($trend) ?>
        </span>
    </div>

</div>

<!-- CHART -->
<div class="glass" style="margin-top:20px;">
    <h3>📊 Budget History</h3>
    <canvas id="chart"></canvas>
</div>

<!-- ML INSIGHT -->
<div class="glass" style="margin-top:20px;">
    <h3>🤖 ML Insight</h3>
    <p><?= htmlspecialchars($mlInsight) ?></p>
    <p><b>ML Score:</b> <?= $mlScore ?>%</p>
</div>

<!-- FORECAST -->
<div class="glass" style="margin-top:20px;">
    <h3>📈 Forecast</h3>
    <p>Projected Next Budget:</p>
    <h2>₱ <?= number_format($forecast) ?></h2>
</div>

</div>

<script>
new Chart(document.getElementById('chart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($years) ?>,
        datasets: [{
            label: 'Budget',
            data: <?= json_encode($amounts) ?>
        }]
    }
});
</script>

</body>
</html>