<?php
include 'config/db.php';
$message = "";

$stmt = $conn->prepare("SELECT * FROM barangays ORDER BY barangay_name ASC");
$stmt->execute();
$barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);
    $barangay_id = $_POST['barangay_id'];

    if (!$full_name || !$phone || !$username || !$password || !$role || !$barangay_id) {
        $message = "All fields are required.";
    }

    elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password)) {
        $message = "Password must be at least 8 characters with letters and numbers.";
    }

    else {

        $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);

        if ($stmt->fetch()) {
            $message = "Username already exists.";
        } else {

            $stmt = $conn->prepare("
                SELECT id FROM users
                WHERE barangay_id = :barangay_id
                AND role = :role
                AND status IN ('pending','approved')
            ");
            $stmt->execute([
                ':barangay_id' => $barangay_id,
                ':role' => $role
            ]);

            if ($stmt->fetch()) {
                $message = "This barangay already has one registered $role.";
            } else {

                $hashed = md5($password);

                $stmt = $conn->prepare("
                    INSERT INTO users (full_name, phone, username, password, role, barangay_id, status)
                    VALUES (:full_name,:phone,:username,:password,:role,:barangay_id,'pending')
                ");

                $stmt->execute([
                    ':full_name' => $full_name,
                    ':phone' => $phone,
                    ':username' => $username,
                    ':password' => $hashed,
                    ':role' => $role,
                    ':barangay_id' => $barangay_id
                ]);

                $message = "Registration submitted. Wait for admin approval.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Register</title>
<link rel="stylesheet" href="assets/style.css">
<style>
.form-box{
    width:400px;
    margin:50px auto;
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 0 10px #ccc;
}
input,select{
    width:100%;
    padding:10px;
    margin-bottom:12px;
}
button{
    width:100%;
    padding:10px;
    background:#28a745;
    color:white;
    border:none;
}
.msg{margin-top:10px;color:red;}
</style>
</head>
<body>

<div class="form-box">
    <h2>SK Official Registration</h2>

    <form method="POST">
        <input type="text" name="full_name" placeholder="Full Name" required>
        <input type="text" name="phone" placeholder="Phone Number" required>
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>

        <select name="role" required>
            <option value="">Select Role</option>
            <option value="chairperson">SK Chairperson</option>
            <option value="secretary">SK Secretary</option>
            <option value="treasurer">SK Treasurer</option>
        </select>

        <select name="barangay_id" required>
            <option value="">Select Barangay</option>
            <?php foreach($barangays as $b){ ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['barangay_name']) ?></option>
            <?php } ?>
        </select>

        <button type="submit">Register</button>
    </form>

    <div class="msg"><?= $message ?></div>
    <p><a href="index.php">Back to Login</a></p>
</div>

</body>
</html>