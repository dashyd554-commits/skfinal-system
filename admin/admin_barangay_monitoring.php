<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= SAFE BARANGAY ANALYTICS ================= */
$stmt = $pdo->prepare("
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
$barangays = $stmt->fetchAll();

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
    return min(100, round($score, 2));
}

foreach($barangays as $i => $b){
    $barangays[$i]['ml_score'] = computeScore($b);
}

usort($barangays, fn($a,$b) => $b['ml_score'] <=> $a['ml_score']);

$top          = $barangays[0]['barangay_name'] ?? 'N/A';
$topScore     = $barangays[0]['ml_score'] ?? 0;
$totalBarangays = count($barangays);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Barangay Monitoring</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root{
    --sidebar-w:240px;
    --navy:#0d1b2a;
    --glass:rgba(255,255,255,0.06);
    --glass-border:rgba(255,255,255,0.10);
    --text:#e2e8f0;
    --muted:#94a3b8;
    --accent:#38bdf8;
}

*{margin:0;padding:0;box-sizing:border-box;}

html,body{
    height:100%;
    overflow:hidden; /* FULL SCREEN FIX */
    font-family:'Sora','Segoe UI',sans-serif;
    background:
    radial-gradient(circle at top left, rgba(56,189,248,0.15), transparent 40%),
    radial-gradient(circle at bottom right, rgba(245,158,11,0.08), transparent 40%),
    #0d1b2a;
    color:white;
}

/* SIDEBAR FIX */
.sidebar{
    position:fixed !important;
    top:0;
    left:0;
    width:var(--sidebar-w) !important;
    height:100vh !important;
    z-index:1000;
}

/* MAIN SCREEN FIX */
.main{
    margin-left:var(--sidebar-w);
    width:calc(100% - var(--sidebar-w));
    height:100vh;
    padding:20px;
    display:flex;
    flex-direction:column;
    gap:15px;
    overflow:hidden; /* IMPORTANT */
}

/* HEADER */
.page-header{
    flex-shrink:0;
}

.page-header h2{
    font-size:24px;
    margin-bottom:4px;
}

.page-header p{
    color:var(--muted);
    font-size:13px;
}

/* KPI */
.kpi-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:12px;
    flex-shrink:0;
}

.kpi-card{
    background:var(--glass);
    border:1px solid var(--glass-border);
    backdrop-filter:blur(14px);
    border-radius:14px;
    padding:16px;
}

.kpi-label{
    font-size:11px;
    color:var(--muted);
    text-transform:uppercase;
    margin-bottom:8px;
}

.kpi-value{
    font-size:22px;
    font-weight:700;
}

/* PANELS */
.panel{
    background:var(--glass);
    border:1px solid var(--glass-border);
    backdrop-filter:blur(18px);
    border-radius:16px;
    padding:16px;
}

/* CHART FIX HEIGHT */
.chart-panel{
    flex-shrink:0;
    height:240px;
}

.chart-panel canvas{
    height:160px !important;
}

/* TABLE PANEL TAKES REMAINING SCREEN */
.table-panel{
    flex:1;
    display:flex;
    flex-direction:column;
    overflow:hidden;
}

.table-title{
    margin-bottom:10px;
    color:var(--muted);
    font-size:13px;
}

/* ONLY TABLE SCROLLS */
.table-wrap{
    flex:1;
    overflow-y:auto;
    overflow-x:auto;
    border-radius:10px;
}

table{
    width:100%;
    min-width:900px;
    border-collapse:collapse;
}

th{
    background:#1b2d42;
    color:#94a3b8;
    font-size:12px;
    padding:12px;
    position:sticky;
    top:0;
    z-index:2;
    text-align:left;
}

td{
    padding:11px;
    font-size:13px;
    border-bottom:1px solid rgba(255,255,255,0.06);
}

tr:hover{
    background:rgba(255,255,255,0.03);
}

.score{
    font-weight:bold;
    color:#38bdf8;
}

.footer{
    flex-shrink:0;
    text-align:center;
    font-size:11px;
    color:var(--muted);
}

/* MOBILE */
@media(max-width:900px){
    .kpi-grid{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:768px){
    :root{ --sidebar-w:65px; }

    .main{
        padding:14px;
    }

    .chart-panel{
        height:200px;
    }
}
</style>
</head>
<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <!-- HEADER -->
    <div class="page-header">
        <h2>📊 Barangay Monitoring Center</h2>
        <p>Live comparative monitoring of all barangays</p>
    </div>

    <!-- KPI -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">TOTAL BARANGAYS</div>
            <div class="kpi-value"><?= $totalBarangays ?></div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">TOP BARANGAY</div>
            <div class="kpi-value" style="font-size:15px;"><?= htmlspecialchars($top) ?></div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">HIGHEST ML SCORE</div>
            <div class="kpi-value"><?= $topScore ?>%</div>
        </div>

        <div class="kpi-card">
            <div class="kpi-label">STATUS</div>
            <div class="kpi-value">LIVE</div>
        </div>
    </div>

    <!-- CHART -->
    <div class="panel chart-panel">
        <div class="table-title">📈 PERFORMANCE RANKING CHART</div>
        <canvas id="mlChart"></canvas>
    </div>

    <!-- TABLE -->
    <div class="panel table-panel">
        <div class="table-title">📋 DETAILED PERFORMANCE TABLE</div>

        <div class="table-wrap">
            <table>
                <tr>
                    <th>#</th>
                    <th>Barangay</th>
                    <th>Activities</th>
                    <th>Participants</th>
                    <th>Annual Budget</th>
                    <th>Used Budget</th>
                    <th>Remaining</th>
                    <th>ML Score</th>
                </tr>

                <?php foreach($barangays as $i=>$b){ ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($b['barangay_name']) ?></td>
                    <td><?= $b['total_activities'] ?></td>
                    <td><?= number_format($b['total_participants']) ?></td>
                    <td>₱<?= number_format((float)$b['total_amount'],2) ?></td>
                    <td>₱<?= number_format((float)$b['budget_used'],2) ?></td>
                    <td>₱<?= number_format((float)$b['remaining_budget'],2) ?></td>
                    <td class="score"><?= $b['ml_score'] ?>%</td>
                </tr>
                <?php } ?>
            </table>
        </div>
    </div>

    <div class="footer">
        © 2026 SK Decision Support System
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
            data:scores,
            borderRadius:6
        }]
    },
    options:{
        maintainAspectRatio:false,
        plugins:{legend:{display:false}},
        responsive:true,
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