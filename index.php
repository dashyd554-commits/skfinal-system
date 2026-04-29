<?php
session_start();
include 'config/db.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    /* ADMIN LOGIN (STATIC) */
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin'] = true;
        header("Location: admin/admin_dashboard.php");
        exit();
    }

    /* USER LOGIN (MYSQL PDO) */
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $message = "User not found!";
    } else {

        if ($user['status'] !== 'approved') {
            $message = "Waiting for admin approval.";
        }

        elseif (!password_verify($password, $user['password'])) {
            $message = "Invalid password!";
        }

        else {

            $_SESSION['user'] = $user;

            if ($user['role'] === 'chairman') {
                header("Location: chairperson/chairperson_dashboard.php");
            } elseif ($user['role'] === 'secretary') {
                header("Location: secretary/secretary_dashboard.php");
            } elseif ($user['role'] === 'treasurer') {
                header("Location: treasurer/treasurer_dashboard.php");
            } else {
                header("Location: index.php");
            }

            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>SK Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial;
}

body{
    min-height:100vh;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    align-items:center;
    background:url('assets/bg.jpg') no-repeat center center/cover;
    padding:20px;
}

.header{
    margin-top:20px;
    text-align:center;
    color:white;
}

.header h1{
    font-size:32px;
    text-shadow:0 0 10px rgba(0,0,0,0.5);
}

.header p{
    font-size:15px;
}

.logos{
    margin-top:15px;
    display:flex;
    justify-content:center;
    gap:15px;
    flex-wrap:wrap;
}

.logos img{
    width:90px;
    height:auto;
}

.login-box{
    width:100%;
    max-width:350px;
    padding:30px;
    border-radius:15px;
    background:rgba(255,255,255,0.2);
    backdrop-filter:blur(10px);
    box-shadow:0 0 20px rgba(0,0,0,0.2);
    text-align:center;
    margin:20px 0;
}

input{
    width:100%;
    padding:12px;
    margin:10px 0;
    border:none;
    border-radius:20px;
    outline:none;
}

button{
    width:100%;
    padding:12px;
    border:none;
    border-radius:20px;
    background:#2d89ef;
    color:white;
    cursor:pointer;
    font-weight:bold;
}

button:hover{
    background:#1b5fbf;
}

.error{
    color:black;
    font-size:13px;
    margin-top:10px;
}

.link{
    margin-top:15px;
}

.link a{
    color:blue;
}

.footer{
    width:100%;
    text-align:center;
    color:white;
    font-size:13px;
    padding:12px;
    margin-top:20px;
    background:rgba(0,0,0,0.25);
    border-radius:10px;
}

@media(max-width:768px){
    .header h1{
        font-size:24px;
    }

    .logos img{
        width:65px;
    }

    .login-box{
        padding:20px;
    }

    .footer{
        font-size:11px;
    }
}

h2{
    color:#2d89ef;
}
</style>
</head>

<body>

<div>
    <div class="header">
        <h1>SK Decision Support System</h1>
        <p>(SK-DSS)</p>
    </div>

    <div class="logos">
        <img src="assets/logo1.png">
        <img src="assets/logo2.png">
    </div>
</div>

<div class="login-box">
    <h2>Welcome SK Please Login</h2>

    <form method="POST">
        <input type="text" name="username" placeholder="Username..." required>
        <input type="password" name="password" placeholder="Password..." required>
        <button type="submit">Log In</button>
    </form>

    <div class="error"><?php echo $message; ?></div>

    <div class="link">
        No Account? <a href="auth/register.php">Register Here</a>
    </div>
</div>

<div class="footer">
    © 2026 SK Decision Support System | Responsive Community Planning Platform
</div>

</body>
</html>