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
        u.username,
        u.role,
        u.status,
        b.barangay_name
    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.role IN ('chairman','secretary','treasurer')
    ORDER BY b.barangay_name, u.role
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

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
    html, body {
    margin: 0;
    padding: 0;
    overflow-x: hidden; /* ❌ disables right-left scroll */
    width: 100%;
}
.container {
    padding: 20px;
}

.card {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(20px);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

th {
    background: #28a745;
    color: white;
    padding: 10px;
    text-align: left;
}

td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}

.badge {
    padding: 5px 10px;
    border-radius: 6px;
    color: white;
    font-size: 12px;
}

.approved { background: green; }
.pending { background: orange; }
.rejected { background: red; }

.role {
    font-weight: bold;
    text-transform: capitalize;
}
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>👥 Officials Information</h2>
        <p>All registered SK officials per barangay</p>
    </div>

    <div class="container">

        <?php if (!empty($grouped)) { ?>

            <?php foreach ($grouped as $barangay => $list) { ?>

                <div class="card">

                    <h3>🏘️ <?= htmlspecialchars($barangay ?? 'Unknown Barangay') ?></h3>

                    <table>
                        <tr>
                        
                            <th>Username</th>
                    
                            <th>Role</th>
                            <th>Status</th>
                        </tr>

                        <?php foreach ($list as $o) { ?>
                        <tr>
                           
                            <td><?= htmlspecialchars($o['username']) ?></td>
                            
                            <td class="role"><?= $o['role'] ?></td>
                            <td>
                                <span class="badge <?= $o['status'] ?>">
                                    <?= strtoupper($o['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php } ?>

                    </table>

                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="card">
                <p>No officials found.</p>
            </div>

        <?php } ?>

    </div>

</div>

</body>
</html>