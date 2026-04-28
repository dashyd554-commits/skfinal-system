<?php
include '../config/db.php';

$message = "";

/* LOAD BARANGAYS */
$brgyStmt = $conn->prepare("SELECT * FROM barangays ORDER BY barangay_name ASC");
$brgyStmt->execute();
$barangays = $brgyStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $passwordRaw = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $barangay_id = trim($_POST['barangay_id'] ?? '');

    /* ================= VALIDATION ================= */
    if (!$username || !$passwordRaw || !$confirmPassword || !$role || !$barangay_id) {
        $message = "⚠️ All fields are required!";
    }

    elseif (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $message = "❌ Username must be a valid email!";
    }

    elseif (strlen($passwordRaw) < 8) {
        $message = "❌ Password must be at least 8 characters!";
    }

    elseif ($passwordRaw !== $confirmPassword) {
        $message = "❌ Passwords do not match!";
    }

    else {

        /* CHECK EMAIL EXISTS */
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            $message = "❌ Email already registered!";
        }

        else {

            /* CHECK ONE ROLE PER BARANGAY ONLY */
            $stmt = $conn->prepare("
                SELECT id FROM users
                WHERE barangay_id = ? AND role = ?
            ");
            $stmt->execute([$barangay_id, $role]);

            if ($stmt->fetch()) {
                $message = "❌ This barangay already has one registered " . ucfirst($role) . "!";
            }

            else {

                /* HASH PASSWORD */
                $hashedPassword = password_hash($passwordRaw, PASSWORD_DEFAULT);

                /* INSERT USER */
                $stmt = $conn->prepare("
                    INSERT INTO users (username, password, role, status, barangay_id)
                    VALUES (?, ?, ?, 'pending', ?)
                ");

                if ($stmt->execute([$username, $hashedPassword, $role, $barangay_id])) {

                    echo "<script>
                            alert('✅ Registered Successfully! Wait for admin approval.');
                            window.location='../index.php';
                          </script>";
                    exit();

                } else {
                    $message = '❌ Registration failed!';
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
.container{
    display:flex;
    justify-content:center;
    align-items:center;
    min-height:100vh;
}
.register-box{
    width:400px;
    padding:25px;
    background:white;
    border-radius:12px;
    box-shadow:0 0 15px rgba(0,0,0,0.1);
}
input,select{
    width:100%;
    padding:12px;
    margin-bottom:12px;
    border:1px solid #ccc;
    border-radius:8px;
}
button{
    width:100%;
    padding:12px;
    border:none;
    border-radius:8px;
    background:#4f6ef7;
    color:white;
    cursor:pointer;
}
button:hover{
    background:#3656d4;
}
.message{
    text-align:center;
    margin-top:10px;
    font-size:14px;
    font-weight:bold;
    color:#dc3545;
}
a{
    display:block;
    text-align:center;
    margin-top:12px;
    text-decoration:none;
}
</style>
</head>

<body>

<div class="container">

    <div class="register-box">

        <h2 style="text-align:center;">📝 SK Officer Registration</h2>

        <form method="POST">

            <input type="email" name="username" placeholder="Email Address" required>

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