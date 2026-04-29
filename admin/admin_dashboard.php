<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= KPI ================= */
function getCount($conn, $sql) {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
}

$totalUsers = getCount($conn, "SELECT COUNT(*) AS total FROM users");
$pendingUsers = getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE status='pending'");
$totalBarangays = getCount($conn, "SELECT COUNT(*) AS total FROM barangays");
$totalActivities = getCount($conn, "SELECT COUNT(*) AS total FROM activities");

/* ================= BARANGAY DATA (FIXED) ================= */
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.barangay_name,

        COALESCE(SUM(a.participants),0) AS total_participants,
        COUNT(a.id) AS total_activities,

        COALESCE(bu.total_amount,0) AS annual_budget,
        COALESCE(SUM(a.allocated_budget),0) AS budget_used,

        (COALESCE(bu.total_amount,0) - COALESCE(SUM(a.allocated_budget),0)) AS remaining

    FROM barangays b
    LEFT JOIN activities a ON b.id = a.barangay_id
    LEFT JOIN budgets bu ON bu.barangay_id = b.id

    GROUP BY b.id, b.barangay_name, bu.total_amount
    ORDER BY total_participants DESC
");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ML SCORE ================= */
function mlScore($d) {

    $participants = $d['total_participants'];
    $activities = $d['total_activities'];
    $budgetUsed = $d['budget_used'];
    $budget = $d['annual_budget'] ?: 1;

    $efficiency = ($budgetUsed > 0) ? ($participants / $budgetUsed) : 0;
    $budgetRatio = $budgetUsed / $budget;

    $score = (
        ($efficiency * 50) +
        ($activities * 10) +
        ($budgetRatio * 40)
    );

    return min(100, round($score, 2));
}

/* ================= APPLY ML ================= */
foreach ($data as $i => $d) {
    $data[$i]['ml_score'] = mlScore($d);
}

/* ================= TOP BARANGAY ================= */
usort($data, fn($a,$b) => $b['ml_score'] <=> $a['ml_score']);

$topBarangay = $data[0]['barangay_name'] ?? "N/A";
$topScore = $data[0]['ml_score'] ?? 0;

/* ================= SYSTEM ML ================= */
$avgML = count($data) > 0
    ? array_sum(array_column($data, 'ml_score')) / count($data)
    : 0;

$mlScore = round($avgML, 2);

if ($mlScore >= 70) {
    $mlStatus = "HIGH SYSTEM ENGAGEMENT";
    $mlColor = "green";
    $mlInsight = "Barangays are highly active and efficient.";
} elseif ($mlScore >= 40) {
    $mlStatus = "MODERATE ENGAGEMENT";
    $mlColor = "orange";
    $mlInsight = "Some barangays need improvement.";
} else {
    $mlStatus = "LOW ENGAGEMENT";
    $mlColor = "red";
    $mlInsight = "System activity is weak.";
}
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
    grid-template-columns:repeat(4,1fr);
    gap:15px;
}

.card{
    padding:20px;
    text-align:center;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th {
    background: #dc3545;
    color: white;
    padding: 10px;
}

td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    text-align: center;
}

.glass {
    padding: 20px;
    margin-top: 20px;
}

.ml-box {
    border-left: 6px solid;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>📊 Barangay AI Performance Dashboard</h2>
    </div>

    <!-- KPI GRID -->
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

    <!-- ML INSIGHT -->
    <div class="glass ml-box" style="border-color:<?= $mlColor ?>;">
        <h3>🤖 AI Insight</h3>
        <p><b>Status:</b> <?= $mlStatus ?></p>
        <p><b>System ML Score:</b> <?= $mlScore ?>%</p>
        <p><b>Top Barangay:</b> <?= $topBarangay ?> (<?= $topScore ?>%)</p>
        <p><?= $mlInsight ?></p>
    </div>

    <!-- GRAPH -->
    <div class="glass">
        <h3>📈 ML Performance Graph</h3>
        <canvas id="mlChart"></canvas>
    </div>

    <!-- TABLE -->
    <div class="glass">
        <h3>📊 Barangay Analytics</h3>

        <table>
            <tr>
                <th>Barangay</th>
                <th>Participants</th>
                <th>Activities</th>
                <th>Annual Budget</th>
                <th>Budget Used</th>
                <th>Remaining</th>
                <th>ML Score</th>
            </tr>

            <?php foreach($data as $d){ ?>
            <tr>
                <td><?= htmlspecialchars($d['barangay_name']) ?></td>
                <td><?= $d['total_participants'] ?></td>
                <td><?= $d['total_activities'] ?></td>
                <td>₱<?= number_format($d['annual_budget'],2) ?></td>
                <td>₱<?= number_format($d['budget_used'],2) ?></td>
                <td>₱<?= number_format($d['remaining'],2) ?></td>
                <td><b><?= $d['ml_score'] ?>%</b></td>
            </tr>
            <?php } ?>

        </table>
    </div>

</div>

<!-- GRAPH SCRIPT -->
<script>
const labels = <?= json_encode(array_column($data, 'barangay_name')) ?>;
const scores = <?= json_encode(array_column($data, 'ml_score')) ?>;

new Chart(document.getElementById('mlChart'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'ML Score (%)',
            data: scores,
            borderWidth: 1
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

</body>
</html>