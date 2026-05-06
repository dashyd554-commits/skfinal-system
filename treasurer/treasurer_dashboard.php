<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'treasurer') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= GET LATEST BUDGET ================= */
$stmt = $conn->prepare("
    SELECT *
    FROM budgets
    WHERE barangay_id = ?
    ORDER BY year DESC
    LIMIT 1
");
$stmt->execute([$barangay_id]);
$budget = $stmt->fetch(PDO::FETCH_ASSOC);

$annualBudget = $budget['total_amount'] ?? 0;

/* ================= APPROVED DISBURSEMENT ================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM budget_transactions
    WHERE barangay_id = ?
");
$stmt->execute([$barangay_id]);
$approvedDisbursement = $stmt->fetchColumn();

$usedBudget = $approvedDisbursement;
$remainingBudget = $annualBudget - $usedBudget;

/* ================= REJECTED PROPOSALS ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM projects
    WHERE barangay_id = ?
    AND status = 'rejected'
");
$stmt->execute([$barangay_id]);
$rejectedFunds = $stmt->fetchColumn();

/* ================= PENDING TREASURER APPROVAL ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM projects
    WHERE barangay_id = ?
    AND status = 'pending_treasurer'
");
$stmt->execute([$barangay_id]);
$totalPendingProposal = $stmt->fetchColumn();

/* ================= FULLY APPROVED PROPOSALS ================= */
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM projects
    WHERE barangay_id = ?
    AND status = 'approved'
");
$stmt->execute([$barangay_id]);
$totalApprovedProposal = $stmt->fetchColumn();

/* ================= TRANSACTIONS ================= */
$stmt = $conn->prepare("
    SELECT *
    FROM budget_transactions
    WHERE barangay_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$barangay_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Treasurer Dashboard</title>

<link rel="stylesheet" href="../assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
*{
    box-sizing:border-box;
    font-family:Arial;
}

body{
    margin:0;
    height:100vh;
    overflow:hidden;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
}

.wrapper{
    display:flex;
    height:100vh;
    width:100%;
}

.main{
    flex:1;
    height:100vh;
    overflow-y:auto;
    padding:20px;
}

h2{
    color:whitesmoke;
    text-align:center;
    margin-bottom:20px;
}

.grid4{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-bottom:15px;
}

.grid3{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:15px;
    margin-bottom:15px;
}

.glass{
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    border-radius:15px;
    padding:20px;
    color:white;
    box-shadow:0 8px 25px rgba(0,0,0,0.2);
    margin-bottom:15px;
}

.card{
    text-align:center;
}

.card h3{
    margin:0;
    font-size:15px;
}

.card h2{
    margin-top:10px;
    color:#1e3c72;
}

.section-title{
    color:white;
    margin-bottom:15px;
}

.chart-box{
    height:320px;
    width:100%;
    display:flex;
    justify-content:center;
    align-items:center;
}

.chart-holder{
    width:300px;
    height:300px;
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#1e3c72;
    color:white;
    padding:12px;
}

td{
    padding:12px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,0.2);
    color:#1e3c72;
}

@media(max-width:1000px){
    .grid4,.grid3{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:768px){
    body{
        overflow:auto;
    }

    .wrapper{
        flex-direction:column;
    }

    .main{
        height:auto;
    }

    .grid4,.grid3{
        grid-template-columns:1fr;
    }

    table,th,td{
        font-size:12px;
    }
}
</style>
</head>

<body>

<div class="wrapper">

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>💰 Treasurer Financial Dashboard</h2>

<div class="grid4">
    <div class="glass card"><h3>Annual Budget</h3><h2>₱<?= number_format($annualBudget,2) ?></h2></div>
    <div class="glass card"><h3>Used Budget</h3><h2>₱<?= number_format($usedBudget,2) ?></h2></div>
    <div class="glass card"><h3>Remaining Budget</h3><h2>₱<?= number_format($remainingBudget,2) ?></h2></div>
    <div class="glass card"><h3>Pending Proposal</h3><h2><?= $totalPendingProposal ?></h2></div>
</div>

<div class="grid3">
    <div class="glass card"><h3>Rejected Proposals</h3><h2><?= $rejectedFunds ?></h2></div>
    <div class="glass card"><h3>Approved Proposal</h3><h2><?= $totalApprovedProposal ?></h2></div>
    <div class="glass card"><h3>Approved Disbursement</h3><h2>₱<?= number_format($approvedDisbursement,2) ?></h2></div>
</div>

<div class="glass">
    <h3 class="section-title">📊 Budget Utilization</h3>
    <div class="chart-box">
        <div class="chart-holder">
            <canvas id="budgetChart"></canvas>
        </div>
    </div>
</div>

<div class="glass">
    <h3 class="section-title">💸 Recent Spending Transactions</h3>

    <table>
        <tr>
            <th>ID</th>
            <th>Amount</th>
            <th>Description</th>
            <th>Date</th>
        </tr>

        <?php if(empty($transactions)){ ?>
            <tr><td colspan="4">No transactions yet</td></tr>
        <?php } ?>

        <?php foreach($transactions as $t){ ?>
        <tr>
            <td><?= $t['id'] ?></td>
            <td>₱<?= number_format($t['amount'],2) ?></td>
            <td><?= htmlspecialchars($t['description']) ?></td>
            <td><?= date('F d, Y h:i A', strtotime($t['created_at'])) ?></td>
        </tr>
        <?php } ?>
    </table>
</div>

</div>
</div>

<script>
new Chart(document.getElementById('budgetChart'), {
    type:'doughnut',
    data:{
        labels:['Used Budget','Remaining Budget'],
        datasets:[{
            data:[<?= (float)$usedBudget ?>, <?= (float)$remainingBudget ?>]
        }]
    },
    options:{
        responsive:true,
        maintainAspectRatio:false,
        plugins:{
            legend:{position:'bottom'}
        }
    }
});
</script>

</body>
</html>