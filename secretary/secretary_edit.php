<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$id = $_GET['id'] ?? null;

if (!$id) {
    die("No council member selected.");
}

/* ================= LOAD CURRENT RECORD ================= */
$stmt = $conn->prepare("
    SELECT * FROM sk_council
    WHERE id = :id
    AND barangay_id = :bid
");
$stmt->execute([
    ':id' => $id,
    ':bid' => $barangay_id
]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Council member not found.");
}

/* ================= POSITIONS ================= */
$positions = [
    "1st Council","2nd Council","3rd Council",
    "4th Council","5th Council","6th Council","7th Council"
];

$error = "";

/* ================= UPDATE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name']);
    $position = trim($_POST['position']);

    /* NAME VALIDATION */
    if (!preg_match("/^[a-zA-Z\s\.]+$/", $name)) {
        $error = "⚠ Invalid name format.";
    } else {

        /* CHECK IF POSITION ALREADY TAKEN BY ANOTHER MEMBER */
        $check = $conn->prepare("
            SELECT COUNT(*)
            FROM sk_council
            WHERE barangay_id = :bid
            AND position = :position
            AND id != :id
        ");
        $check->execute([
            ':bid' => $barangay_id,
            ':position' => $position,
            ':id' => $id
        ]);

        $exists = $check->fetchColumn();

        if ($exists > 0) {
            $error = "⚠ Position already assigned to another council member.";
        } else {

            $update = $conn->prepare("
                UPDATE sk_council
                SET name = :name,
                    position = :position
                WHERE id = :id
                AND barangay_id = :bid
            ");

            $update->execute([
                ':name' => $name,
                ':position' => $position,
                ':id' => $id,
                ':bid' => $barangay_id
            ]);

            header("Location: secretary_sk_council.php?updated=1");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit SK Council</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    font-family:Arial;
}

.main{
    margin-left:190px;
    padding:20px;
    width:calc(100% - 210px);
}

.form{
    max-width:500px;
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    border-radius:15px;
    padding:25px;
    color:white;
    box-shadow:0 8px 25px rgba(0,0,0,0.25);
}

h3{
    text-align:center;
    margin-bottom:20px;
}

label{
    display:block;
    margin-top:10px;
    margin-bottom:5px;
}

input,select{
    width:100%;
    padding:10px;
    margin-bottom:12px;
    border:none;
    border-radius:8px;
}

button{
    width:100%;
    padding:12px;
    background:#1e3c72;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-size:15px;
}

button:hover{
    background:#16325c;
}

.error{
    background:rgba(255,0,0,0.2);
    padding:10px;
    border-radius:8px;
    margin-bottom:15px;
    color:#ffdede;
}

@media(max-width:768px){
    .main{
        margin-left:0;
        width:100%;
        padding:10px;
    }
}
</style>
</head>

<body>

<div class="main">

<div class="form">

<h3>✏ Edit SK Council Member</h3>

<?php if($error!=""){ ?>
    <div class="error"><?= $error ?></div>
<?php } ?>

<form method="POST">

<label>Full Name</label>
<input type="text" name="name"
value="<?= htmlspecialchars($data['name']) ?>"
pattern="[A-Za-z\s\.]+" required>

<label>Position</label>
<select name="position" required>
<?php foreach($positions as $p){ ?>
<option value="<?= $p ?>" <?= $data['position']==$p?'selected':'' ?>>
<?= $p ?>
</option>
<?php } ?>
</select>

<button type="submit">Update Council Member</button>
<a href="secretary_sk_council.php" class="back-btn">← Back</a>
</form>

</div>

</div>

</body>
</html>