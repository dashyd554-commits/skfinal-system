<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

/* ================= BARANGAY LIST ================= */
$stmt = $conn->prepare("
    SELECT * FROM barangays ORDER BY barangay_name ASC
");
$stmt->execute();
$barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= BARANGAY PERFORMANCE ================= */
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.barangay_name,

        COALESCE(SUM(bu.annual_budget),0) AS total_budget,
        COALESCE(SUM(bu.budget_used),0) AS used_budget,

        COALESCE(SUM(p.vote_yes),0) AS total_approved_projects,

        COALESCE(SUM(a.participants),0) AS total_participants

    FROM barangays b
    LEFT JOIN budgets bu ON bu.barangay_id = b.id
    LEFT JOIN proposals p ON p.barangay_id = b.id AND p.status = 'approved'
    LEFT JOIN activities a ON a.barangay_id = b.id

    GROUP BY b.id
    ORDER BY used_budget DESC
");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= TOP BARANGAY ================= */
$top = $data[0] ?? null;
?>

<!DOCTYPE html>
<html>
<head>
<title>Barangay Monitoring</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.grid {
    display:grid;
    grid-template-columns: repeat(3, 1fr);
    gap:15px;
}

.card {
    background:rgba(255,255,255,0.2);
    backdrop-filter:blur(10px);
    padding:20px;
    border-radius:12px;
    text-align:center;
}

.section {
    margin-top:20px;
    background:rgba(255,255,255,0.2);
    backdrop-filter:blur(15px);
    padding:20px;
    border-radius:12px;
}

table {
    width:100%;
    border-collapse:collapse;
}

th {
    background:#0d6efd;
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
        <h2>🏘 Barangay Monitoring</h2>
        <p>Compare performance, budget usage, and development progress</p>
    </div>

    <!-- TOP BARANGAY -->
    <div class="section">
        <h3>🏆 Top Performing Barangay</h3>

        <?php if ($top) { ?>
            <p><b><?= $top['barangay_name'] ?></b></p>
            <p>💰 Budget Used: ₱ <?= number_format($top['used_budget']) ?></p>
            <p>📊 Approved Projects: <?= $top['total_approved_projects'] ?></p>
            <p>👥 Participants: <?= $top['total_participants'] ?></p>
        <?php } ?>
    </div>

    <!-- BARANGAY TABLE -->
    <div class="section">
        <h3>📊 Barangay Performance Comparison</h3>

        <table>
            <tr>
                <th>Barangay</th>
                <th>Total Budget</th>
                <th>Used Budget</th>
                <th>Approved Projects</th>
                <th>Participants</th>
                <th>Efficiency (%)</th>
            </tr>

            <?php foreach ($data as $row) { 

                $efficiency = ($row['total_budget'] > 0)
                    ? ($row['used_budget'] / $row['total_budget']) * 100
                    : 0;
            ?>

            <tr>
                <td><?= $row['barangay_name'] ?></td>
                <td>₱ <?= number_format($row['total_budget']) ?></td>
                <td>₱ <?= number_format($row['used_budget']) ?></td>
                <td><?= $row['total_approved_projects'] ?></td>
                <td><?= $row['total_participants'] ?></td>
                <td><?= number_format($efficiency,2) ?>%</td>
            </tr>

            <?php } ?>

        </table>
    </div>

</div>

</body>
</html>