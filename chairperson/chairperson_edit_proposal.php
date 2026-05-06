<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'chairman') {
    header("Location: ../index.php");
    exit();
}

$id = $_GET['id'] ?? null;

$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    die("Proposal not found");
}

/* UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = $_POST['name'];
    $purpose = $_POST['purpose'];
    $budget = $_POST['budget_requested'];

    $update = $conn->prepare("
        UPDATE projects 
        SET name=?, purpose=?, budget_requested=?
        WHERE id=?
    ");

    $update->execute([$name, $purpose, $budget, $id]);

    header("Location: chairperson_status.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Proposal</title>
<link rel="stylesheet" href="../assets/style.css">

<style>
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

/* MAIN */
.main{
    margin-left:190px;
    padding:30px;
    width:calc(100% - 190px);
}

/* CARD */
.card{
    max-width:500px;
    margin:40px auto;
    padding:25px;
    background: rgba(255,255,255,0.08);
    backdrop-filter: blur(15px);
    border-radius:15px;
    box-shadow:0 8px 25px rgba(0,0,0,0.3);
}

/* TITLE */
h2{
    text-align:center;
    margin-bottom:20px;
}

/* INPUTS */
input{
    width:100%;
    padding:12px;
    margin-bottom:12px;
    border:none;
    border-radius:8px;
    background: rgba(255,255,255,0.1);
    color:white;
    outline:none;
}

input::placeholder{
    color:#ccc;
}

/* BUTTON */
button{
    width:100%;
    padding:12px;
    background:#1e3c72;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight:bold;
}

button:hover{
    background:#16305d;
}
h2{
    color: whitesmoke;
}

/* LABELS */
label{
    font-size:14px;
    color:#e5e7eb;
}

/* MOBILE */
@media(max-width:768px){
    .main{
        margin-left:0;
        width:100%;
        padding:15px;
    }

    .card{
        margin:20px auto;
    }
}
</style>

</head>

<body>

<div class="main">

<h2>✏️ Edit Proposal</h2>

<div class="card">

<form method="POST">

    <label>Proposal Title</label>
    <input type="text" name="name"
           value="<?= htmlspecialchars($project['name']) ?>"
           required>

    <label>Purpose</label>
    <input type="text" name="purpose"
           value="<?= htmlspecialchars($project['purpose']) ?>"
           required>

    <label>Requested Budget</label>
    <input type="number" name="budget_requested"
           value="<?= $project['budget_requested'] ?>"
           required>

    <button type="submit">Update Proposal</button>
    <a href="chairperson_status.php" class="back-btn">← Back</a>

</form>

</div>

</div>

</body>
</html>