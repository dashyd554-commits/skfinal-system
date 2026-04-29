<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

/* ================= APPROVE / REJECT ACTION ================= */
if (isset($_GET['action'], $_GET['id'])) {

    $id = (int) $_GET['id'];
    $action = $_GET['action'];

    if (in_array($action, ['approved', 'rejected'])) {

        $stmt = $conn->prepare("
            UPDATE users 
            SET status = :status 
            WHERE id = :id
        ");

        $stmt->execute([
            ':status' => $action,
            ':id' => $id
        ]);
    }

    header("Location: admin_approve_user.php");
    exit();
}

/* ================= PENDING USERS ================= */
$stmt = $conn->prepare("
    SELECT u.id, u.full_name, u.username, u.role, u.phone, b.barangay_name
    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.status = 'pending'
    ORDER BY u.created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Approve Users</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
table {
    width:100%;
    border-collapse:collapse;
    background:white;
}

th {
    background:#198754;
    color:white;
    padding:10px;
}

td {
    padding:10px;
    border-bottom:1px solid #ddd;
}

.btn {
    padding:5px 10px;
    border-radius:5px;
    text-decoration:none;
    color:white;
}

.approve { background:green; }
.reject { background:red; }

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
    <h2>✅ Approve Users</h2>
</div>

<div class="glass">

<table>
    <tr>
        <th>Name</th>
        <th>Username</th>
        <th>Role</th>
        <th>Barangay</th>
        <th>Action</th>
    </tr>

    <?php foreach ($users as $u) { ?>
    <tr>
        <td><?= htmlspecialchars($u['full_name']) ?></td>
        <td><?= htmlspecialchars($u['username']) ?></td>
        <td><?= htmlspecialchars($u['role']) ?></td>
        <td><?= htmlspecialchars($u['barangay_name'] ?? 'N/A') ?></td>
        <td>
            <a class="btn approve" href="?action=approved&id=<?= $u['id'] ?>">Approve</a>
            <a class="btn reject" href="?action=rejected&id=<?= $u['id'] ?>">Reject</a>
        </td>
    </tr>
    <?php } ?>

</table>

</div>

</div>

</body>
</html>