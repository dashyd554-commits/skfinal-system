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

<link rel="stylesheet" href="../assets/style.css">

<style>

/* GLOBAL FIX */
*{
    box-sizing:border-box;
}

body{
    font-family: 'Sora', 'Segoe UI', sans-serif;
    color: var(--text);
    min-height: 100vh;

    /* background image + overlay (clean + consistent) */
    background:
        linear-gradient(rgba(13, 27, 42, 0.75), rgba(13, 27, 42, 0.85)),
        url('../assets/bg.jpg') no-repeat center center fixed;

    background-size: cover;
    overflow-y: auto;
}

/* WRAPPER LIKE OTHER PAGES */
.wrapper{
    display:flex;
    min-height:100vh;
}

/* MAIN AREA FIX (IMPORTANT) */
.main{
    flex:1;
    padding:20px;
    display:flex;
    flex-direction:column;
    align-items:center; /* center content */
}

/* TITLE */
h2{
    color:whitesmoke;
    text-align:center;
    margin-bottom:20px;
}

/* FORM CONTAINER FIX */
.form-box{
    width:100%;
    max-width:520px;   /* responsive instead of margin-left */
    background:rgba(255,255,255,0.2);
    padding:20px;
    border-radius:12px;
    backdrop-filter:blur(12px);
}

/* INPUTS */
input, textarea{
    width:100%;
    padding:10px;
    margin:8px 0;
    border:none;
    border-radius:6px;
}

/* BUTTON */
button{
    width:100%;
    padding:10px;
    background:#1e3c72;
    color:white;
    border:none;
    border-radius:6px;
    cursor:pointer;
}

/* MESSAGE */
.msg{
    margin-top:10px;
    color:#00ff88;
    text-align:center;
}

/* RESPONSIVE */
@media(max-width:768px){
    .form-box{
        width:95%;
    }
}

</style>
</head>

<body>

<div class="wrapper">

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

</div>

</body>
</html>