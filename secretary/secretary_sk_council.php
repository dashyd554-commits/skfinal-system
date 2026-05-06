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
    "1st Council","2nd Council","3rd Council",
    "4th Council","5th Council","6th Council","7th Council"
];

/* ================= ADD MEMBER ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');

    if (!preg_match("/^[a-zA-Z\s\.]+$/", $name)) {
        $message = "❌ Name must not contain numbers.";
    }
    elseif (empty($name) || empty($position)) {
        $message = "❌ All fields are required.";
    }
    else {

        $check = $conn->prepare("
            SELECT id FROM sk_council
            WHERE barangay_id = :bid AND name = :name
        ");
        $check->execute([':bid'=>$barangay_id, ':name'=>$name]);

        if ($check->fetch()) {
            $message = "❌ Name already exists.";
        } else {

            $checkPos = $conn->prepare("
                SELECT id FROM sk_council
                WHERE barangay_id = :bid AND position = :pos
            ");
            $checkPos->execute([':bid'=>$barangay_id, ':pos'=>$position]);

            if ($checkPos->fetch()) {
                $message = "❌ Position already taken.";
            } else {

                $stmt = $conn->prepare("
                    INSERT INTO sk_council (barangay_id, name, position, status)
                    VALUES (:bid, :name, :pos, true)
                ");

                $stmt->execute([
                    ':bid'=>$barangay_id,
                    ':name'=>$name,
                    ':pos'=>$position
                ]);

                header("Location: secretary_sk_council.php?added=1");
                exit();
            }
        }
    }
}

/* ================= FETCH ================= */
$stmt = $conn->prepare("
    SELECT * FROM sk_council
    WHERE barangay_id = :bid
    ORDER BY id ASC
");
$stmt->execute([':bid'=>$barangay_id]);
$council = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>SK Council Setup</title>

<link rel="stylesheet" href="../assets/style.css">

<style>
*{box-sizing:border-box;}

body{
    margin:0;
    height:100vh;
    overflow:hidden;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    font-family:Arial;
}

/* ===== WRAPPER FIX ===== */
.wrapper{
    display:flex;
    height:100vh;
    overflow:hidden;
}

/* ===== MAIN FIX ===== */
.main{
    flex:1;
    height:100vh;
    overflow-y:auto;
    padding:15px;
}

/* TITLE */
h3{
    color:whitesmoke;
    text-align:center;
    margin:10px 0;
}

/* GRID */
.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:15px;
}

/* GLASS */
.glass{
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    border-radius:12px;
    padding:15px;
}

/* FORM */
input, select{
    width:100%;
    padding:8px;
    margin-bottom:8px;
}

button{
    width:100%;
    padding:10px;
    background:#1e3c72;
    color:white;
    border:none;
    border-radius:8px;
}

/* MEMBER BOX */
.member{
    background:white;
    padding:10px;
    margin-bottom:10px;
    border-radius:8px;
}

/* BUTTONS */
.actions{
    display:flex;
    gap:8px;
    margin-top:8px;
}

.btn{
    padding:6px 10px;
    border-radius:6px;
    text-decoration:none;
    font-size:12px;
    color:white;
}

.edit-btn{background:purple;}
.delete-btn{background:palevioletred;}

/* RESPONSIVE */
@media(max-width:768px){
    body{overflow:auto;}

    .wrapper{
        flex-direction:column;
    }

    .main{
        height:auto;
    }

    .grid{
        grid-template-columns:1fr;
    }
}
</style>
</head>

<body>

<div class="wrapper">

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

<input type="text" name="name" placeholder="Full Name" required>

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
    <?= $c['position'] ?>

    <div class="actions">
        <a class="btn edit-btn" href="secretary_edit.php?id=<?= $c['id'] ?>">✏️ Edit</a>
        <a class="btn delete-btn" href="secretary_delete.php?id=<?= $c['id'] ?>" onclick="return confirm('Delete this member?')">🗑️ Delete</a>
    </div>
</div>
<?php } ?>

</div>

</div>

</div>

</div>

</body>
</html>