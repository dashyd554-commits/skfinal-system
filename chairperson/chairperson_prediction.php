<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= LOAD ML JSON ================= */
$mlFile = "../ml/ml_results.json";
$results = [];

if (file_exists($mlFile)) {
    $json = file_get_contents($mlFile);
    $decoded = json_decode($json, true);

    if (is_array($decoded)) {
        foreach ($decoded as $row) {
            if (($row['barangay_id'] ?? 0) == $barangay_id) {
                $results[] = $row;
            }
        }
    }
}

/* ================= DEFAULT VALUES ================= */
$topScore = 0;
$topActivity = "No Data";
$totalParticipants = 0;

function normalizeScore($score){
    $score = floatval($score);
    return max(0, min(100, round($score,2)));
}

/* ================= PROCESS DATA ================= */
if (!empty($results)) {

    usort($results, fn($a,$b) =>
        ($b['predicted_score'] ?? 0) <=> ($a['predicted_score'] ?? 0)
    );

    foreach ($results as $r) {
        $totalParticipants += (int)($r['participants'] ?? 0);
    }

    $topActivity = $results[0]['title'] ?? "N/A";
    $topScore = normalizeScore($results[0]['predicted_score'] ?? 0);
}

/* ================= BUDGET ================= */
$stmt = $conn->prepare("
    SELECT total_amount, used_amount
    FROM budgets
    WHERE barangay_id = :bid
    ORDER BY year DESC
    LIMIT 1
");
$stmt->execute([':bid'=>$barangay_id]);
$budgetData = $stmt->fetch(PDO::FETCH_ASSOC);

$annualBudget = $budgetData['total_amount'] ?? 0;
$usedBudget = $budgetData['used_amount'] ?? 0;
$remainingBudget = $annualBudget - $usedBudget;

$growthRate = $topScore / 100;
$projectedIncrease = $remainingBudget * ($growthRate * 0.25);
$futureBudget = $remainingBudget + $projectedIncrease;
?>

<!DOCTYPE html>
<html>
<head>
<title>Chairperson ML Prediction</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    overflow-x:hidden;
}

.main{
    margin-left:190px;
    padding:20px;
    width:calc(100% - 200px);
}

.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:15px;
}

.glass{
    background:rgba(255,255,255,0.20);
    backdrop-filter:blur(15px);
    border-radius:15px;
    padding:20px;
    color:white;
    margin-bottom:15px;
}

.card{text-align:center;}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#1e3c72;
    color:white;
    padding:10px;
}

td{
    padding:8px;
    text-align:center;
}

@media(max-width:768px){
    .main{
        margin-left:70px;
        width:calc(100% - 80px);
    }
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>🤖 ML Dashboard</h2>

<!-- KPI -->
<div class="grid">

<div class="glass card">
<h3>Budget</h3>
<h2>₱<?= number_format($annualBudget,2) ?></h2>
</div>

<div class="glass card">
<h3>Used</h3>
<h2>₱<?= number_format($usedBudget,2) ?></h2>
</div>

<div class="glass card">
<h3>Remaining</h3>
<h2>₱<?= number_format($remainingBudget,2) ?></h2>
</div>

<div class="glass card">
<h3>Top Score</h3>
<h2><?= $topScore ?>%</h2>
</div>

</div>

<!-- TOP -->
<div class="glass">
<h3>Top Activity</h3>
<p><?= htmlspecialchars($topActivity) ?></p>
</div>

<!-- TABLE -->
<div class="glass">
<h3>ML Results</h3>

<?php if(!empty($results)) { ?>
<table>
<tr>
<th>Activity</th>
<th>Participants</th>
<th>Budget</th>
<th>Score</th>
</tr>

<?php foreach($results as $r){ ?>
<tr>
<td><?= htmlspecialchars($r['title']) ?></td>
<td><?= $r['participants'] ?></td>
<td>₱<?= number_format($r['budget'],2) ?></td>
<td><?= normalizeScore($r['predicted_score']) ?>%</td>
</tr>
<?php } ?>

</table>
<?php } else { ?>
<p>⚠ No ML data available. Run Flask API first: /predict</p>
<?php } ?>

</div>

</div>

</body>
</html>