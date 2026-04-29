<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairperson') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

/* ================= GET PROPOSALS ================= */
$stmt = $conn->prepare("
    SELECT *
    FROM proposals
    WHERE created_by = ?
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);

$proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Proposal Status</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
table {
    width:100%;
    border-collapse:collapse;
    background:white;
}

th {
    background:#343a40;
    color:white;
    padding:10px;
}

td {
    padding:10px;
    border-bottom:1px solid #ddd;
}

.badge {
    padding:5px 10px;
    border-radius:6px;
    color:white;
    font-size:12px;
}

.pending { background:orange; }
.approved { background:green; }
.rejected { background:red; }
.voting { background:#0d6efd; }

.glass {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(20px);
    padding:20px;
    border-radius:15px;
}
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>📌 My Proposal Status</h2>

<div class="glass">

<table>
    <tr>
        <th>Title</th>
        <th>Type</th>
        <th>Budget</th>
        <th>Status</th>
        <th>Votes</th>
        <th>Date</th>
    </tr>

    <?php foreach ($proposals as $p) { ?>
    <tr>
        <td><?= htmlspecialchars($p['title']) ?></td>
        <td><?= $p['type'] ?></td>
        <td>₱ <?= number_format($p['proposed_budget']) ?></td>
        <td>
            <span class="badge <?= str_replace('_','',$p['status']) ?>">
                <?= $p['status'] ?>
            </span>
        </td>
        <td>
            👍 <?= $p['vote_yes'] ?> |
            👎 <?= $p['vote_no'] ?>
        </td>
        <td><?= $p['created_at'] ?></td>
    </tr>
    <?php } ?>

</table>

</div>

</div>

</body>
</html>