<?php
include '../config/db.php';
require '../vendor/autoload.php';
require '../config/mail.php';

$message = "";

/* ================= SEND OTP ================= */
if (isset($_POST['send_code'])) {

    $email = trim($_POST['email']);

    if (!$email) {
        $message = "Please enter your registered email.";
    } else {

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = "Email not found.";
        } else {

            $code = rand(100000,999999);
            $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $stmt = $conn->prepare("
                UPDATE users
                SET reset_code = ?, reset_expiry = ?
                WHERE email = ?
            ");
            $stmt->execute([$code, $expiry, $email]);

            $subject = "SK Password Reset Code";
            $body = "
                <h3>Forgot Password OTP</h3>
                <p>Your reset code is:</p>
                <h2>$code</h2>
                <p>This code expires in 10 minutes.</p>
            ";

            sendEmail($email, $subject, $body);

            $message = "OTP sent to your email.";
        }
    }
}

/* ================= RESET PASSWORD ================= */
if (isset($_POST['reset_password'])) {

    $email = trim($_POST['email']);
    $code = trim($_POST['code']);
    $newpass = trim($_POST['new_password']);
    $confirmpass = trim($_POST['confirm_password']);

    if (!$email || !$code || !$newpass || !$confirmpass) {
        $message = "All fields are required.";
    }

    elseif ($newpass !== $confirmpass) {
        $message = "Passwords do not match.";
    }

    elseif (strlen($newpass) < 8) {
        $message = "Password must be at least 8 characters.";
    }

    else {

        $stmt = $conn->prepare("
            SELECT * FROM users
            WHERE email = ? AND reset_code = ?
            AND reset_expiry >= NOW()
        ");
        $stmt->execute([$email, $code]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $message = "Invalid or expired OTP.";
        } else {

            $hashed = password_hash($newpass, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                UPDATE users
                SET password = ?, reset_code = NULL, reset_expiry = NULL
                WHERE email = ?
            ");
            $stmt->execute([$hashed, $email]);

            echo "<script>
                alert('Password successfully changed!');
                window.location='../index.php';
            </script>";
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Forgot Password</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body{
    font-family:Arial;
    background:url('../assets/bg.jpg') no-repeat center center/cover;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
}
.box{
    width:380px;
    background:rgba(255,255,255,0.9);
    padding:25px;
    border-radius:12px;
}
input,button{
    width:100%;
    padding:12px;
    margin:8px 0;
}
button{
    background:#2d89ef;
    color:white;
    border:none;
}
.msg{
    color:red;
    text-align:center;
}
a{
    display:block;
    text-align:center;
    margin-top:10px;
}
</style>
</head>
<body>

<div class="box">
    <h2 align="center">🔑 Forgot Password</h2>

    <form method="POST">
        <input type="email" name="email" placeholder="Registered Email" required>
        <button type="submit" name="send_code">Send OTP Code</button>
    </form>

    <hr>

    <form method="POST">
        <input type="email" name="email" placeholder="Registered Email" required>
        <input type="text" name="code" placeholder="Enter OTP Code" required>
        <input type="password" name="new_password" placeholder="New Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
        <button type="submit" name="reset_password">Reset Password</button>
    </form>

    <div class="msg"><?= $message ?></div>

    <a href="../index.php">← Back to Login</a>
</div>

</body>
</html>