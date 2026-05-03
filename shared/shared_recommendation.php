<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
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

/* ================= FALLBACK AI LOGIC ================= */
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

/* ================= ML API INTEGRATION ================= */
$ml_result = null;

$ml_payload = json_encode([
    "barangay_id" => $barangay_id,
    "total_budget" => $totalBudget,
    "used_budget" => $used,
    "utilization" => $ratio
]);

$ch = curl_init("https://skmanagementsys.onrender.com/predict"); // your ML API
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $ml_payload);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 && $response) {
    $ml_result = json_decode($response, true);
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

/* MAIN LAYOUT */
.main{
    margin-left:190px;
    padding:25px;
    width:calc(100% - 190px);
}

/* HEADERS */
h2{
    text-align:center;
    color:#ffffff;
    margin-bottom:20px;
}

h3{
    color:#ffffff;
    margin-bottom:10px;
}

/* CARDS */
.card{
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(18px);
    border-radius:14px;
    padding:20px;
    margin-bottom:20px;
    box-shadow:0 8px 20px rgba(0,0,0,0.25);
    border:1px solid rgba(255,255,255,0.1);
}

/* TEXT */
p{
    margin:6px 0;
    font-size:15px;
    color:#1e3c72;
}

/* ML BOX */
.ml-box{
    background: rgba(255,255,255,0.12);
    border-left:5px solid #60a5fa;
    padding:15px;
    border-radius:10px;
    margin-top:10px;
}

/* STRONG TEXT */
b{
    color:#ffffff;
}

/* ERROR TEXT */
.error{
    color:#ff6b6b;
}

/* SIDEBAR RESPONSIVE FIX */
@media(max-width:768px){
    .main{
        margin-left:0;
        width:100%;
        padding:15px;
    }

    h2{
        font-size:18px;
    }

    h3{
        font-size:16px;
    }

    p{
        font-size:13px;
        color:#1e3c72 ;
    }

    .card{
        padding:15px;
    }
}
</style>
</style>

</head>
<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>🤖 AI + ML Recommendation System</h2>

<!-- BUDGET -->
<div class="card">
    <h3>💰 Budget Analysis</h3>
    <p><b>Total Budget:</b> ₱<?= number_format($totalBudget,2) ?></p>
    <p><b>Used Budget:</b> ₱<?= number_format($used,2) ?></p>
    <p><b>Utilization:</b> <?= round($ratio,2) ?>%</p>
</div>

<!-- RULE-BASED AI -->
<div class="card">
    <h3>🧠 Rule-Based Insight</h3>
    <p><?= $insight ?></p>

    <h3>📌 Recommendation</h3>
    <p><?= $recommendation ?></p>
</div>

<!-- ML RESULT -->
<div class="card">
    <h3>🤖 Machine Learning Prediction</h3>

    <?php if ($ml_result): ?>
        <div class="ml-box">
            <p><b>Prediction:</b> <?= htmlspecialchars($ml_result['prediction'] ?? 'N/A') ?></p>
            <p><b>Confidence Score:</b> <?= htmlspecialchars($ml_result['score'] ?? 'N/A') ?></p>
            <p><b>Suggested Action:</b> <?= htmlspecialchars($ml_result['recommendation'] ?? 'No recommendation') ?></p>
        </div>
    <?php else: ?>
        <p style="color:red;">ML service unavailable. Using fallback AI only.</p>
    <?php endif; ?>
</div>

</div>

</body>
</html>