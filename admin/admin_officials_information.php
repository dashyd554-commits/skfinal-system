<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

/* ================= GET OFFICIALS ================= */
$stmt = $conn->prepare("
    SELECT u.id, u.full_name, u.username, u.phone, u.role, u.status,
           b.barangay_name
    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.role IN ('chairperson','secretary','treasurer')
    ORDER BY b.barangay_name, u.role
");
$stmt->execute();
$officials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Officials Information</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
table {
    width:100%;
    border-collapse:collapse;
    background:white;
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
    <h2>🏛️ Barangay Officials Information</h2>
</div>

<div class="glass">

<table>
    <tr>
        <th>Barangay</th>
        <th>Name</th>
        <th>Username</th>
        <th>Phone</th>
        <th>Role</th>
        <th>Status</th>
    </tr>

    <?php foreach ($officials as $o) { ?>
    <tr>
        <td><?= htmlspecialchars($o['barangay_name'] ?? 'N/A') ?></td>
        <td><?= htmlspecialchars($o['full_name']) ?></td>
        <td><?= htmlspecialchars($o['username']) ?></td>
        <td><?= htmlspecialchars($o['phone']) ?></td>
        <td><?= htmlspecialchars($o['role']) ?></td>
        <td><?= htmlspecialchars($o['status']) ?></td>
    </tr>
    <?php } ?>

</table>

</div>

</div>

</body>
</html>