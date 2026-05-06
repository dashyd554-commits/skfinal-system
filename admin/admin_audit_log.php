<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= FILTER ================= */
$role_filter = $_GET['role'] ?? '';

$sql = "
    SELECT 
        username,
        barangay_name,
        action_type,
        table_name,
        description,
        action_time
    FROM audit_logs
    WHERE 1=1
";

$params = [];

/* Role filter (based on username keywords) */
if (!empty($role_filter)) {
    if ($role_filter == 'chair') {
        $sql .= " AND LOWER(username) LIKE '%chair%'";
    } elseif ($role_filter == 'secretary') {
        $sql .= " AND LOWER(username) LIKE '%secretary%'";
    } elseif ($role_filter == 'treasurer') {
        $sql .= " AND LOWER(username) LIKE '%treasurer%'";
    } elseif ($role_filter == 'admin') {
        $sql .= " AND LOWER(username) LIKE '%admin%'";
    }
}

$sql .= " ORDER BY action_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Audit Logs</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/style.css">

<style>
:root{
    --sidebar-w:240px;
}

*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    font-family:'Segoe UI',sans-serif;
    background:#0d1b2a;
    color:white;
}

/* ================= LAYOUT FIX ================= */
.wrapper{
    display:flex;
    width:100%;
    min-height:100vh;
}

/* sidebar fixed */
.sidebar{
    position:fixed !important;
    top:0;
    left:0;
    width:var(--sidebar-w);
    height:100vh;
    z-index:1000;
}

/* main content */
.main{
    margin-left:var(--sidebar-w);
    flex:1;
    padding:25px;
    display:flex;
    flex-direction:column;
    gap:15px;
    min-width:0;
}

/* ================= HEADER ================= */
.header h2{
    font-size:22px;
}

/* ================= GLASS ================= */
.glass{
    background:rgba(255,255,255,0.06);
    border:1px solid rgba(255,255,255,0.1);
    border-radius:16px;
    padding:20px;
    backdrop-filter:blur(15px);
    display:flex;
    flex-direction:column;

    max-height:calc(100vh - 120px);
}

/* ================= FILTER ================= */
.filter-box{
    margin-bottom:10px;
}

select, button{
    padding:8px;
    border-radius:8px;
    border:none;
    margin-right:5px;
}

/* ================= TABLE ================= */
.table-container{
    overflow:auto;
    flex:1;
    border-radius:10px;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:900px;
}

th{
    position:sticky;
    top:0;
    background:#1e3c72;
    padding:12px;
    text-align:center;
    z-index:2;
}

td{
    padding:10px;
    text-align:center;
    background:white;
    color:#0d1b2a;
    border-bottom:1px solid rgba(0,0,0,0.1);
    font-weight:500;
}

/* ================= BADGES ================= */
.badge{
    padding:5px 10px;
    border-radius:6px;
    font-size:12px;
    color:white;
    font-weight:600;
}

.insert{background:green;}
.update{background:orange;}
.delete{background:red;}

.role{
    font-size:11px;
    color:#666;
}

/* ================= RESPONSIVE ================= */
@media (max-width:768px){
    :root{ --sidebar-w:60px; }

    .main{
        margin-left:60px;
        padding:15px;
    }
}
</style>
</head>

<body>

<div class="wrapper">

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>🕘 System Audit Logs (All Officials Activity)</h2>
    </div>

    <div class="glass">

        <!-- FILTER -->
        <form method="GET" class="filter-box">
            <select name="role">
                <option value="">All Roles</option>
                <option value="chair" <?= $role_filter=='chair'?'selected':'' ?>>Chairperson</option>
                <option value="secretary" <?= $role_filter=='secretary'?'selected':'' ?>>Secretary</option>
                <option value="treasurer" <?= $role_filter=='treasurer'?'selected':'' ?>>Treasurer</option>
                <option value="admin" <?= $role_filter=='admin'?'selected':'' ?>>Admin</option>
            </select>

            <button type="submit">Filter</button>
            <button type="button" onclick="window.print()">Print</button>
        </form>

        <!-- TABLE -->
        <div class="table-container">
            <table>
                <tr>
                    <th>User</th>
                    <th>Barangay</th>
                    <th>Action</th>
                    <th>Table</th>
                    <th>Description</th>
                    <th>Time</th>
                </tr>

                <?php if($logs): ?>
                    <?php foreach($logs as $log): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($log['username'] ?? 'system') ?><br>

                            <span class="role">
                                <?php
                                $u = strtolower($log['username'] ?? '');

                                if (str_contains($u, 'chair')) echo "🟢 Chairperson";
                                elseif (str_contains($u, 'secretary')) echo "🔵 Secretary";
                                elseif (str_contains($u, 'treasurer')) echo "🟣 Treasurer";
                                elseif (str_contains($u, 'admin')) echo "🔴 Admin";
                                else echo "⚪ System";
                                ?>
                            </span>
                        </td>

                        <td><?= htmlspecialchars($log['barangay_name'] ?? 'N/A') ?></td>

                        <td>
                            <span class="badge <?= strtolower($log['action_type'] ?? '') ?>">
                                <?= strtoupper($log['action_type'] ?? '') ?>
                            </span>
                        </td>

                        <td><?= htmlspecialchars($log['table_name'] ?? '') ?></td>

                        <td style="text-align:left; max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                            <?= htmlspecialchars($log['description'] ?? '') ?>
                        </td>

                        <td><?= htmlspecialchars($log['action_time'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No logs found.</td>
                    </tr>
                <?php endif; ?>

            </table>
        </div>

    </div>

</div>

</div>

</body>
</html>