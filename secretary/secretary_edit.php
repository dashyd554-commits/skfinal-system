<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

$id = $_GET['id'] ?? null;

$stmt = $conn->prepare("SELECT * FROM sk_council WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Not found");
}

$positions = [
    "1st Council","2nd Council","3rd Council",
    "4th Council","5th Council","6th Council","7th Council"
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name']);
    $position = $_POST['position'];

    if (!preg_match("/^[a-zA-Z\s\.]+$/", $name)) {
        $error = "Invalid name";
    } else {

        $update = $conn->prepare("
            UPDATE sk_council 
            SET name=?, position=?
            WHERE id=?
        ");

        $update->execute([$name, $position, $id]);

        header("Location: secretary_sk_council.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Council</title>
<link rel="stylesheet" href="../assets/style.css">

<style>
body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    font-family:Arial;
}
.form{
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    border-radius:15px;
    padding:20px;
    color:white;
    box-shadow:0 8px 25px rgba(0,0,0,0.2);
    margin-bottom:20px;
}
input,select{
    width:100%;
    padding:10px;
    margin-bottom:10px;
}
button{
    width:100%;
    padding:10px;
    background:#1e3c72;
    color:white;
    border:none;
}
</style>
</head>

<body>

<div class="form">

<h3>Edit Council</h3>

<form method="POST">

<input type="text" name="name"
value="<?= htmlspecialchars($data['name']) ?>"
pattern="[A-Za-z\s\.]+" required>

<select name="position">
<?php foreach($positions as $p){ ?>
<option value="<?= $p ?>" <?= $data['position']==$p?'selected':'' ?>>
<?= $p ?>
</option>
<?php } ?>
</select>

<button type="submit">Update</button>

</form>

</div>

</body>
</html>