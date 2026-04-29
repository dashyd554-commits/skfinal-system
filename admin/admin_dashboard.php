<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= SAFE COUNT ================= */
function getCount($conn, $sql) {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
}

/* ================= KPI ================= */
$totalUsers = getCount($conn, "SELECT COUNT(*) AS total FROM users");
$pendingUsers = getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE status='pending'");
$totalBarangays = getCount($conn, "SELECT COUNT(*) AS total FROM barangays");
$totalActivities = getCount($conn, "SELECT COUNT(*) AS total FROM activities");
$totalParticipants = getCount($conn, "SELECT COALESCE(SUM(participants),0) AS total FROM activities");

/* ================= PERFORMANCE DATASET ================= */
$stmt = $conn->prepare("
    SELECT 
        b.id AS barangay_id,
        b.barangay_name,

        COALESCE(SUM(a.participants),0) AS total_participants,
        COALESCE(SUM(a.allocated_budget),0) AS spent_budget,
        COUNT(a.id) AS total_activities,

        COALESCE(bu.total_amount,0) AS annual_budget,
        (COALESCE(bu.total_amount,0) - COALESCE(SUM(a.allocated_budget),0)) AS remaining_budget,

        COALESCE(AVG(a.evaluation_score),0) AS avg_score

    FROM barangays b
    LEFT JOIN activities a ON b.id = a.barangay_id
    LEFT JOIN budgets bu ON bu.barangay_id = b.id

    GROUP BY b.id, b.barangay_name, bu.total_amount
");
$stmt->execute();
$barangayStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ML SCORE FUNCTION ================= */
function computeML($d) {
    $participants = $d['total_participants'];
    $budgetUsed = $d['spent_budget'];
    $budgetTotal = $d['annual_budget'] ?: 1;
    $avgScore = $d['avg_score'];

    $efficiency = ($budgetUsed > 0) ? ($participants / $budgetUsed) : 0;
    $budgetUsage = $budgetUsed / $budgetTotal;

    $score = ($efficiency * 40) + ($avgScore * 30) + ($budgetUsage * 30);

    return min(100, round($score, 2));
}

/* ================= APPLY ML SCORE ================= */
foreach ($barangayStats as $i => $b) {
    $barangayStats[$i]['ml_score'] = computeML($b);
}

/* ================= SORT BY ML ================= */
usort($barangayStats, fn($a, $b) => $b['ml_score'] <=> $a['ml_score']);

$topBarangay = $barangayStats[0]['barangay_name'] ?? "N/A";
$topScore = $barangayStats[0]['ml_score'] ?? 0;

/* ================= ML STATUS ================= */
$avgML = count($barangayStats) > 0 
    ? array_sum(array_column($barangayStats, 'ml_score')) / count($barangayStats)
    : 0;

$mlScore = round($avgML, 2);

if ($mlScore >= 70) {
    $mlStatus = "HIGH ENGAGEMENT";
    $mlColor = "green";
} elseif ($mlScore >= 40) {
    $mlStatus = "MODERATE ENGAGEMENT";
    $mlColor = "orange";
} else {
    $mlStatus = "LOW ENGAGEMENT";
    $mlColor = "red";
}

/* ================= AUDIT LOGS ================= */
$stmt = $conn->prepare("
    SELECT username, barangay_name, action_type, description, action_time
    FROM audit_logs
    ORDER BY action_time DESC
    LIMIT 5
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
.grid {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
}
.card {
    padding:20px;
    text-align:center;
}
table {
    width:100%;
    border-collapse:collapse;
}
th {
    background:#dc3545;
    color:white;
    padding:10px;
}
td {
    padding:10px;
    border-bottom:1px solid #ddd;
}
.ml-box {
    padding:20px;
    border-left:5px solid;
    margin-top:20px;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>🛠 Admin Dashboard (REAL-TIME + ML)</h2>
    </div>

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
            <h3>Activities</h3>
            <h2><?= $totalActivities ?></h2>
        </div>

    </div>

    <!-- ML PANEL -->
    <div class="glass ml-box" style="border-color:<?= $mlColor ?>;">
        <h3>🤖 AI INSIGHT (ML SYSTEM)</h3>
        <p><b>System Score:</b> <?= $mlScore ?>%</p>
        <p><b>Status:</b> <?= $mlStatus ?></p>
        <p><b>Top Barangay:</b> <?= $topBarangay ?> (<?= $topScore ?>%)</p>
    </div>

    <!-- CHART -->
    <div class="glass" style="margin-top:20px;padding:20px;">
        <h3>📊 ML Performance Graph</h3>
        <canvas id="mlChart"></canvas>
    </div>

    <!-- BARANGAY TABLE -->
    <div class="glass" style="margin-top:20px;padding:20px;">
        <h3>🏆 Barangay Ranking (ML Based)</h3>

        <table>
            <tr>
                <th>Barangay</th>
                <th>Participants</th>
                <th>Activities</th>
                <th>Budget Used</th>
                <th>Remaining</th>
                <th>ML Score</th>
            </tr>

            <?php foreach($barangayStats as $b){ ?>
            <tr>
                <td><?= htmlspecialchars($b['barangay_name']) ?></td>
                <td><?= $b['total_participants'] ?></td>
                <td><?= $b['total_activities'] ?></td>
                <td><?= number_format($b['spent_budget'],2) ?></td>
                <td><?= number_format($b['remaining_budget'],2) ?></td>
                <td><b><?= $b['ml_score'] ?>%</b></td>
            </tr>
            <?php } ?>

        </table>
    </div>

    <!-- AUDIT LOG -->
    <div class="glass" style="margin-top:20px;padding:20px;">
        <h3>🕘 Audit Logs</h3>

        <table>
            <tr>
                <th>User</th>
                <th>Barangay</th>
                <th>Action</th>
                <th>Description</th>
                <th>Time</th>
            </tr>

            <?php foreach($auditLogs as $log){ ?>
            <tr>
                <td><?= htmlspecialchars($log['username'] ?? 'system') ?></td>
                <td><?= htmlspecialchars($log['barangay_name'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($log['action_type']) ?></td>
                <td><?= htmlspecialchars($log['description']) ?></td>
                <td><?= $log['action_time'] ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>

</div>

<script>
const labels = <?= json_encode(array_column($barangayStats, 'barangay_name')) ?>;
const data = <?= json_encode(array_column($barangayStats, 'ml_score')) ?>;

new Chart(document.getElementById('mlChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'ML Score (%)',
            data: data
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});
</script>

<!-- REAL TIME AUTO REFRESH -->
<script>
setInterval(() => {
    location.reload();
}, 10000);
</script>

</body>
</html>