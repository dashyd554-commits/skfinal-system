<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

/* ==================== PRESENT BUDGET ==================== */
$stmt = $conn->prepare("SELECT amount FROM budgets ORDER BY id DESC LIMIT 1");
$stmt->execute();
$budgetData = $stmt->fetch(PDO::FETCH_ASSOC);
$totalBudget = $budgetData['amount'] ?? 0;

/* ==================== ACTIVITIES ==================== */
$stmt = $conn->prepare("SELECT COUNT(*) AS total_projects FROM activities");
$stmt->execute();
$totalProjects = $stmt->fetch(PDO::FETCH_ASSOC)['total_projects'] ?? 0;

$stmt = $conn->prepare("SELECT COALESCE(SUM(participants),0) AS total_participants FROM activities");
$stmt->execute();
$totalParticipants = $stmt->fetch(PDO::FETCH_ASSOC)['total_participants'] ?? 0;

$stmt = $conn->prepare("SELECT title, participants FROM activities");
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$data = [];

foreach ($activities as $a) {
    $labels[] = $a['title'];
    $data[] = $a['participants'];
}

/* ==================== SAFE ML LOAD ==================== */
$mlFile = "../ml/ml_results.json";
$mlData = [];

if (file_exists($mlFile)) {
    $json = file_get_contents($mlFile);
    $decoded = json_decode($json, true);

    if (is_array($decoded)) {
        $mlData = $decoded;
    }
}

/* ==================== CLEAN ML ==================== */
$cleanML = [];

foreach ($mlData as $item) {
    if (is_array($item)) {
        $cleanML[] = [
            'title' => $item['title'] ?? 'Unknown',
            'participants' => $item['participants'] ?? 0,
            'score' => $item['predicted_score'] ?? 0
        ];
    }
}

usort($cleanML, fn($a, $b) => $b['score'] <=> $a['score']);

$topActivity = $cleanML[0]['title'] ?? "No ML Data";
$topScore = $cleanML[0]['score'] ?? 0;

/* ==================== FORECAST ==================== */
$predictedIncrease = ($topScore / 100) * ($totalBudget * 0.30);
$futureBudget = $totalBudget + $predictedIncrease;

/* ==================== AI INSIGHT ==================== */
if ($topScore >= 70) {
    $mlTip = "High engagement detected. Strong community participation supports sustainable budget growth.";
    $conclusion = "The system indicates a HIGH-PERFORMANCE community. Current programs are effective and can be expanded.";
    $nextActivity = "Inter-Barangay Sports Festival / Youth Leadership Summit";
} elseif ($topScore >= 40) {
    $mlTip = "Moderate engagement detected. Some activities perform well but need improvement.";
    $conclusion = "The system shows MODERATE engagement. Growth is possible with better program targeting.";
    $nextActivity = "Community Clean-Up Drive + Environmental Awareness Campaign";
} else {
    $mlTip = "Low engagement detected. Participation needs improvement.";
    $conclusion = "The system indicates LOW ENGAGEMENT. Immediate intervention is required.";
    $nextActivity = "Youth Motivation Program + Skills Training Workshop";
}

$budgetImpact = ($totalParticipants > 0)
    ? round(($totalParticipants / 10) * 50)
    : 0;
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
td{padding:10px;border-bottom:1px solid #ddd}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>🤖 AI Chairman Dashboard</h2>
</div>

<!-- KPI -->
<div class="grid">

    <div class="glass card">
        <h3>💰 Budget</h3>
        <h2>₱ <?= number_format($totalBudget) ?></h2>
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
        <h3>🏆 Top Activity</h3>
        <h2><?= htmlspecialchars($topActivity) ?></h2>
        <small><?= $topScore ?>%</small>
    </div>

</div>

<!-- CHART -->
<div class="glass">
    <h3>📊 Activity Participation</h3>
    <canvas id="chart"></canvas>
</div>

<!-- ML RESULTS -->
<div class="glass">
    <h3>🤖 ML Results</h3>

    <table>
        <tr>
            <th>Activity</th>
            <th>Participants</th>
            <th>Score</th>
        </tr>

        <?php foreach (array_slice($cleanML, 0, 5) as $r) { ?>
        <tr>
            <td><?= htmlspecialchars($r['title']) ?></td>
            <td><?= $r['participants'] ?></td>
            <td><?= $r['score'] ?>%</td>
        </tr>
        <?php } ?>
    </table>
</div>

<!-- FORECAST -->
<div class="glass">
    <h3>💰 Budget Forecast</h3>
    <p>Present Budget: ₱ <?= number_format($totalBudget) ?></p>
    <p>Predicted Growth: ₱ <?= number_format($predictedIncrease) ?></p>
    <p>Future Budget Estimate: ₱ <?= number_format($futureBudget) ?></p>
</div>

<!-- INSIGHT -->
<div class="glass">
    <h3>🤖 ML Insight</h3>
    <p><?= $mlTip ?></p>
</div>

<!-- FINAL CONCLUSION -->
<div class="glass">
    <h3>📌 AI CONCLUSION & NEXT ACTIVITY SUGGESTION</h3>

    <p><b>Conclusion:</b></p>
    <p><?= $conclusion ?></p>

    <hr>

    <p><b>🚀 Recommended Next Activity:</b></p>
    <h3 style="color:#0d6efd;"><?= $nextActivity ?></h3>

    <hr>

    <p><b>💰 Estimated Budget Impact:</b> ₱ <?= number_format($budgetImpact) ?></p>
</div>

</div>

<script>
new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Participants',
            data: <?= json_encode($data) ?>
        }]
    }
});
</script>

</body>
</html>