<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'treasurer') {
    header("Location: ../index.php");
    exit();
}

/* ================= BARANGAY ID ================= */
$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= BUDGET DATA (FILTERED) ================= */
$stmt = $conn->prepare("
    SELECT * 
    FROM budgets 
    WHERE barangay_id = :barangay_id 
    ORDER BY year DESC
");

$stmt->execute([
    ':barangay_id' => $barangay_id
]);

$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ACTIVITY DATA (FILTERED) ================= */
$stmt = $conn->prepare("
    SELECT title, participants 
    FROM activities 
    WHERE barangay_id = :barangay_id 
    ORDER BY participants DESC
");

$stmt->execute([
    ':barangay_id' => $barangay_id
]);

$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ML CALCULATIONS ================= */

$totalBudget = 0;
$years = [];
$amounts = [];

foreach ($budgets as $b) {
    $totalBudget += (int)$b['total_amount'];
    $years[] = $b['year'];
    $amounts[] = (int)$b['total_amount'];
}

$totalParticipants = 0;

foreach ($activities as $a) {
    $totalParticipants += (int)$a['participants'];
}

/* ================= TOP ACTIVITY ================= */
$topActivity = $activities[0]['title'] ?? 'N/A';
$topParticipants = $activities[0]['participants'] ?? 0;

/* ================= ML TREND ================= */
$trend = "stable";
$mlInsight = "Insufficient data for ML analysis.";
$recommendation = [];

if (count($amounts) >= 2) {

    $last = $amounts[0];
    $prev = $amounts[1];

    if ($last > $prev) {
        $trend = "increasing";
        $mlInsight = "Budget is increasing. Financial capacity is improving.";
    } elseif ($last < $prev) {
        $trend = "decreasing";
        $mlInsight = "Budget is decreasing. Review funding allocation.";
    } else {
        $trend = "stable";
        $mlInsight = "Budget is stable across recent years.";
    }
}

/* ================= RECOMMENDATION ENGINE ================= */

if ($totalParticipants > 200) {
    $recommendation[] = "High community engagement detected. Expand successful programs.";
} elseif ($totalParticipants > 100) {
    $recommendation[] = "Moderate engagement detected. Improve promotion strategies.";
} else {
    $recommendation[] = "Low engagement detected. Increase awareness campaigns.";
}

if ($topParticipants > 50) {
    $recommendation[] = "Focus on scaling top activity: $topActivity";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Treasurer Reports</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

th {
    background: #ff9800;
    color: white;
    padding: 10px;
}

td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}

tr:hover {
    background: #f5f5f5;
}

.glass {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(500px);
    border-radius: 15px;
    padding: 20px;
}

@media (max-width: 768px) {
    table {
        display: block;
        overflow-x: auto;
        white-space: nowrap;
    }
}
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>📊 Treasurer Reports (ML Enhanced)</h2>
    <p>Financial + Activity Intelligence System</p>
</div>

<!-- BUDGET REPORT -->
<div class="glass">

    <h3>💰 Budget Records</h3>

    <table>
        <tr>
            <th>ID</th>
            <th>Amount</th>
            <th>Year</th>
        </tr>

        <?php if (!empty($budgets)) { ?>
            <?php foreach ($budgets as $row) { ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td>₱ <?= number_format($row['total_amount']) ?></td>
                <td><?= $row['year'] ?></td>
            </tr>
            <?php } ?>
        <?php } else { ?>
            <tr>
                <td colspan="3" style="text-align:center;">No budget data found</td>
            </tr>
        <?php } ?>

    </table>

</div>

<!-- ACTIVITY REPORT -->
<div class="glass">

    <h3>📌 Activity Participation</h3>

    <table>
        <tr>
            <th>Activity</th>
            <th>Participants</th>
        </tr>

        <?php if (!empty($activities)) { ?>
            <?php foreach ($activities as $row) { ?>
            <tr>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= (int)$row['participants'] ?></td>
            </tr>
            <?php } ?>
        <?php } else { ?>
            <tr>
                <td colspan="2" style="text-align:center;">No activity data found</td>
            </tr>
        <?php } ?>

    </table>

</div>

<!-- ML INSIGHT -->
<div class="glass">

    <h3>🤖 ML Insight</h3>

    <p><b>Budget Trend:</b> <?= strtoupper($trend) ?></p>
    <p><?= htmlspecialchars($mlInsight) ?></p>

</div>

<!-- AI RECOMMENDATIONS -->
<div class="glass">

    <h3>💡 AI Recommendations</h3>

    <ul>
        <?php if (!empty($recommendation)) { ?>
            <?php foreach ($recommendation as $r) { ?>
                <li><?= htmlspecialchars($r) ?></li>
            <?php } ?>
        <?php } else { ?>
            <li>No recommendations available</li>
        <?php } ?>
    </ul>

</div>

</div>

</body>
</html>