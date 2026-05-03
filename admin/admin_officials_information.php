<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= GET OFFICIALS ================= */
$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.fullname,
        u.age,
        u.email,
        u.username,
        u.role,
        u.status,
        u.is_verified,
        u.approved_by_admin,
        b.barangay_name
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
    $grouped[$o['barangay_name']][] = $o;
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

html,body{
    width:100%;
    overflow-x:hidden;
}

body{
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    min-height:100vh;
}

/* ===== MAIN CONTENT FIX ===== */
.main{
    margin-left:90px; /* match actual sidebar visible width */
    width:calc(100% - 90px);
    padding:20px;
    min-height:100vh;
    overflow-x:hidden;
}

/* ===== HEADER ===== */
.header{
    text-align:center;
    color:white;
    margin-bottom:25px;
}

.header h2{
    font-size:32px;
    margin-bottom:8px;
}

.header p{
    font-size:14px;
}

/* ===== BARANGAY CARD ===== */
.card{
    width:100%;
    max-width:100%;
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    padding:20px;
    border-radius:18px;
    margin-bottom:25px;
    box-shadow:0 4px 15px rgba(0,0,0,0.25);
    overflow:hidden;
}

.card h3{
    color:white;
    margin-bottom:18px;
    border-bottom:1px solid rgba(255,255,255,0.3);
    padding-bottom:10px;
}

/* ===== OFFICIAL BOX ===== */
.official-box{
    width:100%;
    max-width:100%;
    background:rgba(255,255,255,0.95);
    border-radius:12px;
    padding:15px;
    margin-bottom:15px;
    box-shadow:0 2px 8px rgba(0,0,0,0.1);
    overflow:hidden;
    word-break:break-word;
}

.official-box h4{
    color:#28a745;
    margin-bottom:10px;
    font-size:18px;
    text-transform:capitalize;
}

.info{
    font-size:14px;
    margin:6px 0;
    line-height:1.5;
    word-break:break-word;
}

.badge{
    display:inline-block;
    padding:4px 8px;
    border-radius:5px;
    color:white;
    font-size:11px;
    font-weight:bold;
}

.approved{background:green;}
.pending{background:orange;}
.rejected{background:red;}
.verifyyes{background:#007bff;}
.verifyno{background:gray;}
.adminyes{background:#20c997;}
.adminno{background:#dc3545;}

.actions{
    margin-top:12px;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
}

.actions a{
    text-decoration:none;
    padding:8px 14px;
    border-radius:7px;
    color:white;
    font-size:12px;
    font-weight:bold;
}

.edit{background:#007bff;}
.delete{background:#dc3545;}

/* ===== FOOTER ===== */
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

/* ===== MOBILE ===== */
@media(max-width:768px){

    .main{
        margin-left:0;
        width:100%;
        padding:12px;
    }

    .header h2{
        font-size:22px;
    }

    .actions a{
        width:100%;
        text-align:center;
    }
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>👥 SK Officials Information Registry</h2>
        <p>Per Barangay Registered Officials</p>
    </div>

    <?php if(!empty($grouped)){ ?>

        <?php foreach($grouped as $barangay => $list){ ?>

            <div class="card">

                <h3>🏘️ <?= htmlspecialchars($barangay) ?></h3>

                <?php foreach($list as $o){ ?>

                    <div class="official-box">

                        <h4><?= htmlspecialchars($o['role']) ?></h4>

                        <div class="info"><b>Full Name:</b> <?= htmlspecialchars($o['fullname'] ?? 'N/A') ?></div>
                        <div class="info"><b>Age:</b> <?= htmlspecialchars($o['age'] ?? 'N/A') ?></div>
                        <div class="info"><b>Email:</b> <?= htmlspecialchars($o['email'] ?? 'N/A') ?></div>
                        <div class="info"><b>Username:</b> <?= htmlspecialchars($o['username']) ?></div>

                        <div class="info">
                            <b>Status:</b>
                            <span class="badge <?= $o['status'] ?>">
                                <?= strtoupper($o['status']) ?>
                            </span>
                        </div>

                        <div class="info">
                            <b>Email Verification:</b>
                            <span class="badge <?= $o['is_verified'] ? 'verifyyes' : 'verifyno' ?>">
                                <?= $o['is_verified'] ? 'VERIFIED' : 'NOT VERIFIED' ?>
                            </span>
                        </div>

                        <div class="info">
                            <b>Admin Approval:</b>
                            <span class="badge <?= $o['approved_by_admin'] ? 'adminyes' : 'adminno' ?>">
                                <?= $o['approved_by_admin'] ? 'APPROVED' : 'NOT APPROVED' ?>
                            </span>
                        </div>

                        <div class="actions">
                            <a class="edit" href="admin_edit_account.php?id=<?= $o['id'] ?>">✏ Edit</a>
                            <a class="delete"
                               href="admin_delete_official.php?id=<?= $o['id'] ?>"
                               onclick="return confirm('Are you sure you want to delete this account?')">
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