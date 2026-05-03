<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= BASIC KPI ================= */
function getCount($conn, $sql) {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchColumn();
}

$totalUsers = getCount($conn, "SELECT COUNT(*) FROM users");
$totalBarangays = getCount($conn, "SELECT COUNT(*) FROM barangays");
$totalActivities = getCount($conn, "SELECT COUNT(*) FROM activities");
$totalApprovedProjects = getCount($conn, "SELECT COUNT(*) FROM projects WHERE status='approved'");

/* ================= REALTIME ACTIVE / INACTIVE USERS ================= */
/*
    NOTE:
    This assumes you have a column like:
    users.status = 'active' or 'inactive'
*/

$activeUsers = getCount($conn, "SELECT COUNT(*) FROM users WHERE status = 'active'");
$inactiveUsers = getCount($conn, "SELECT COUNT(*) FROM users WHERE status = 'inactive'");

/* ================= BARANGAY ANALYTICS ================= */
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.barangay_name,

        COALESCE(act.total_participants,0) AS total_participants,
        COALESCE(act.total_activities,0) AS total_activities,
        COALESCE(act.used_amount,0) AS used_amount,

        (COALESCE(act.used_amount,0)) AS remaining_budget

    FROM barangays b

    LEFT JOIN (
        SELECT 
            barangay_id,
            SUM(participants) AS total_participants,
            COUNT(*) AS total_activities,
            SUM(COALESCE(allocated_budget,0)) AS used_amount
        FROM activities
        GROUP BY barangay_id
    ) act ON act.barangay_id = b.id

    ORDER BY b.barangay_name ASC
");

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ML SCORE ================= */
function mlScore($d) {

    $participants = $d['total_participants'] ?? 0;
    $activities = $d['total_activities'] ?? 0;
    $budgetUsed = $d['used_amount'] ?? 0;

    $efficiency = ($budgetUsed > 0) ? ($participants / $budgetUsed) : 0;

    $score = ($efficiency * 60) + ($activities * 10);

    return min(100, round($score, 2));
}

/* APPLY ML */
foreach ($data as $i => $d) {
    $data[$i]['ml_score'] = mlScore($d);
}

/* SORT */
usort($data, fn($a,$b) => $b['ml_score'] <=> $a['ml_score']);

$top = $data[0] ?? null;
$lowest = end($data) ?: null;

/* ================= AUDIT LOG ================= */
$stmt = $conn->prepare("
    SELECT 
        a.action_type,
        a.action_time,
        a.username
    FROM audit_logs a
    ORDER BY a.action_time DESC
    LIMIT 10
");

$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.grid{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:15px;
}

.card{
    padding:20px;
    text-align:center;
}

.glass{
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(18px);
    border-radius: 15px;
    padding: 20px;
    margin-top:20px;
}

.footer{
    width:100%;
    text-align:center;
    margin-top:25px;
    padding:14px;
    color:white;
    background:rgba(0,0,0,0.25);
    border-radius:10px;
    font-size:13px;
}

h2{
    text-align:center;
    color:whitesmoke;
    margin-bottom:20px;
}

h3{
    color:white;
}

.card h2, p{
    color:#1e3c72;
}

@media(max-width:768px){
    .grid{
        grid-template-columns:repeat(2,1fr);
    }

    .footer{
        font-size:11px;
    }
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>📊 Admin Analytics Dashboard</h2>

<!-- KPI -->
<div class="grid">

    <div class="glass card">
        <h3>Barangays</h3>
        <h2><?= $totalBarangays ?></h2>
    </div>

    <div class="glass card">
        <h3>Total Users</h3>
        <h2><?= $totalUsers ?></h2>
    </div>

    <div class="glass card">
        <h3>Active Users</h3>
        <h2><?= $activeUsers ?></h2>
    </div>

    <div class="glass card">
        <h3>Inactive Users</h3>
        <h2><?= $inactiveUsers ?></h2>
    </div>

    <div class="glass card">
        <h3>Projects</h3>
        <h2><?= $totalApprovedProjects ?></h2>
    </div>

</div>

<!-- AI SUMMARY -->
<div class="glass">
    <h3>🤖 Performance Insight</h3>

    <p><b>🏆 Highest Performer:</b>
        <?= $top['barangay_name'] ?? 'N/A' ?>
        (Score: <?= $top['ml_score'] ?? 0 ?>%)
    </p>

    <p><b>⚠ Lowest Performer:</b>
        <?= $lowest['barangay_name'] ?? 'N/A' ?>
        (Score: <?= $lowest['ml_score'] ?? 0 ?>%)
    </p>
</div>

<!-- CHART -->
<div class="glass">
    <h3>📈 Barangay Performance Overview</h3>
    <canvas id="chart"></canvas>
</div>

<!-- AUDIT -->
<div class="glass">
    <h3>📜 Audit Logs</h3>

    <?php foreach($auditLogs as $log){ ?>
        <div style="padding:8px;border-bottom:1px solid #ddd;">
            <b><?= $log['username'] ?? 'System' ?></b>
            - <?= $log['action_type'] ?>
            <br>
            <small><?= $log['action_time'] ?></small>
        </div>
    <?php } ?>
</div>

</div>

<div class="footer">
    © 2026 SK Decision Support System | Responsive Community Planning Platform
</div>

<script>
const chartData = <?= json_encode($data) ?>;

new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
        labels: chartData.map(x => x.barangay_name),
        datasets: [
            {
                label: 'ML Score',
                data: chartData.map(x => x.ml_score)
            }
        ]
    },
    options: {
        responsive:true,
        scales:{
            y:{ beginAtZero:true }
        }
    }
});
</script>

</body>
</html>