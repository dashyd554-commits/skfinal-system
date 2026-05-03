<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$message = "";

/* ================= POSITIONS ================= */
$positions = [
    "1st Council",
    "2nd Council",
    "3rd Council",
    "4th Council",
    "5th Council",
    "6th Council",
    "7th Council"
];

/* ================= ADD MEMBER ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');

    // prevent numbers in name
    if (!preg_match("/^[a-zA-Z\s\.]+$/", $name)) {
        $message = "❌ Name must not contain numbers.";
    }
    elseif (empty($name) || empty($position)) {
        $message = "❌ All fields are required.";
    }
    else {

        // check duplicate name
        $check = $conn->prepare("
            SELECT id FROM sk_council
            WHERE barangay_id = :bid AND name = :name
        ");
        $check->execute([
            ':bid' => $barangay_id,
            ':name' => $name
        ]);

        if ($check->fetch()) {
            $message = "❌ Name already exists.";
        }
        else {

            // check position limit (unique per barangay already enforced)
            $checkPos = $conn->prepare("
                SELECT id FROM sk_council
                WHERE barangay_id = :bid AND position = :pos
            ");
            $checkPos->execute([
                ':bid' => $barangay_id,
                ':pos' => $position
            ]);

            if ($checkPos->fetch()) {
                $message = "❌ Position already taken.";
            } else {

                $stmt = $conn->prepare("
                    INSERT INTO sk_council (barangay_id, name, position, status)
                    VALUES (:bid, :name, :pos, true)
                ");

                $stmt->execute([
                    ':bid' => $barangay_id,
                    ':name' => $name,
                    ':pos' => $position
                ]);

                header("Location: secretary_sk_council.php?added=1");
                exit();
            }
        }
    }
}

/* ================= FETCH COUNCIL ================= */
$stmt = $conn->prepare("
    SELECT * FROM sk_council
    WHERE barangay_id = :bid
    ORDER BY id ASC
");
$stmt->execute([':bid' => $barangay_id]);
$council = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>SK Council Setup</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
}

.main{
    margin-left:190px;   /* moved dashboard 30px to left */
    padding:20px;
    width:calc(100% - 200px);
    overflow-x:hidden;
}

.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}

.glass{
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    border-radius:15px;
    padding:20px;
}

input, select{
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
    border-radius:8px;
}

.member{
    background:white;
    padding:10px;
    margin-bottom:10px;
    border-radius:8px;
}

.actions a{
    margin-right:10px;
    color:white;
    text-decoration:none;
}

h3{color:white;}
.actions{
    margin-top:10px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.btn{
    display:inline-block;
    padding:8px 12px;
    border-radius:8px;
    text-decoration:none;
    font-size:13px;
    font-weight:bold;
    text-align:center;
    transition:0.2s ease;
}

/* EDIT BUTTON */
.edit-btn{
    background:purple;
    color:white;
}

.edit-btn:hover{
    background:#16305d;
}

/* DELETE BUTTON */
.delete-btn{
    background:palevioletred;
    color:white;
}

.delete-btn:hover{
    background:#c0392b;
}
a{color: white;}
h3{
    color: whitesmoke;
    text-align: center;
    margin-bottom: 20px;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h3>👥 SK Council Setup</h3>

<div class="grid">

<!-- FORM -->
<div class="glass">

<h3>Add Member</h3>

<?php if(isset($_GET['added'])) echo "<p style='color:green'>Added successfully</p>"; ?>
<p style="color:red"><?= $message ?></p>

<form method="POST">

<input type="text" name="name" placeholder="Full Name" required pattern="[A-Za-z\s\.]+"
title="Letters only">

<select name="position" required>
    <option value="">Select Position</option>
    <?php foreach($positions as $p){ ?>
        <option value="<?= $p ?>"><?= $p ?></option>
    <?php } ?>
</select>

<button type="submit">Add</button>

</form>

</div>

<!-- LIST -->
<div class="glass">

<h3>Members</h3>

<?php foreach($council as $c){ ?>
    <div class="member">
        <b><?= htmlspecialchars($c['name']) ?></b><br>
        <?= $c['position'] ?><br>

        <div class="actions">
    <a class="btn edit-btn" href="secretary_edit.php?id=<?= $c['id'] ?>">
        ✏️ Edit
    </a>

    <a class="btn delete-btn" href="secretary_delete.php?id=<?= $c['id'] ?>" onclick="return confirm('Delete this member?')">
        🗑️ Delete
    </a>
</div>
    </div>
<?php } ?>

</div>

</div>

</div>

</body>
</html>