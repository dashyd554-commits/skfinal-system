<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= CALL FLASK API ================= */
$apiUrl = "https://skfinal-system-1.onrender.com/predict"; // CHANGE if needed

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

/* ================= SAFE DEFAULTS ================= */
$topScore = $data['budget_efficiency_score'] ?? 0;
$category = $data['category'] ?? "No Data";
$recommendation = $data['recommendation'] ?? "No Recommendation";
$successProbability = $data['success_probability'] ?? 0;

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

/* ================= FORECAST ================= */
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
    box-shadow:0 5px 15px rgba(0,0,0,0.2);
}

.card{text-align:center;}

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

<?php if(!$data || $httpCode != 200){ ?>
    <div class="glass">
        <h3>⚠ ML API ERROR</h3>
        <p>Cannot connect to Flask API.</p>
        <p>Check: <b><?= htmlspecialchars($apiUrl) ?></b></p>
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
<h2><?= round($topScore,2) ?>%</h2>
</div>

</div>

<!-- RESULT -->
<div class="glass">
<h3>📊 AI Result Summary</h3>

<p><b>Category:</b> <?= htmlspecialchars($category) ?></p>
<p><b>Success Probability:</b> <?= round($successProbability * 100,2) ?>%</p>
<p><b>Recommendation:</b> <?= htmlspecialchars($recommendation) ?></p>

</div>

<!-- FORECAST -->
<div class="glass">
<h3>💰 Budget Forecast</h3>
<p>Remaining: ₱<?= number_format($remainingBudget,2) ?></p>
<p>Projected Increase: ₱<?= number_format($projectedIncrease,2) ?></p>
<h3>Future Budget: ₱<?= number_format($futureBudget,2) ?></h3>
</div>

</div>

</body>
</html>