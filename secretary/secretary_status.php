<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= GET PROJECT ID SAFELY ================= */
$project_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($project_id <= 0) {
    die("❌ Invalid or missing project ID");
}

/* ================= GET PROJECT ================= */
$stmt = $conn->prepare("
    SELECT *
    FROM projects
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

/* ================= GET VOTES ================= */
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN vote = 'yes' THEN 1 ELSE 0 END),0) AS vote_yes,
        COALESCE(SUM(CASE WHEN vote = 'no' THEN 1 ELSE 0 END),0) AS vote_no
    FROM council_votes
    WHERE project_id = :project_id
");

$stmt->execute([':project_id' => $project_id]);
$votes = $stmt->fetch();

/* ================= DECISION ================= */
$yes = (int)$votes['vote_yes'];
$no = (int)$votes['vote_no'];

if ($yes >= 4) {
    $status = "APPROVED";
} else {
    $status = "REJECTED";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Project Status</title>
<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">
<style>
body{
    font-family:Arial;
    padding:20px;
}
.box{
    background:#f4f4f4;
    padding:20px;
    border-radius:10px;
    width:50%;
}
.approved{color:green;font-weight:bold;}
.rejected{color:red;font-weight:bold;}
</style>
</head>

<body>

<div class="box">

<h2><?= htmlspecialchars($project['name'] ?? 'No Title') ?></h2>

<p><b>Votes YES:</b> <?= $yes ?></p>
<p><b>Votes NO:</b> <?= $no ?></p>

<h3>Status:
    <span class="<?= strtolower($status) ?>">
        <?= $status ?>
    </span>
</h3>

</div>

</body>
</html>