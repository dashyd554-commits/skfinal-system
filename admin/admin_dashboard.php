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

/* ================= BARANGAY PERFORMANCE (FIXED JOIN) ================= */
$stmt = $conn->prepare("
    SELECT 
        b.barangay_name,
        COALESCE(SUM(a.participants),0) AS total_participants,
        COUNT(a.id) AS total_activities
    FROM barangays b
    LEFT JOIN activities a ON b.id = a.barangay_id
    GROUP BY b.id, b.barangay_name
    ORDER BY total_participants DESC
");
$stmt->execute();
$barangayStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= SAFE TOP BARANGAY ================= */
$topBarangay = "N/A";
$topParticipants = 0;

if (!empty($barangayStats)) {
    $topBarangay = $barangayStats[0]['barangay_name'] ?? "N/A";
    $topParticipants = $barangayStats[0]['total_participants'] ?? 0;
}

/* ================= ML SCORE ================= */
$engagementScore = ($totalActivities > 0)
    ? ($totalParticipants / $totalActivities)
    : 0;

$mlScore = min(100, round($engagementScore / 10, 2));

if ($mlScore >= 70) {
    $mlStatus = "HIGH SYSTEM ENGAGEMENT";
    $mlColor = "green";
    $mlInsight = "Barangays are actively engaged in youth programs.";
}
elseif ($mlScore >= 40) {
    $mlStatus = "MODERATE ENGAGEMENT";
    $mlColor = "orange";
    $mlInsight = "Some barangays need improvement in participation.";
}
else {
    $mlStatus = "LOW ENGAGEMENT";
    $mlColor = "red";
    $mlInsight = "Many barangays are inactive or underperforming.";
}

/* ================= AUDIT LOGS (FIXED) ================= */
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
    border-bottom:1px solid #ddd;
}
.ml-box{
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
        <h2>🛠 Admin Dashboard</h2>
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

    <!-- ML -->
    <div class="glass ml-box" style="border-color:<?= $mlColor ?>;">
        <h3>🤖 AI Insight</h3>
        <p><b>Status:</b> <?= $mlStatus ?></p>
        <p><b>Score:</b> <?= $mlScore ?>%</p>
        <p><b>Top Barangay:</b> <?= htmlspecialchars($topBarangay) ?> (<?= $topParticipants ?>)</p>
        <p><?= $mlInsight ?></p>
    </div>

    <!-- BARANGAY TABLE -->
    <div class="glass" style="margin-top:20px; padding:20px;">
        <h3>📊 Barangay Ranking</h3>

        <table>
            <tr>
                <th>Barangay</th>
                <th>Participants</th>
                <th>Activities</th>
            </tr>

            <?php foreach($barangayStats as $b){ ?>
            <tr>
                <td><?= htmlspecialchars($b['barangay_name']) ?></td>
                <td><?= $b['total_participants'] ?></td>
                <td><?= $b['total_activities'] ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>

    <!-- AUDIT LOG -->
    <div class="glass" style="margin-top:20px; padding:20px;">
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
                <td><?= htmlspecialchars($log['action_type'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($log['description'] ?? 'N/A') ?></td>
                <td><?= $log['action_time'] ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>

</div>

</body>
</html>