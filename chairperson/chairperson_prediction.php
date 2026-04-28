<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

/* ==================== READ PYTHON ML JSON SAFELY ==================== */
$mlFile = "../ml/ml_results.json";
$results = [];

if (file_exists($mlFile)) {

    $json = file_get_contents($mlFile);
    $decoded = json_decode($json, true);

    // IMPORTANT FIX: ensure it's array of results
    if (is_array($decoded)) {
        $results = $decoded;
    }
}

/* ==================== DEFAULT VALUES (PREVENT CRASH) ==================== */
$totalActivities = count($results);
$totalParticipants = 0;

$topActivity = "No Data";
$topScore = 0;
$currentBudget = 0;

/* ==================== PROCESS DATA SAFELY ==================== */
if (!empty($results)) {

    foreach ($results as $r) {
        $totalParticipants += $r['participants'] ?? 0;
    }

    // top activity (already sorted from Python, but still safe)
    $topActivity = $results[0]['title'] ?? 'N/A';
    $topScore = $results[0]['predicted_score'] ?? 0;
    $currentBudget = $results[0]['budget'] ?? 0;
}

/* ==================== CONCLUSION ==================== */
if ($topScore >= 70) {
    $conclusion = "Machine Learning shows HIGH engagement. Strong participation supports budget growth and expansion of SK programs.";
} elseif ($topScore >= 40) {
    $conclusion = "Machine Learning shows MODERATE engagement. Some programs are effective but still need improvement.";
} else {
    $conclusion = "Machine Learning shows LOW engagement. Current activities need redesign to improve participation.";
}

/* ==================== SUGGESTIONS ==================== */
$suggestions = [];

if ($topActivity != 'No Data') {
    $suggestions[] = "Focus on the top performing activity: " . $topActivity;
}

if ($topScore < 40) {
    $suggestions[] = "Improve promotion using barangay outreach and social media campaigns.";
}

$suggestions[] = "Prioritize activities with higher participant turnout.";
$suggestions[] = "Schedule events during weekends or holidays for better attendance.";
$suggestions[] = "Use ML results as basis for SK annual planning decisions.";

/* ==================== BUDGET FORECAST ==================== */
$projectedIncrease = ($topScore / 100) * 15000;
$futureBudget = $currentBudget + $projectedIncrease;
?>

<!DOCTYPE html>
<html>
<head>
<title>Prediction</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.main{
    margin-left:220px;
    padding:20px;
}

table{
    width:100%;
    border-collapse:collapse;
    background:white;
}

th{
    background:#dc3545;
    color:white;
    padding:10px;
}

td{
    padding:10px;
    border-bottom:1px solid #ddd;
}

tr:hover{
    background:#f5f5f5;
}

.glass{
    padding:20px;
    margin-top:20px;
}

@media(max-width:768px){
    .main{ margin-left:70px; }
}
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>🤖 Machine Learning Prediction Results</h2>
    <p>AI-based SK activity forecasting</p>
</div>

<!-- TOP ACTIVITY -->
<div class="glass">
    <h3>🏆 Top Predicted Activity</h3>
    <p><b><?= htmlspecialchars($topActivity) ?></b> — <?= $topScore ?>%</p>
</div>

<!-- FORECAST -->
<div class="glass">
    <h3>💰 Budget Forecast</h3>
    <p>Base Budget: ₱ <?= number_format($currentBudget) ?></p>
    <p>Predicted Increase: ₱ <?= number_format($projectedIncrease) ?></p>
    <p><b>Future Budget: ₱ <?= number_format($futureBudget) ?></b></p>
</div>

<!-- CONCLUSION -->
<div class="glass">
    <h3>📌 Conclusion</h3>
    <p><?= $conclusion ?></p>
</div>

<!-- RECOMMENDATIONS -->
<div class="glass">
    <h3>💡 Recommendations</h3>
    <ul>
        <?php foreach ($suggestions as $s) { ?>
            <li><?= htmlspecialchars($s) ?></li>
        <?php } ?>
    </ul>
</div>

<!-- TABLE -->
<div class="glass">
    <h3>📊 ML Results</h3>

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
                <td>₱ <?= number_format($r['budget'] ?? 0) ?></td>
                <td><?= $r['predicted_score'] ?? 0 ?>%</td>
            </tr>
            <?php } ?>
        <?php } else { ?>
            <tr>
                <td colspan="4" style="text-align:center;">
                    No ML results found. Run Python model first.
                </td>
            </tr>
        <?php } ?>

    </table>

</div>

</div>

</body>
</html>