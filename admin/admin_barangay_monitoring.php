<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= SAFE BARANGAY ANALYTICS ================= */
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.barangay_name,

        COALESCE((
            SELECT COUNT(*)
            FROM activities
            WHERE barangay_id = b.id
        ),0) AS total_activities,

        COALESCE((
            SELECT SUM(participants)
            FROM activities
            WHERE barangay_id = b.id
        ),0) AS total_participants,

        COALESCE((
            SELECT total_amount
            FROM budgets
            WHERE barangay_id = b.id
            ORDER BY year DESC
            LIMIT 1
        ),0) AS total_amount,

        COALESCE((
            SELECT used_amount
            FROM budgets
            WHERE barangay_id = b.id
            ORDER BY year DESC
            LIMIT 1
        ),0) AS budget_used,

        COALESCE((
            SELECT remaining_budget
            FROM budgets
            WHERE barangay_id = b.id
            ORDER BY year DESC
            LIMIT 1
        ),0) AS remaining_budget

    FROM barangays b
    ORDER BY b.barangay_name ASC
");
$stmt->execute();
$barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ML SCORE ================= */
function computeScore($row){

    $participants = $row['total_participants'];
    $activities   = $row['total_activities'];
    $budgetUsed   = $row['budget_used'];
    $budget       = $row['total_amount'] ?: 1;

    $engagement = ($participants + ($activities * 15));
    $efficiency = ($budgetUsed > 0) ? ($participants / $budgetUsed) * 1000 : 0;
    $budgetRate = ($budgetUsed / $budget) * 100;

    $score = ($engagement * 0.30) + ($efficiency * 0.40) + ($budgetRate * 0.30);

    return min(100, round($score,2));
}

foreach($barangays as $i => $b){
    $barangays[$i]['ml_score'] = computeScore($b);
}

/* ================= SORT TOP ================= */
usort($barangays, fn($a,$b)=>$b['ml_score'] <=> $a['ml_score']);

$top = $barangays[0]['barangay_name'] ?? 'N/A';
$topScore = $barangays[0]['ml_score'] ?? 0;
$totalBarangays = count($barangays);
?>

<!DOCTYPE html>
<html>
<head>
<title>Barangay Monitoring</title>

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

.card{
    padding:20px;
    text-align:center;
}

.glass{
    background:rgba(255,255,255,0.20);
    backdrop-filter:blur(20px);
    border-radius:15px;
    padding:20px;
    box-shadow:0 8px 20px rgba(0,0,0,0.15);
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

th{
    background:#007bff;
    color:white;
    padding:12px;
}

td{
    padding:10px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,0.2);
    color:white;
}

h2,h3,p{
    color:white;
}

.chart-box{
    margin-top:20px;
}

@media(max-width:1000px){
    .grid{
        grid-template-columns:repeat(2,1fr);
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
        <h2>🏘️ Barangay Monitoring Center</h2>
        <p>Live comparative monitoring of all barangays</p>
    </div>

    <!-- KPI -->
    <div class="grid">

        <div class="glass card">
            <h3>Total Barangays</h3>
            <h2><?= $totalBarangays ?></h2>
        </div>

        <div class="glass card">
            <h3>Top Performing Barangay</h3>
            <h2><?= htmlspecialchars($top) ?></h2>
        </div>

        <div class="glass card">
            <h3>Highest ML Score</h3>
            <h2><?= $topScore ?>%</h2>
        </div>

        <div class="glass card">
            <h3>Monitoring Status</h3>
            <h2>LIVE</h2>
        </div>

    </div>

    <!-- CHART -->
    <div class="glass chart-box">
        <h3>📈 Barangay ML Performance Ranking</h3>
        <canvas id="mlChart"></canvas>
    </div>

    <!-- TABLE -->
    <div class="glass">
        <h3>📊 Barangay Detailed Performance Table</h3>

        <table>
            <tr>
                <th>Barangay</th>
                <th>Activities</th>
                <th>Participants</th>
                <th>Annual Budget</th>
                <th>Used Budget</th>
                <th>Remaining</th>
                <th>ML Score</th>
            </tr>

            <?php foreach($barangays as $b){ ?>
            <tr>
                <td><?= htmlspecialchars($b['barangay_name']) ?></td>
                <td><?= $b['total_activities'] ?></td>
                <td><?= $b['total_participants'] ?></td>
                <td>₱<?= number_format($b['total_amount'],2) ?></td>
                <td>₱<?= number_format($b['budget_used'],2) ?></td>
                <td>₱<?= number_format($b['remaining_budget'],2) ?></td>
                <td><b><?= $b['ml_score'] ?>%</b></td>
            </tr>
            <?php } ?>
        </table>
    </div>

</div>

<script>
const labels = <?= json_encode(array_column($barangays,'barangay_name')) ?>;
const scores = <?= json_encode(array_column($barangays,'ml_score')) ?>;

new Chart(document.getElementById('mlChart'),{
    type:'bar',
    data:{
        labels:labels,
        datasets:[{
            label:'ML Score',
            data:scores
        }]
    },
    options:{
        responsive:true,
        plugins:{
            legend:{display:true}
        },
        scales:{
            y:{
                beginAtZero:true,
                max:100
            }
        }
    }
});
</script>

</body>
</html>