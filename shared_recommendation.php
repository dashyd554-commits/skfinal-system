<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= APPROVAL RATE ================= */
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) FILTER (WHERE status='approved') as approved,
        COUNT(*) FILTER (WHERE status='rejected') as rejected
    FROM projects
    WHERE barangay_id = :bid
");
$stmt->execute([':bid'=>$barangay_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

$total = $data['approved'] + $data['rejected'];
$approval_rate = ($total > 0) ? ($data['approved'] / $total) * 100 : 0;

/* ================= BUDGET EFFICIENCY ================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(annual_budget),0) FROM budgets
    WHERE barangay_id = :bid
");
$stmt->execute([':bid'=>$barangay_id]);
$budget = $stmt->fetchColumn();

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(budget_used),0) FROM budgets
    WHERE barangay_id = :bid
");
$stmt->execute([':bid'=>$barangay_id]);
$used = $stmt->fetchColumn();

$efficiency = ($budget > 0) ? ($used / $budget) * 100 : 0;

/* ================= RECOMMENDATION ENGINE ================= */
$recommendations = [];

if ($approval_rate >= 70) {
    $recommendations[] = "📈 High approval rate: Barangay shows strong governance efficiency.";
}

if ($efficiency >= 80) {
    $recommendations[] = "⚠ High budget usage: Consider increasing annual allocation.";
} elseif ($efficiency >= 50) {
    $recommendations[] = "📊 Balanced budget usage detected.";
} else {
    $recommendations[] = "💰 Low budget usage: Opportunity for more development projects.";
}

/* ================= PROJECT TYPE INSIGHT ================= */
$stmt = $conn->prepare("
    SELECT name, COUNT(*) as total
    FROM projects
    WHERE barangay_id = :bid
    GROUP BY name
    ORDER BY total DESC
    LIMIT 1
");
$stmt->execute([':bid'=>$barangay_id]);
$top = $stmt->fetch(PDO::FETCH_ASSOC);

if ($top) {
    $recommendations[] = "🔥 '{$top['name']}' projects show highest success frequency.";
}

/* ================= FINAL AI SUGGESTION ================= */
if ($approval_rate >= 75 && $efficiency >= 60) {
    $recommendations[] = "🚀 Suggested annual budget increase: 10% for expansion.";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Shared AI Recommendation</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.card {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(500px);
    padding: 20px;
    border-radius: 15px;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>🤖 Shared AI Recommendation Engine</h2>

<div class="card">

<h3>📊 System Analysis</h3>

<p>Approval Rate: <?= round($approval_rate,2) ?>%</p>
<p>Budget Efficiency: <?= round($efficiency,2) ?>%</p>

<hr>

<h3>💡 AI Recommendations</h3>

<?php foreach($recommendations as $r){ ?>
    <p>• <?= $r ?></p>
<?php } ?>

</div>

</div>

</body>
</html>