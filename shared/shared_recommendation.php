<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= TOTAL BUDGET ================= */
$stmt = $conn->prepare("
    SELECT COALESCE(total_amount,0)
    FROM budgets
    WHERE barangay_id = :barangay_id
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([':barangay_id' => $barangay_id]);
$totalBudget = $stmt->fetchColumn() ?: 0;

/* ================= USED BUDGET ================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) AS total_used
    FROM budget_transactions
    WHERE barangay_id = :barangay_id
");
$stmt->execute([':barangay_id' => $barangay_id]);
$budget = $stmt->fetch(PDO::FETCH_ASSOC);

$used = $budget['total_used'] ?? 0;
$ratio = ($totalBudget > 0) ? ($used / $totalBudget) * 100 : 0;

/* ================= RULE BASED ================= */
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

/* ================= ML API ================= */
$ml_online = false;
$prediction = "No Data";
$confidence = 0;
$ml_recommendation = "No recommendation";

$ch = curl_init("https://skfinal-system.onrender.com/predict");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response && $http_code == 200) {
    $decoded = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        $mean_score = floatval($decoded['mean_score'] ?? 0);

        if ($mean_score >= 70) {
            $prediction = "High Performance Barangay";
            $confidence = 85;
            $ml_recommendation = "Barangay financial execution is excellent. Continue strong youth-centered project investments.";
        }
        elseif ($mean_score >= 40) {
            $prediction = "Moderate Performance Barangay";
            $confidence = 60;
            $ml_recommendation = "Barangay shows average utilization. Improve implementation efficiency for higher impact.";
        }
        elseif ($mean_score > 0) {
            $prediction = "Low Performance Barangay";
            $confidence = 30;
            $ml_recommendation = "Barangay project and budget activity are weak. Increase execution and monitoring.";
        }

        $ml_online = true;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Barangay AI Recommendation</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    font-family:Arial;
}
.main{
    margin-left:190px;
    padding:25px;
    width:calc(100% - 210px);
}
h2{
    text-align:center;
    color:#ffffff;
    margin-bottom:20px;
}
h3{
    color:#ffffff;
    margin-bottom:10px;
}
.card{
    background: rgba(255,255,255,0.10);
    backdrop-filter: blur(18px);
    border-radius:14px;
    padding:20px;
    margin-bottom:20px;
    box-shadow:0 8px 20px rgba(0,0,0,0.25);
}
p{
    margin:6px 0;
    font-size:15px;
    color:#1e3c72;
}
.ml-box{
    background: rgba(255,255,255,0.12);
    border-left:5px solid #60a5fa;
    padding:15px;
    border-radius:10px;
    margin-top:10px;
}
b{
    color:#ffffff;
}
@media(max-width:768px){
    .main{
        margin-left:0;
        width:100%;
        padding:15px;
    }
}
</style>
</head>
<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>🤖 AI + ML Recommendation System</h2>

<div class="card">
    <h3>💰 Budget Analysis</h3>
    <p><b>Total Budget:</b> ₱<?= number_format($totalBudget,2) ?></p>
    <p><b>Used Budget:</b> ₱<?= number_format($used,2) ?></p>
    <p><b>Utilization:</b> <?= round($ratio,2) ?>%</p>
</div>

<div class="card">
    <h3>🧠 Rule-Based Insight</h3>
    <p><?= $insight ?></p>

    <h3>📌 Recommendation</h3>
    <p><?= $recommendation ?></p>
</div>

<div class="card">
    <h3>🤖 Machine Learning Prediction</h3>

    <?php if ($ml_online): ?>
        <div class="ml-box">
            <p><b>Prediction:</b> <?= htmlspecialchars($prediction) ?></p>
            <p><b>Confidence Score:</b> <?= $confidence ?>%</p>
            <p><b>Suggested Action:</b> <?= htmlspecialchars($ml_recommendation) ?></p>
        </div>
    <?php else: ?>
        <p style="color:red;">ML service unavailable. Using fallback AI only.</p>
    <?php endif; ?>
</div>

</div>

</body>
</html>