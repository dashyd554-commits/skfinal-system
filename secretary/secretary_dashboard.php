<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= TOTAL PENDING PROPOSALS ONLY ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM projects
    WHERE barangay_id = :barangay_id
    AND status = 'pending_secretary'
");
$stmt->execute([
    ':barangay_id' => $barangay_id
]);
$totalProposals = $stmt->fetchColumn() ?: 0;

/* ================= APPROVED BY COUNCIL ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM projects
    WHERE barangay_id = :barangay_id
    AND status IN ('pending_treasurer','approved')
");
$stmt->execute([
    ':barangay_id' => $barangay_id
]);
$totalApprovedCouncil = $stmt->fetchColumn() ?: 0;

/* ================= REJECTED BY COUNCIL ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM projects
    WHERE barangay_id = :barangay_id
    AND status = 'rejected'
");
$stmt->execute([
    ':barangay_id' => $barangay_id
]);
$totalRejectedCouncil = $stmt->fetchColumn() ?: 0;

/* ================= SAFE DATA LOAD ================= */
try {
    $stmt = $conn->prepare("
        SELECT title, participants 
        FROM activities 
        WHERE barangay_id = :barangay_id
    ");
    $stmt->execute([
        ':barangay_id' => $barangay_id
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

/* ================= INIT ================= */
$labels = [];
$data = [];
$total = 0;
$mlResults = [];

/* ================= PROCESS DATA ================= */
if (!empty($rows)) {

    foreach ($rows as $row) {
        $participants = (int)($row['participants'] ?? 0);

        $labels[] = $row['title'] ?? 'Unknown';
        $data[] = $participants;
        $total += $participants;
    }

    foreach ($rows as $row) {

        $participants = (int)($row['participants'] ?? 0);

        $score = ($total > 0)
            ? sqrt($participants / max($total, 1)) * 100
            : 0;

        $mlResults[] = [
            'title' => $row['title'] ?? 'Unknown',
            'participants' => $participants,
            'score' => round($score, 2)
        ];
    }

    usort($mlResults, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $topActivity = $mlResults[0]['title'] ?? 'No Data';
    $topScore = $mlResults[0]['score'] ?? 0;

} else {
    $topActivity = "No Data";
    $topScore = 0;
}

/* ================= INSIGHT ================= */
if ($total >= 200) {
    $mlInsight = "High engagement detected. Strong community participation.";
    $recommendation = "Maintain and expand successful activities.";
} elseif ($total >= 100) {
    $mlInsight = "Moderate engagement detected.";
    $recommendation = "Improve promotion and replicate successful programs.";
} elseif ($total > 0) {
    $mlInsight = "Low engagement detected.";
    $recommendation = "Increase outreach and improve event design.";
} else {
    $mlInsight = "No activity data available.";
    $recommendation = "Start recording activities to generate insights.";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Secretary Dashboard</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
*{box-sizing:border-box;}

body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    font-family:Arial;
}

.main{
    margin-left:190px;
    padding:20px;
    width:calc(100% - 200px);
}

.header h2{
    color:white;
    text-align:center;
    margin-bottom:20px;
}

.grid{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:15px;
    margin-bottom:20px;
}

.glass{
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    border-radius:15px;
    padding:20px;
    color:white;
    box-shadow:0 8px 25px rgba(0,0,0,0.2);
    margin-bottom:20px;
}

.card{text-align:center;}

.card h2{
    color:#1e3c72;
    margin-top:10px;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

th{
    background:#1e3c72;
    color:white;
    padding:12px;
}

td{
    padding:10px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,0.2);
    color:#1e3c72;
}

p{
    color:#1e3c72;
    line-height:1.8;
}

.highlight{
    font-weight:bold;
    font-size:18px;
}

canvas{
    background:rgba(255,255,255,0.08);
    border-radius:10px;
    padding:10px;
}

@media(max-width:768px){
    .main{
        margin-left:0;
        width:100%;
        padding:10px;
    }

    .grid{
        grid-template-columns:1fr;
    }
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>🤖 Secretary Dashboard</h2>
</div>

<div class="grid">

    <div class="glass card">
        <h3>👥 Total Participants</h3>
        <h2><?= $total ?></h2>
    </div>

    <div class="glass card">
        <h3>📊 Activities Count</h3>
        <h2><?= count($labels) ?></h2>
    </div>

    <div class="glass card">
        <h3>📄 Pending Proposals</h3>
        <h2><?= $totalProposals ?></h2>
    </div>

    <div class="glass card">
        <h3>✅ Approved by Council</h3>
        <h2><?= $totalApprovedCouncil ?></h2>
    </div>

    <div class="glass card">
        <h3>❌ Rejected by Council</h3>
        <h2><?= $totalRejectedCouncil ?></h2>
    </div>

</div>

<div class="glass">
    <h3>📊 Participation per Activity</h3>
    <canvas id="chart"></canvas>
</div>

<div class="glass">
    <h3>🤖 ML Activity Ranking</h3>
    <table>
        <tr>
            <th>Activity</th>
            <th>Participants</th>
            <th>ML Score</th>
        </tr>

        <?php if (!empty($mlResults)) { ?>
            <?php foreach ($mlResults as $r) { ?>
            <tr>
                <td><?= htmlspecialchars($r['title']) ?></td>
                <td><?= (int)$r['participants'] ?></td>
                <td><?= $r['score'] ?>%</td>
            </tr>
            <?php } ?>
        <?php } else { ?>
            <tr><td colspan="3">No activity data found</td></tr>
        <?php } ?>
    </table>
</div>

<div class="glass">
    <h3>📢 AI Insight</h3>
    <p><?= htmlspecialchars($mlInsight) ?></p>
</div>

<div class="glass">
    <h3>💡 Recommendation</h3>
    <p class="highlight"><?= htmlspecialchars($recommendation) ?></p>
</div>

<div class="glass">
    <h3>🏆 Top Activity (ML)</h3>
    <p class="highlight"><?= htmlspecialchars($topActivity) ?> (<?= $topScore ?>%)</p>
</div>

</div>

<script>
new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Participants',
            data: <?= json_encode($data) ?>,
            borderWidth: 1
        }]
    },
    options: {
        responsive:true,
        plugins:{
            legend:{ labels:{ color:'white' } }
        },
        scales:{
            x:{ ticks:{ color:'white' } },
            y:{ ticks:{ color:'white' } }
        }
    }
});
</script>

</body>
</html>