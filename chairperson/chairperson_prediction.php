<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

/* ================= BARANGAY ID ================= */
$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= LOAD ML JSON (FILTERED PER BARANGAY) ================= */
$mlFile = "../ml/ml_results.json";
$results = [];

if (file_exists($mlFile)) {
    $json = file_get_contents($mlFile);
    $decoded = json_decode($json, true);

    if (is_array($decoded)) {
        foreach ($decoded as $row) {

            // FILTER BY BARANGAY
            if (($row['barangay_id'] ?? null) == $barangay_id) {
                $results[] = $row;
            }
        }
    }
}

/* ================= DEFAULT VALUES ================= */
$totalParticipants = 0;
$totalBudget = 0;
$topActivity = "No Data";
$topScore = 0;

/* ================= PROCESS ML DATA ================= */
if (!empty($results)) {

    foreach ($results as $r) {
        $totalParticipants += (int)($r['participants'] ?? 0);
        $totalBudget += (float)($r['budget'] ?? 0);
    }

    $topActivity = $results[0]['title'] ?? "N/A";
    $topScore = $results[0]['predicted_score'] ?? 0;
}

/* ================= SCORE NORMALIZATION ================= */
function normalizeScore($score) {
    $score = floatval($score);

    if ($score < 0) return 0;
    if ($score > 100) return 100;

    return round($score, 2);
}

$topScore = normalizeScore($topScore);

/* ================= BUDGET (FILTERED BY BARANGAY) ================= */
$stmt = $conn->prepare("
    SELECT total_amount 
    FROM budgets 
    WHERE barangay_id = :barangay_id 
    ORDER BY id DESC 
    LIMIT 1
");

$stmt->execute([
    ':barangay_id' => $barangay_id
]);

$budgetData = $stmt->fetch(PDO::FETCH_ASSOC);
$totalBudget = $budgetData['total_amount'] ?? 0;

/* ================= CONCLUSION ================= */
if ($topScore >= 70) {
    $conclusion = "High engagement detected. Strong community participation supports program expansion.";
    $impact = "Increase funding allocation for successful activities.";
} elseif ($topScore >= 40) {
    $conclusion = "Moderate engagement detected. Some programs are effective but need improvement.";
    $impact = "Improve outreach and participation strategies.";
} else {
    $conclusion = "Low engagement detected. Activities need restructuring.";
    $impact = "Redesign programs and increase community engagement.";
}

/* ================= RECOMMENDATIONS ================= */
$suggestions = [];

$suggestions[] = "Focus on high-performing activities like $topActivity.";
$suggestions[] = "Improve participation through community outreach.";
$suggestions[] = "Schedule activities during peak attendance times.";
$suggestions[] = "Use ML insights for better SK planning decisions.";

/* ================= BUDGET FORECAST ================= */
$growthRate = $topScore / 100;
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
.main{
    margin-left:190px;
    padding:20px;
    width:calc(100% - 200px);
    overflow-x:hidden;
}

.glass {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(500px);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
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
    <p><?= htmlspecialchars($conclusion) ?></p>
    <hr>
    <p><b>📊 Recommendation Impact:</b> <?= htmlspecialchars($impact) ?></p>
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
                <td><?= (int)($r['participants'] ?? 0) ?></td>
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