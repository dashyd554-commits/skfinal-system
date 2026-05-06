<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

$message = "";
$messageType = "";

$min_age = 15;
$max_age = 30;

/* RETAIN VALUES */
$fullname = $_POST['fullname'] ?? '';
$age = $_POST['age'] ?? '';
$username = $_POST['username'] ?? '';
$plain_password = $_POST['plain_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$contact_number = $_POST['contact_number'] ?? '';
$role = $_POST['role'] ?? '';
$barangay_id = $_POST['barangay_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullname = trim($fullname);
    $age = intval($age);
    $username = trim($username);
    $plain_password = trim($plain_password);
    $confirm_password = trim($confirm_password);
    $contact_number = trim($contact_number);

    if ($age < $min_age || $age > $max_age) {
        $message = "❌ Age must be between $min_age and $max_age.";
        $messageType = "error";
    }

    elseif (!preg_match('/^09\d{9}$/', $contact_number)) {
        $message = "❌ Contact number must be valid Philippine number.";
        $messageType = "error";
    }

    elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $plain_password)) {
        $message = "❌ Password must be at least 8 characters with letters and numbers.";
        $messageType = "error";
    }

    elseif ($plain_password !== $confirm_password) {
        $message = "❌ Password and Confirm Password do not match.";
        $messageType = "error";
    }

    else {

        $stmt = $conn->prepare("SELECT 1 FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->fetchColumn()) {
            $message = "❌ Username already exists!";
            $messageType = "error";
        }

        else {

            $stmt = $conn->prepare("SELECT 1 FROM users WHERE fullname = ?");
            $stmt->execute([$fullname]);

            if ($stmt->fetchColumn()) {
                $message = "❌ Fullname already exists!";
                $messageType = "error";
            }

            else {

                $stmt = $conn->prepare("SELECT 1 FROM users WHERE plain_password = ?");
                $stmt->execute([$plain_password]);

                if ($stmt->fetchColumn()) {
                    $message = "❌ Password already used!";
                    $messageType = "error";
                }

                else {

                    $stmt = $conn->prepare("SELECT 1 FROM users WHERE barangay_id = ? AND role = ?");
                    $stmt->execute([$barangay_id, $role]);

                    if ($stmt->fetchColumn()) {
                        $message = "❌ This barangay already has a ".ucfirst($role).".";
                        $messageType = "error";
                    }

                    else {

                        $stmt = $conn->prepare("
                            INSERT INTO users
                            (fullname, age, username, plain_password, contact_number, role, barangay_id, status, last_activity)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                        ");

                        $inserted = $stmt->execute([
                            $fullname,
                            $age,
                            $username,
                            $plain_password,
                            $contact_number,
                            $role,
                            $barangay_id
                        ]);

                        if ($inserted) {

                            $barangayStmt = $conn->prepare("SELECT barangay_name FROM barangays WHERE id = ?");
                            $barangayStmt->execute([$barangay_id]);
                            $barangay_name = $barangayStmt->fetchColumn();

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

                            $fullname = $age = $username = $plain_password = $confirm_password = $contact_number = $role = $barangay_id = "";
                        }
                        else {
                            $message = "❌ Failed to insert official.";
                            $messageType = "error";
                        }
                    }
                }
            }
        }
    }
}

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

<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{
    font-family:'Segoe UI',sans-serif;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
}
.wrapper{
    display:flex;
    width:100%;
    min-height:100vh;
}
.main{
    flex:1;
    min-width:0;
    padding:28px 24px;
}
.page-title{
    color:#e8eaf0;
    font-size:22px;
    font-weight:600;
    margin-bottom:24px;
    padding-bottom:14px;
    border-bottom:1px solid rgba(255,255,255,0.08);
}
.glass{
    max-width:700px;
    margin:auto;
    background:rgba(255,255,255,0.05);
    border:1px solid rgba(255,255,255,0.09);
    border-radius:18px;
    backdrop-filter:blur(18px);
    padding:25px;
}
.message{
    max-width:700px;
    margin:0 auto 15px auto;
    padding:12px;
    border-radius:10px;
    font-weight:600;
    text-align:center;
}
.success{background:#d1e7dd;color:#0f5132;}
.error{background:#f8d7da;color:#842029;}
.form-group{
    margin-bottom:15px;
}
input,select{
    width:100%;
    padding:13px;
    border:none;
    border-radius:10px;
    background:rgba(255,255,255,0.08);
    color:white;
    font-size:14px;
}
select option{color:black;}
button{
    width:100%;
    padding:14px;
    background:#5b8af5;
    border:none;
    border-radius:10px;
    color:white;
    font-weight:bold;
    cursor:pointer;
    margin-top:10px;
}
.error-text{
    color:#ff8a8a;
    font-size:12px;
    margin-top:4px;
    margin-left:4px;
    display:block;
    min-height:14px;
}
.footer{
    text-align:center;
    padding:14px;
    color:#5a6070;
    font-size:12px;
    margin-top:15px;
}
@media(max-width:768px){
    .main{padding:18px 14px;}
}
</style>
</head>
<body>

<div class="wrapper">
<?php include '../assets/sidebar.php'; ?>

<div class="main">
    <h1 class="page-title">👮 Add SK Officials</h1>

    <?php if($message){ ?>
        <div class="message <?= $messageType ?>"><?= $message ?></div>
    <?php } ?>

    <div class="glass">
        <form method="POST" onsubmit="return finalValidate()">

            <div class="form-group">
                <input type="text" id="fullname" name="fullname" placeholder="Full Name" value="<?= htmlspecialchars($fullname) ?>" onkeyup="validateFullname()" required>
                <small id="fullnameError" class="error-text"></small>
            </div>

            <div class="form-group">
                <input type="number" id="age" name="age" placeholder="Age" value="<?= htmlspecialchars($age) ?>" onkeyup="validateAge()" required>
                <small id="ageError" class="error-text"></small>
            </div>

            <div class="form-group">
                <input type="text" id="contact_number" name="contact_number" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($contact_number) ?>" onkeyup="validateContact()" required>
                <small id="contactError" class="error-text"></small>
            </div>

            <div class="form-group">
                <input type="text" id="username" name="username" placeholder="Username" value="<?= htmlspecialchars($username) ?>" onkeyup="validateUsername()" required>
                <small id="usernameError" class="error-text"></small>
            </div>

            <div class="form-group">
                <input type="password" id="plain_password" name="plain_password" placeholder="Password" value="<?= htmlspecialchars($plain_password) ?>" onkeyup="validatePassword()" required>
                <small id="passwordError" class="error-text"></small>
            </div>

            <div class="form-group">
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" value="<?= htmlspecialchars($confirm_password) ?>" onkeyup="validateConfirmPassword()" required>
                <small id="confirmError" class="error-text"></small>
            </div>

            <div class="form-group">
                <select id="role" name="role" onchange="validateRole()" required>
                    <option value="">Select Role</option>
                    <option value="chairman" <?= $role=='chairman'?'selected':'' ?>>Chairman</option>
                    <option value="secretary" <?= $role=='secretary'?'selected':'' ?>>Secretary</option>
                    <option value="treasurer" <?= $role=='treasurer'?'selected':'' ?>>Treasurer</option>
                </select>
                <small id="roleError" class="error-text"></small>
            </div>

            <div class="form-group">
                <select id="barangay_id" name="barangay_id" onchange="validateBarangay()" required>
                    <option value="">Select Barangay</option>
                    <?php foreach($barangays as $b){ ?>
                        <option value="<?= $b['id'] ?>" <?= $barangay_id==$b['id']?'selected':'' ?>>
                            <?= htmlspecialchars($b['barangay_name']) ?>
                        </option>
                    <?php } ?>
                </select>
                <small id="barangayError" class="error-text"></small>
            </div>

            <button type="submit">➕ Add Official</button>
        </form>
    </div>

    <div class="footer">
        © 2026 SK Decision Support System | Responsive Community Planning Platform
    </div>
</div>
</div>

<script>
function validateFullname(){
    let val=document.getElementById("fullname").value.trim();
    document.getElementById("fullnameError").innerHTML=(val.length<5)?"Full name must be at least 5 characters.":"";
    return val.length>=5;
}
function validateAge(){
    let age=parseInt(document.getElementById("age").value);
    document.getElementById("ageError").innerHTML=(age<15||age>30||isNaN(age))?"Age must be between 15 and 30.":"";
    return !(age<15||age>30||isNaN(age));
}
function validateContact(){
    let val=document.getElementById("contact_number").value;
    document.getElementById("contactError").innerHTML=!/^09\d{9}$/.test(val)?"Enter valid Philippine number.":"";
    return /^09\d{9}$/.test(val);
}
function validateUsername(){
    let val=document.getElementById("username").value.trim();
    document.getElementById("usernameError").innerHTML=(val.length<4)?"Username must be at least 4 characters.":"";
    return val.length>=4;
}
function validatePassword(){
    let val=document.getElementById("plain_password").value;
    document.getElementById("passwordError").innerHTML=!/^(?=.*[A-Za-z])(?=.*\d).{8,}$/.test(val)?"Password must contain letters and numbers.":"";
    return /^(?=.*[A-Za-z])(?=.*\d).{8,}$/.test(val);
}
function validateConfirmPassword(){
    let p=document.getElementById("plain_password").value;
    let c=document.getElementById("confirm_password").value;
    document.getElementById("confirmError").innerHTML=(p!==c)?"Password does not match.":"";
    return p===c;
}
function validateRole(){
    let val=document.getElementById("role").value;
    document.getElementById("roleError").innerHTML=(val=="")?"Please select role.":"";
    return val!="";
}
function validateBarangay(){
    let val=document.getElementById("barangay_id").value;
    document.getElementById("barangayError").innerHTML=(val=="")?"Please select barangay.":"";
    return val!="";
}
function finalValidate(){
    let valid =
        validateFullname() &&
        validateAge() &&
        validateContact() &&
        validateUsername() &&
        validatePassword() &&
        validateConfirmPassword() &&
        validateRole() &&
        validateBarangay();

    if(valid){
        return confirm("Are you sure you want to add this SK Official?");
    }
    return false;
}
</script>

</body>
</html>