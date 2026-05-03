<?php
session_start();
include '../config/db.php';

/* ================= SECURITY CHECK ================= */
if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

$message = "";
$messageType = "";

/* ================= AGE LIMIT ================= */
$min_age = 15;
$max_age = 30;

/* ================= ADD OFFICIAL ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullname      = trim($_POST['fullname'] ?? '');
    $age           = intval($_POST['age'] ?? 0);
    $username      = trim($_POST['username'] ?? '');
    $plain_password = trim($_POST['plain_password'] ?? '');
    $role          = $_POST['role'] ?? '';
    $barangay_id   = $_POST['barangay_id'] ?? null;

    /* ================= VALIDATION ================= */

    if ($age < $min_age || $age > $max_age) {
        $message = "❌ Age must be between $min_age and $max_age.";
        $messageType = "error";
    }

    elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $plain_password)) {
        $message = "❌ Password must be at least 8 characters and contain letters and numbers.";
        $messageType = "error";
    }

    else {

        /* USERNAME DUPLICATE */
        $stmt = $conn->prepare("SELECT 1 FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->fetchColumn()) {
            $message = "❌ Username already exists!";
            $messageType = "error";
        }

        else {

            /* FULLNAME DUPLICATE */
            $stmt = $conn->prepare("SELECT 1 FROM users WHERE fullname = ?");
            $stmt->execute([$fullname]);

            if ($stmt->fetchColumn()) {
                $message = "❌ Full name already exists!";
                $messageType = "error";
            }

            else {

                /* PASSWORD DUPLICATE */
                $stmt = $conn->prepare("SELECT 1 FROM users WHERE plain_password = ?");
                $stmt->execute([$plain_password]);

                if ($stmt->fetchColumn()) {
                    $message = "❌ Password already used!";
                    $messageType = "error";
                }

                else {

                    /* ONE ROLE PER BARANGAY CHECK */
                    $stmt = $conn->prepare("
                        SELECT 1 FROM users 
                        WHERE barangay_id = ? AND role = ?
                    ");
                    $stmt->execute([$barangay_id, $role]);

                    if ($stmt->fetchColumn()) {
                        $message = "❌ This barangay already has a ".ucfirst($role).".";
                        $messageType = "error";
                    }

                    else {

                        /* ================= INSERT USER ================= */
                        $stmt = $conn->prepare("
                            INSERT INTO users
                            (fullname, age, username, plain_password, role, barangay_id, status, last_activity)
                            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
                        ");

                        $inserted = $stmt->execute([
                            $fullname,
                            $age,
                            $username,
                            $plain_password,
                            $role,
                            $barangay_id
                        ]);

                        if ($inserted) {

                            /* ================= GET BARANGAY NAME ================= */
                            $barangayStmt = $conn->prepare("SELECT barangay_name FROM barangays WHERE id = ?");
                            $barangayStmt->execute([$barangay_id]);
                            $barangay_name = $barangayStmt->fetchColumn();

                            /* ================= AUDIT LOG ================= */
                            $log = "Admin created user {$fullname} ({$role})";

                            $audit = $conn->prepare("
                                INSERT INTO audit_logs
                                (username, barangay_name, action_type, table_name, description)
                                VALUES (?, ?, 'INSERT', 'users', ?)
                            ");

                            $audit->execute([
                                'admin',
                                $barangay_name,
                                $log
                            ]);

                            $message = "✅ SK Official added successfully!";
                            $messageType = "success";
                        } else {
                            $message = "❌ Failed to insert official.";
                            $messageType = "error";
                        }
                    }
                }
            }
        }
    }
}

/* ================= BARANGAYS ================= */
$stmt = $conn->prepare("SELECT * FROM barangays ORDER BY barangay_name ASC");
$stmt->execute();
$barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Add SK Officials</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial;
}

body{
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
}

.main{
    margin-left:190px;
    width:calc(100% - 190px);
    padding:20px;
}

@media(max-width:768px){
    .main{
        margin-left:0;
        width:100%;
    }
}

.header{
    text-align:center;
    color:white;
    margin-bottom:20px;
}

.form-box{
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(15px);
    padding:20px;
    border-radius:15px;
    margin-bottom:20px;
}

input, select{
    width:100%;
    padding:10px;
    margin:8px 0;
    border:none;
    border-radius:8px;
}

button{
    width:100%;
    padding:10px;
    background:#1e3c72;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight:bold;
}

button:hover{
    opacity:0.9;
}

.message{
    padding:12px;
    border-radius:8px;
    font-weight:bold;
    margin-bottom:10px;
    text-align:center;
}

.message.success{
    background:#d1e7dd;
    color:#0f5132;
    border:1px solid #badbcc;
}

.message.error{
    background:#f8d7da;
    color:#842029;
    border:1px solid #f5c2c7;
}

.footer{
    width:100%;
    text-align:center;
    margin-top:25px;
    padding:14px;
    color:white;
    background:rgba(0,0,0,0.25);
    border-radius:10px;
    font-size:13px;
}

html, body{
    overflow-x:hidden;
}

@media(max-width:768px){
    .footer{
        font-size:11px;
        padding:10px;
        margin-bottom:10px;
    }
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>👮 Add SK Officials</h2>
    <p>Admin-controlled creation of municipal SK officials</p>
</div>

<?php if(!empty($message)) { ?>
    <div class="message <?= $messageType ?>">
        <?= $message ?>
    </div>
<?php } ?>

<div class="form-box">

<form method="POST">

    <input type="text" name="fullname" placeholder="Full Name" required>

    <input type="number" name="age" placeholder="Age" required>

    <input type="text" name="username" placeholder="Username" required>

    <input type="text" name="plain_password" placeholder="Password" required>

    <select name="role" required>
        <option value="">Select Role</option>
        <option value="chairman">Chairman</option>
        <option value="secretary">Secretary</option>
        <option value="treasurer">Treasurer</option>
    </select>

    <select name="barangay_id" required>
        <option value="">Select Barangay</option>
        <?php foreach($barangays as $b){ ?>
            <option value="<?= $b['id'] ?>">
                <?= htmlspecialchars($b['barangay_name']) ?>
            </option>
        <?php } ?>
    </select>

    <button type="submit">➕ Add Official</button>

</form>

</div>

</div>

<div class="footer">
    © 2026 SK Decision Support System | Responsive Community Planning Platform
</div>

</body>
</html>