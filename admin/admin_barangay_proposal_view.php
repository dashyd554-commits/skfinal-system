<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* ================= FILTERS ================= */
$search = $_GET['search'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "
    SELECT 
        p.id,
        p.name,
        p.description,
        p.status,
        b.barangay_name
    FROM projects p
    LEFT JOIN barangays b ON p.barangay_id = b.id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND LOWER(p.name) LIKE LOWER(?) ";
    $params[] = "%$search%";
}

if (!empty($barangay_filter)) {
    $sql .= " AND b.barangay_name = ? ";
    $params[] = $barangay_filter;
}

if (!empty($status_filter)) {
    $sql .= " AND LOWER(p.status) = LOWER(?) ";
    $params[] = $status_filter;
}

$sql .= " ORDER BY p.id DESC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$proposals = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* COUNTS */
$totalProposal = count($proposals);
$approvedCount = 0;
$rejectedCount = 0;
$pendingCount = 0;

foreach($proposals as $p){
    if(strtolower($p['status'])=='approved') $approvedCount++;
    elseif(strtolower($p['status'])=='rejected') $rejectedCount++;
    else $pendingCount++;
}

/* BARANGAY DROPDOWN */
$bstmt = $pdo->prepare("SELECT barangay_name FROM barangays ORDER BY barangay_name ASC");
$bstmt->execute();
$barangays = $bstmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Barangay Proposal Monitoring</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/style.css">

<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}

body{
    font-family:'Segoe UI',system-ui,sans-serif;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    height:100vh;
    overflow:hidden;
}

/* WRAPPER */
.wrapper{
    display:flex;
    width:100%;
    height:100vh;
}

/* MAIN FIXED LAYOUT */
.main{
    flex:1;
    min-width:0;
    padding:28px 24px;
    height:100vh;
    display:flex;
    flex-direction:column;
    overflow:hidden; /* IMPORTANT */
}

/* TITLE */
.page-title{
    color:#e8eaf0;
    font-size:22px;
    font-weight:600;
    margin-bottom:18px;
}

/* KPI */
.kpi-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
    margin-bottom:18px;
    flex-shrink:0;
}

.kpi-card{
    background:rgba(255,255,255,0.06);
    border:1px solid rgba(255,255,255,0.08);
    border-radius:14px;
    padding:18px;
    text-align:center;
    backdrop-filter:blur(18px);
}

.kpi-label{font-size:11px;color:white;font-weight:bold;}
.kpi-value{font-size:26px;font-weight:700;color:#1e3c72;}

/* GLASS PANEL */
.glass{
    background:rgba(255,255,255,0.05);
    border:1px solid rgba(255,255,255,0.08);
    border-radius:16px;
    padding:20px;
    backdrop-filter:blur(18px);

    display:flex;
    flex-direction:column;

    flex:1;
    min-height:0;
    overflow:hidden; /* IMPORTANT */
}

.glass-title{
    color:white;
    font-size:15px;
    margin-bottom:14px;
    font-weight:600;
}

/* FILTERS */
.filters{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:14px;
    flex-shrink:0;
}

.filters input,.filters select{
    flex:1;
    padding:11px;
    border:none;
    border-radius:10px;
    background:rgba(255,255,255,0.08);
    color:white;
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

/* TABLE SCROLL AREA */
.table-container{
    flex:1;
    overflow-y:auto;
    overflow-x:auto;
    border-radius:12px;
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
    color:white;
}

th{
    background:#1e3c72;
    padding:12px;
    position:sticky;
    top:0;
    z-index:2;
}

td{
    padding:12px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,0.06);
    color:#1e3c72;
    font-weight:bold;
    background:rgba(255,255,255,0.85);
}

/* BUTTON */
.btn{
    padding:7px 14px;
    border-radius:8px;
    text-decoration:none;
    color:white;
    font-size:12px;
}

.view{background:#17a2b8;}

/* FOOTER */
.footer{
    text-align:center;
    color:#aaa;
    font-size:12px;
    margin-top:10px;
    flex-shrink:0;
}

/* PRINT */
@media print{
    .sidebar,.filters,.btn,.footer{display:none!important;}
    body{background:white;overflow:visible;}
    .main{height:auto;overflow:visible;}
    .glass{background:white!important;border:1px solid #ccc;}
    .page-title,.glass-title,.kpi-label,.kpi-value{color:black!important;}
    td,th{color:black!important;border:1px solid #ccc;}
}
</style>
</head>

<body>

<div class="wrapper">
<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <h1 class="page-title">📁 Barangay Proposal Monitoring</h1>

    <!-- KPI -->
    <div class="kpi-grid">
        <div class="kpi-card"><div class="kpi-label">TOTAL PROPOSALS</div><div class="kpi-value"><?= $totalProposal ?></div></div>
        <div class="kpi-card"><div class="kpi-label">APPROVED</div><div class="kpi-value"><?= $approvedCount ?></div></div>
        <div class="kpi-card"><div class="kpi-label">REJECTED</div><div class="kpi-value"><?= $rejectedCount ?></div></div>
        <div class="kpi-card"><div class="kpi-label">PENDING</div><div class="kpi-value"><?= $pendingCount ?></div></div>
    </div>

    <!-- TABLE -->
    <div class="glass">
        <div class="glass-title">📋 Proposal Registry Table</div>

        <form method="GET" class="filters">
            <input type="text" name="search" placeholder="Search project name..." value="<?= htmlspecialchars($search) ?>">

            <select name="barangay">
                <option value="">All Barangays</option>
                <?php foreach($barangays as $b){ ?>
                    <option value="<?= $b['barangay_name'] ?>" <?= ($barangay_filter==$b['barangay_name'])?'selected':'' ?>>
                        <?= htmlspecialchars($b['barangay_name']) ?>
                    </option>
                <?php } ?>
            </select>

            <select name="status">
                <option value="">All Status</option>
                <option value="approved" <?= ($status_filter=='approved')?'selected':'' ?>>Approved</option>
                <option value="rejected" <?= ($status_filter=='rejected')?'selected':'' ?>>Rejected</option>
                <option value="pending" <?= ($status_filter=='pending')?'selected':'' ?>>Pending</option>
            </select>

            <button type="submit">Filter</button>
            <button type="button" onclick="window.print()">🖨 Print</button>
        </form>

        <div class="table-container">
            <table>
                <tr>
                    <th>BARANGAY</th>
                    <th>PROJECT NAME</th>
                    <th>DESCRIPTION</th>
                    <th>STATUS</th>
                    <th>ACTION</th>
                </tr>

                <?php if($proposals): ?>
                    <?php foreach($proposals as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['barangay_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($p['name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($p['description'] ?? 'No description') ?></td>
                        <td><?= strtoupper($p['status']) ?></td>
                        <td>
                            <a href="admin_proposal_view.php?id=<?= $p['id'] ?>" class="btn view">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">No proposal found.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="footer">© 2026 SK Decision Support System</div>

</div>
</div>

</body>
</html>