<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

/* ================= LOAD ML JSON SAFELY ================= */
$mlFile = "../ml/ml_results.json";
$results = [];

if (file_exists($mlFile)) {
    $json = file_get_contents($mlFile);
    $decoded = json_decode($json, true);

    if (is_array($decoded)) {
        $results = $decoded;
    }
}

/* ================= DEFAULT VALUES ================= */
$totalParticipants = 0;
$totalBudget = 0;

$topActivity = "No Data";
$topScore = 0;

/* ================= SAFE PROCESSING ================= */
if (!empty($results)) {

    foreach ($results as $r) {
        $totalParticipants += $r['participants'] ?? 0;
        $totalBudget += $r['budget'] ?? 0;
    }

    $topActivity = $results[0]['title'] ?? "N/A";
    $topScore = $results[0]['predicted_score'] ?? 0;
}

/* ================= NORMALIZE ML SCORE (FIX OVER 100%) ================= */
function normalizeScore($score) {

    // ensure number
    $score = floatval($score);

    // prevent negatives
    if ($score < 0) return 0;

    // HARD CAP 100%
    if ($score > 100) return 100;

    return round($score, 2);
}

$topScore = normalizeScore($topScore);

/* ================= CONCLUSION ================= */
if ($topScore >= 70) {
    $conclusion = "High engagement detected. Strong community participation supports program expansion and budget growth.";
    $impact = "Increase funding allocation for scaling successful activities.";
} elseif ($topScore >= 40) {
    $conclusion = "Moderate engagement detected. Some programs are effective but need improvement in participation.";
    $impact = "Optimize scheduling and improve outreach programs.";
} else {
    $conclusion = "Low engagement detected. Activities need restructuring and stronger community involvement.";
    $impact = "Reevaluate program design and increase engagement strategies.";
}

/* ================= RECOMMENDATIONS ================= */
$suggestions = [];

$suggestions[] = "Focus on high-performing activities like $topActivity.";
$suggestions[] = "Improve participation through community outreach.";
$suggestions[] = "Schedule events during weekends or holidays.";
$suggestions[] = "Use ML insights for annual SK planning decisions.";

/* ================= REALISTIC BUDGET FORECAST ================= */
$growthRate = $topScore / 100;

// SAFE multiplier (prevents insane budget jumps)
$projectedIncrease = $totalBudget * ($growthRate * 0.25);

$futureBudget = $totalBudget + $projectedIncrease;
?>

<!DOCTYPE html>
<html>
<head>
<title>Chairperson Prediction</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.main { margin-left:220px; padding:20px; }

.glass {
    padding:20px;
    margin-top:20px;
    background:white;
    border-radius:10px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
}

table {
    width:100%;
    border-collapse:collapse;
    background:white;
}

th {
    background:#0d6efd;
    color:white;
    padding:10px;
}

td {
    padding:10px;
    border-bottom:1px solid #ddd;
}

tr:hover {
    background:#f5f5f5;
}
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>🤖 ML Prediction Dashboard</h2>
</div>

<!-- TOP ACTIVITY -->
<div class="glass">
    <h3>🏆 Top Activity</h3>
    <p><b><?= htmlspecialchars($topActivity) ?></b> — <?= $topScore ?>%</p>
</div>

<!-- CONCLUSION -->
<div class="glass">
    <h3>📌 AI Conclusion</h3>
    <p><?= $conclusion ?></p>
    <hr>
    <p><b>📊 Recommendation Impact:</b> <?= $impact ?></p>
</div>

<!-- BUDGET FORECAST -->
<div class="glass">
    <h3>💰 Budget Forecast</h3>
    <p>Current Budget: ₱ <?= number_format($totalBudget, 2) ?></p>
    <p>Projected Increase: ₱ <?= number_format($projectedIncrease, 2) ?></p>
    <h3>Future Budget: ₱ <?= number_format($futureBudget, 2) ?></h3>
</div>

<!-- RECOMMENDATIONS -->
<div class="glass">
    <h3>💡 ML Recommendations</h3>
    <ul>
        <?php foreach ($suggestions as $s) { ?>
            <li><?= htmlspecialchars($s) ?></li>
        <?php } ?>
    </ul>
</div>

<!-- TABLE -->
<div class="glass">
    <h3>📊 ML Results Table</h3>

    <table>
        <tr>
            <th>Activity</th>
            <th>Participants</th>
            <th>Budget</th>
            <th>Score</th>
        </tr>

        <?php if (!empty($results)) { ?>
            <?php foreach ($results as $r) { ?>
            <tr>
                <td><?= htmlspecialchars($r['title'] ?? 'N/A') ?></td>
                <td><?= $r['participants'] ?? 0 ?></td>
                <td>₱ <?= number_format($r['budget'] ?? 0, 2) ?></td>
                <td><?= normalizeScore($r['predicted_score'] ?? 0) ?>%</td>
            </tr>
            <?php } ?>
        <?php } else { ?>
            <tr>
                <td colspan="4" style="text-align:center;">
                    No ML data available. Run model first.
                </td>
            </tr>
        <?php } ?>
    </table>
</div>

</div>

</body>
</html>