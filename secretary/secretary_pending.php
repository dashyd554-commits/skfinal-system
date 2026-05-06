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

<style>
*{box-sizing:border-box;}

body{
    margin:0;
    height:100vh;
    overflow:hidden;
    font-family:Arial;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
}

/* ===== WRAPPER FIX ===== */
.wrapper{
    display:flex;
    height:100vh;
    overflow:hidden;
}

/* ===== MAIN FIX ===== */
.main{
    flex:1;
    height:100vh;
    overflow-y:auto;
    padding:15px;
}

/* TITLE */
h2{
    text-align:center;
    color:whitesmoke;
    margin:10px 0;
}

/* TABLE WRAP FIX */
.table-box{
    width:100%;
    overflow-x:auto;
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    border-radius:12px;
    overflow:hidden;
}

/* HEADER */
th{
    background:#1e3c72;
    color:white;
    padding:10px;
    white-space:nowrap;
}

/* ROWS */
td{
    padding:10px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,0.2);
    color:#1e3c72;
}

/* BUTTON */
.btn{
    padding:6px 10px;
    background:green;
    color:white;
    text-decoration:none;
    border-radius:5px;
    display:inline-block;
}

/* RESPONSIVE */
@media(max-width:768px){
    body{overflow:auto;}

    .wrapper{
        flex-direction:column;
    }

    .main{
        height:auto;
    }

    table{
        font-size:12px;
    }
}
</style>
</head>

<body>

<div class="wrapper">

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>📂 Pending Proposals for Voting</h2>

<div class="table-box">

<table>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Purpose</th>
    <th>Budget</th>
    <th>Action</th>
</tr>

<?php if(count($projects) > 0): ?>
    <?php foreach($projects as $p){ ?>
    <tr>
        <td><?= $p['id'] ?></td>
        <td><?= htmlspecialchars($p['name']) ?></td>
        <td><?= htmlspecialchars($p['purpose']) ?></td>
        <td>₱<?= number_format($p['budget_requested'],2) ?></td>
        <td>
            <a class="btn" href="secretary_vote.php?id=<?= $p['id'] ?>">
                Open Voting
            </a>
        </td>
    </tr>
    <?php } ?>
<?php else: ?>
<tr>
    <td colspan="5">No pending proposals found.</td>
</tr>
<?php endif; ?>

</table>

</div>

</div>
</div>

</body>
</html>