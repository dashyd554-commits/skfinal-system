<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= FETCH AUDIT LOGS ================= */
$stmt = $conn->prepare("
    SELECT 
        username,
        barangay_name,
        action_type,
        table_name,
        description,
        action_time
    FROM audit_logs
    ORDER BY action_time DESC
");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Audit Logs</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

th {
    background: #343a40;
    color: white;
    padding: 10px;
    text-align: center;
}

td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    text-align: center;
}

.glass {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(500px);
    border-radius: 15px;
    padding: 20px;
}

.badge {
    padding: 5px 10px;
    border-radius: 5px;
    color: white;
    font-size: 12px;
}

.insert { background: green; }
.update { background: orange; }
.delete { background: red; }
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>🕘 System Audit Logs</h2>
    </div>

    <div class="glass">

        <h3>Activity History</h3>

        <table>
            <tr>
                <th>User</th>
                <th>Barangay</th>
                <th>Action</th>
                <th>Table</th>
                <th>Description</th>
                <th>Time</th>
            </tr>

            <?php foreach($logs as $log){ ?>
            <tr>
                <td><?= htmlspecialchars($log['username'] ?? 'system') ?></td>
                <td><?= htmlspecialchars($log['barangay_name'] ?? 'N/A') ?></td>
                <td>
                    <span class="badge <?= strtolower($log['action_type']) ?>">
                        <?= $log['action_type'] ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($log['table_name']) ?></td>
                <td style="text-align:left; max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
    <?= htmlspecialchars($log['description']) ?>
</td>
                <td><?= $log['action_time'] ?></td>
            </tr>
            <?php } ?>

        </table>

    </div>

</div>

</body>
</html>