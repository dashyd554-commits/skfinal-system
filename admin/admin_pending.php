<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= GET PENDING USERS (FIXED JOIN) ================= */
$stmt = $conn->prepare("
    SELECT 
        users.*,
        barangays.barangay_name
    FROM users
    LEFT JOIN barangays ON users.barangay_id = barangays.id
    WHERE users.status = 'pending'
    ORDER BY users.id DESC
");
$stmt->execute();

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pendingCount = count($users);

/* ================= BARANGAY DISTRIBUTION ML ================= */
$barangayCount = [];
$roleCount = [];

foreach ($users as $u) {

    $b = $u['barangay_name'] ?? 'Unknown';  // FIXED HERE
    $r = $u['role'] ?? 'unknown';

    $barangayCount[$b] = ($barangayCount[$b] ?? 0) + 1;
    $roleCount[$r] = ($roleCount[$r] ?? 0) + 1;
}

/* ================= MOST ACTIVE BARANGAY ================= */
$mostActiveBarangay = "N/A";

if (!empty($barangayCount)) {
    arsort($barangayCount);
    $mostActiveBarangay = array_key_first($barangayCount);
}

/* ================= MOST REQUESTED ROLE ================= */
$mostRequestedRole = "N/A";

if (!empty($roleCount)) {
    arsort($roleCount);
    $mostRequestedRole = array_key_first($roleCount);
}

/* ================= ML RISK MODEL ================= */
$mlScore = min(100, $pendingCount * 5);

if ($mlScore >= 70) {

    $mlStatus = "HIGH RISK";
    $mlColor = "red";
    $mlInsight = "Approval backlog is critical and may delay system operations.";
    $mlRecommendation = "Assign additional reviewers or batch approve pending users.";

} elseif ($mlScore >= 40) {

    $mlStatus = "MODERATE LOAD";
    $mlColor = "orange";
    $mlInsight = "Pending queue is growing and requires monitoring.";
    $mlRecommendation = "Process approvals consistently to avoid accumulation.";

} else {

    $mlStatus = "NORMAL LOAD";
    $mlColor = "green";
    $mlInsight = "System approval flow is stable.";
    $mlRecommendation = "Maintain current approval pace.";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Pending Users</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.main {
    margin-left: 220px;
    width: calc(100% - 220px);
    padding: 20px;
}

.glass {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    background: white;
}

th {
    background: #dc3545;
    color: white;
    padding: 12px;
}

td {
    padding: 12px;
    border-bottom: 1px solid #eee;
}

tr:hover {
    background: #f8f9fa;
}

.badge {
    background: orange;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
}

.ml-box {
    margin-bottom: 20px;
    padding: 20px;
    border-left: 5px solid;
}
</style>

</head>
<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>⏳ Pending Users</h2>
        <p>AI-assisted approval monitoring system</p>
    </div>

    <!-- ================= ML PANEL ================= -->
    <div class="glass ml-box" style="border-color: <?= $mlColor ?>;">

        <h3>🤖 AI Approval Intelligence</h3>

        <p><b>Status:</b> <?= $mlStatus ?></p>
        <p><b>Pending Users:</b> <?= $pendingCount ?></p>
        <p><b>Most Requested Role:</b> <?= $mostRequestedRole ?></p>
        <p><b>Most Active Barangay:</b> <?= $mostActiveBarangay ?></p>

        <p><b>Insight:</b> <?= $mlInsight ?></p>
        <p><b>Recommendation:</b> <?= $mlRecommendation ?></p>

    </div>

    <!-- ================= TABLE ================= -->
    <div class="glass">

        <h3>Users Waiting Approval</h3>

        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Barangay</th>
                <th>Status</th>
                <th>Action</th>
            </tr>

            <?php if ($pendingCount > 0) { ?>
                <?php foreach ($users as $row) { ?>
                <tr>
                    <td><?= $row['id']; ?></td>
                    <td><?= htmlspecialchars($row['username']); ?></td>
                    <td><?= htmlspecialchars($row['role']); ?></td>

                    <!-- FIXED BARANGAY NAME -->
                    <td><?= htmlspecialchars($row['barangay_name'] ?? 'N/A'); ?></td>

                    <td><span class="badge">Pending</span></td>
                    <td>
                        <a href="admin_approve_user.php?id=<?= $row['id']; ?>">
                            Approve
                        </a>
                    </td>
                </tr>
                <?php } ?>
            <?php } else { ?>
                <tr>
                    <td colspan="6" style="text-align:center; padding:20px;">
                        No pending users
                    </td>
                </tr>
            <?php } ?>

        </table>

    </div>

</div>

</body>
</html>