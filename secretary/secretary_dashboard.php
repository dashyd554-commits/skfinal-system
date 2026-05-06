<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= RUN ML PYTHON ================= */
$python_file = "../ml/train_ml.py";
if (file_exists($python_file)) {
    @exec("python $python_file");
}

/* ================= PROPOSAL COUNTS ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM projects
    WHERE barangay_id=:barangay_id
    AND status='pending_secretary'
");
$stmt->execute([':barangay_id'=>$barangay_id]);
$totalProposals = $stmt->fetchColumn() ?: 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) FROM projects
    WHERE barangay_id=:barangay_id
    AND status IN ('pending_treasurer','approved')
");
$stmt->execute([':barangay_id'=>$barangay_id]);
$totalApprovedCouncil = $stmt->fetchColumn() ?: 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) FROM projects
    WHERE barangay_id=:barangay_id
    AND status='rejected'
");
$stmt->execute([':barangay_id'=>$barangay_id]);
$totalRejectedCouncil = $stmt->fetchColumn() ?: 0;

/* ================= ACTIVITY DATA ================= */
$stmt = $conn->prepare("
    SELECT title, participants
    FROM activities
    WHERE barangay_id=:barangay_id
");
$stmt->execute([':barangay_id'=>$barangay_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$data   = [];
$total  = 0;

foreach($rows as $row){
    $labels[] = $row['title'];
    $data[]   = (int)$row['participants'];
    $total   += (int)$row['participants'];
}

/* ================= REAL ML RESULTS ================= */
$ml_online = false;
$topScore = 0;
$category = "No Data";
$success_probability = 0;
$budget_efficiency = 0;
$recommendation = "No Recommendation";
$topActivity = "No Data";

$mlFile = "../ml/ml_results.json";

if(file_exists($mlFile)){
    $mlData = json_decode(file_get_contents($mlFile), true);

    if(json_last_error()===JSON_ERROR_NONE && isset($mlData[$barangay_id])){
        $b = $mlData[$barangay_id];

        $topScore            = round((float)($b['mean_score'] ?? 0),2);
        $category            = $b['category'] ?? 'No Data';
        $success_probability = round((float)($b['success_probability'] ?? 0) * 100,2);
        $budget_efficiency   = round((float)($b['budget_efficiency_score'] ?? 0),2);
        $recommendation      = $b['recommendation'] ?? 'No Recommendation';
        $ml_online = true;
    }
}

/* ================= TOP ACTIVITY (REALISTIC) ================= */
$topActivity = "No Data";
$topParticipants = 0;

if(!empty($rows)){
    usort($rows,function($a,$b){
        return $b['participants'] <=> $a['participants'];
    });

    $topActivity = $rows[0]['title'];
    $topParticipants = $rows[0]['participants'];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Secretary Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../assets/style.css">

<style>
*{box-sizing:border-box;}

body{
    margin:0;
    height:100vh;
    overflow:hidden;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    font-family:Arial;
}

.wrapper{
    display:flex;
    height:100vh;
    overflow:hidden;
}

.main{
    flex:1;
    height:100vh;
    overflow-y:auto;
    padding:15px;
}

.header h2{
    color:white;
    text-align:center;
    margin:10px 0;
}

.grid{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:10px;
    margin-bottom:20px;
}

.glass{
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    border-radius:12px;
    padding:12px;
    color:white;
    box-shadow:0 8px 20px rgba(0,0,0,0.2);
    margin-bottom:20px;
}

.card{text-align:center;}
.card h2{color:#ffd166; margin:5px 0;}

.chart-box{
    height:300px;
    margin-bottom:20px;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-bottom:20px;
}

th{
    background:#1e3c72;
    color:white;
    padding:8px;
}

td{
    padding:8px;
    text-align:center;
    color:white;
    border-bottom:1px solid rgba(255,255,255,0.2);
}

@media(max-width:768px){
    .wrapper{flex-direction:column;}
    .main{height:auto;}
    .grid{grid-template-columns:1fr;}
    .chart-box{height:250px;}
}
</style>
</head>

<body>

<div class="wrapper">

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>🤖 Secretary Dashboard</h2>
</div>

<div class="grid">
    <div class="glass card"><h3>Participants</h3><h2><?= $total ?></h2></div>
    <div class="glass card"><h3>Activities</h3><h2><?= count($labels) ?></h2></div>
    <div class="glass card"><h3>Pending</h3><h2><?= $totalProposals ?></h2></div>
    <div class="glass card"><h3>Approved</h3><h2><?= $totalApprovedCouncil ?></h2></div>
    <div class="glass card"><h3>Rejected</h3><h2><?= $totalRejectedCouncil ?></h2></div>
</div>

<div class="glass chart-box">
    <h3>📊 Participation Chart</h3>
    <canvas id="chart"></canvas>
</div>

<div class="glass">
    <h3>🤖 AI Result</h3>
    <p><b>AI Mean Score:</b> <?= $topScore ?>%</p>
    <p><b>Category:</b> <?= htmlspecialchars($category) ?></p>
    <p><b>Success Probability:</b> <?= $success_probability ?>%</p>
    <p><b>Budget Efficiency:</b> <?= $budget_efficiency ?>%</p>
</div>

<div class="glass">
    <h3>💡 AI Recommendation</h3>
    <p><?= htmlspecialchars($recommendation) ?></p>
</div>

<div class="glass">
    <h3>🏆 Top Activity</h3>
    <p><b><?= htmlspecialchars($topActivity) ?> (<?= $topParticipants ?> participants)</b></p>
</div>

</div>
</div>

<script>
new Chart(document.getElementById('chart'), {
    type:'bar',
    data:{
        labels: <?= json_encode($labels) ?>,
        datasets:[{
            label:'Participants',
            data: <?= json_encode($data) ?>,
            borderWidth:1,
            backgroundColor:'rgba(255,209,102,0.7)'
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false
    }
});
</script>

</body>
</html>