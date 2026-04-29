<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

/* ================= AUDIT LOGS ================= */
$stmt = $conn->prepare("
    SELECT a.action, a.log_time,
           u.full_name, u.username,
           b.barangay_name
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN barangays b ON a.barangay_id = b.id
    ORDER BY a.log_time DESC
    LIMIT 200
");
$stmt->execute();

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Audit History</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
table {
    width:100%;
    border-collapse:collapse;
    background:white;
}

th {
    background:#6f42c1;
    color:white;
    padding:10px;
}

td {
    padding:10px;
    border-bottom:1px solid #ddd;
    font-size:13px;
}

tr:hover { background:#f5f5f5; }

.glass {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(20px);
    border-radius: 15px;
    padding: 20px;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>📜 System Audit Log</h2>
</div>

<div class="glass">

<table>
    <tr>
        <th>User</th>
        <th>Barangay</th>
        <th>Action</th>
        <th>Time</th>
    </tr>

    <?php foreach ($logs as $l) { ?>
    <tr>
        <td><?= htmlspecialchars($l['full_name'] ?? 'System') ?></td>
        <td><?= htmlspecialchars($l['barangay_name'] ?? 'N/A') ?></td>
        <td><?= htmlspecialchars($l['action']) ?></td>
        <td><?= $l['log_time'] ?></td>
    </tr>
    <?php } ?>

</table>

</div>

</div>

</body>
</html>