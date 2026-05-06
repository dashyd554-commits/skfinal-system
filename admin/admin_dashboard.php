<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

function getCount($conn, $sql) {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchColumn();
}

$totalUsers            = getCount($conn, "SELECT COUNT(*) FROM users");
$totalBarangays        = getCount($conn, "SELECT COUNT(*) FROM barangays");
$totalApprovedProjects = getCount($conn, "SELECT COUNT(*) FROM projects WHERE status='approved'");
$activeUsers           = getCount($conn, "SELECT COUNT(*) FROM users WHERE status='active'");
$inactiveUsers         = getCount($conn, "SELECT COUNT(*) FROM users WHERE status='inactive'");

/* ================= PERFORMANCE DATA ================= */
$stmt = $conn->prepare("
    SELECT
        b.id,
        b.barangay_name,

        COALESCE(act.total_participants,0) AS total_participants,
        COALESCE(act.total_activities,0)   AS total_activities,
        COALESCE(act.used_amount,0)        AS used_amount,
        COALESCE(pr.total_projects,0)      AS total_projects

    FROM barangays b

    LEFT JOIN (
        SELECT barangay_id,
               SUM(participants) AS total_participants,
               COUNT(*) AS total_activities,
               SUM(COALESCE(allocated_budget,0)) AS used_amount
        FROM activities
        GROUP BY barangay_id
    ) act ON act.barangay_id = b.id

    LEFT JOIN (
        SELECT barangay_id,
               COUNT(*) AS total_projects
        FROM projects
        WHERE status='approved'
        GROUP BY barangay_id
    ) pr ON pr.barangay_id = b.id

    ORDER BY b.barangay_name ASC
");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= NORMALIZATION ================= */
$maxActivities    = max(array_column($data, 'total_activities')) ?: 1;
$maxParticipants  = max(array_column($data, 'total_participants')) ?: 1;
$maxProjects      = max(array_column($data, 'total_projects')) ?: 1;

$tempBudgetEff = [];
foreach($data as $d){
    $tempBudgetEff[] = ($d['used_amount'] > 0) ? ($d['total_participants'] / $d['used_amount']) : 0;
}
$maxBudgetEff = max($tempBudgetEff) ?: 1;

/* ================= REAL KPI PERFORMANCE SCORE ================= */
function mlScore($d, $maxActivities, $maxParticipants, $maxProjects, $maxBudgetEff) {

    $activityScore = ($d['total_activities'] / $maxActivities) * 30;
    $participantScore = ($d['total_participants'] / $maxParticipants) * 30;
    $projectScore = ($d['total_projects'] / $maxProjects) * 15;

    $budgetEfficiency = ($d['used_amount'] > 0)
        ? ($d['total_participants'] / $d['used_amount'])
        : 0;

    $budgetScore = ($budgetEfficiency / $maxBudgetEff) * 25;

    return round($activityScore + $participantScore + $projectScore + $budgetScore, 2);
}

foreach ($data as $i => $d) {
    $data[$i]['ml_score'] = mlScore($d, $maxActivities, $maxParticipants, $maxProjects, $maxBudgetEff);
}

usort($data, fn($a,$b) => $b['ml_score'] <=> $a['ml_score']);

$top    = $data[0] ?? null;
$lowest = end($data) ?: null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>

<link rel="stylesheet" href="../assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
*, *::before, *::after{ box-sizing:border-box; margin:0; padding:0; }

body{
    margin:0;
    height:100vh;
    overflow:hidden;
    font-family:'Segoe UI',sans-serif;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
}

.wrapper{
    display:flex;
    width:100%;
    height:100vh;
    overflow:hidden;
}

.main{
    flex:1;
    min-width:0;
    height:100vh;
    overflow-y:auto;
    overflow-x:hidden;
    padding:20px;
}

.page-title{
    color:#e8eaf0;
    font-size:22px;
    font-weight:600;
    margin-bottom:18px;
    padding-bottom:10px;
    border-bottom:1px solid rgba(255,255,255,0.08);
}

.kpi-grid{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:10px;
    margin-bottom:14px;
}

.kpi-card{
    background:rgba(255,255,255,0.06);
    border:1px solid rgba(255,255,255,0.09);
    border-radius:12px;
    padding:12px 10px;
    text-align:center;
    backdrop-filter:blur(18px);
}

.kpi-icon{
    font-size:18px;
    margin-bottom:4px;
}

.kpi-label{
    font-size:10px;
    color:white;
    margin-bottom:5px;
    text-transform:uppercase;
}

.kpi-value{
    font-size:24px;
    font-weight:700;
    color:#1e3c72;
}

.glass{
    background:rgba(255,255,255,0.05);
    border:1px solid rgba(255,255,255,0.09);
    border-radius:14px;
    padding:16px;
    margin-bottom:14px;
    backdrop-filter:blur(50px);
}

.glass-title{
    font-size:14px;
    font-weight:600;
    color:white;
    margin-bottom:12px;
}

.insight-row{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
}

.insight-badge{
    flex:1;
    min-width:220px;
    background:rgba(255,255,255,0.06);
    border-left:3px solid #5b8af5;
    border-radius:10px;
    padding:12px;
}

.insight-badge.warn{
    border-left-color:#dc3545;
}

.badge-label{
    font-size:11px;
    color:white;
    margin-bottom:4px;
}

.badge-value{
    font-size:15px;
    font-weight:700;
    color:#1e3c72;
}

.badge-score{
    font-size:12px;
    color:#8b93a7;
}

.chart-wrap{
    width:100%;
    height:260px;
}

.footer{
    text-align:center;
    padding:8px;
    color:#5a6070;
    font-size:11px;
    margin-top:4px;
}

.hamburger{
    display:none;
}
.overlay{
    display:none;
}

@media(max-width:1024px){
    .kpi-grid{ grid-template-columns:repeat(3,1fr); }
}

@media(max-width:768px){
    .sidebar{
        position:fixed !important;
        left:-260px !important;
        top:0;
        bottom:0;
        width:240px !important;
        z-index:100;
        transition:left .25s ease;
    }
    .sidebar.open{ left:0 !important; }

    .hamburger{
        display:flex;
        position:fixed;
        top:14px;
        left:14px;
        width:38px;
        height:38px;
        background:#1a1f2e;
        border:none;
        border-radius:8px;
        z-index:200;
        flex-direction:column;
        justify-content:center;
        align-items:center;
        gap:4px;
    }
    .hamburger span{
        width:18px;
        height:2px;
        background:white;
    }

    .overlay.open{
        display:block;
        position:fixed;
        inset:0;
        background:rgba(0,0,0,0.4);
        z-index:99;
    }

    .main{
        padding:60px 14px 20px;
    }

    .kpi-grid{ grid-template-columns:repeat(2,1fr); }
}
</style>
</head>
<body>

<button class="hamburger" onclick="toggleSidebar()">
    <span></span><span></span><span></span>
</button>
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<div class="wrapper">

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <h1 class="page-title">📊 Admin Analytics Dashboard</h1>

    <div class="kpi-grid">
        <div class="kpi-card"><div class="kpi-icon">🏘️</div><div class="kpi-label">Barangays</div><div class="kpi-value"><?= $totalBarangays ?></div></div>
        <div class="kpi-card"><div class="kpi-icon">👥</div><div class="kpi-label">Total Users</div><div class="kpi-value"><?= $totalUsers ?></div></div>
        <div class="kpi-card"><div class="kpi-icon">🟢</div><div class="kpi-label">Active Users</div><div class="kpi-value"><?= $activeUsers ?></div></div>
        <div class="kpi-card"><div class="kpi-icon">🔴</div><div class="kpi-label">Inactive Users</div><div class="kpi-value"><?= $inactiveUsers ?></div></div>
        <div class="kpi-card"><div class="kpi-icon">📁</div><div class="kpi-label">Projects</div><div class="kpi-value"><?= $totalApprovedProjects ?></div></div>
    </div>

    <div class="glass">
        <div class="glass-title">🤖 Barangay Performance Insight</div>
        <div class="insight-row">
            <div class="insight-badge">
                <div class="badge-label">🏆 Highest Performer</div>
                <div class="badge-value"><?= htmlspecialchars($top['barangay_name'] ?? 'N/A') ?></div>
                <div class="badge-score">Performance Score: <?= $top['ml_score'] ?? 0 ?>%</div>
            </div>
            <div class="insight-badge warn">
                <div class="badge-label">⚠ Lowest Performer</div>
                <div class="badge-value"><?= htmlspecialchars($lowest['barangay_name'] ?? 'N/A') ?></div>
                <div class="badge-score">Performance Score: <?= $lowest['ml_score'] ?? 0 ?>%</div>
            </div>
        </div>
    </div>

    <div class="glass">
        <div class="glass-title">📈 Real Barangay Performance Graph</div>
        <div class="chart-wrap">
            <canvas id="chart"></canvas>
        </div>
    </div>

    <div class="footer">
        © 2026 SK Decision Support System | Responsive Community Planning Platform
    </div>

</div>
</div>

<script>
const chartData = <?= json_encode($data) ?>;

new Chart(document.getElementById('chart'), {
    type:'bar',
    data:{
        labels: chartData.map(x=>x.barangay_name),
        datasets:[{
            label:'Performance Score (%)',
            data: chartData.map(x=>x.ml_score),
            backgroundColor:'rgba(91,138,245,0.7)',
            borderColor:'rgba(91,138,245,1)',
            borderWidth:1,
            borderRadius:6
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{
            legend:{ labels:{ color:'#c5cad8' } }
        },
        scales:{
            x:{ ticks:{ color:'#c5cad8' } },
            y:{
                beginAtZero:true,
                max:100,
                ticks:{ color:'#c5cad8' }
            }
        }
    }
});

function toggleSidebar(){
    document.querySelector('.sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('open');
}
</script>

</body>
</html>