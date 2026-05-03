<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$user_id = $_SESSION['user']['id'] ?? $_SESSION['user']['user_id'] ?? null;

if (!$user_id) {
    die("User ID not found in session.");
}

/* ================= APPROVED PROJECTS ================= */
$stmt = $conn->prepare("
    SELECT *
    FROM projects
    WHERE status = 'approved'
    AND barangay_id = :barangay_id
    ORDER BY id DESC
");
$stmt->execute([':barangay_id' => $barangay_id]);
$approved = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* ================= REJECTED PROJECTS ================= */
$stmt = $conn->prepare("
    SELECT *
    FROM projects
    WHERE status = 'rejected'
    AND barangay_id = :barangay_id
    ORDER BY id DESC
");
$stmt->execute([':barangay_id' => $barangay_id]);
$rejected = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* ================= BUDGET UTILIZATION ================= */
$stmt = $conn->prepare("
    SELECT 
        b.total_amount,
        b.remaining_budget,
        COALESCE(SUM(bt.amount),0) AS used_amount
    FROM budgets b
    LEFT JOIN budget_transactions bt 
        ON bt.barangay_id = b.barangay_id
    WHERE b.barangay_id = :barangay_id
      AND b.id = (
          SELECT MAX(id)
          FROM budgets b2
          WHERE b2.barangay_id = :barangay_id2
      )
    GROUP BY b.total_amount, b.remaining_budget
");
$stmt->execute([
    ':barangay_id' => $barangay_id,
    ':barangay_id2' => $barangay_id
]);

$budget = $stmt->fetch(PDO::FETCH_ASSOC);

$totalBudget = $budget['total_amount'] ?? 0;
$usedBudget = $budget['used_amount'] ?? 0;
$remaining = $budget['remaining_budget'] ?? ($totalBudget - $usedBudget);

/* ================= PARTICIPATION TREND ================= */
$stmt = $conn->prepare("
    SELECT 
        name AS title,
        COALESCE(target_participants,0) AS participants,
        COALESCE(budget_requested,0) AS budget
    FROM projects
    WHERE barangay_id = :barangay_id
    AND status != 'cancelled'
    ORDER BY id DESC
    LIMIT 10
");
$stmt->execute([':barangay_id' => $barangay_id]);
$trend = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* ================= PERFORMANCE ================= */
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_projects,
        SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved_projects,
        SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) AS rejected_projects
    FROM projects
    WHERE barangay_id = :barangay_id
    AND status != 'cancelled'
");
$stmt->execute([':barangay_id' => $barangay_id]);
$performance = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= VOTING ================= */
$stmt = $conn->prepare("
    SELECT 
        p.id,
        p.name,
        COALESCE(SUM(CASE WHEN cv.vote='yes' THEN 1 ELSE 0 END),0) AS vote_yes,
        COALESCE(SUM(CASE WHEN cv.vote='no' THEN 1 ELSE 0 END),0) AS vote_no
    FROM projects p
    LEFT JOIN council_votes cv ON p.id = cv.project_id
    WHERE p.barangay_id = :bid
    AND p.created_by = :uid
    AND p.status != 'cancelled'
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$stmt->execute([
    ':bid' => $barangay_id,
    ':uid' => $user_id
]);
$voting = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Shared Reports Dashboard</title>

<link rel="stylesheet" href="../assets/sbstyle.css">
<link rel="stylesheet" href="../assets/style.css">

<style>
*{box-sizing:border-box;}

body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    font-family:Arial;
}

.main{
    margin-left:190px;
    padding:20px;
    width:calc(100% - 200px);
}

.header h2{
    color:white;
    text-align:center;
    margin-bottom:20px;
}

/* KPI BALANCED ROWS */
.grid{
    display:flex;
    flex-direction:column;
    gap:15px;
}

.row{
    display:flex;
    gap:15px;
}

.row.top .glass{
    flex:1;
}

.row.bottom{
    justify-content:center;
}

.row.bottom .glass{
    width:250px;
}

.glass{
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    border-radius:15px;
    padding:20px;
    color:#fff;
    box-shadow:0 8px 25px rgba(0,0,0,0.2);
    margin-bottom:20px;
}

.card{text-align:center;}
.card h2{color:#1e3c72;}

.section-title{
    color:white;
    margin-bottom:10px;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

th{
    background:#1e3c72;
    color:white;
    padding:12px;
}

td{
    padding:10px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,0.2);
    color:#1e3c72;
}

p{
    color:#1e3c72;
    line-height:1.8;
}

h3{
    color:white;
}

@media(max-width:768px){
    .main{
        margin-left:0;
        width:100%;
        padding:10px;
    }

    .row{
        flex-direction:column;
    }

    .row.bottom{
        align-items:center;
    }

    .row.bottom .glass{
        width:100%;
    }
}
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>📊 Shared Reports Dashboard</h2>
    </div>

    <div class="grid">

        <!-- TOP ROW -->
        <div class="row top">
            <div class="glass card">
                <h3>✅ Approved Projects</h3>
                <h2><?= count($approved) ?></h2>
            </div>

            <div class="glass card">
                <h3>❌ Rejected Projects</h3>
                <h2><?= count($rejected) ?></h2>
            </div>

            <div class="glass card">
                <h3>💰 Total Budget</h3>
                <h2>₱<?= number_format($totalBudget,2) ?></h2>
            </div>
        </div>

        <!-- BOTTOM ROW -->
        <div class="row bottom">
            <div class="glass card">
                <h3>💸 Used Budget</h3>
                <h2>₱<?= number_format($usedBudget,2) ?></h2>
            </div>

            <div class="glass card">
                <h3>📉 Remaining Budget</h3>
                <h2>₱<?= number_format($remaining,2) ?></h2>
            </div>
        </div>

    </div>

    <div class="glass">
        <h3 class="section-title">Approved Projects Report</h3>
        <table>
            <tr><th>Project Name</th><th>Budget</th><th>Status</th></tr>
            <?php foreach($approved as $a){ ?>
            <tr>
                <td><?= htmlspecialchars($a['name']) ?></td>
                <td>₱<?= number_format($a['budget_requested'],2) ?></td>
                <td>Approved</td>
            </tr>
            <?php } ?>
        </table>
    </div>

    <div class="glass">
        <h3 class="section-title">Rejected Projects Report</h3>
        <table>
            <tr><th>Project Name</th><th>Budget</th><th>Status</th></tr>
            <?php foreach($rejected as $r){ ?>
            <tr>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td>₱<?= number_format($r['budget_requested'],2) ?></td>
                <td>Rejected</td>
            </tr>
            <?php } ?>
        </table>
    </div>

    <div class="glass">
        <h3 class="section-title">Yearly Budget Utilization</h3>
        <p><b>Total Budget:</b> ₱<?= number_format($totalBudget,2) ?></p>
        <p><b>Used Budget:</b> ₱<?= number_format($usedBudget,2) ?></p>
        <p><b>Remaining Budget:</b> ₱<?= number_format($remaining,2) ?></p>
    </div>

    <div class="glass">
        <h3 class="section-title">Participation Trend</h3>
        <table>
            <tr><th>Project</th><th>Participants</th><th>Budget</th></tr>
            <?php foreach($trend as $t){ ?>
            <tr>
                <td><?= htmlspecialchars($t['title']) ?></td>
                <td><?= $t['participants'] ?></td>
                <td>₱<?= number_format($t['budget'],2) ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>

    <div class="glass">
        <h3 class="section-title">Barangay Project Performance</h3>
        <p><b>Total Projects:</b> <?= $performance['total_projects'] ?></p>
        <p><b>Approved:</b> <?= $performance['approved_projects'] ?></p>
        <p><b>Rejected:</b> <?= $performance['rejected_projects'] ?></p>
    </div>

    <div class="glass">
        <h3 class="section-title">SK Council Vote Tally</h3>
        <table>
            <tr><th>Project</th><th>YES</th><th>NO</th></tr>
            <?php foreach($voting as $v){ ?>
            <tr>
                <td><?= htmlspecialchars($v['name']) ?></td>
                <td><?= $v['vote_yes'] ?></td>
                <td><?= $v['vote_no'] ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>

</div>

</body>
</html>