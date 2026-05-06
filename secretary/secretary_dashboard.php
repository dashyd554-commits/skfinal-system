<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= DATA ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM projects
    WHERE barangay_id = :barangay_id
    AND status = 'pending_secretary'
");
$stmt->execute([':barangay_id' => $barangay_id]);
$totalProposals = $stmt->fetchColumn() ?: 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM projects
    WHERE barangay_id = :barangay_id
    AND status IN ('pending_treasurer','approved')
");
$stmt->execute([':barangay_id' => $barangay_id]);
$totalApprovedCouncil = $stmt->fetchColumn() ?: 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM projects
    WHERE barangay_id = :barangay_id
    AND status = 'rejected'
");
$stmt->execute([':barangay_id' => $barangay_id]);
$totalRejectedCouncil = $stmt->fetchColumn() ?: 0;

/* ================= ACTIVITY ================= */
$stmt = $conn->prepare("
    SELECT title, participants 
    FROM activities 
    WHERE barangay_id = :barangay_id
");
$stmt->execute([':barangay_id' => $barangay_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$data = [];
$total = 0;
$mlResults = [];

if (!empty($rows)) {

    foreach ($rows as $row) {
        $participants = (int)($row['participants'] ?? 0);

        $labels[] = $row['title'];
        $data[] = $participants;
        $total += $participants;
    }

    foreach ($rows as $row) {
        $participants = (int)($row['participants'] ?? 0);

        $score = ($total > 0)
            ? sqrt($participants / max($total, 1)) * 100
            : 0;

        $mlResults[] = [
            'title' => $row['title'],
            'participants' => $participants,
            'score' => round($score, 2)
        ];
    }

    usort($mlResults, fn($a,$b) => $b['score'] <=> $a['score']);

    $topActivity = $mlResults[0]['title'];
    $topScore = $mlResults[0]['score'];

} else {
    $topActivity = "No Data";
    $topScore = 0;
}

$mlInsight = $total >= 200
    ? "High engagement detected. Strong community participation."
    : ($total >= 100
        ? "Moderate engagement detected."
        : ($total > 0
            ? "Low engagement detected."
            : "No activity data available."));

$recommendation = $total >= 200
    ? "Maintain and expand successful activities."
    : ($total >= 100
        ? "Improve promotion and replicate successful programs."
        : ($total > 0
            ? "Increase outreach and improve event design."
            : "Start recording activities to generate insights."));
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
    font-family:Arial;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;

    height:100vh;
    overflow:hidden;
}

/* ===== WRAPPER FIX ===== */
.wrapper{
    display:flex;
    height:100vh;
    overflow:hidden;
}

/* ===== MAIN FIX ===== */
.main{
    flex:1;
    height:100vh;
    overflow-y:auto;
    padding:15px;
}

/* TITLE */
.header h2{
    color:white;
    text-align:center;
    margin:10px 0;
}

/* GRID (compact) */
.grid{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:10px;
}

/* CARDS */
.glass{
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    border-radius:12px;
    padding:12px;
    color:white;
    box-shadow:0 8px 20px rgba(0,0,0,0.2);
}

/* CARD TEXT */
.card{text-align:center;}
.card h2{color:#1e3c72; margin:5px 0;}

/* CHART FIX (IMPORTANT) */
.chart-box{
    height:300px;
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#1e3c72;
    color:white;
    padding:8px;
}

td{
    padding:8px;
    text-align:center;
    color:#1e3c72;
    border-bottom:1px solid rgba(255,255,255,0.2);
}

/* RESPONSIVE */
@media(max-width:768px){
    body{overflow:auto;}

    .wrapper{
        flex-direction:column;
    }

    .main{
        height:auto;
    }

    .grid{
        grid-template-columns:1fr;
    }

    .chart-box{
        height:250px;
    }
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
    <h3>🤖 ML Ranking</h3>
    <table>
        <tr><th>Activity</th><th>Participants</th><th>Score</th></tr>
        <?php foreach($mlResults as $r){ ?>
        <tr>
            <td><?= htmlspecialchars($r['title']) ?></td>
            <td><?= $r['participants'] ?></td>
            <td><?= $r['score'] ?>%</td>
        </tr>
        <?php } ?>
    </table>
</div>

<div class="glass">
    <h3>📢 Insight</h3>
    <p><?= $mlInsight ?></p>
</div>

<div class="glass">
    <h3>💡 Recommendation</h3>
    <p><b><?= $recommendation ?></b></p>
</div>

<div class="glass">
    <h3>🏆 Top Activity</h3>
    <p><b><?= $topActivity ?> (<?= $topScore ?>%)</b></p>
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
            borderWidth:1
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