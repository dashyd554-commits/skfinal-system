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
$pendingUsers = getCount($conn, "SELECT COUNT(*) FROM users WHERE status='pending'");
$totalBarangays = getCount($conn, "SELECT COUNT(*) FROM barangays");
$totalActivities = getCount($conn, "SELECT COUNT(*) FROM activities");
$totalApprovedProjects = getCount($conn, "SELECT COUNT(*) FROM projects WHERE status='approved'");

/* ================= LATEST BUDGET ================= */
$totalBudget = getCount($conn, "SELECT COALESCE(SUM(total_amount),0) FROM budgets");

/* ================= CORRECT BARANGAY ANALYTICS ================= */
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.barangay_name,

        COALESCE(act.total_participants,0) AS total_participants,
        COALESCE(act.total_activities,0) AS total_activities,
        COALESCE(act.used_amount,0) AS used_amount,

        COALESCE(bg.total_amount,0) AS total_amount,

        (COALESCE(bg.total_amount,0) - COALESCE(act.used_amount,0)) AS remaining_budget

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

    LEFT JOIN (
        SELECT DISTINCT ON (barangay_id)
            barangay_id,
            total_amount
        FROM budgets
        ORDER BY barangay_id, year DESC, id DESC
    ) bg ON bg.barangay_id = b.id

    ORDER BY b.barangay_name ASC
");

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ML SCORE ================= */
function mlScore($d) {

    $participants = $d['total_participants'];
    $activities = $d['total_activities'];
    $budgetUsed = $d['used_amount'];
    $budget = max($d['total_amount'], 1);

    $efficiency = ($budgetUsed > 0) ? ($participants / $budgetUsed) : 0;
    $budgetRatio = $budgetUsed / $budget;

    $score = ($efficiency * 50) + ($activities * 10) + ($budgetRatio * 40);

    return min(100, round($score, 2));
}

/* APPLY ML */
foreach ($data as $i => $d) {
    $data[$i]['ml_score'] = mlScore($d);
}

/* SORT */
usort($data, fn($a,$b) => $b['ml_score'] <=> $a['ml_score']);

$top = $data[0] ?? null;
$lowest = $data[count($data)-1] ?? null;

/* ================= AUDIT LOG ================= */
$stmt = $conn->prepare("
    SELECT a.action_type, a.action_time, u.username
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.id
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
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(20px);
    border-radius: 15px;
    padding: 20px;
    margin-top:20px;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#1e3c72;
    color:white;
    padding:10px;
}

td{
    padding:10px;
    text-align:center;
    border-bottom:1px solid #ddd;
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
        <h3>Users</h3>
        <h2><?= $totalUsers ?></h2>
    </div>

    <div class="glass card">
        <h3>Pending</h3>
        <h2><?= $pendingUsers ?></h2>
    </div>

    <div class="glass card">
        <h3>Projects</h3>
        <h2><?= $totalApprovedProjects ?></h2>
    </div>

    <div class="glass card">
        <h3>Budget</h3>
        <h2>₱<?= number_format($totalBudget) ?></h2>
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
            },
            {
                label: 'Participants',
                data: chartData.map(x => x.total_participants)
            },
            {
                label: 'Used Budget',
                data: chartData.map(x => x.used_amount)
            },
            {
                label: 'Remaining Budget',
                data: chartData.map(x => x.remaining_budget)
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