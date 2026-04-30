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
    WHERE barangay_id = ?
    AND status = 'pending_secretary'
    ORDER BY id DESC
");
$stmt->execute([$barangay_id]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Secretary Pending</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.main { 
    margin-left:190px;   /* moved dashboard 30px to left */
    padding:20px;
    width:calc(100% - 200px);
    overflow-x:hidden;
}
table { 
    width:100%;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(500px);
    border-radius: 15px;
    padding: 20px;
}
th { 
    background:#007bff; 
    color:white; 
    padding:10px; 
}
td {
     padding:10px; 
     text-align:center; 
     border-bottom:1px solid #ddd; 
    }
.btn { 
    padding:6px 10px; 
    background:green; 
    color:white; 
    text-decoration:none; 
    border-radius:5px; 
    }
</style>
</head>
<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">
<h2>📂 Pending Proposals for Voting</h2>

<table>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Purpose</th>
    <th>Budget</th>
    <th>Action</th>
</tr>

<?php foreach($projects as $p){ ?>
<tr>
    <td><?= $p['id'] ?></td>
    <td><?= htmlspecialchars($p['name']) ?></td>
    <td><?= htmlspecialchars($p['purpose']) ?></td>
    <td>₱<?= number_format($p['budget_requested'],2) ?></td>
    <td>
        <a class="btn" href="secretary_vote.php?id=<?= $p['id'] ?>">Open Voting</a>
    </td>
</tr>
<?php } ?>

</table>
</div>

</body>
</html>