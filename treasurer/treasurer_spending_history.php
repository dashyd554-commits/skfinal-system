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

/* ================= EXTRA KPI ================= */
$totalTransactions = count($transactions);
$latestRelease = !empty($transactions)
    ? date("F d, Y", strtotime($transactions[0]['created_at']))
    : "No Record";
?>

<!DOCTYPE html>
<html>
<head>
<title>Treasurer Spending History</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/style.css">

<style>
*{
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    min-height:100vh;
}

.wrapper{
    display:flex;
    min-height:100vh;
}

.main{
    flex:1;
    padding:20px;
    overflow-x:hidden;
}

h2{
    text-align:center;
    color:white;
    margin-bottom:20px;
    font-size:28px;
}

.grid3{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:15px;
    margin-bottom:20px;
}

.glass{
    background:rgba(255,255,255,0.13);
    backdrop-filter:blur(18px);
    border-radius:18px;
    padding:20px;
    color:white;
    box-shadow:0 8px 25px rgba(0,0,0,0.25);
    margin-bottom:20px;
}

.card{
    text-align:center;
}

.card h3{
    margin:0;
    font-size:15px;
    color:#dbeafe;
}

.card h2{
    margin-top:10px;
    font-size:24px;
    color:#ffffff;
}

.section-title{
    color:white;
    margin-bottom:15px;
    font-size:20px;
}

.table-wrap{
    width:100%;
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:900px;
}

th{
    background:#1e3c72;
    color:white;
    padding:12px;
    font-size:14px;
}

td{
    padding:12px;
    text-align:center;
    background:rgba(255,255,255,0.88);
    color:#1e3c72;
    font-size:14px;
    border-bottom:1px solid #ddd;
}

.empty{
    text-align:center;
    padding:20px;
    color:#1e3c72;
    font-weight:bold;
}

@media(max-width:900px){
    .grid3{
        grid-template-columns:1fr;
    }
}

@media(max-width:768px){
    .main{
        padding:12px;
    }

    h2{
        font-size:22px;
    }
}
</style>
</head>

<body>

<div class="wrapper">

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>💸 History of Spending / Approved Disbursement</h2>

<div class="grid3">

    <div class="glass card">
        <h3>Total Approved Spending</h3>
        <h2>₱<?= number_format($totalSpent,2) ?></h2>
    </div>

    <div class="glass card">
        <h3>Total Transactions</h3>
        <h2><?= $totalTransactions ?></h2>
    </div>

    <div class="glass card">
        <h3>Latest Release</h3>
        <h2><?= $latestRelease ?></h2>
    </div>

</div>

<div class="glass">
    <h3 class="section-title">Approved Financial Ledger</h3>

    <div class="table-wrap">
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
            <?php } ?>

            <?php foreach($transactions as $t){ ?>
            <tr>
                <td><?= $t['id'] ?></td>
                <td><?= htmlspecialchars($t['proposal_name']) ?></td>
                <td>₱<?= number_format($t['amount'],2) ?></td>
                <td><?= htmlspecialchars($t['description']) ?></td>
                <td><?= date("F d, Y h:i A", strtotime($t['created_at'])) ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>
</div>

</div>
</div>

</body>
</html>