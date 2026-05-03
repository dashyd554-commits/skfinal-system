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

/* ================= LOAD USER DATA ================= */
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

$message = "";

/* ================= UPDATE USER ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullname = trim($_POST['fullname'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $is_verified = isset($_POST['is_verified']) ? 1 : 0;
    $approved_by_admin = isset($_POST['approved_by_admin']) ? 1 : 0;

    if (!$fullname || !$email || !$username || !$role) {
        $message = "⚠️ Please fill all required fields!";
    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "❌ Invalid email format!";
    }

    else {

        $stmt = $conn->prepare("
            UPDATE users SET
                fullname = ?,
                age = ?,
                email = ?,
                username = ?,
                role = ?,
                status = ?,
                is_verified = ?,
                approved_by_admin = ?
            WHERE id = ?
        ");

        $updated = $stmt->execute([
            $fullname,
            $age,
            $email,
            $username,
            $role,
            $status,
            $is_verified,
            $approved_by_admin,
            $id
        ]);

        if ($updated) {

            require '../config/mail.php';

            $subject = "Account Updated by Admin";
            $body = "
                <h3>SK Account Update Notification</h3>
                <p>Your account has been updated by the administrator.</p>
                <ul>
                    <li><b>Full Name:</b> $fullname</li>
                    <li><b>Username:</b> $username</li>
                    <li><b>Email:</b> $email</li>
                    <li><b>Role:</b> $role</li>
                    <li><b>Status:</b> $status</li>
                </ul>
            ";

            sendEmail($email, $subject, $body);

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
    overflow-x:hidden;
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
    box-shadow:0 4px 15px rgba(0,0,0,0.25);
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
    outline:none;
}

.checkbox-group{
    background:rgba(255,255,255,0.8);
    padding:12px;
    border-radius:8px;
    margin-bottom:12px;
}

.checkbox-group label{
    display:block;
    margin:8px 0;
    font-weight:bold;
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
    text-decoration:none;
    text-align:center;
    color:white;
    font-weight:bold;
    cursor:pointer;
}

button{
    background:#007bff;
}

.cancel{
    background:#dc3545;
}

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

@media(max-width:768px){
    .main{
        margin-left:0;
        width:100%;
        padding:12px;
    }

    .container{
        padding:20px;
    }

    .btn-group{
        flex-direction:column;
    }
}
</style>
</head>

<body>

<div class="main">

<h2>✏️ Edit User Account</h2>

    <div class="container">

        <div class="message"><?= $message ?></div>

        <form method="POST">

            <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" placeholder="Full Name" required>

            <input type="number" name="age" value="<?= htmlspecialchars($user['age'] ?? '') ?>" placeholder="Age">

            <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="Email" required>

            <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" placeholder="Username" required>

            <select name="role" required>
                <option value="chairman" <?= $user['role']=='chairman'?'selected':'' ?>>Chairman</option>
                <option value="secretary" <?= $user['role']=='secretary'?'selected':'' ?>>Secretary</option>
                <option value="treasurer" <?= $user['role']=='treasurer'?'selected':'' ?>>Treasurer</option>
            </select>

            <select name="status">
                <option value="pending" <?= $user['status']=='pending'?'selected':'' ?>>Pending</option>
                <option value="approved" <?= $user['status']=='approved'?'selected':'' ?>>Approved</option>
                <option value="rejected" <?= $user['status']=='rejected'?'selected':'' ?>>Rejected</option>
            </select>

            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="is_verified" <?= $user['is_verified'] ? 'checked' : '' ?>>
                    Email Verified
                </label>

                <label>
                    <input type="checkbox" name="approved_by_admin" <?= $user['approved_by_admin'] ? 'checked' : '' ?>>
                    Admin Approved
                </label>
            </div>

            <div class="btn-group">
                <button type="submit">✅ Update Account</button>
                <a class="cancel" href="admin_officials_information.php">❌ Cancel Edit</a>
            </div>

        </form>

    </div>

    <div class="footer">
            © 2026 SK Decision Support System | Responsive Community Planning Platform
        </div>

</div>

</body>
</html>