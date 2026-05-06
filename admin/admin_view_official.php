<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: admin_officials_information.php");
    exit();
}

$id = $_GET['id'];

$stmt = $conn->prepare("
    SELECT 
        u.*,
        b.barangay_name
    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.id = ?
");
$stmt->execute([$id]);
$official = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$official) {
    header("Location: admin_officials_information.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>View Official</title>
<link rel="stylesheet" href="../assets/style.css">
<style>
body{
    font-family:'Segoe UI',sans-serif;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
}
.wrapper{
    display:flex;
    min-height:100vh;
}
.main{
    flex:1;
    padding:30px;
}
.glass{
    max-width:700px;
    margin:auto;
    background:rgba(255,255,255,0.05);
    border:1px solid rgba(255,255,255,0.08);
    backdrop-filter:blur(18px);
    border-radius:18px;
    padding:30px;
    color:white;
}
h2{
    margin-bottom:20px;
}
.info{
    margin:12px 0;
    font-size:15px;
    padding-bottom:8px;
    border-bottom:1px solid rgba(255,255,255,0.05);
}
.label{
    font-weight:bold;
    color:#9fb8ff;
}
.btn-group{
    margin-top:25px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.back, .edit{
    display:inline-block;
    padding:10px 18px;
    color:white;
    text-decoration:none;
    border-radius:8px;
    font-size:14px;
}
.back{
    background:#5b8af5;
}
.edit{
    background:#28a745;
}
.back:hover,.edit:hover{
    opacity:0.85;
}
</style>
</head>
<body>

<div class="wrapper">
<?php include '../assets/sidebar.php'; ?>

<div class="main">
    <div class="glass">
        <h2>👤 Official Complete Information</h2>

        <div class="info"><span class="label">Full Name:</span> <?= htmlspecialchars($official['fullname']) ?></div>
        <div class="info"><span class="label">Age:</span> <?= htmlspecialchars($official['age']) ?></div>
        <div class="info"><span class="label">Position:</span> <?= ucfirst(htmlspecialchars($official['role'])) ?></div>
        <div class="info"><span class="label">Barangay:</span> <?= htmlspecialchars($official['barangay_name']) ?></div>
        <div class="info"><span class="label">Contact Number:</span> <?= htmlspecialchars($official['contact_number'] ?? 'N/A') ?></div>
        <div class="info"><span class="label">Username:</span> <?= htmlspecialchars($official['username']) ?></div>
        <div class="info"><span class="label">Password:</span> <?= htmlspecialchars($official['plain_password'] ?? 'N/A') ?></div>
        <div class="info"><span class="label">Current Status:</span> <?= ucfirst(htmlspecialchars($official['status'])) ?></div>

        <div class="btn-group">
            <a href="admin_officials_information.php" class="back">← Back to Officials List</a>
            <a href="admin_edit_account.php?id=<?= $official['id'] ?>" class="edit">✏ Edit Official</a>
        </div>
    </div>
</div>
</div>

</body>
</html>