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

/* ================= SUBMIT ACTIVITY ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $purpose = trim($_POST['purpose']);
    $target_participants = (int)$_POST['target_participants'];
    $budget_requested = (float)$_POST['budget_requested'];
    $expected_benefit = trim($_POST['expected_benefit']);
    $date_proposed = $_POST['date_proposed'];

    if (!$title || !$description || !$purpose || !$target_participants || !$budget_requested || !$date_proposed) {
        $message = "Please fill in all required fields.";
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

        $message = "Activity proposal submitted successfully and sent to Secretary for voting.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Propose Activity</title>
<link rel="stylesheet" href="../assets/sbstyle.css">
<link rel="stylesheet" href="../assets/style.css">

<style>
.main{
    margin-left:200px;
    padding:20px;
}

.form-box{
    width:500px;
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
    padding:10px;
    width:100%;
    background:#2d89ef;
    color:white;
    border:none;
}
.msg{
    margin:10px 0;
    color:green;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>📋 Propose Activity</h2>

<div class="form-box">

<form method="POST">

    <input type="text" name="title" placeholder="Activity Title" required>

    <textarea name="description" placeholder="Description" required></textarea>

    <textarea name="purpose" placeholder="Purpose" required></textarea>

    <input type="number" name="target_participants" placeholder="Target Participants" required>

    <input type="number" step="0.01" name="budget_requested" placeholder="Proposed Budget (₱)" required>

    <input type="text" name="expected_benefit" placeholder="Expected Benefit" required>

    <input type="date" name="date_proposed" required>

    <button type="submit">Submit Proposal</button>

</form>

<div class="msg"><?= $message ?></div>

</div>

</div>

</body>
</html>