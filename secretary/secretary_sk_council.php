<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$message = "";

/* ================= HANDLE SUBMIT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // recount every submit
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM sk_council
        WHERE barangay_id = :barangay_id
    ");
    $stmt->execute([':barangay_id' => $barangay_id]);
    $count = $stmt->fetchColumn();

    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');

    if (empty($name) || empty($position)) {
        $message = "❌ All fields are required.";
    }
    elseif ($count >= 7) {
        $message = "❌ Only 7 SK Council members are allowed.";
    }
    else {

        $stmt = $conn->prepare("
            INSERT INTO sk_council (barangay_id, name, position, status)
            VALUES (:barangay_id, :name, :position, true)
        ");

        $stmt->execute([
            ':barangay_id' => $barangay_id,
            ':name' => $name,
            ':position' => $position
        ]);

        header("Location: secretary_sk_council.php?added=1");
        exit();
    }
}

/* ================= GET ALL COUNCIL ================= */
$stmt = $conn->prepare("
    SELECT * FROM sk_council
    WHERE barangay_id = :barangay_id
    ORDER BY id ASC
");
$stmt->execute([':barangay_id' => $barangay_id]);
$council = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countCouncil = count($council);
?>

<!DOCTYPE html>
<html>
<head>
<title>SK Council Setup</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
*{box-sizing:border-box;}

body{
    background: url('../assets/bg.jpg') no-repeat center center fixed;
    background-size: cover;
    margin:0;
}

.main{
    margin-left:190px;
    padding:20px;
    width:calc(100% - 200px);
}

.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}

.glass{
    background:rgba(255,255,255,0.35);
    backdrop-filter:blur(30px);
    border-radius:15px;
    box-shadow:0 8px 25px rgba(0,0,0,0.08);
    padding:20px;
}

input{
    width:100%;
    padding:12px;
    margin-bottom:12px;
    border-radius:8px;
    border:1px solid #ccc;
}

button{
    width:100%;
    padding:12px;
    border:none;
    background:#1e3c72;
    color:white;
    border-radius:8px;
    cursor:pointer;
    font-size:15px;
}

button:hover{
    background:#16305d;
}

.member-card{
    background:white;
    padding:12px;
    margin-bottom:10px;
    border-radius:10px;
    box-shadow:0 3px 10px rgba(0,0,0,0.05);
}

.member-card b{
    color:#1e3c72;
}

.msg{
    color:red;
    margin-bottom:10px;
}

.success{
    color:green;
    margin-bottom:10px;
}

.counter{
    font-size:18px;
    margin-bottom:15px;
    color:#444;
}

@media(max-width:900px){
    .grid{
        grid-template-columns:1fr;
    }

    .main{
        margin-left:70px;
        width:calc(100% - 80px);
    }
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <h2>👥 SK Council Setup Panel</h2>

    <div class="grid">

        <!-- LEFT FORM -->
        <div class="glass">

            <h3>Register SK Council Member</h3>

            <div class="counter">
                Current Registered: <b><?= $countCouncil ?>/7</b>
            </div>

            <?php if(isset($_GET['added'])){ ?>
                <div class="success">✅ Council member added successfully.</div>
            <?php } ?>

            <div class="msg"><?= $message ?></div>

            <form method="POST">

                <input type="text" name="name" placeholder="Enter Full Name" required>

                <input type="text" name="position" placeholder="Enter Position" required>

                <button type="submit">➕ Add Council Member</button>

            </form>

        </div>

        <!-- RIGHT LIST -->
        <div class="glass">

            <h3>Current SK Council Registry</h3>

            <?php if($countCouncil == 0){ ?>
                <p>No registered council members yet.</p>
            <?php } ?>

            <?php foreach($council as $member){ ?>
                <div class="member-card">
                    <b><?= htmlspecialchars($member['name']) ?></b><br>
                    <small><?= htmlspecialchars($member['position']) ?></small>
                </div>
            <?php } ?>

        </div>

    </div>

</div>

</body>
</html>