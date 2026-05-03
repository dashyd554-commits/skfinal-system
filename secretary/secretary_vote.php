<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($project_id <= 0) {
    die("❌ Invalid or missing project ID");
}

/* ================= GET PROJECT ================= */
$stmt = $conn->prepare("
    SELECT * FROM projects
    WHERE id = :id AND barangay_id = :barangay_id
");
$stmt->execute([
    ':id' => $project_id,
    ':barangay_id' => $barangay_id
]);

$project = $stmt->fetch();

if (!$project) {
    die("❌ Project not found");
}

/* ================= GET 7 COUNCIL ================= */
$stmt = $conn->prepare("
    SELECT * FROM sk_council
    WHERE barangay_id = :barangay_id
    ORDER BY id ASC
    LIMIT 7
");
$stmt->execute([':barangay_id' => $barangay_id]);

$council = $stmt->fetchAll();

if (count($council) < 7) {
    die("❌ You must register exactly 7 SK Council members before voting.");
}

/* ================= HANDLE VOTING ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $yes = 0;
    $no = 0;

    foreach ($council as $member) {

        $vote = $_POST['vote_' . $member['id']] ?? null;

        if ($vote === 'yes') $yes++;
        if ($vote === 'no') $no++;

        $check = $conn->prepare("
            SELECT id FROM council_votes
            WHERE project_id = :project_id AND council_id = :council_id
        ");

        $check->execute([
            ':project_id' => $project_id,
            ':council_id' => $member['id']
        ]);

        if (!$check->fetch()) {

            $insert = $conn->prepare("
                INSERT INTO council_votes (project_id, council_id, vote)
                VALUES (:project_id, :council_id, :vote)
            ");

            $insert->execute([
                ':project_id' => $project_id,
                ':council_id' => $member['id'],
                ':vote' => $vote
            ]);
        }
    }

    /* ================= DECISION RULE ================= */
    if ($yes >= 4) {

        $update = $conn->prepare("
            UPDATE projects
            SET status = 'pending_treasurer'
            WHERE id = :id
        ");

        $update->execute([':id' => $project_id]);

        header("Location: ../secretary/secretary_dashboard.php?vote=success");
        exit();

    } else {

        $update = $conn->prepare("
            UPDATE projects
            SET status = 'rejected'
            WHERE id = :id
        ");

        $update->execute([':id' => $project_id]);

        header("Location: ../secretary/secretary_dashboard.php?vote=rejected");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>SK Council Voting</title>

<style>
body{
    margin:0;
    font-family:Arial;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;

    display:flex;
    justify-content:center;
    align-items:center;
    min-height:100vh;
}

.container{
    width:100%;
    max-width:600px;
    padding:20px;
}

.back-btn{
    display:inline-block;
    margin-bottom:20px;
    padding:10px 18px;
    background:#1e3c72;
    color:white;
    text-decoration:none;
    border-radius:8px;
    font-weight:bold;
}

.back-btn:hover{
    background:#16305d;
}

h2{
    color: whitesmoke;
    text-align:center;
}
h3{
    color: whitesmoke;
    margin-bottom:20px;
    text-align:center;
}

.card{
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    border-radius:15px;
    padding:15px;
    margin-bottom:20px;
    text-align:start;
    box-shadow:0 8px 25px rgba(0,0,0,0.2);
    color:#1e3c72;
}

select{
    width:100%;
    padding:10px;
    border-radius:8px;
    border:none;
    outline:none;
    margin-top:10px;
}

button{
    width:100%;
    padding:12px;
    background:#1e3c72;
    color:white;
    border:none;
    border-radius:10px;
    cursor:pointer;
    font-size:16px;
    margin-top:10px;
    margin-bottom: 20px;
}

button:hover{
    background:#0056b3;
}

@media(max-width:600px){
    .container{
        padding:10px;
    }
}
</style>
</head>

<body>

<div class="container">

<h2>🗳 SK Council Voting</h2>

<h3>Project: <?= htmlspecialchars($project['name'] ?? 'No Title') ?></h3>

<form method="POST">

<?php foreach ($council as $member) { ?>

    <div class="card">
        <b><?= htmlspecialchars($member['name']) ?></b>

        <select name="vote_<?= $member['id'] ?>" required>
            <option value="">Select Vote</option>
            <option value="yes">Yes</option>
            <option value="no">No</option>
        </select>
    </div>

<?php } ?>

<button type="submit">Submit Votes</button>
<a href="secretary_pending.php" class="back-btn">← Back</a>

</form>

</div>

</body>
</html>