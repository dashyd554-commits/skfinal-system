<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* FILTERS */
$search = $_GET['search'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';

$sql = "
    SELECT 
        u.id,
        u.fullname,
        u.age,
        u.role,
        u.status,
        u.contact_number,
        b.barangay_name
    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.role IN ('chairman','secretary','treasurer')
";

$params = [];

if (!empty($search)) {
    $sql .= " AND LOWER(u.fullname) LIKE LOWER(?) ";
    $params[] = "%$search%";
}

if (!empty($barangay_filter)) {
    $sql .= " AND b.barangay_name = ? ";
    $params[] = $barangay_filter;
}

$sql .= " ORDER BY u.fullname ASC ";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$officials = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* COUNTS */
$totalOfficials = count($officials);
$chairCount = 0;
$secCount = 0;
$treasCount = 0;

foreach($officials as $o){
    if($o['role']=='chairman') $chairCount++;
    if($o['role']=='secretary') $secCount++;
    if($o['role']=='treasurer') $treasCount++;
}

/* BARANGAY LIST */
$bstmt = $conn->prepare("SELECT barangay_name FROM barangays ORDER BY barangay_name ASC");
$bstmt->execute();
$barangays = $bstmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Officials Information</title>

<link rel="stylesheet" href="../assets/style.css">

<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

body{
    font-family:'Segoe UI', system-ui, sans-serif;
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
    overflow-y:auto;
}

.page-title{
    color:#e8eaf0;
    font-size:22px;
    font-weight:600;
    margin-bottom:24px;
    padding-bottom:14px;
    border-bottom:1px solid rgba(255,255,255,0.08);
}

.kpi-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
    margin-bottom:22px;
}

.kpi-card{
    background:rgba(255,255,255,0.06);
    border:1px solid rgba(255,255,255,0.09);
    border-radius:14px;
    padding:18px 14px;
    text-align:center;
    backdrop-filter:blur(18px);
}

.kpi-card .kpi-label{
    font-size:11px;
    color:white;
    margin-bottom:8px;
    text-transform:uppercase;
    letter-spacing:.08em;
    font-weight: ;
}

.kpi-card .kpi-value{
    font-size:28px;
    font-weight:700;
    color:#1e3c72;
}

.glass{
    background:rgba(255,255,255,0.05);
    border:1px solid rgba(255,255,255,0.09);
    border-radius:16px;
    padding:22px;
    margin-bottom:20px;
    backdrop-filter:blur(18px);
}

.glass-title{
    font-size:15px;
    font-weight:600;
    color:whitesmoke;
    margin-bottom:16px;
}

/* FILTERS */
.filters{
    display:flex;
    gap:10px;
    margin-bottom:15px;
    flex-wrap:wrap;
}
.filters input,.filters select{
    flex:1;
    padding:12px;
    border:none;
    border-radius:10px;
    background:rgba(255,255,255,0.07);
    color:white;
    outline:none;
}
.filters button{
    width:120px;
    border:none;
    border-radius:10px;
    background:#5b8af5;
    color:white;
    cursor:pointer;
    font-weight:600;
}

/* TABLE */
.table-container{
    max-height:430px;
    overflow-y:auto;
    overflow-x:hidden;
    border-radius:12px;
}

table{
    width:100%;
    table-layout:fixed;
    border-collapse:collapse;
    color:white;
    font-size:13px;
}

th{
    background-color: #1e3c72;
    padding:12px 8px;
    position:sticky;
    top:0;
    z-index:2;
    white-space:nowrap;
}

td{
    padding:12px 8px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,0.06);
    word-wrap:break-word;
    overflow-wrap:break-word;
    color: #1e3c72;
    font-weight: bold;
}

tr:hover{
    background:rgba(255,255,255,0.04);
}
.btn{
    padding:7px 13px;
    border-radius:8px;
    text-decoration:none;
    color:white;
    font-size:12px;
    margin:2px;
    display:inline-block;
}
.view{ background:#17a2b8; }
.active{ background:#dc3545; }

.footer{
    text-align:center;
    padding:14px;
    color:#5a6070;
    font-size:12px;
    margin-top:8px;
    border-top:1px solid rgba(255,255,255,0.06);
}

/* MOBILE */
.hamburger{
    display:none;
    position:fixed;
    top:14px;
    left:14px;
    z-index:200;
    background:#1a1f2e;
    border:1px solid rgba(255,255,255,0.12);
    border-radius:8px;
    width:38px;
    height:38px;
    cursor:pointer;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap:5px;
}
.hamburger span{
    width:18px;
    height:2px;
    background:#c5cad8;
}
.overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.5);
    z-index:99;
}

@media(max-width:768px){
    .sidebar{
        position:fixed !important;
        left:-260px !important;
        top:0;
        bottom:0;
        z-index:100;
        width:240px !important;
        transition:left .25s ease;
    }
    .sidebar.open{ left:0 !important; }
    .overlay.open{ display:block; }
    .hamburger{ display:flex; }
    .main{ padding:64px 16px 20px; }
    .kpi-grid{ grid-template-columns:repeat(2,1fr); }
}
/* PRINT SETTINGS */
@media print{
    body{
        background:white !important;
    }
    #printHeader{
        display:block !important;
    }

    .sidebar,
    .hamburger,
    .overlay,
    .filters,
    .footer,
    .btn{
        display:none !important;
    }

    .main{
        padding:0;
        margin:0;
        width:100%;
    }

    .glass{
        background:white !important;
        border:none !important;
        box-shadow:none !important;
        padding:0;
    }

    .kpi-grid{
        margin-bottom:20px;
    }

    .kpi-card{
        border:1px solid #ccc;
        background:white !important;
        color:black !important;
    }

    .kpi-label,
    .kpi-value,
    .page-title,
    .glass-title{
        color:black !important;
    }

    table{
        color:black !important;
        font-size:12px;
    }

    th{
        background:#d9d9d9 !important;
        color:black !important;
    }

    td{
        color:black !important;
        border:1px solid #ccc;
    }

    .table-container{
        max-height:none;
        overflow:visible;
    }
}
</style>
</head>
<body>

<button class="hamburger" onclick="toggleSidebar()">
    <span></span><span></span><span></span>
</button>
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<div class="wrapper">

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <h1 class="page-title">👥 SK Officials Information</h1>

    <div class="kpi-grid">
        <div class="kpi-card"><div class="kpi-label">TOTAL OFFICIALS</div><div class="kpi-value"><?= $totalOfficials ?></div></div>
        <div class="kpi-card"><div class="kpi-label">CHAIRMEN</div><div class="kpi-value"><?= $chairCount ?></div></div>
        <div class="kpi-card"><div class="kpi-label">SECRETARIES</div><div class="kpi-value"><?= $secCount ?></div></div>
        <div class="kpi-card"><div class="kpi-label">TREASURERS</div><div class="kpi-value"><?= $treasCount ?></div></div>
    </div>

    <div class="glass">
    <div id="printHeader" style="display:none; text-align:center; margin-bottom:20px;">
    <h2>SK OFFICIALS REGISTRY REPORT</h2>
    <p>Generated on: <?= date('F d, Y h:i A') ?></p>
    <hr style="margin-top:10px;">
</div>
        <div class="glass-title">📋 Officials Registry Table</div>

        <form method="GET" class="filters">
            <input type="text" name="search" placeholder="Search by fullname..." value="<?= htmlspecialchars($search) ?>">
            <select name="barangay">
                <option value="">All Barangays</option>
                <?php foreach($barangays as $b){ ?>
                    <option value="<?= $b['barangay_name'] ?>" <?= ($barangay_filter==$b['barangay_name'])?'selected':'' ?>>
                        <?= htmlspecialchars($b['barangay_name']) ?>
                    </option>
                <?php } ?>
            </select>
            <button type="submit">Filter</button>
            <button type="button" onclick="printOfficials()">🖨 Print</button>
        </form>

        <div class="table-container">
            <table>
                <tr>
                    <th>POSITION</th>
                    <th>FULLNAME</th>
                    <th>AGE</th>
                    <th>BARANGAY</th>
                    <th>CONTACT NUMBER</th>
                    <th>ACTION</th>
                </tr>

                <?php if(!empty($officials)){ ?>
                    <?php foreach($officials as $o){ ?>
                    <tr>
                        <td><?= ucfirst($o['role']) ?></td>
                        <td><?= htmlspecialchars($o['fullname']) ?></td>
                        <td><?= htmlspecialchars($o['age']) ?></td>
                        <td><?= htmlspecialchars($o['barangay_name']) ?></td>
                        <td><?= htmlspecialchars($o['contact_number'] ?? 'N/A') ?></td>
                        <td>
                            <a href="admin_view_official.php?id=<?= $o['id'] ?>" class="btn view">View</a>
                            <a href="admin_deactivate_official.php?id=<?= $o['id'] ?>" class="btn active"
                               onclick="return confirm('Are you sure you want to deactivate this account?')">Active</a>
                        </td>
                    </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr><td colspan="6">No officials found.</td></tr>
                <?php } ?>
            </table>
        </div>
    </div>

    <div class="footer">
        © 2026 SK Decision Support System | Responsive Community Planning Platform
    </div>

</div>
</div>

<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('open');
}
</script>
<script>
function printOfficials(){
    window.print();
}
</script>

</body>
</html>