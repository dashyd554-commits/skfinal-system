<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= KPI COUNTS ================= */
function getCount($conn, $sql) {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
}

$totalUsers = getCount($conn, "SELECT COUNT(*) AS total FROM users");
$totalBarangays = getCount($conn, "SELECT COUNT(*) AS total FROM barangays");
$totalActivities = getCount($conn, "SELECT COUNT(*) AS total FROM activities");

/* ================= MAIN DATA QUERY ================= */
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.barangay_name,

        COALESCE(SUM(a.participants),0) AS participants,
        COUNT(a.id) AS activities,

        COALESCE(bu.total_amount,0) AS annual_budget,
        COALESCE(SUM(a.allocated_budget),0) AS budget_used,

        (COALESCE(bu.total_amount,0) - COALESCE(SUM(a.allocated_budget),0)) AS remaining

    FROM barangays b
    LEFT JOIN activities a ON b.id = a.barangay_id
    LEFT JOIN budgets bu ON bu.barangay_id = b.id

    GROUP BY b.id, b.barangay_name, bu.total_amount
    ORDER BY b.barangay_name
");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ML FUNCTION ================= */
function mlScore($d) {

    $participants = $d['participants'];
    $activities = $d['activities'];
    $budgetUsed = $d['budget_used'];
    $budget = $d['annual_budget'] ?: 1;

    $efficiency = ($budgetUsed > 0) ? ($participants / $budgetUsed) : 0;
    $budgetRatio = $budgetUsed / $budget;

    $score = (
        ($efficiency * 40) +
        ($activities * 5) +
        ($budgetRatio * 55)
    );

    return min(100, round($score, 2));
}

/* ================= APPLY ML ================= */
foreach ($data as $i => $d) {
    $data[$i]['ml'] = mlScore($d);
}

/* ================= SORT ================= */
usort($data, fn($a,$b) => $b['ml'] <=> $a['ml']);

$topBarangay = $data[0]['barangay_name'] ?? "N/A";
$topScore = $data[0]['ml'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<!-- ✅ CHART.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
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
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>📊 Barangay Performance Dashboard</h2>
    </div>

    <!-- KPI -->
    <div class="glass">
        <h3>System Overview</h3>
        <p>Barangays: <?= $totalBarangays ?></p>
        <p>Users: <?= $totalUsers ?></p>
        <p>Activities: <?= $totalActivities ?></p>
        <p><b>Top Barangay:</b> <?= $topBarangay ?> (<?= $topScore ?>%)</p>
    </div>

    <!-- 📈 GRAPH (RESTORED) -->
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
                <td><?= $d['participants'] ?></td>
                <td><?= $d['activities'] ?></td>
                <td>₱<?= number_format($d['annual_budget'],2) ?></td>
                <td>₱<?= number_format($d['budget_used'],2) ?></td>
                <td>₱<?= number_format($d['remaining'],2) ?></td>
                <td><b><?= $d['ml'] ?>%</b></td>
            </tr>
            <?php } ?>

        </table>
    </div>

</div>

<!-- 📊 GRAPH SCRIPT -->
<script>
const labels = <?= json_encode(array_column($data, 'barangay_name')) ?>;
const scores = <?= json_encode(array_column($data, 'ml')) ?>;

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