<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= GET USERS (FIXED JOIN) ================= */
$stmt = $conn->prepare("
    SELECT 
        users.*,
        barangays.barangay_name
    FROM users
    LEFT JOIN barangays ON users.barangay_id = barangays.id
    ORDER BY users.id DESC
");
$stmt->execute();

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= BASIC COUNTS ================= */
$totalUsers = count($users);
$pendingCount = 0;

$roleCount = [];
$barangayCount = [];

foreach ($users as $u) {

    $role = $u['role'] ?? 'unknown';
    $barangay = $u['barangay_name'] ?? 'unknown'; // FIXED HERE

    $roleCount[$role] = ($roleCount[$role] ?? 0) + 1;
    $barangayCount[$barangay] = ($barangayCount[$barangay] ?? 0) + 1;

    if (($u['status'] ?? '') === 'pending') {
        $pendingCount++;
    }
}

/* ================= SAFE MAX ================= */
function safe_max($array) {
    return !empty($array) ? max($array) : 0;
}

/* ================= SYSTEM SCORE ================= */
$dominantRole = safe_max($roleCount);

$roleBalanceScore = ($totalUsers > 0)
    ? (100 - (($dominantRole / $totalUsers) * 100))
    : 0;

/* ================= BARANGAY SCORE ================= */
$dominantBarangay = safe_max($barangayCount);

$barangayBalanceScore = ($totalUsers > 0)
    ? (100 - (($dominantBarangay / $totalUsers) * 100))
    : 0;

/* ================= ML SCORE ================= */
$mlScore = round(
    ($roleBalanceScore * 0.5) + ($barangayBalanceScore * 0.5),
    2
);

/* ================= TOP BARANGAY (SAFE) ================= */
$topBarangay = "N/A";

if (!empty($barangayCount)) {
    arsort($barangayCount);
    $topBarangay = array_key_first($barangayCount);
}

/* ================= ML STATUS ================= */
if ($pendingCount >= 10) {

    $mlStatus = "HIGH SYSTEM LOAD";
    $mlColor = "red";
    $mlInsight = "Approval queue is overloaded and may delay barangay processing.";
    $mlRecommendation = "Prioritize approval workflow and reduce backlog immediately.";

} elseif ($mlScore < 40) {

    $mlStatus = "UNBALANCED SYSTEM";
    $mlColor = "orange";
    $mlInsight = "User distribution across roles or barangays is uneven.";
    $mlRecommendation = "Encourage balanced recruitment across barangays and positions.";

} else {

    $mlStatus = "HEALTHY SYSTEM";
    $mlColor = "green";
    $mlInsight = "System shows balanced distribution and stable administrative flow.";
    $mlRecommendation = "Maintain current governance and monitoring strategy.";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Users</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.main{
    margin-left:190px;   /* moved dashboard 30px to left */
    padding:20px;
    width:calc(100% - 200px);
    overflow-x:hidden;
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
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    color: white;
}

.pending { background: orange; }
.approved { background: green; }
.rejected { background: red; }

.ml-box {
    padding: 20px;
    margin-bottom: 20px;
    border-left: 5px solid;
}
.glass {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(500px);
    border-radius: 15px;
    padding: 20px;
}
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>👤 Manage Users (AI Enhanced)</h2>
        <p>Barangay-aware user monitoring system</p>
    </div>

    <!-- ================= ML PANEL ================= -->
    <div class="glass ml-box" style="border-color: <?= $mlColor ?>;">

        <h3>🤖 System Intelligence Dashboard</h3>

        <p><b>Status:</b> <?= $mlStatus ?></p>
        <p><b>ML Score:</b> <?= $mlScore ?>%</p>

        <p><b>Total Users:</b> <?= $totalUsers ?></p>
        <p><b>Pending Users:</b> <?= $pendingCount ?></p>
        <p><b>Top Barangay:</b> <?= $topBarangay ?></p>

        <p><b>Insight:</b> <?= $mlInsight ?></p>
        <p><b>Recommendation:</b> <?= $mlRecommendation ?></p>

    </div>

    <!-- ================= TABLE ================= -->
    <div class="glass" style="padding:20px;">

        <h3>All Users</h3>

        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Barangay</th>
                <th>Status</th>
                <th>Action</th>
            </tr>

            <?php foreach ($users as $row) { ?>
            <tr>
                <td><?= $row['id']; ?></td>
                <td><?= htmlspecialchars($row['username']); ?></td>
                <td><?= htmlspecialchars($row['role']); ?></td>

                <!-- FIXED: SHOW BARANGAY NAME -->
                <td><?= htmlspecialchars($row['barangay_name'] ?? 'N/A'); ?></td>

                <td>
                    <span class="badge <?= $row['status']; ?>">
                        <?= $row['status']; ?>
                    </span>
                </td>

                <td>
                    <a href="delete.php?id=<?= $row['id']; ?>"
                       onclick="return confirm('Delete this user?')">
                        Delete
                    </a>
                </td>
            </tr>
            <?php } ?>

        </table>

    </div>

</div>

</body>
</html>