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

/* ================= EXTRA KPI ================= */
$totalApprovedProjects = getCount($conn, "SELECT COUNT(*) FROM projects WHERE status='approved'");
$totalBudget = getCount($conn, "SELECT COALESCE(SUM(total_amount),0) FROM budgets");

/* ================= BARANGAY ANALYTICS ================= */
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.barangay_name,
        COALESCE(SUM(a.participants),0) AS total_participants,
        COUNT(a.id) AS total_activities,
        COALESCE(SUM(a.allocated_budget),0) AS budget_used,
        COALESCE(bu.total_amount,0) AS total_amount,
        (COALESCE(bu.total_amount,0) - COALESCE(SUM(a.allocated_budget),0)) AS remaining
    FROM barangays b
    LEFT JOIN activities a ON a.barangay_id = b.id
    LEFT JOIN budgets bu ON bu.barangay_id = b.id
    GROUP BY b.id, bu.total_amount
");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ML SCORE ================= */
function mlScore($d) {
    $participants = $d['total_participants'];
    $activities = $d['total_activities'];
    $budgetUsed = $d['budget_used'];
    $budget = $d['total_amount'] ?: 1;

    $efficiency = ($budgetUsed > 0) ? ($participants / $budgetUsed) : 0;
    $budgetRatio = $budgetUsed / $budget;

    $score = ($efficiency * 50) + ($activities * 10) + ($budgetRatio * 40);

    return min(100, round($score, 2));
}

/* APPLY ML */
foreach ($data as $i => $d) {
    $data[$i]['ml_score'] = mlScore($d);
}

/* TOP BARANGAY */
usort($data, fn($a,$b) => $b['ml_score'] <=> $a['ml_score']);

$topBarangay = $data[0]['barangay_name'] ?? "N/A";
$topScore = $data[0]['ml_score'] ?? 0;

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
<title>Admin Dashboard (Real-Time)</title>

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
    backdrop-filter: blur(500px);
    border-radius: 15px;
    padding: 20px;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#dc3545;
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

    <div class="header">
        <h2>📊 Admin Real-Time Monitoring Dashboard</h2>
    </div>

    <!-- KPI -->
    <div class="grid">

        <div class="glass card">
            <h3>Barangays</h3>
            <h2 id="barangays"><?= $totalBarangays ?></h2>
        </div>

        <div class="glass card">
            <h3>Users</h3>
            <h2 id="users"><?= $totalUsers ?></h2>
        </div>

        <div class="glass card">
            <h3>Pending</h3>
            <h2 id="pending"><?= $pendingUsers ?></h2>
        </div>

        <div class="glass card">
            <h3>Projects</h3>
            <h2 id="projects"><?= $totalApprovedProjects ?></h2>
        </div>

        <div class="glass card">
            <h3>Budget</h3>
            <h2 id="budget">₱<?= number_format($totalBudget) ?></h2>
        </div>

    </div>

    <!-- AI INSIGHT -->
    <div class="glass" style="margin-top:20px;">
        <h3>🤖 AI Insight</h3>
        <p><b>Top Barangay:</b> <span id="top_barangay"><?= $topBarangay ?></span></p>
        <p>ML Score: <?= $topScore ?>%</p>
    </div>

    <!-- CHART -->
    <div class="glass" style="margin-top:20px;">
        <h3>📈 Barangay Performance</h3>
        <canvas id="chart"></canvas>
    </div>

    <!-- AUDIT LOG -->
    <div class="glass" style="margin-top:20px;">
        <h3>📜 Live Audit Feed</h3>
        <div id="audit_logs">
            <?php foreach($auditLogs as $log){ ?>
                <div style="padding:8px;border-bottom:1px solid #eee;">
                    <b><?= $log['username'] ?? 'System' ?></b> - <?= $log['action_type'] ?>
                    <br><small><?= $log['action_time'] ?></small>
                </div>
            <?php } ?>
        </div>
    </div>

</div>

<script>
/* ================= CHART ================= */
const labels = <?= json_encode(array_column($data, 'barangay_name')) ?>;
const scores = <?= json_encode(array_column($data, 'ml_score')) ?>;
const participants = <?= json_encode(array_column($data, 'total_participants')) ?>;
const activities = <?= json_encode(array_column($data, 'total_activities')) ?>;
const usedBudget = <?= json_encode(array_column($data, 'budget_used')) ?>;

let myChart = new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'ML Score',
                data: scores
            },
            {
                label: 'Participants',
                data: participants
            },
            {
                label: 'Activities',
                data: activities
            },
            {
                label: 'Budget Used',
                data: usedBudget
            }
        ]
    },
    options: {
        responsive:true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

</body>
</html>