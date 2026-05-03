<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= UPDATE LAST ACTIVITY (REALTIME STATUS) ================= */
if (isset($_SESSION['user']['id'])) {
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
}

/* ================= GET OFFICIALS ================= */
$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.fullname,
        u.age,
        u.username,
        u.role,
        u.status,
        u.plain_password,
        u.last_activity,
        b.barangay_name,

        CASE 
            WHEN u.last_activity IS NOT NULL 
             AND u.last_activity >= NOW() - INTERVAL '5 minutes'
            THEN 'ACTIVE'
            ELSE 'INACTIVE'
        END AS online_status

    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.role IN ('chairman','secretary','treasurer')
    ORDER BY b.barangay_name ASC
");

$stmt->execute();
$officials = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= GROUP BY BARANGAY ================= */
$grouped = [];

foreach ($officials as $o) {
    $barangay = $o['barangay_name'] ?? 'Unassigned';
    $grouped[$barangay][] = $o;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Officials Information</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Arial, sans-serif;
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

.header{
    text-align:center;
    color:white;
    margin-bottom:25px;
}

.card{
    width:100%;
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    padding:20px;
    border-radius:18px;
    margin-bottom:25px;
}

.official-box{
    background:rgba(255,255,255,0.95);
    border-radius:12px;
    padding:15px;
    margin-bottom:15px;
}

.info{
    font-size:14px;
    margin:6px 0;
}

.badge{
    display:inline-block;
    padding:4px 8px;
    border-radius:5px;
    color:white;
    font-size:11px;
    font-weight:bold;
}

.verifyyes{background:#28a745;}
.adminno{background:#dc3545;}

.actions{
    margin-top:12px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.actions a{
    text-decoration:none;
    padding:8px 14px;
    border-radius:7px;
    color:white;
    font-size:12px;
}

.edit{background:#007bff;}
.delete{background:#dc3545;}

.footer{
    text-align:center;
    margin-top:20px;
    padding:14px;
    color:white;
    background:rgba(0,0,0,0.25);
    border-radius:10px;
}

/* MOBILE FIX */
@media(max-width:768px){
    .footer{
        font-size:11px;
        padding:10px;
    }
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>👥 SK Officials Registry</h2>
    <p>Real-time Active / Inactive Monitoring</p>
</div>

<?php if(!empty($grouped)){ ?>

    <?php foreach($grouped as $barangay => $list){ ?>

        <div class="card">

            <h3 style="color:white;">🏘️ <?= htmlspecialchars($barangay) ?></h3>

            <?php foreach($list as $o){ ?>

                <div class="official-box">

                    <h4><?= htmlspecialchars($o['role']) ?></h4>

                    <div class="info"><b>Full Name:</b> <?= htmlspecialchars($o['fullname'] ?? 'N/A') ?></div>
                    <div class="info"><b>Age:</b> <?= htmlspecialchars($o['age'] ?? 'N/A') ?></div>
                    <div class="info"><b>Username:</b> <?= htmlspecialchars($o['username']) ?></div>

                    <!-- ✅ PASSWORD NOW VISIBLE TO ADMIN -->
                    <div class="info">
                        <b>Password:</b> <?= htmlspecialchars($o['plain_password'] ?? 'N/A') ?>
                    </div>

                    <div class="info">
                        <b>Status:</b>
                        <span class="badge <?= $o['online_status'] === 'ACTIVE' ? 'verifyyes' : 'adminno' ?>">
                            <?= $o['online_status'] ?>
                        </span>
                    </div>

                    <div class="actions">
                        <a class="edit" href="admin_edit.php?id=<?= $o['id'] ?>">✏ Edit</a>
                        <a class="delete" href="admin_delete.php?id=<?= $o['id'] ?>"
                           onclick="return confirm('Delete this account?')">
                           🗑 Delete
                        </a>
                    </div>

                </div>

            <?php } ?>

        </div>

    <?php } ?>

<?php } else { ?>

    <div class="card">
        <p style="color:white;">No officials found.</p>
    </div>

<?php } ?>

<div class="footer">
    © 2026 SK Decision Support System | Responsive Community Planning Platform
</div>

</div>

</body>
</html>