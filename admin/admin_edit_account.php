<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Invalid request");
}

/* ================= LOAD USER ================= */
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

$message = "";

/* ================= AGE LIMIT (SK RULE) ================= */
$min_age = 15;
$max_age = 30;

/* ================= UPDATE USER ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullname = trim($_POST['fullname'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? '';
    $plain_password = $_POST['plain_password'] ?? ''; // ✅ NEW

    /* ================= VALIDATION ================= */

    if ($age < $min_age || $age > $max_age) {
        $message = "❌ Age must be between $min_age and $max_age.";
    }

    else {

        /* ================= DUPLICATE CHECK (USERNAME) ================= */
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $id]);

        if ($stmt->fetch()) {
            $message = "❌ Username already exists!";
        }

        /* ================= DUPLICATE CHECK (FULL NAME) ================= */
        else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE fullname = ? AND id != ?");
            $stmt->execute([$fullname, $id]);

            if ($stmt->fetch()) {
                $message = "❌ Full name already exists!";
            }

            else {

                /* ================= PASSWORD HANDLING ================= */
                if (!empty($plain_password)) {

                    if (strlen($plain_password) < 8) {
                        $message = "❌ Password must be at least 8 characters!";
                    }

                    else {
                        $hashed = password_hash($plain_password, PASSWORD_DEFAULT);
                        $plain = $plain_password;
                    }

                } else {
                    $hashed = $user['plain_password'];
                    $plain = $user['plain_password'];
                }

                if (empty($message)) {

                    /* ================= UPDATE ================= */
                    $stmt = $conn->prepare("
                        UPDATE users SET
                            fullname = ?,
                            age = ?,
                            username = ?,
                            plain_password = ?,
                            role = ?,
                            status = ?
                        WHERE id = ?
                    ");

                    $updated = $stmt->execute([
                        $fullname,
                        $age,
                        $username,
                        $plain,
                        $role,
                        $status,
                        $id
                    ]);

                    if ($updated) {
                        echo "<script>
                            alert('✅ Account updated successfully!');
                            window.location='admin_officials_information.php';
                        </script>";
                        exit();
                    } else {
                        $message = "❌ Update failed!";
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit Account</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial,sans-serif;
}

body{
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    min-height:100vh;
}

.main{
    margin-left:90px;
    width:calc(100% - 90px);
    padding:20px;
}

.container{
    max-width:600px;
    margin:30px auto;
    background:rgba(255,255,255,0.18);
    backdrop-filter:blur(18px);
    padding:30px;
    border-radius:18px;
}

h2{
    text-align:center;
    color:white;
    margin-bottom:20px;
}

.message{
    text-align:center;
    color:yellow;
    margin-bottom:15px;
    font-weight:bold;
}

input,select{
    width:100%;
    padding:12px;
    margin-bottom:12px;
    border:none;
    border-radius:8px;
}

.btn-group{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-top:15px;
}

button,.cancel{
    flex:1;
    padding:12px;
    border:none;
    border-radius:8px;
    text-align:center;
    color:white;
    font-weight:bold;
    cursor:pointer;
    text-decoration:none;
}

button{ background:#007bff; }
.cancel{ background:#dc3545; }

.footer{
    width:100%;
    text-align:center;
    margin-top:20px;
    padding:14px;
    color:white;
    background:rgba(0,0,0,0.25);
    border-radius:10px;
    font-size:13px;
}
html, body{
    overflow-x:hidden;
}
/* MOBILE FIX */
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

<div class="main">

<h2>✏️ Edit User Account</h2>

<div class="container">

    <?php if(!empty($message)) { ?>
        <div class="message"><?= $message ?></div>
    <?php } ?>

    <form method="POST">

        <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" required>

        <input type="number" name="age" value="<?= htmlspecialchars($user['age'] ?? '') ?>" required>

        <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>

        <!-- ✅ PASSWORD EDIT FIELD -->
        <input type="text" name="plain_password" placeholder="Enter new password (leave blank to keep old)">

        <select name="role" required>
            <option value="chairman" <?= $user['role']=='chairman'?'selected':'' ?>>Chairman</option>
            <option value="secretary" <?= $user['role']=='secretary'?'selected':'' ?>>Secretary</option>
            <option value="treasurer" <?= $user['role']=='treasurer'?'selected':'' ?>>Treasurer</option>
        </select>

        <select name="status">
            <option value="pending" <?= $user['status']=='pending'?'selected':'' ?>>Pending</option>
            <option value="approved" <?= $user['status']=='approved'?'selected':'' ?>>Approved</option>
            <option value="inactive" <?= $user['status']=='inactive'?'selected':'' ?>>Inactive</option>
        </select>

        <div class="btn-group">
            <button type="submit">✅ Update Account</button>
            <a class="cancel" href="admin_officials_information.php">❌ Cancel</a>
        </div>

    </form>

</div>

<div class="footer">
    © 2026 SK Decision Support System | Responsive Community Planning Platform
</div>

</div>

</body>
</html>