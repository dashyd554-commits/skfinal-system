<?php
session_start();
include 'config/db.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    /* ================= FETCH USER ================= */
    $stmt = $conn->prepare("
        SELECT * FROM users
        WHERE username = :username
        LIMIT 1
    ");

    $stmt->execute([
        ':username' => $username
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    /* ================= CHECK USER ================= */
    if ($user && password_verify($password, $user['password'])) {

        if ($user['status'] !== 'approved') {
            $message = "Account not approved by admin.";
        } else {

            /* ================= SESSION ================= */
            $_SESSION['user'] = $user;

            /* ================= ROLE REDIRECT ================= */
            if ($user['role'] == 'admin') {
                header("Location: admin/admin_dashboard.php");
                exit();
            }
            elseif ($user['role'] == 'chairperson') {
                header("Location: chairperson/chairperson_dashboard.php");
                exit();
            }
            elseif ($user['role'] == 'secretary') {
                header("Location: secretary/secretary_dashboard.php");
                exit();
            }
            elseif ($user['role'] == 'treasurer') {
                header("Location: treasurer/treasurer_dashboard.php");
                exit();
            }
        }

    } else {
        $message = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<style>
.form-box{
    width:350px;
    margin:80px auto;
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 0 10px #ccc;
}
input{
    width:100%;
    padding:10px;
    margin-bottom:12px;
}
button{
    width:100%;
    padding:10px;
    background:#007bff;
    color:white;
    border:none;
}
.msg{margin-top:10px;color:red;}
</style>
</head>
<body>

<div class="form-box">
    <h2>SK System Login</h2>

    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>

    <div class="msg"><?= $message ?></div>

    <p><a href="register.php">Register Account</a></p>
</div>

</body>
</html>"