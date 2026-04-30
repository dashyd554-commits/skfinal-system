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

/* ================= APPROVED ONLY SPENDING ================= */
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM budget_transactions
    WHERE barangay_id = ?
");
$stmt->execute([$barangay_id]);
$approvedDisbursement = $stmt->fetchColumn();

/* ✔ FIX: computed from approved ONLY */
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
<link rel="stylesheet" href="../assets/sbstyle.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
*{
    box-sizing:border-box;
    font-family:Arial;
}

body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
}

/* dark overlay */
body::before{
    content:"";
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.4);
    z-index:-1;
}

.main{
    margin-left:190px;
    padding:20px;
    width:calc(100% - 200px);
}

/* GRID SAME STRUCTURE */
.grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:20px;
    margin-bottom:20px;
}

/* GLASS CARD */
.glass{
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    border-radius:15px;
    padding:20px;
    color:#fff;
    box-shadow:0 8px 25px rgba(0,0,0,0.2);
}

.card h3{
    margin:0;
    font-size:14px;
    color:#ddd;
}

.card h2{
    margin-top:10px;
    color:#fff;
}

.section-title{
    color:#fff;
    margin-bottom:15px;
}

/* CHART */
.chart-holder{
    width:320px;
    margin:auto;
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    color:#fff;
}

th{
    background:rgba(30,60,114,0.85);
    padding:12px;
}

td{
    padding:10px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,0.2);
}

/* RESPONSIVE */
@media(max-width:1000px){
    .grid{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:768px){
    .main{
        margin-left:70px;
    }

    .grid{
        grid-template-columns:1fr;
    }
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <h2 style="color:white;">💰 Treasurer Financial Dashboard</h2>

    <!-- CARDS -->
    <div class="grid">

        <div class="glass card">
            <h3>Annual Budget</h3>
            <h2>₱<?= number_format($annualBudget,2) ?></h2>
        </div>

        <div class="glass card">
            <h3>Used Budget</h3>
            <h2>₱<?= number_format($usedBudget,2) ?></h2>
        </div>

        <div class="glass card">
            <h3>Remaining Budget</h3>
            <h2>₱<?= number_format($remainingBudget,2) ?></h2>
        </div>

        <div class="glass card">
            <h3>Rejected Proposals</h3>
            <h2><?= $rejectedFunds ?></h2>
        </div>

    </div>

    <!-- SECOND ROW (KEEP YOUR ORIGINAL LAYOUT) -->
    <div class="grid">

        <div class="glass card">
            <h3>Approved Disbursement</h3>
            <h2>₱<?= number_format($approvedDisbursement,2) ?></h2>
        </div>

        <!-- ✔ YOUR ORIGINAL CHART SECTION (RESTORED) -->
        <div class="glass" style="grid-column: span 3;">
            <h3 class="section-title">📊 Budget Utilization</h3>
            <div class="chart-holder">
                <canvas id="budgetChart"></canvas>
            </div>
        </div>

    </div>

    <!-- TABLE -->
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
                <tr>
                    <td colspan="4">No transactions yet</td>
                </tr>
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

<!-- ✔ YOUR ORIGINAL CHART SCRIPT (RESTORED) -->
<script>
new Chart(document.getElementById('budgetChart'), {
    type: 'doughnut',
    data: {
        labels: ['Used Budget','Remaining Budget'],
        datasets: [{
            data: [
                <?= (float)$usedBudget ?>,
                <?= (float)$remainingBudget ?>
            ]
        }]
    },
    options:{
        responsive:true,
        plugins:{
            legend:{ position:'bottom' }
        }
    }
});
</script>

</body>
</html>