<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= APPROVED PROJECTS ================= */
$stmt = $conn->prepare("
    SELECT * FROM projects
    WHERE status = 'approved'
    AND barangay_id = :barangay_id
    ORDER BY id DESC
");
$stmt->execute([':barangay_id' => $barangay_id]);
$approved = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* ================= REJECTED PROJECTS ================= */
$stmt = $conn->prepare("
    SELECT * FROM projects
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
        COALESCE(SUM(bt.amount), 0) AS used_amount
    FROM budgets b
    LEFT JOIN budget_transactions bt 
        ON bt.barangay_id = b.barangay_id
    WHERE b.barangay_id = :barangay_id
    GROUP BY b.total_amount
");
$stmt->execute([':barangay_id' => $barangay_id]);
$budget = $stmt->fetch(PDO::FETCH_ASSOC);

$totalBudget = $budget['total_amount'] ?? 0;
$usedBudget = $budget['used_amount'] ?? 0;
$remaining = $totalBudget - $usedBudget;

/* ================= PARTICIPATION TREND ================= */
$stmt = $conn->prepare("
    SELECT 
        name AS title,
        target_participants AS participants
    FROM projects
    WHERE barangay_id = :barangay_id
    ORDER BY id DESC
    LIMIT 10
");
$stmt->execute([':barangay_id' => $barangay_id]);
$trend = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

/* ================= PERFORMANCE ================= */
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_projects,
        SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved_projects
    FROM projects
    WHERE barangay_id = :barangay_id
");
$stmt->execute([':barangay_id' => $barangay_id]);
$performance = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= VOTING ================= */
$stmt = $conn->prepare("
    SELECT name, COALESCE(vote_yes,0) AS vote_yes, COALESCE(vote_no,0) AS vote_no
    FROM projects
    WHERE barangay_id = :barangay_id
");
$stmt->execute([':barangay_id' => $barangay_id]);
$voting = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<!DOCTYPE html>
<html>
<head>
<title>Shared Reports</title>

<link rel="stylesheet" href="../assets/sbstyle.css">
<link rel="stylesheet" href="../assets/style.css">

<style>
*{box-sizing:border-box;}

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

.header h2{
    margin-bottom:20px;
}

.grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-bottom:20px;
}

.glass{
    background:rgba(255,255,255,0.35);
    backdrop-filter:blur(30px);
    border-radius:15px;
    box-shadow:0 8px 25px rgba(0,0,0,0.08);
    padding:20px;
    margin-bottom:20px;
}

.card{
    text-align:center;
}

.card h3{
    margin:0;
    font-size:16px;
    color:#555;
}

.card h2{
    margin-top:10px;
    color:#222;
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
    border-bottom:1px solid #ddd;
}

.section-title{
    margin-bottom:10px;
    color:#1e3c72;
}

@media(max-width:1200px){
    .grid{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:768px){
    .main{
        margin-left:70px;
        width:calc(100% - 80px);
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

    <div class="header">
        <h2>📊 Shared Reports Dashboard</h2>
    </div>

    <!-- TOP SUMMARY -->
    <div class="grid">

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

        <div class="glass card">
            <h3>📉 Remaining Budget</h3>
            <h2>₱<?= number_format($remaining,2) ?></h2>
        </div>

    </div>

    <!-- APPROVED -->
    <div class="glass">
        <h3 class="section-title">Approved Projects Report</h3>
        <table>
            <tr><th>Project Name</th><th>Status</th></tr>
            <?php foreach($approved as $a){ ?>
            <tr>
                <td><?= htmlspecialchars($a['name']) ?></td>
                <td>Approved</td>
            </tr>
            <?php } ?>
        </table>
    </div>

    <!-- REJECTED -->
    <div class="glass">
        <h3 class="section-title">Rejected Projects Report</h3>
        <table>
            <tr><th>Project Name</th><th>Status</th></tr>
            <?php foreach($rejected as $r){ ?>
            <tr>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td>Rejected</td>
            </tr>
            <?php } ?>
        </table>
    </div>

    <!-- BUDGET -->
    <div class="glass">
        <h3 class="section-title">Yearly Budget Utilization</h3>
        <p><b>Total Budget:</b> ₱<?= number_format($totalBudget,2) ?></p>
        <p><b>Used Budget:</b> ₱<?= number_format($usedBudget,2) ?></p>
        <p><b>Remaining Budget:</b> ₱<?= number_format($remaining,2) ?></p>
    </div>

    <!-- TREND -->
    <div class="glass">
        <h3 class="section-title">Participation Trend</h3>
        <table>
            <tr><th>Project</th><th>Participants</th></tr>
            <?php foreach($trend as $t){ ?>
            <tr>
                <td><?= htmlspecialchars($t['title']) ?></td>
                <td><?= $t['participants'] ?></td>
            </tr>
            <?php } ?>
        </table>
    </div>

    <!-- PERFORMANCE -->
    <div class="glass">
        <h3 class="section-title">Barangay Project Performance</h3>
        <p><b>Total Projects:</b> <?= $performance['total_projects'] ?></p>
        <p><b>Approved Projects:</b> <?= $performance['approved_projects'] ?></p>
    </div>

    <!-- VOTING -->
    <div class="glass">
        <h3 class="section-title">SK Council Vote Tally</h3>
        <table>
            <tr><th>Project</th><th>Yes</th><th>No</th></tr>
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