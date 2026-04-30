<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= TOP PROJECT TYPES ================= */
$stmt = $conn->prepare("
    SELECT 
        name,
        COUNT(*) AS total,
        SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved
    FROM projects
    WHERE barangay_id = :barangay_id
    GROUP BY name
    ORDER BY approved DESC
");
$stmt->execute([':barangay_id' => $barangay_id]);
$types = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= BUDGET USAGE ================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(bt.amount),0) AS total_used
    FROM budget_transactions bt
    WHERE bt.barangay_id = :barangay_id
");
$stmt->execute([':barangay_id' => $barangay_id]);
$budget = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= TOTAL BUDGET ================= */
$stmt = $conn->prepare("
    SELECT total_amount
    FROM budgets
    WHERE barangay_id = :barangay_id
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([':barangay_id' => $barangay_id]);
$totalBudget = $stmt->fetchColumn() ?: 0;

$used = $budget['total_used'] ?? 0;
$ratio = ($totalBudget > 0) ? ($used / $totalBudget) * 100 : 0;

/* ================= AI LOGIC ================= */
if ($ratio >= 80) {
    $insight = "High budget utilization. Barangay is highly active.";
    $recommendation = "Maintain funding level or optimize spending.";
} elseif ($ratio >= 40) {
    $insight = "Moderate budget usage.";
    $recommendation = "Slight budget increase recommended (5–10%).";
} else {
    $insight = "Low budget utilization.";
    $recommendation = "Improve project execution before increasing budget.";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Barangay AI Recommendation</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
    h3{
    margin-left:190px;   /* moved dashboard 30px to left */
    padding:20px;
    width:calc(100% - 200px);
    overflow-x:hidden;
    background:rgba(255,255,255,0.55);
    backdrop-filter:blur(500px);
    }
    p{
        margin-left:190px;   /* moved dashboard 30px to left */
    padding:20px;
    width:calc(100% - 200px);
    overflow-x:hidden;
    background:rgba(255,255,255,0.55);
    backdrop-filter:blur(500px);
    }
</style>

</head>
<body>

<?php include '../assets/sidebar.php'; ?>

<div style="margin-left:200px;padding:20px; ">

<h2>🤖 AI Recommendation (Per Barangay)</h2>

<h3>💰 Budget Analysis</h3>
<p>Total Budget: ₱<?= number_format($totalBudget,2) ?></p>
<p>Used Budget: ₱<?= number_format($used,2) ?></p>
<p>Utilization: <?= round($ratio,2) ?>%</p>

<hr>

<h3>🧠 AI Insight</h3>
<p><?= $insight ?></p>

<h3>📌 Recommendation</h3>
<p><?= $recommendation ?></p>

</div>

</body>
</html>