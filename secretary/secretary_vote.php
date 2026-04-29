<?php
session_start();
include '../config/db.php';

if ($_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

$id = $_GET['id'];

/* ================= GET PROPOSAL ================= */
$stmt = $conn->prepare("SELECT * FROM proposals WHERE id=?");
$stmt->execute([$id]);
$proposal = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= SAVE VOTES ================= */
if ($_POST) {

    $yes = 0;
    $no = 0;

    for ($i=1; $i<=7; $i++) {

        $vote = $_POST["council$i"];

        if ($vote == "yes") $yes++;
        if ($vote == "no") $no++;
    }

    /* ================= DECISION LOGIC ================= */
    if ($yes >= 4) {
        $status = "pending_treasurer";
    } else {
        $status = "rejected";
    }

    /* ================= UPDATE PROPOSAL ================= */
    $stmt = $conn->prepare("
        UPDATE proposals
        SET vote_yes=?, vote_no=?, status=?
        WHERE id=?
    ");

    $stmt->execute([$yes, $no, $status, $id]);

    header("Location: secretary_pending.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Voting Panel</title>

<link rel="stylesheet" href="../assets/style.css">

<style>
.vote-box {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(20px);
    padding:20px;
    border-radius:15px;
}

select {
    width:100%;
    padding:10px;
    margin-bottom:10px;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>🗳 Council Voting</h2>

<div class="vote-box">

<h3><?= htmlspecialchars($proposal['title']) ?></h3>

<form method="POST">

<?php for ($i=1; $i<=7; $i++) { ?>

<label>Council <?= $i ?></label>
<select name="council<?= $i ?>" required>
    <option value="yes">YES</option>
    <option value="no">NO</option>
</select>

<?php } ?>

<button type="submit">Submit Vote</button>

</form>

</div>

</div>

</body>
</html>