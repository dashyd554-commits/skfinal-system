<?php
include '../config/db.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $otp = trim($_POST['otp'] ?? '');

    if (!$email || !$otp) {
        $message = "⚠️ All fields are required!";
    } else {

        /* CHECK USER */
        $stmt = $conn->prepare("
            SELECT * FROM users 
            WHERE email = ? AND verification_code = ?
        ");
        $stmt->execute([$email, $otp]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {

            /* CHECK EXPIRY */
            $now = date('Y-m-d H:i:s');

            if ($user['verification_expiry'] < $now) {
                $message = "❌ OTP expired. Please register again.";
            } else {

                /* VERIFY USER */
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET is_verified = 1,
                        verification_code = NULL,
                        verification_expiry = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);

                echo "<script>
                alert('✅ Email verified successfully!');
                window.location = '../index.php';
                </script>";
                exit();
            }

        } else {
            $message = "❌ Invalid OTP or email!";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Email Verification</title>

<style>
body{
    font-family: Arial;
    background:#f2f2f2;
}

.box{
    width:400px;
    margin:100px auto;
    padding:25px;
    background:white;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.1);
}

input{
    width:100%;
    padding:12px;
    margin:8px 0;
    border:1px solid #ccc;
    border-radius:8px;
}

button{
    width:100%;
    padding:12px;
    background:#4f6ef7;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
}

.message{
    margin-top:10px;
    text-align:center;
    color:red;
}
</style>
</head>

<body>

<div class="box">

<h2>🔐 Verify Your Email</h2>

<form method="POST">

<input type="email" name="email" placeholder="Enter Email" required>

<input type="text" name="otp" placeholder="Enter OTP Code" required>

<button type="submit">Verify</button>

</form>

<div class="message"><?= $message ?></div>

</div>

</body>
</html>