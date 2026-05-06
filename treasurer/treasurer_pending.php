<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'treasurer') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$message = "";
$messageType = "";

/* ================= APPROVE ================= */
if (isset($_GET['approve'])) {

    $project_id = (int)$_GET['approve'];

    $stmt = $conn->prepare("
        SELECT * FROM projects
        WHERE id = ? AND barangay_id = ?
    ");
    $stmt->execute([$project_id, $barangay_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($project) {

        $stmt = $conn->prepare("
            SELECT * FROM budgets
            WHERE barangay_id = ?
            ORDER BY year DESC
            LIMIT 1
        ");
        $stmt->execute([$barangay_id]);
        $budget = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($budget) {

            $remaining = $budget['total_amount'] - $budget['used_amount'];

            if ($remaining >= $project['budget_requested']) {

                $newUsed = $budget['used_amount'] + $project['budget_requested'];
                $newRemain = $budget['total_amount'] - $newUsed;

                $stmt = $conn->prepare("
                    UPDATE budgets
                    SET used_amount = ?, remaining_budget = ?
                    WHERE id = ?
                ");
                $stmt->execute([$newUsed, $newRemain, $budget['id']]);

                $stmt = $conn->prepare("
                    UPDATE projects
                    SET status = 'approved'
                    WHERE id = ?
                ");
                $stmt->execute([$project_id]);

                $stmt = $conn->prepare("
                    INSERT INTO budget_transactions
                    (barangay_id, project_id, amount, description)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $barangay_id,
                    $project_id,
                    $project['budget_requested'],
                    "Approved: " . $project['name']
                ]);

                header("Location: treasurer_pending.php?success=1");
                exit();

            } else {
                $message = "❌ Not enough remaining budget to approve this proposal.";
                $messageType = "error";
            }
        }
    }
}

/* ================= REJECT ================= */
if (isset($_GET['reject'])) {

    $project_id = (int)$_GET['reject'];

    $stmt = $conn->prepare("
        UPDATE projects
        SET status = 'rejected'
        WHERE id = ? AND barangay_id = ?
    ");
    $stmt->execute([$project_id, $barangay_id]);

    header("Location: treasurer_pending.php?rejected=1");
    exit();
}

/* ================= MESSAGE ================= */
if (isset($_GET['success'])) {
    $message = "✅ Proposal approved successfully. Budget deducted.";
    $messageType = "success";
}

if (isset($_GET['rejected'])) {
    $message = "❌ Proposal rejected successfully.";
    $messageType = "error";
}

/* ================= LOAD PENDING ================= */
$stmt = $conn->prepare("
    SELECT *
    FROM projects
    WHERE barangay_id = ?
    AND status = 'pending_treasurer'
    ORDER BY id DESC
");
$stmt->execute([$barangay_id]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= CURRENT BUDGET ================= */
$stmt = $conn->prepare("
    SELECT * FROM budgets
    WHERE barangay_id = ?
    ORDER BY year DESC
    LIMIT 1
");
$stmt->execute([$barangay_id]);
$currentBudget = $stmt->fetch(PDO::FETCH_ASSOC);

$totalBudget = $currentBudget['total_amount'] ?? 0;
$usedBudget = $currentBudget['used_amount'] ?? 0;
$remainingBudget = $currentBudget['remaining_budget'] ?? ($totalBudget - $usedBudget);
?>

<!DOCTYPE html>
<html>
<head>
<title>Treasurer Pending Approval</title>

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

.alert{
    padding:12px;
    border-radius:10px;
    margin-bottom:20px;
    font-weight:bold;
}

.success{
    background:rgba(34,197,94,0.2);
    color:#dcfce7;
    border:1px solid rgba(34,197,94,0.4);
}

.error{
    background:rgba(239,68,68,0.2);
    color:#fee2e2;
    border:1px solid rgba(239,68,68,0.4);
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
    box-shadow:0 8px 25px rgba(0,0,0,0.25);
    margin-bottom:20px;
}

.card{
    text-align:center;
    color:white;
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
    min-width:800px;
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
    background:rgba(255,255,255,0.85);
    color:#1e3c72;
    font-size:14px;
    border-bottom:1px solid #ddd;
}

.action-btn{
    padding:8px 14px;
    border-radius:8px;
    color:white;
    text-decoration:none;
    font-size:13px;
    font-weight:bold;
    display:inline-block;
    margin:2px;
    transition:0.3s;
}

.approve{
    background:#16a34a;
}

.approve:hover{
    background:#15803d;
}

.reject{
    background:#dc2626;
}

.reject:hover{
    background:#b91c1c;
}

.empty{
    text-align:center;
    color:white;
    padding:20px;
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

<h2>📂 Treasurer Pending Review</h2>

<?php if($message != ""){ ?>
    <div class="alert <?= $messageType ?>">
        <?= $message ?>
    </div>
<?php } ?>

<div class="grid3">
    <div class="glass card">
        <h3>Total Budget</h3>
        <h2>₱<?= number_format($totalBudget,2) ?></h2>
    </div>

    <div class="glass card">
        <h3>Used Budget</h3>
        <h2>₱<?= number_format($usedBudget,2) ?></h2>
    </div>

    <div class="glass card">
        <h3>Remaining Budget</h3>
        <h2>₱<?= number_format($remainingBudget,2) ?></h2>
    </div>
</div>

<div class="glass">
    <h3 class="section-title">Pending Proposal List</h3>

    <div class="table-wrap">
        <table>
            <tr>
                <th>ID</th>
                <th>Project Title</th>
                <th>Purpose</th>
                <th>Budget Request</th>
                <th>Action</th>
            </tr>

            <?php if(count($projects)==0){ ?>
                <tr>
                    <td colspan="5" class="empty">No pending projects available.</td>
                </tr>
            <?php } ?>

            <?php foreach($projects as $p){ ?>
            <tr>
                <td><?= $p['id'] ?></td>
                <td><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['purpose']) ?></td>
                <td>₱<?= number_format($p['budget_requested'],2) ?></td>
                <td>
                <td>
    <a class="action-btn"
       style="background:#2563eb;"
       href="treasurer_view.php?id=<?= $p['id'] ?>">
       👁 View
    </a>
</td>
                </td>
            </tr>
            <?php } ?>
        </table>
    </div>
</div>

</div>
</div>

</body>
</html>