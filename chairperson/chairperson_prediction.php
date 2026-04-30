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

if(file_exists($mlFile)){
    $json = file_get_contents($mlFile);
    $decoded = json_decode($json,true);

    if(is_array($decoded)){
        foreach($decoded as $row){
            if(($row['barangay_id'] ?? 0) == $barangay_id){
                $results[] = $row;
            }
        }
    }
}

/* ================= DEFAULT ================= */
$totalParticipants = 0;
$totalActivities = count($results);
$topActivity = "No Data";
$topScore = 0;

function normalizeScore($score){
    $score = floatval($score);
    if($score < 0) return 0;
    if($score > 100) return 100;
    return round($score,2);
}


/* ================= PROCESS ML ================= */
if(!empty($results)){

    usort($results, fn($a,$b)=>$b['predicted_score'] <=> $a['predicted_score']);

    foreach($results as $r){
        $totalParticipants += (int)($r['participants'] ?? 0);
    }

    $topActivity = $results[0]['title'] ?? 'N/A';
    $topScore = normalizeScore($results[0]['predicted_score'] ?? 0);
}

/* ================= GET REAL BUDGET ================= */
$stmt = $conn->prepare("
    SELECT total_amount, used_amount, remaining_budget
    FROM budgets
    WHERE barangay_id = :bid
    ORDER BY year DESC
    LIMIT 1
");
$stmt->execute([':bid'=>$barangay_id]);
$budgetData = $stmt->fetch(PDO::FETCH_ASSOC);

$annualBudget = $budgetData['total_amount'] ?? 0;
$usedBudget = $budgetData['used_amount'] ?? 0;
$remainingBudget = $budgetData['remaining_budget'] ?? ($annualBudget - $usedBudget);

/* ================= AI CONCLUSION ================= */
if($topScore >= 70){
    $conclusion = "High engagement detected. Existing successful activities can be expanded for stronger barangay impact.";
    $impact = "Allocate more funds to high-performing community programs.";
}
elseif($topScore >= 40){
    $conclusion = "Moderate engagement detected. Some activities perform well but several need strategic enhancement.";
    $impact = "Improve scheduling, promotions, and youth participation initiatives.";
}
else{
    $conclusion = "Low engagement detected. Current activities show weak participation and limited impact.";
    $impact = "Rebuild project planning and conduct stronger needs assessment.";
}

/* ================= AI SUGGESTIONS ================= */
$suggestions = [];
$suggestions[] = "Prioritize successful activities similar to ".$topActivity;
$suggestions[] = "Use barangay youth surveys before planning projects.";
$suggestions[] = "Improve attendance through incentive-based participation.";
$suggestions[] = "Allocate budget based on ML predicted effectiveness.";

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
    width:calc(100% - 210px);
    overflow-x:hidden;
}

.grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-bottom:20px;
}

.glass{
    background:rgba(255,255,255,0.20);
    backdrop-filter:blur(20px);
    border-radius:15px;
    padding:20px;
    margin-bottom:20px;
    box-shadow:0 8px 20px rgba(0,0,0,0.15);
    color:white;
}

.card{
    text-align:center;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
    background:rgba(255,255,255,0.1);
}

th{
    background:#0d6efd;
    color:white;
    padding:12px;
}

td{
    padding:10px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,0.2);
    color:white;
}

.chart-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}

@media(max-width:1000px){
    .grid{
        grid-template-columns:repeat(2,1fr);
    }
    .chart-grid{
        grid-template-columns:1fr;
    }
}

@media(max-width:768px){
    .grid{
        grid-template-columns:1fr;
    }
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

    <div class="header">
        <h2>🤖 AI ML Prediction Dashboard</h2>
        <p style="color:white;">Smart forecast based on approved budget deductions only</p>
    </div>

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
            <h3>Top ML Score</h3>
            <h2><?= $topScore ?>%</h2>
        </div>

    </div>

    <!-- TOP ACTIVITY -->
    <div class="glass">
        <h3>🏆 Top Recommended Activity</h3>
        <p><b><?= htmlspecialchars($topActivity) ?></b> is predicted as the strongest engagement driver.</p>
    </div>

    <!-- AI CONCLUSION -->
    <div class="glass">
        <h3>📌 AI Conclusion</h3>
        <p><?= htmlspecialchars($conclusion) ?></p>
        <hr>
        <p><b>Expected Strategic Impact:</b> <?= htmlspecialchars($impact) ?></p>
    </div>

    <!-- BUDGET FORECAST -->
    <div class="glass">
        <h3>💰 Future Budget Forecast</h3>
        <p>Current Remaining Budget: ₱<?= number_format($remainingBudget,2) ?></p>
        <p>Projected Increase Capacity: ₱<?= number_format($projectedIncrease,2) ?></p>
        <h3>Forecasted Available Budget: ₱<?= number_format($futureBudget,2) ?></h3>
    </div>

    <!-- RECOMMENDATIONS -->
    <div class="glass">
        <h3>💡 AI Recommendations</h3>
        <ul>
            <?php foreach($suggestions as $s){ ?>
                <li><?= htmlspecialchars($s) ?></li>
            <?php } ?>
        </ul>
    </div>

    <!-- CHARTS -->
    <div class="chart-grid">

        <div class="glass">
            <h3>📈 ML Score Comparison</h3>
            <canvas id="scoreChart"></canvas>
        </div>

        <div class="glass">
            <h3>👥 Participants & Activities</h3>
            <canvas id="partChart"></canvas>
        </div>

    </div>

    <!-- TABLE -->
    <div class="glass">
        <h3>📊 ML Results Table</h3>

        <table>
            <tr>
                <th>Activity</th>
                <th>Participants</th>
                <th>Budget</th>
                <th>Predicted Score</th>
            </tr>

            <?php if(!empty($results)){ ?>
                <?php foreach($results as $r){ ?>
                <tr>
                    <td><?= htmlspecialchars($r['title']) ?></td>
                    <td><?= (int)$r['participants'] ?></td>
                    <td>₱<?= number_format($r['budget'],2) ?></td>
                    <td><?= normalizeScore($r['predicted_score']) ?>%</td>
                </tr>
                <?php } ?>
            <?php } else { ?>
                <tr>
                    <td colspan="4">No ML data available. Run train_model.py first.</td>
                </tr>
            <?php } ?>
        </table>
    </div>

</div>

<script>
const labels = <?= json_encode(array_column($results,'title')) ?>;
const scores = <?= json_encode(array_map(fn($r)=>normalizeScore($r['predicted_score']),$results)) ?>;
const participants = <?= json_encode(array_column($results,'participants')) ?>;
const activities = <?= json_encode(array_fill(0,count($results),1)) ?>;

new Chart(document.getElementById('scoreChart'),{
    type:'bar',
    data:{
        labels:labels,
        datasets:[{
            label:'Predicted ML Score',
            data:scores
        }]
    },
    options:{
        responsive:true,
        scales:{
            y:{beginAtZero:true,max:100}
        }
    }
});

new Chart(document.getElementById('partChart'),{
    type:'line',
    data:{
        labels:labels,
        datasets:[
            {
                label:'Participants',
                data:participants
            },
            {
                label:'Activities',
                data:activities
            }
        ]
    },
    options:{
        responsive:true
    }
});
</script>

</body>
</html>