<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= SAFE ACTIVITIES ONLY THIS BARANGAY ================= */
try {
    $stmt = $conn->prepare("
        SELECT *
        FROM activities
        WHERE barangay_id = :barangay_id
        ORDER BY date DESC NULLS LAST
    ");
    $stmt->bindValue(':barangay_id', $barangay_id);
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $activities = [];
}

/* ================= SAFE BUDGETS ONLY THIS BARANGAY ================= */
try {
    $stmt = $conn->prepare("
        SELECT *
        FROM budgets
        WHERE barangay_id = :barangay_id
        ORDER BY year DESC NULLS LAST
    ");
    $stmt->bindValue(':barangay_id', $barangay_id);
    $stmt->execute();
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $budgets = [];
}

/* ================= TOTAL SUMMARY ================= */
$totalActivities = count($activities);
$totalParticipants = 0;
$totalBudget = 0;

foreach ($activities as $a) {
    $totalParticipants += $a['participants'] ?? 0;
}

if (!empty($budgets)) {
    $totalBudget = $budgets[0]['total_amount'] ?? 0;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Chairman Reports</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.main{
    margin-left:190px;
    padding:20px;
    width:calc(100% - 200px);
    overflow-x:hidden;
}

.grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:15px;
    margin-bottom:20px;
}

.card{
    text-align:center;
    padding:20px;
}

.section{
    padding:20px;
    margin-bottom:20px;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
    background:white;
    border-radius:10px;
    overflow:hidden;
}

th{
    background:#2d89ef;
    color:white;
    padding:12px;
    text-align:left;
}

td{
    padding:12px;
    border-bottom:1px solid #eee;
}

tr:hover{
    background:#f5f5f5;
}

.glass{
    background:rgba(255,255,255,0.2);
    backdrop-filter:blur(500px);
    border-radius:15px;
    padding:20px;
}

.empty{
    text-align:center;
    padding:20px;
    color:gray;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>📊 Barangay Reports Dashboard</h2>
        <p>Chairman monitoring reports for your barangay only</p>
    </div>

    <!-- KPI SUMMARY -->
    <div class="grid">

        <div class="glass card">
            <h3>📅 Activities</h3>
            <h2><?= $totalActivities ?></h2>
        </div>

        <div class="glass card">
            <h3>👥 Participants</h3>
            <h2><?= $totalParticipants ?></h2>
        </div>

        <div class="glass card">
            <h3>💰 Latest Budget</h3>
            <h2>₱<?= number_format($totalBudget,2) ?></h2>
        </div>

    </div>

    <!-- ACTIVITIES REPORT -->
    <div class="glass section">

        <h3>📅 Activities Report</h3>

        <table>
            <tr>
                <th>Title</th>
                <th>Participants</th>
                <th>Allocated Budget</th>
                <th>Date</th>
            </tr>

            <?php if (!empty($activities)) { ?>
                <?php foreach ($activities as $row) { ?>
                <tr>
                    <td><?= htmlspecialchars($row['title'] ?? 'N/A') ?></td>
                    <td><?= $row['participants'] ?? 0 ?></td>
                    <td>₱<?= number_format($row['allocated_budget'] ?? 0,2) ?></td>
                    <td><?= $row['date'] ?? 'N/A' ?></td>
                </tr>
                <?php } ?>
            <?php } else { ?>
                <tr>
                    <td colspan="4" class="empty">No activities found</td>
                </tr>
            <?php } ?>

        </table>
    </div>

    <!-- BUDGET REPORT -->
    <div class="glass section">

        <h3>💰 Budget History Report</h3>

        <?php if (!empty($budgets)) { ?>

        <table>
            <tr>
                <th>Annual Budget</th>
                <th>Year</th>
            </tr>

            <?php foreach ($budgets as $row) { ?>
            <tr>
                <td>₱<?= number_format($row['total_amount'] ?? 0,2) ?></td>
                <td><?= $row['year'] ?? 'N/A' ?></td>
            </tr>
            <?php } ?>

        </table>

        <?php } else { ?>
            <p class="empty">No budget data available.</p>
        <?php } ?>

    </div>

</div>

</body>
</html>