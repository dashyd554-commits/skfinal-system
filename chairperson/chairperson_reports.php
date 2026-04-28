<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= SAFE ACTIVITIES ================= */
try {
    $stmt = $conn->prepare("SELECT * FROM activities ORDER BY date DESC NULLS LAST");
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $activities = [];
}

/* ================= SAFE BUDGETS ================= */
try {
    $stmt = $conn->prepare("SELECT * FROM budgets ORDER BY year DESC NULLS LAST");
    $stmt->execute();
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $budgets = [];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Reports</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.main {
    margin-left: 220px;
    padding: 20px;
}

.section {
    padding: 20px;
    margin-bottom: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    background: white;
    border-radius: 10px;
    overflow: hidden;
}

th {
    background: #2d89ef;
    color: white;
    padding: 12px;
    text-align: left;
}

td {
    padding: 12px;
    border-bottom: 1px solid #eee;
}

tr:hover {
    background: #f5f5f5;
}

.empty {
    text-align: center;
    padding: 20px;
    color: gray;
}
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>📊 Reports Dashboard</h2>
        <p>System analytics and records overview</p>
    </div>

    <!-- ================= ACTIVITIES ================= -->
    <div class="glass section">

        <h3>📅 Activities Report</h3>

        <table>
            <tr>
                <th>Title</th>
                <th>Participants</th>
                <th>Date</th>
            </tr>

            <?php if (!empty($activities)) { ?>
                <?php foreach ($activities as $row) { ?>
                <tr>
                    <td><?= htmlspecialchars($row['title'] ?? 'N/A') ?></td>
                    <td><?= $row['participants'] ?? 0 ?></td>
                    <td><?= $row['date'] ?? 'N/A' ?></td>
                </tr>
                <?php } ?>
            <?php } else { ?>
                <tr>
                    <td colspan="3" class="empty">No activities found</td>
                </tr>
            <?php } ?>

        </table>
    </div>

    <!-- ================= BUDGET ================= -->
    <div class="glass section">

        <h3>💰 Budget Report</h3>

        <?php if (!empty($budgets)) { ?>

        <table>
            <tr>
                <th>Amount</th>
                <th>Year</th>
            </tr>

            <?php foreach ($budgets as $row) { ?>
            <tr>
                <td>₱ <?= number_format($row['amount'] ?? 0) ?></td>
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