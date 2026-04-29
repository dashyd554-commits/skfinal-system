<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

/* ================= SAFE ML LOAD ================= */
$mlFile = "../ml/ml_results.json";
$mlData = [];

if (file_exists($mlFile)) {
    $json = file_get_contents($mlFile);
    $decoded = json_decode($json, true);

    if (is_array($decoded)) {
        $mlData = $decoded;
    }
}

/* ================= DEFAULT VALUES ================= */
$totalParticipants = 0;
$topActivity = "N/A";
$topScore = 0;
$averageScore = 0;
$ranked = [];

/* ================= PROCESS ML DATA (SAFE + NORMALIZED) ================= */
if (!empty($mlData)) {

    $sumScore = 0;

    foreach ($mlData as $row) {

        $participants = (int)($row['participants'] ?? 0);
        $score = (float)($row['predicted_score'] ?? 0);

        // FORCE SCORE LIMIT (0–100)
        $score = max(0, min(100, $score));

        $totalParticipants += $participants;
        $sumScore += $score;

        $ranked[] = [
            "title" => $row['title'] ?? "Unknown",
            "participants" => $participants,
            "score" => $score
        ];
    }

    // sort by score DESC
    usort($ranked, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $topActivity = $ranked[0]['title'] ?? "N/A";
    $topScore = $ranked[0]['score'] ?? 0;

    $averageScore = count($ranked) > 0 ? ($sumScore / count($ranked)) : 0;
}

/* ================= FIXED BUDGET QUERY ================= */
/* IMPORTANT: your column is total_amount not amount */
$stmt = $conn->prepare("SELECT total_amount FROM budgets ORDER BY id DESC LIMIT 1");
$stmt->execute();
$budgetData = $stmt->fetch(PDO::FETCH_ASSOC);

$totalBudget = $budgetData['total_amount'] ?? 0;

/* ================= SAFE FORECAST ================= */
$predictedIncrease = 0;
$futureBudget = $totalBudget;

if ($topScore > 0) {
    $predictedIncrease = ($topScore / 100) * ($totalBudget * 0.3);
    $futureBudget = $totalBudget + $predictedIncrease;
}

/* ================= CONCLUSION ================= */
$conclusion = [];

if (empty($mlData)) {

    $conclusion[] = "No ML data available yet. Run the prediction model first.";
    $conclusion[] = "System is waiting for activity performance data.";

} elseif ($topScore >= 70) {

    $conclusion[] = "High engagement detected in '{$topActivity}'.";
    $conclusion[] = "Recommendation: Expand successful programs and increase budget allocation.";
    $conclusion[] = "Next Step: Scale sports, leadership, and youth outreach programs.";

} elseif ($topScore >= 40) {

    $conclusion[] = "Moderate engagement detected.";
    $conclusion[] = "Recommendation: Improve promotion and participation strategies.";
    $conclusion[] = "Next Step: Combine training + sports-based engagement.";

} else {

    $conclusion[] = "Low engagement detected.";
    $conclusion[] = "Recommendation: Redesign programs to increase participation.";
    $conclusion[] = "Next Step: Community outreach and youth motivation programs.";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Chairman Recommendation</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.main {
    margin-left:220px;
    padding:20px;
}

.card {
    padding:20px;
}

.box {
    padding:10px;
    margin:10px 0;
    background:rgba(255,255,255,0.6);
    border-radius:8px;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>🤖 Chairman AI Recommendation</h2>
</div>

<!-- INSIGHT -->
<div class="glass card">
    <h3>📊 ML System Insight</h3>
    <p>Total Participants: <b><?= $totalParticipants ?></b></p>
    <p>Top Activity: <b><?= htmlspecialchars($topActivity) ?></b></p>
    <p>Top Score: <b><?= round($topScore, 2) ?>%</b></p>
    <p>Average Score: <b><?= round($averageScore, 2) ?>%</b></p>
</div>

<!-- CONCLUSION -->
<div class="glass card" style="margin-top:20px;">
    <h3>📌 AI Conclusion</h3>

    <?php foreach ($conclusion as $c) { ?>
        <div class="box">
            <?= htmlspecialchars($c) ?>
        </div>
    <?php } ?>
</div>

<!-- FORECAST -->
<div class="glass card" style="margin-top:20px;">
    <h3>💰 Budget Forecast</h3>
    <p>Present Budget: ₱ <?= number_format($totalBudget) ?></p>
    <p>Predicted Growth: ₱ <?= number_format($predictedIncrease) ?></p>
    <p><b>Future Budget: ₱ <?= number_format($futureBudget) ?></b></p>
</div>

</div>

</body>
</html>