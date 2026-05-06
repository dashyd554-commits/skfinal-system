<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

$stmt = $conn->prepare("
    SELECT *
    FROM projects
    WHERE barangay_id=?
    AND status IN ('approved','rejected','pending_treasurer')
    ORDER BY id DESC
");
$stmt->execute([$barangay_id]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Secretary History</title>

<link rel="stylesheet" href="../assets/style.css">

<style>
*{
    box-sizing:border-box;
}

body{
    margin:0;
    height:100vh;
    overflow:hidden;
    font-family:Arial;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
}

/* FIXED SCREEN LAYOUT */
.wrapper{
    display:flex;
    height:100vh;
    width:100%;
}

.main{
    flex:1;
    height:100vh;
    overflow-y:auto;
    padding:20px;
}

/* TITLE */
h2{
    color:whitesmoke;
    text-align:center;
    margin-bottom:20px;
}

/* TABLE CONTAINER */
.table-box{
    background:rgba(255,255,255,0.15);
    backdrop-filter:blur(18px);
    border-radius:15px;
    padding:20px;
    box-shadow:0 8px 25px rgba(0,0,0,0.2);
}

/* TABLE */
table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#1e3c72;
    color:white;
    padding:12px;
}

td{
    padding:12px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,0.2);
    color:#1e3c72;
}

/* STATUS COLORS */
.approved{
    color:lightgreen;
    font-weight:bold;
}

.rejected{
    color:red;
    font-weight:bold;
}

.pending{
    color:violet;
    font-weight:bold;
}

/* MOBILE */
@media(max-width:768px){
    body{
        overflow:auto;
    }

    .wrapper{
        flex-direction:column;
    }

    .main{
        height:auto;
    }

    table,th,td{
        font-size:12px;
    }
}
</style>
</head>

<body>

<div class="wrapper">

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>🕘 Voting History</h2>

<div class="table-box">

<table>
<tr>
    <th>ID</th>
    <th>Title</th>
    <th>Budget</th>
    <th>Status</th>
</tr>

<?php if(count($data) > 0){ ?>
    <?php foreach($data as $d){ ?>
    <tr>
        <td><?= $d['id'] ?></td>
        <td><?= htmlspecialchars($d['name']) ?></td>
        <td>₱<?= number_format($d['budget_requested'],2) ?></td>
        <td>
            <?php
                if($d['status'] == 'approved'){
                    echo "<span class='approved'>Approved</span>";
                }elseif($d['status'] == 'rejected'){
                    echo "<span class='rejected'>Rejected</span>";
                }else{
                    echo "<span class='pending'>Pending Treasurer</span>";
                }
            ?>
        </td>
    </tr>
    <?php } ?>
<?php } else { ?>
    <tr>
        <td colspan="4">No voting history found.</td>
    </tr>
<?php } ?>

</table>

</div>
</div>
</div>

</body>
</html>