<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

$stmt = $conn->prepare("
    SELECT *
    FROM projects
    WHERE barangay_id=?
    AND status IN ('approved','rejected','pending_treasurer')
    ORDER BY id DESC
");
$stmt->execute([$barangay_id]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Secretary History</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.main{
    margin-left:190px;   /* moved dashboard 30px to left */
    padding:20px;
    width:calc(100% - 200px);
    overflow-x:hidden;
}
table{
    width:100%;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(500px);
    border-radius: 15px;
    padding: 20px;
}
th{
    background:#333;
    color:white;
    padding:10px;
}
td{
    padding:10px;
    text-align:center;
    }
</style>
</head>
<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>🕘 Voting History</h2>

<table>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Budget</th>
    <th>Status</th>
</tr>

<?php foreach($data as $d){ ?>
<tr>
    <td><?= $d['id'] ?></td>
    <td><?= htmlspecialchars($d['name']) ?></td>
    <td>₱<?= number_format($d['budget_requested'],2) ?></td>
    <td><?= $d['status'] ?></td>
</tr>
<?php } ?>

</table>

</div>

</body>
</html>