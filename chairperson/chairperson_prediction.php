<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= CALL FLASK API ================= */
$apiUrl = "https://skfinal-system.onrender.com/predict";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

/* ================= DEFAULT VALUES ================= */
$topScore = 0;
$category = "No Data";
$recommendation = "No Recommendation";
$successProbability = 0;
$data = null;

/* ================= PARSE ================= */
if ($httpCode == 200 && $response) {
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $topScore = floatval($data['mean_score'] ?? 0);
    }
}

/* ================= AI LOGIC ================= */
if ($topScore >= 70) {
    $category = "High Performance";
    $recommendation = "Maintain current youth programs and expand funding support.";
    $successProbability = 0.85;
} elseif ($topScore >= 40) {
    $category = "Moderate Performance";
    $recommendation = "Improve participation rate and optimize budgeting.";
    $successProbability = 0.60;
} elseif ($topScore > 0) {
    $category = "Low Performance";
    $recommendation = "Strengthen youth engagement and planning.";
    $successProbability = 0.30;
}

/* ================= BUDGET ================= */
$stmt = $conn->prepare("
    SELECT total_amount, used_amount
    FROM budgets
    WHERE barangay_id = :bid
    ORDER BY year DESC
    LIMIT 1
");
$stmt->execute([':bid' => $barangay_id]);
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
<title>AI ML Prediction</title>

<link rel="stylesheet" href="../assets/style.css">

<style>
*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    font-family:Arial, sans-serif;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    height:100vh;
    overflow:hidden;
}

/* ===== LAYOUT FIX ===== */
.wrapper{
    display:flex;
    height:100vh;
    overflow:hidden;
}

/* MAIN */
.main{
    flex:1;
    padding:20px;
    overflow-y:auto;
    height:100vh;
}

/* TITLE */
h2{
    text-align:center;
    color:white;
    margin-bottom:15px;
}

/* GRID */
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:12px;
}

/* GLASS CARD */
.glass{
    background:rgba(255,255,255,0.18);
    backdrop-filter:blur(15px);
    border-radius:14px;
    padding:15px;
    color:white;
    box-shadow:0 6px 20px rgba(0,0,0,0.25);
}

/* CARD TEXT */
.card{
    text-align:center;
}

.card h2{
    margin-top:8px;
    color:#1e3c72;
}

/* SECTIONS */
.section{
    margin-top:15px;
}

/* RESPONSIVE */
@media(max-width:768px){
    body{
        overflow:auto;
    }

    .wrapper{
        flex-direction:column;
    }

    .main{
        height:auto;
    }
}
</style>

</head>

<body>

<div class="wrapper">
<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>🤖 AI ML Prediction Dashboard</h2>

<?php if(!$data){ ?>
    <div class="glass section">
        <h3>⚠ ML API ERROR</h3>
        <p>Cannot connect to Flask API.</p>
        <p>Check: <?= htmlspecialchars($apiUrl) ?></p>
    </div>
<?php } ?>

<!-- KPI -->
<div class="grid section">

    <div class="glass card">
        <h3>Annual Budget</h3>
        <h2>₱<?= number_format($annualBudget,2) ?></h2>
    </div>

    <div class="glass card">
        <h3>Used Budget</h3>
        <h2>₱<?= number_format($usedBudget,2) ?></h2>
    </div>

    <div class="glass card">
        <h3>Remaining</h3>
        <h2>₱<?= number_format($remainingBudget,2) ?></h2>
    </div>

    <div class="glass card">
        <h3>AI Score</h3>
        <h2><?= number_format($topScore,2) ?>%</h2>
    </div>

</div>

<!-- AI RESULT -->
<div class="glass section">
    <h3>📊 AI Result</h3>
    <p><b>Category:</b> <?= htmlspecialchars($category) ?></p>
    <p><b>Success Probability:</b> <?= number_format($successProbability * 100,2) ?>%</p>
    <p><b>Recommendation:</b> <?= htmlspecialchars($recommendation) ?></p>
</div>

<!-- FORECAST -->
<div class="glass section">
    <h3>💰 Budget Forecast</h3>
    <p><b>Remaining:</b> ₱<?= number_format($remainingBudget,2) ?></p>
    <p><b>Projected Increase:</b> ₱<?= number_format($projectedIncrease,2) ?></p>
    <h3>Future Budget: ₱<?= number_format($futureBudget,2) ?></h3>
</div>

</div>
</div>

</body>
</html>