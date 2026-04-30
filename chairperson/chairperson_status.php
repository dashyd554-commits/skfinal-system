<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'chairman') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$created_by = $_SESSION['user']['id'];

/* ================= FETCH PROPOSALS ================= */
$stmt = $conn->prepare("
    SELECT 
        p.id,
        p.name,
        p.purpose,
        p.budget_requested,
        p.status,
        p.created_at,

        COALESCE(SUM(CASE WHEN cv.vote = 'yes' THEN 1 ELSE 0 END),0) AS vote_yes,
        COALESCE(SUM(CASE WHEN cv.vote = 'no' THEN 1 ELSE 0 END),0) AS vote_no

    FROM projects p
    LEFT JOIN council_votes cv ON p.id = cv.project_id

    WHERE p.barangay_id = :bid
    AND p.created_by = :uid

    GROUP BY p.id
    ORDER BY p.created_at DESC
");

$stmt->execute([
    ':bid' => $barangay_id,
    ':uid' => $created_by
]);

$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= STATUS FORMAT ================= */
function statusLabel($status) {

    if ($status == 'pending_secretary') {
        return "<span style='color:orange;font-weight:bold;'>Pending Secretary Voting</span>";
    }
    elseif ($status == 'voting') {
        return "<span style='color:blue;font-weight:bold;'>Under Council Voting</span>";
    }
    elseif ($status == 'pending_treasurer') {
        return "<span style='color:purple;font-weight:bold;'>Pending Treasurer Approval</span>";
    }
    elseif ($status == 'approved') {
        return "<span style='color:green;font-weight:bold;'>Approved</span>";
    }
    elseif ($status == 'rejected') {
        return "<span style='color:red;font-weight:bold;'>Rejected</span>";
    }
    else {
        return $status;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Project / Activity Status</title>
<link rel="stylesheet" href="../assets/sbstyle.css">
<link rel="stylesheet" href="../assets/style.css">

<style>
.main{
    margin-left:190px;   /* moved dashboard 30px to left */
    padding:20px;
    width:calc(100% - 200px);
    overflow-x:hidden;
}

table{
    width:100%;
    border-collapse:collapse;
    background:rgba(255,255,255,0.2);
    backdrop-filter:blur(18px);
}

th, td{
    padding:12px;
    border-bottom:1px solid #ccc;
    text-align:center;
}

th{
    background:#007bff;
    color:white;
}
h2{
    text-align: center;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>📊 Project / Activity Proposal Status</h2>

<table>
<tr>
    <th>Proposal Title</th>
    <th>Purpose</th>
    <th>Requested Budget</th>
    <th>Yes Votes</th>
    <th>No Votes</th>
    <th>Status</th>
    <th>Date Submitted</th>
</tr>

<?php if(count($projects) > 0): ?>
    <?php foreach($projects as $p): ?>
    <tr>
        <td><?= htmlspecialchars($p['name']) ?></td>
        <td><?= htmlspecialchars($p['purpose']) ?></td>
        <td>₱<?= number_format($p['budget_requested'],2) ?></td>
        <td><?= $p['vote_yes'] ?></td>
        <td><?= $p['vote_no'] ?></td>
        <td><?= statusLabel($p['status']) ?></td>
        <td><?= date('M d, Y', strtotime($p['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="7">No submitted proposal yet.</td>
    </tr>
<?php endif; ?>
</table>

</div>

</body>
</html>