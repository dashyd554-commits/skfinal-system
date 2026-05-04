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

/* ================= DEFAULT AI VALUES ================= */
$topScore = 0;
$category = "No Data";
$recommendation = "No Recommendation";
$successProbability = 0;
$data = null;

/* ================= PARSE API RESPONSE ================= */
if ($httpCode == 200 && $response) {
    $data = json_decode($response, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        $topScore = floatval($data['mean_score'] ?? 0);
    }
}

/* ================= AUTO AI INTERPRETATION ================= */
if ($topScore >= 70) {
    $category = "High Performance";
    $recommendation = "Maintain current youth programs and expand funding support.";
    $successProbability = 0.85;
}
elseif ($topScore >= 40) {
    $category = "Moderate Performance";
    $recommendation = "Improve participation rate and optimize project budgeting.";
    $successProbability = 0.60;
}
elseif ($topScore > 0) {
    $category = "Low Performance";
    $recommendation = "Reassess project planning and strengthen youth engagement.";
    $successProbability = 0.30;
}

/* ================= GET REAL BUDGET ================= */
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

/* ================= BUDGET FORECAST ================= */
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

<style>
body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    font-family:Arial, sans-serif;
}

.main{
    margin-left:190px;
    padding:20px;
    width:calc(100% - 210px);
}

.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:15px;
    margin-bottom:20px;
}

.glass{
    background:rgba(255,255,255,0.20);
    backdrop-filter:blur(15px);
    border-radius:15px;
    padding:20px;
    color:white;
    margin-bottom:15px;
    box-shadow:0 8px 20px rgba(0,0,0,0.2);
}

.card{
    text-align:center;
}

.card h2{
    margin:10px 0;
}

h2,h3,p{
    margin:8px 0;
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

    <h2>🤖 AI ML Prediction Dashboard</h2>

    <?php if(!$data){ ?>
        <div class="glass">
            <h3>⚠ ML API ERROR</h3>
            <p>Cannot connect to Flask API.</p>
            <p>Check URL: <b><?= htmlspecialchars($apiUrl) ?></b></p>
        </div>
    <?php } ?>

    <!-- KPI -->
    <div class="grid">

        <div class="glass card">
            <h3>Annual Budget</h3>
            <h2>₱<?= number_format($annualBudget,2) ?></h2>
        </div>

        <div class="glass card">
            <h3>Used Budget</h3>
            <h2>₱<?= number_format($usedBudget,2) ?></h2>
        </div>

        <div class="glass card">
            <h3>Remaining Budget</h3>
            <h2>₱<?= number_format($remainingBudget,2) ?></h2>
        </div>

        <div class="glass card">
            <h3>AI Score</h3>
            <h2><?= number_format($topScore,2) ?>%</h2>
        </div>

    </div>

    <!-- AI SUMMARY -->
    <div class="glass">
        <h3>📊 AI Result Summary</h3>
        <p><b>Category:</b> <?= htmlspecialchars($category) ?></p>
        <p><b>Success Probability:</b> <?= number_format($successProbability * 100,2) ?>%</p>
        <p><b>Recommendation:</b> <?= htmlspecialchars($recommendation) ?></p>
    </div>

    <!-- BUDGET FORECAST -->
    <div class="glass">
        <h3>💰 Budget Forecast</h3>
        <p><b>Remaining:</b> ₱<?= number_format($remainingBudget,2) ?></p>
        <p><b>Projected Increase:</b> ₱<?= number_format($projectedIncrease,2) ?></p>
        <h3>Future Budget: ₱<?= number_format($futureBudget,2) ?></h3>
    </div>

</div>

</body>
</html>