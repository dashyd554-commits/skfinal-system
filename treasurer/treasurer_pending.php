<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'treasurer') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$message = "";

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

            // ✅ SAFE COMPUTATION (DO NOT TRUST remaining_budget)
            $remaining = $budget['total_amount'] - $budget['used_amount'];

            if ($remaining >= $project['budget_requested']) {

                $newUsed = $budget['used_amount'] + $project['budget_requested'];
                $newRemain = $budget['total_amount'] - $newUsed;

                /* UPDATE BUDGET ONLY ON APPROVE */
                $stmt = $conn->prepare("
                    UPDATE budgets
                    SET used_amount = ?, remaining_budget = ?
                    WHERE id = ?
                ");
                $stmt->execute([$newUsed, $newRemain, $budget['id']]);

                /* APPROVE PROJECT */
                $stmt = $conn->prepare("
                    UPDATE projects
                    SET status = 'approved'
                    WHERE id = ?
                ");
                $stmt->execute([$project_id]);

                /* INSERT TRANSACTION */
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
                $message = "❌ Not enough budget to approve this proposal.";
            }
        }
    }
}

/* ================= REJECT (NO BUDGET CHANGE) ================= */
if (isset($_GET['reject'])) {

    $project_id = (int)$_GET['reject'];

    // ❌ ONLY STATUS UPDATE - NO BUDGET TOUCH
    $stmt = $conn->prepare("
        UPDATE projects
        SET status = 'rejected'
        WHERE id = ? AND barangay_id = ?
    ");
    $stmt->execute([$project_id, $barangay_id]);

    header("Location: treasurer_pending.php?rejected=1");
    exit();
}

/* ================= MESSAGES ================= */
if (isset($_GET['success'])) {
    $message = "✅ Proposal approved and budget deducted.";
}
if (isset($_GET['rejected'])) {
    $message = "❌ Proposal rejected (NO budget deducted).";
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

/* ================= BUDGET ================= */
$stmt = $conn->prepare("
    SELECT * FROM budgets
    WHERE barangay_id = ?
    ORDER BY year DESC
    LIMIT 1
");
$stmt->execute([$barangay_id]);
$currentBudget = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Treasurer Pending Approval</title>

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

.glass{
    background:rgba(255,255,255,0.45);
    backdrop-filter:blur(20px);
    border-radius:18px;
    padding:20px;
    margin-bottom:20px;
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
    padding:10px;
    text-align:center;
    border-bottom:1px solid #ddd;
}

.btn-approve{
    padding:6px 12px;
    background:green;
    color:white;
    text-decoration:none;
    border-radius:5px;
}

.btn-reject{
    padding:6px 12px;
    background:red;
    color:white;
    text-decoration:none;
    border-radius:5px;
    margin-left:5px;
}

.msg{
    font-weight:bold;
    margin-bottom:10px;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>📂 Treasurer Pending Review</h2>

<div class="msg"><?= $message ?></div>

<div class="glass">
    <h3>Budget Overview</h3>
    <p>Total: ₱<?= number_format($currentBudget['total_amount'] ?? 0,2) ?></p>
    <p>Used: ₱<?= number_format($currentBudget['used_amount'] ?? 0,2) ?></p>
    <p>Remaining: ₱<?= number_format($currentBudget['remaining_budget'] ?? 0,2) ?></p>
</div>

<div class="glass">
<table>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Purpose</th>
    <th>Budget</th>
    <th>Action</th>
</tr>

<?php if(count($projects)==0){ ?>
<tr>
    <td colspan="5">No pending projects</td>
</tr>
<?php } ?>

<?php foreach($projects as $p){ ?>
<tr>
    <td><?= $p['id'] ?></td>
    <td><?= htmlspecialchars($p['name']) ?></td>
    <td><?= htmlspecialchars($p['purpose']) ?></td>
    <td>₱<?= number_format($p['budget_requested'],2) ?></td>
    <td>
        <a class="btn-approve" href="?approve=<?= $p['id'] ?>">Approve</a>
        <a class="btn-reject" href="?reject=<?= $p['id'] ?>">Reject</a>
    </td>
</tr>
<?php } ?>

</table>
</div>

</div>

</body>
</html>