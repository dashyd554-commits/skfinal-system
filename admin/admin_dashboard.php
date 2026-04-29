<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

/* ================= TOTAL BARANGAYS ================= */
$stmt = $conn->prepare("SELECT COUNT(*) FROM barangays");
$stmt->execute();
$totalBarangays = $stmt->fetchColumn();

/* ================= TOTAL USERS ================= */
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE status='approved'");
$stmt->execute();
$totalUsers = $stmt->fetchColumn();

/* ================= TOTAL BUDGET ================= */
$stmt = $conn->prepare("SELECT COALESCE(SUM(annual_budget),0) FROM budgets");
$stmt->execute();
$totalBudget = $stmt->fetchColumn();

/* ================= TOTAL PROJECTS ================= */
$stmt = $conn->prepare("SELECT COUNT(*) FROM proposals WHERE status='approved'");
$stmt->execute();
$totalProjects = $stmt->fetchColumn();

/* ================= TOP BARANGAY (BY BUDGET USE) ================= */
$stmt = $conn->prepare("
    SELECT b.barangay_name,
           COALESCE(SUM(bu.budget_used),0) AS used
    FROM barangays b
    LEFT JOIN budgets bu ON b.id = bu.barangay_id
    GROUP BY b.id
    ORDER BY used DESC
    LIMIT 1
");
$stmt->execute();
$topBarangay = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= BUDGET UTILIZATION ================= */
$stmt = $conn->prepare("
    SELECT barangay_id,
           SUM(annual_budget) AS total_budget,
           SUM(budget_used) AS used
    FROM budgets
    GROUP BY barangay_id
");
$stmt->execute();
$chart = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= AUDIT FEED ================= */
$stmt = $conn->prepare("
    SELECT a.action, a.created_at, u.username
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
    ORDER BY a.created_at DESC
    LIMIT 8
");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>System Admin Dashboard</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.grid {
    display:grid;
    grid-template-columns: repeat(4, 1fr);
    gap:15px;
}

.card {
    padding:20px;
    background:rgba(255,255,255,0.2);
    backdrop-filter:blur(10px);
    border-radius:12px;
    text-align:center;
}

.section {
    margin-top:20px;
    padding:20px;
    background:rgba(255,255,255,0.2);
    backdrop-filter:blur(15px);
    border-radius:12px;
}

table {
    width:100%;
    border-collapse:collapse;
}

th {
    background:#2d89ef;
    color:white;
    padding:10px;
}

td {
    padding:10px;
    border-bottom:1px solid #ddd;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>🛡 System Admin Dashboard</h2>
    </div>

    <!-- KPI -->
    <div class="grid">

        <div class="card">
            <h3>Barangays</h3>
            <h2><?= $totalBarangays ?></h2>
        </div>

        <div class="card">
            <h3>Approved Users</h3>
            <h2><?= $totalUsers ?></h2>
        </div>

        <div class="card">
            <h3>Total Budget</h3>
            <h2>₱ <?= number_format($totalBudget) ?></h2>
        </div>

        <div class="card">
            <h3>Approved Projects</h3>
            <h2><?= $totalProjects ?></h2>
        </div>

    </div>

    <!-- TOP BARANGAY -->
    <div class="section">
        <h3>🏆 Top Performing Barangay</h3>
        <p>
            <b><?= $topBarangay['barangay_name'] ?? 'N/A' ?></b>
            (Budget Used: ₱ <?= number_format($topBarangay['used'] ?? 0) ?>)
        </p>
    </div>

    <!-- CHART -->
    <div class="section">
        <h3>📊 Budget Utilization</h3>
        <canvas id="chart"></canvas>
    </div>

    <!-- AUDIT -->
    <div class="section">
        <h3>📜 Live Audit Activity</h3>

        <table>
            <tr>
                <th>User</th>
                <th>Action</th>
                <th>Time</th>
            </tr>

            <?php foreach ($logs as $l) { ?>
            <tr>
                <td><?= $l['username'] ?? 'System' ?></td>
                <td><?= $l['action'] ?></td>
                <td><?= $l['created_at'] ?></td>
            </tr>
            <?php } ?>

        </table>
    </div>

</div>

<script>
new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($chart,'barangay_id')) ?>,
        datasets: [{
            label: 'Budget Usage',
            data: <?= json_encode(array_column($chart,'used')) ?>
        }]
    }
});
</script>

</body>
</html>