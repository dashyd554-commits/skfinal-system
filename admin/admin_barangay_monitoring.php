<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= OPTIMIZED BARANGAY QUERY ================= */
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.barangay_name,

        COALESCE(COUNT(DISTINCT a.id),0) AS total_activities,
        COALESCE(SUM(a.participants),0) AS total_participants,

        COALESCE(bu.total_amount,0) AS total_amount,
        COALESCE(bu.budget_used,0) AS budget_used,
        COALESCE(bu.remaining_budget,0) AS remaining_budget

    FROM barangays b

    LEFT JOIN activities a 
        ON a.barangay_id = b.id

    LEFT JOIN budgets bu 
        ON bu.barangay_id = b.id
        AND bu.year = (
            SELECT MAX(year) FROM budgets WHERE barangay_id = b.id
        )

    GROUP BY 
        b.id, b.barangay_name,
        bu.total_amount,
        bu.budget_used,
        bu.remaining_budget

    ORDER BY total_participants DESC
");

$stmt->execute();
$barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ML SCORE ================= */
function computeScore($row) {

    $participants = $row['total_participants'] ?? 0;
    $activities = $row['total_activities'] ?? 0;
    $budgetUsed = $row['budget_used'] ?? 0;
    $budget = $row['total_amount'] ?: 1;

    $engagement = ($participants + ($activities * 10));
    $budgetEfficiency = ($budgetUsed > 0) ? ($participants / $budgetUsed) : 0;
    $budgetRatio = $budgetUsed / $budget;

    $score = ($engagement * 0.4) + ($budgetEfficiency * 50) + ($budgetRatio * 30);

    return min(100, round($score, 2));
}

/* ================= PROCESS ================= */
foreach ($barangays as $i => $b) {
    $barangays[$i]['ml_score'] = computeScore($b);
}

/* ================= TOP BARANGAY ================= */
usort($barangays, fn($a,$b) => $b['ml_score'] <=> $a['ml_score']);

$top = $barangays[0]['barangay_name'] ?? 'N/A';
$topScore = $barangays[0]['ml_score'] ?? 0;

/* ================= TOTAL ================= */
$totalBarangays = count($barangays);
?>

<!DOCTYPE html>
<html>
<head>
<title>Barangay Monitoring</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.grid {
    display:grid;
    grid-template-columns: repeat(4,1fr);
    gap:15px;
}

.card {
    padding:20px;
    text-align:center;
}

.glass {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(500px);
    border-radius: 15px;
    padding: 20px;
}

table {
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

th {
    background:#007bff;
    color:white;
    padding:10px;
}

td {
    padding:10px;
    border-bottom:1px solid #ddd;
    text-align:center;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>🏘️ Barangay Monitoring (Real-Time)</h2>
    </div>

    <!-- KPI -->
    <div class="grid">

        <div class="glass card">
            <h3>Total Barangays</h3>
            <h2><?= $totalBarangays ?></h2>
        </div>

        <div class="glass card">
            <h3>Top Barangay</h3>
            <h2><?= $top ?></h2>
        </div>

        <div class="glass card">
            <h3>Top Score</h3>
            <h2><?= $topScore ?>%</h2>
        </div>

        <div class="glass card">
            <h3>Status</h3>
            <h2>LIVE</h2>
        </div>

    </div>

    <!-- TABLE -->
    <div class="glass">
        <h3>📊 Barangay Performance</h3>

        <table>
            <tr>
                <th>Barangay</th>
                <th>Activities</th>
                <th>Participants</th>
                <th>Budget</th>
                <th>Used</th>
                <th>Remaining</th>
                <th>ML Score</th>
            </tr>

            <?php foreach ($barangays as $b) { ?>
            <tr>
                <td><?= htmlspecialchars($b['barangay_name']) ?></td>
                <td><?= $b['total_activities'] ?></td>
                <td><?= $b['total_participants'] ?></td>
                <td>₱<?= number_format($b['total_amount']) ?></td>
                <td>₱<?= number_format($b['budget_used']) ?></td>
                <td>₱<?= number_format($b['remaining_budget']) ?></td>
                <td><b><?= $b['ml_score'] ?>%</b></td>
            </tr>
            <?php } ?>

        </table>
    </div>

</div>

</body>
</html>