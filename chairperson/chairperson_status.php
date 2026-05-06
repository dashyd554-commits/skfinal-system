<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
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

/* ================= STATUS LABEL ================= */
function statusLabel($status) {
    switch ($status) {
        case 'pending_secretary':
            return "<span style='color:orange;font-weight:bold;'>Pending Secretary Voting</span>";
        case 'voting':
            return "<span style='color:deepskyblue;font-weight:bold;'>Under Council Voting</span>";
        case 'pending_treasurer':
            return "<span style='color:violet;font-weight:bold;'>Pending Treasurer Approval</span>";
        case 'approved':
            return "<span style='color:lightgreen;font-weight:bold;'>Approved</span>";
        case 'rejected':
            return "<span style='color:red;font-weight:bold;'>Rejected</span>";
        case 'cancelled':
            return "<span style='color:gray;font-weight:bold;'>Cancelled</span>";
        default:
            return $status;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Project Status</title>

<link rel="stylesheet" href="../assets/style.css">

<style>
*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    font-family:Arial;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    height:100vh;
    overflow:hidden; /* IMPORTANT FIX */
}

/* ===== LAYOUT FIX ===== */
.wrapper{
    display:flex;
    height:100vh;
    overflow:hidden;
}

/* MAIN CONTENT */
.main{
    flex:1;
    padding:20px;
    overflow-y:auto; /* scroll inside only */
    height:100vh;
}

/* TITLE */
h2{
    text-align:center;
    margin-bottom:15px;
    color:white;
}

/* TABLE WRAPPER FIX */
.table-container{
    width:100%;
    max-height:75vh;
    overflow:auto;
    border-radius:12px;
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(12px);
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 8px 25px rgba(0,0,0,0.25);
}

/* HEADER */
th{
    position:sticky;
    top:0;
    background:#1e3c72;
    color:white;
    padding:12px;
    z-index:2;
}

/* CELLS */
td{
    padding:12px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,0.1);
    color:#1e3c72;
    background:rgba(255,255,255,0.85);
}

/* BUTTONS */
.btn{
    padding:6px 10px;
    border-radius:6px;
    text-decoration:none;
    color:white;
    font-size:12px;
    margin:2px;
    display:inline-block;
}

.edit{ background:#3498db; }
.cancel{ background:#e74c3c; }

/* TITLE */
h2{
    text-align:center;
    margin-bottom:20px;
    color:whitesmoke;
}

/* RESPONSIVE */
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

<h2>📊 Project / Activity Proposal Status</h2>

<div class="table-container">
<table>

<tr>
    <th>Title</th>
    <th>Purpose</th>
    <th>Budget</th>
    <th>Yes</th>
    <th>No</th>
    <th>Status</th>
    <th>Date</th>
    <th>Actions</th>
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

        <td>
            <?php if (!in_array($p['status'], ['approved','rejected','cancelled'])): ?>

                <a class="btn edit" href="chairperson_edit_proposal.php?id=<?= $p['id'] ?>">
                    Edit
                </a>

                <a class="btn cancel"
                   href="chairperson_cancel_proposal.php?id=<?= $p['id'] ?>"
                   onclick="return confirm('Cancel this proposal?')">
                    Cancel
                </a>

            <?php else: ?>
                <span style="color:gray;font-size:12px;">Locked</span>
            <?php endif; ?>
        </td>

    </tr>
    <?php endforeach; ?>
<?php else: ?>
<tr>
    <td colspan="8">No proposals found.</td>
</tr>
<?php endif; ?>

</table>
</div>

</div>
</div>

</body>
</html>