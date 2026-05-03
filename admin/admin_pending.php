<?php
session_start();
include '../config/db.php';

/* ================= SECURITY CHECK ================= */
if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= GET PENDING USERS ================= */
$stmt = $conn->prepare("
    SELECT u.*, b.barangay_name
    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.status = 'pending'
    ORDER BY u.id DESC
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Pending Users</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial;
}

html,body{
    width:100%;
    overflow-x:hidden;
}

body{
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
}

/* MAIN FIXED */
.main{
    margin-left:190px;
    width:calc(100% - 190px);
    padding:20px;
    min-height:100vh;
}

/* MOBILE FIX */
@media(max-width:768px){
    .main{
        margin-left:0;
        width:100%;
        padding:12px;
    }
}

/* HEADER */
.header{
    text-align:center;
    color:white;
    margin-bottom:25px;
}

.header h2{
    font-size:34px;
    text-shadow:0 2px 8px rgba(0,0,0,0.4);
}

.header p{
    margin-top:8px;
    font-size:15px;
}

/* GLASS CONTAINER */
.table-box{
    width:100%;
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    padding:20px;
    border-radius:18px;
    box-shadow:0 4px 20px rgba(0,0,0,0.2);
    overflow-x:auto;
}

/* TABLE */
table{
    width:100%;
    min-width:900px;
    border-collapse:collapse;
    background:rgba(255,255,255,0.96);
    border-radius:12px;
    overflow:hidden;
}

th{
    background:#198754;
    color:white;
    padding:14px;
    font-size:14px;
}

td{
    padding:14px;
    text-align:center;
    border-bottom:1px solid #ddd;
    font-size:14px;
}

tr:hover{
    background:#f2f2f2;
}

/* BADGE */
.badge{
    background:orange;
    color:white;
    padding:5px 10px;
    border-radius:20px;
    font-size:11px;
    font-weight:bold;
}

/* BUTTONS */
.action-btn{
    display:inline-block;
    padding:7px 14px;
    border-radius:8px;
    text-decoration:none;
    color:white;
    font-size:12px;
    font-weight:bold;
    margin:2px;
}

.approve{
    background:#198754;
}

.reject{
    background:#dc3545;
}

.action-btn:hover{
    opacity:0.85;
}

/* EMPTY */
.empty{
    text-align:center;
    color:white;
    padding:40px;
    font-size:18px;
}

/* FOOTER */
.footer{
    margin-top:25px;
    text-align:center;
    color:white;
    font-size:13px;
    background:rgba(0,0,0,0.25);
    padding:12px;
    border-radius:10px;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>👮 Pending User Approval Registry</h2>
        <p>Administrator Approval of Newly Registered SK Officials</p>
    </div>

    <?php if($users){ ?>

    <div class="table-box">
        <table>
            <tr>
                <th>Full Name</th>
                <th>Email</th>
                <th>Username</th>
                <th>Role</th>
                <th>Barangay</th>
                <th>Status</th>
                <th>Action</th>
            </tr>

            <?php foreach($users as $u){ ?>
            <tr>
                <td><?= htmlspecialchars($u['fullname'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($u['email'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= ucfirst(htmlspecialchars($u['role'])) ?></td>
                <td><?= htmlspecialchars($u['barangay_name'] ?? 'N/A') ?></td>
                <td><span class="badge"><?= strtoupper($u['status']) ?></span></td>
                <td>
                    <a class="action-btn approve"
                       href="admin_approve_user.php?id=<?= $u['id'] ?>"
                       onclick="return confirm('Approve this user account?')">
                       ✔ Approve
                    </a>

                    <a class="action-btn reject"
                       href="admin_reject_user.php?id=<?= $u['id'] ?>"
                       onclick="return confirm('Reject this user account?')">
                       ✖ Reject
                    </a>
                </td>
            </tr>
            <?php } ?>

        </table>
    </div>

    <?php } else { ?>

        <div class="table-box empty">
            ✅ No pending user accounts found.
        </div>

    <?php } ?>

    <div class="footer">
        © 2026 SK Decision Support System | Pending User Approval Management
    </div>

</div>

</body>
</html>