<?php
include '../config/db.php';
require '../vendor/autoload.php';
require '../config/mail.php';

$message = "";

/* LOAD BARANGAYS */
$brgyStmt = $conn->prepare("SELECT * FROM barangays ORDER BY barangay_name ASC");
$brgyStmt->execute();
$barangays = $brgyStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullname = trim($_POST['fullname'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $passwordRaw = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $barangay_id = trim($_POST['barangay_id'] ?? '');

    /* ================= VALIDATION ================= */

    if (!$fullname || !$age || !$email || !$username || !$passwordRaw || !$confirmPassword || !$role || !$barangay_id) {
        $message = "⚠️ All fields are required!";
    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "❌ Invalid email format!";
    }

    elseif ($age < 15 || $age > 30) {
        $message = "❌ SK age requirement is 15–30 years old!";
    }

    elseif (strlen($passwordRaw) < 8) {
        $message = "❌ Password must be at least 8 characters!";
    }

    elseif ($passwordRaw !== $confirmPassword) {
        $message = "❌ Passwords do not match!";
    }

    else {

        /* CHECK EMAIL EXISTS */
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $message = "❌ Email already registered!";
        }

        else {

            /* CHECK ROLE PER BARANGAY */
            $stmt = $conn->prepare("
                SELECT id FROM users
                WHERE barangay_id = ? AND role = ?
            ");
            $stmt->execute([$barangay_id, $role]);

            if ($stmt->fetch()) {
                $message = "❌ This barangay already has this role!";
            }

            else {

                /* OTP GENERATION */
                $otp = rand(100000, 999999);
                $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                $hashedPassword = password_hash($passwordRaw, PASSWORD_DEFAULT);

                /* INSERT USER */
                $stmt = $conn->prepare("
                    INSERT INTO users 
                    (fullname, username, password, role, barangay_id, status, email, verification_code, verification_expiry, is_verified, age)
                    VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?, 0, ?)
                ");

                $insert = $stmt->execute([
                    $fullname,
                    $username,
                    $hashedPassword,
                    $role,
                    $barangay_id,
                    $email,
                    $otp,
                    $expiry,
                    $age
                ]);

                if ($insert) {

                    /* SEND EMAIL OTP (ONLY HERE) */
                    $subject = "SK Verification Code";
                    $body = "
                        <h3>Welcome to SK System</h3>
                        <p>Your verification code is:</p>
                        <h2 style='color:#4f6ef7;'>$otp</h2>
                        <p>This code will expire in 10 minutes.</p>
                    ";

                    sendEmail($email, $subject, $body);

                    echo "<script>
                        alert('✅ Registered successfully! Check your email for OTP.');
                        window.location='verify.php';
                    </script>";
                    exit();

                } else {
                    $message = "❌ Registration failed!";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>SK Registration</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/style.css">

<style>
.container{display:flex;justify-content:center;align-items:center;min-height:100vh;}
.register-box{width:420px;padding:25px;background:white;border-radius:12px;box-shadow:0 0 15px rgba(0,0,0,0.1);}
input,select{width:100%;padding:12px;margin-bottom:10px;border:1px solid #ccc;border-radius:8px;}
button{width:100%;padding:12px;border:none;border-radius:8px;background:#4f6ef7;color:white;}
.message{text-align:center;margin-top:10px;color:red;}
</style>
</head>

<body>

<div class="container">
<div class="register-box">

<h2>📝 SK Officer Registration</h2>

<form method="POST">

<input type="text" name="fullname" placeholder="Full Name" required>
<input type="number" name="age" placeholder="Age (15–30)" required>
<input type="email" name="email" placeholder="Email (for verification)" required>
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>
<input type="password" name="confirm_password" placeholder="Confirm Password" required>

<select name="barangay_id" required>
    <option value="">Select Barangay</option>
    <?php foreach($barangays as $b){ ?>
        <option value="<?= $b['id'] ?>">
            <?= htmlspecialchars($b['barangay_name']) ?>
        </option>
    <?php } ?>
</select>

<select name="role" required>
    <option value="">Select Role</option>
    <option value="chairman">Chairman</option>
    <option value="secretary">Secretary</option>
    <option value="treasurer">Treasurer</option>
</select>

<button type="submit">Register</button>

</form>

<div class="message"><?= $message ?></div>

<a href="../index.php">← Back to Login</a>

</div>
</div>

</body>
</html>