<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'treasurer') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= LOAD TRANSACTIONS ================= */
$stmt = $conn->prepare("
    SELECT 
        bt.id,
        bt.amount,
        bt.description,
        bt.created_at,
        COALESCE(p.name, 'Deleted Project') AS proposal_name
    FROM budget_transactions bt
    LEFT JOIN projects p ON bt.project_id = p.id
    WHERE bt.barangay_id = ?
    ORDER BY bt.created_at DESC
");
$stmt->execute([$barangay_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= TOTAL SPENDING ================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM budget_transactions
    WHERE barangay_id = ?
");
$stmt->execute([$barangay_id]);
$totalSpent = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html>
<head>
<title>Treasurer Spending History</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
}

.main{
    margin-left:190px;
    padding:20px;
    width:calc(100% - 200px);
}

h2{
    color:#1e3c72;
}

.box{
    background:rgba(255,255,255,0.6);
    backdrop-filter:blur(20px);
    padding:20px;
    margin-bottom:20px;
    border-radius:12px;
    box-shadow:0 5px 20px rgba(0,0,0,0.1);
}

table{
    width:100%;
    border-collapse:collapse;
    background:rgba(255,255,255,0.6);
    border-radius:12px;
    overflow:hidden;
}

th{
    background:#1e3c72;
    color:white;
    padding:12px;
}

td{
    padding:10px;
    text-align:center;
    border-bottom:1px solid #ddd;
}

.empty{
    text-align:center;
    padding:20px;
    color:#666;
}

@media(max-width:768px){
    .main{
        margin-left:70px;
        width:100%;
    }
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>💸 History of Spending / Approved Disbursement</h2>

<div class="box">
    <h3>Total Approved Spending: ₱<?= number_format($totalSpent,2) ?></h3>
</div>

<table>
<tr>
    <th>ID</th>
    <th>Proposal Title</th>
    <th>Amount Released</th>
    <th>Description</th>
    <th>Date Approved</th>
</tr>

<?php if(empty($transactions)){ ?>

<tr>
    <td colspan="5" class="empty">No spending transactions yet.</td>
</tr>

<?php } else { ?>

<?php foreach($transactions as $t){ ?>
<tr>
    <td><?= $t['id'] ?></td>
    <td><?= htmlspecialchars($t['proposal_name']) ?></td>
    <td>₱<?= number_format($t['amount'],2) ?></td>
    <td><?= htmlspecialchars($t['description']) ?></td>
    <td><?= date("F d, Y h:i A", strtotime($t['created_at'])) ?></td>
</tr>
<?php } ?>

<?php } ?>

</table>

</div>

</body>
</html>