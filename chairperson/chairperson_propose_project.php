<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'chairman') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$created_by = $_SESSION['user']['id'];

$message = "";

/* ================= SUBMIT PROJECT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $purpose = trim($_POST['purpose']);
    $target_participants = (int)$_POST['target_participants'];
    $budget_requested = (float)$_POST['budget_requested'];
    $expected_benefit = trim($_POST['expected_benefit']);
    $date_proposed = $_POST['date_proposed'];

    if (!$title || !$description || !$purpose || !$target_participants || !$budget_requested || !$date_proposed) {
        $message = "Please complete all required fields.";
    } else {

        $stmt = $conn->prepare("
            INSERT INTO projects (
                barangay_id,
                created_by,
                name,
                description,
                purpose,
                target_participants,
                budget_requested,
                status,
                created_at
            )
            VALUES (
                :barangay_id,
                :created_by,
                :name,
                :description,
                :purpose,
                :target_participants,
                :budget_requested,
                'pending_secretary',
                NOW()
            )
        ");

        $stmt->execute([
            ':barangay_id' => $barangay_id,
            ':created_by' => $created_by,
            ':name' => $title,
            ':description' => $description,
            ':purpose' => $purpose,
            ':target_participants' => $target_participants,
            ':budget_requested' => $budget_requested
        ]);

        $message = "Project proposal submitted successfully and sent to Secretary for voting.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Propose Project</title>
<link rel="stylesheet" href="../assets/sbstyle.css">
<link rel="stylesheet" href="../assets/style.css">

<style>
.main{
    margin-left:200px;
    padding:20px;
}

.form-box{
    width:520px;
    background:rgba(255,255,255,0.2);
    padding:20px;
    border-radius:10px;
}

input, textarea{
    width:100%;
    padding:10px;
    margin:8px 0;
}

button{
    width:100%;
    padding:10px;
    background:#28a745;
    color:white;
    border:none;
}

.msg{
    margin-top:10px;
    color:green;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>📁 Propose Project</h2>

<div class="form-box">

<form method="POST">

    <input type="text" name="title" placeholder="Project Title (e.g. Youth Center Construction)" required>

    <textarea name="description" placeholder="Project Description" required></textarea>

    <textarea name="purpose" placeholder="Purpose of Project" required></textarea>

    <input type="number" name="target_participants" placeholder="Target Beneficiaries" required>

    <input type="number" step="0.01" name="budget_requested" placeholder="Requested Budget (₱)" required>

    <input type="text" name="expected_benefit" placeholder="Expected Long-Term Benefit" required>

    <input type="date" name="date_proposed" required>

    <button type="submit">Submit Project Proposal</button>

</form>

<div class="msg"><?= $message ?></div>

</div>

</div>

</body>
</html>