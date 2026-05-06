<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'treasurer') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$message = "";
$messageType = "";

$total_amount = $_POST['total_amount'] ?? '';
$year = $_POST['year'] ?? '';

/* ================= INSERT BUDGET ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $total_amount = trim($total_amount);
    $year = trim($year);

    if ($total_amount === "" || $year === "") {
        $message = "⚠️ All fields are required.";
        $messageType = "error";
    }
    elseif (!is_numeric($total_amount) || $total_amount <= 0) {
        $message = "❌ Budget amount must be greater than zero.";
        $messageType = "error";
    }
    elseif (!is_numeric($year) || strlen($year) != 4 || $year < 2020 || $year > 2100) {
        $message = "❌ Please enter a valid year.";
        $messageType = "error";
    }
    else {

        try {
            $check = $conn->prepare("
                SELECT id FROM budgets
                WHERE barangay_id = ? AND year = ?
            ");
            $check->execute([$barangay_id, $year]);

            if ($check->fetch()) {
                $message = "❌ Budget for this year already exists.";
                $messageType = "error";
            } else {

                $stmt = $conn->prepare("
                    INSERT INTO budgets 
                    (barangay_id, total_amount, used_amount, remaining_budget, year)
                    VALUES (?, ?, 0, ?, ?)
                ");

                $stmt->execute([
                    $barangay_id,
                    $total_amount,
                    $total_amount,
                    $year
                ]);

                $message = "✅ Annual budget saved successfully!";
                $messageType = "success";

                $total_amount = "";
                $year = "";
            }

        } catch (PDOException $e) {
            $message = "❌ Database error occurred.";
            $messageType = "error";
        }
    }
}

/* ================= GET BUDGET HISTORY ================= */
$stmt = $conn->prepare("
    SELECT year, total_amount
    FROM budgets
    WHERE barangay_id = ?
    ORDER BY year ASC
");
$stmt->execute([$barangay_id]);
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_amounts = [];

foreach ($budgets as $b) {
    $total_amounts[] = (float)$b['total_amount'];
}

/* ================= ML ANALYSIS ================= */
$trend = "no";
$insight = "Not enough data for ML analysis.";
$forecast = 0;

$count = count($total_amounts);

if ($count >= 2) {

    $last = $total_amounts[$count - 1];
    $prev = $total_amounts[$count - 2];

    if ($last > $prev) {
        $trend = "up";
        $insight = "Budget shows an increasing trend. Financial capacity is improving.";
        $forecast = $last * 1.10;
    } elseif ($last < $prev) {
        $trend = "down";
        $insight = "Budget shows a decreasing trend. Review funding allocations carefully.";
        $forecast = $last * 0.90;
    } else {
        $trend = "stable";
        $insight = "Budget remains stable based on historical annual records.";
        $forecast = $last;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Budget Management</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/style.css">

<style>
*{
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}

body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    min-height:100vh;
}

.wrapper{
    display:flex;
    min-height:100vh;
}

.main{
    flex:1;
    padding:20px;
    overflow-x:hidden;
}

h2{
    color:white;
    text-align:center;
    margin-bottom:20px;
    font-size:28px;
}

.glass{
    background:rgba(255,255,255,0.13);
    backdrop-filter:blur(18px);
    border-radius:18px;
    padding:22px;
    color:white;
    box-shadow:0 8px 25px rgba(0,0,0,0.25);
    margin-bottom:20px;
}

.form-container{
    max-width:500px;
    margin:0 auto 20px auto;
}

input{
    width:100%;
    padding:12px;
    margin-bottom:12px;
    border:none;
    border-radius:10px;
    font-size:14px;
}

button{
    width:100%;
    padding:12px;
    background:#1e3c72;
    color:white;
    border:none;
    border-radius:10px;
    cursor:pointer;
    font-size:15px;
    font-weight:bold;
    transition:0.3s;
}

button:hover{
    background:#16305d;
}

.alert{
    margin-top:10px;
    padding:10px;
    border-radius:8px;
    font-weight:bold;
}

.success{
    background:rgba(34,197,94,0.2);
    color:#dcfce7;
}

.error{
    background:rgba(239,68,68,0.2);
    color:#fee2e2;
}

.badge{
    display:inline-block;
    padding:6px 12px;
    border-radius:8px;
    color:white;
    font-size:12px;
    font-weight:bold;
}

.up{background:green;}
.down{background:red;}
.stable{background:gray;}
.no{background:#555;}

p{
    line-height:1.8;
    color:#f8fafc;
}

.table-wrap{
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:500px;
}

th{
    background:#1e3c72;
    color:white;
    padding:12px;
}

td{
    padding:10px;
    text-align:center;
    background:rgba(255,255,255,0.85);
    color:#1e3c72;
    border-bottom:1px solid #ddd;
}

@media(max-width:768px){
    .main{
        padding:12px;
    }

    h2{
        font-size:22px;
    }
}
</style>
</head>

<body>

<div class="wrapper">

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <h2>💰 Input Annual Budget Allocation</h2>

    <div class="glass form-container">
        <h3>Budget Entry Form</h3>

        <form method="POST">
            <input type="number" name="total_amount" placeholder="Enter Annual Budget Amount"
                   value="<?= htmlspecialchars($total_amount) ?>" required min="1">

            <input type="number" name="year" placeholder="Enter Budget Year"
                   value="<?= htmlspecialchars($year) ?>" required min="2020" max="2100">

            <button type="submit" onclick="return confirm('Save this annual budget record?')">
                ➕ Save Annual Budget
            </button>
        </form>

        <?php if($message!=""){ ?>
            <div class="alert <?= $messageType ?>">
                <?= $message ?>
            </div>
        <?php } ?>
    </div>

    <div class="glass">
        <h3>🤖 ML Budget Recommendation</h3>

        <p>
            Trend:
            <span class="badge <?= $trend ?>">
                <?= strtoupper($trend) ?>
            </span>
        </p>

        <p><?= $insight ?></p>

        <hr>

        <h3>📈 Suggested Next Annual Budget</h3>
        <p><b>₱<?= number_format($forecast,2) ?></b></p>
    </div>

    <div class="glass">
        <h3>📋 Barangay Budget History</h3>

        <div class="table-wrap">
            <table>
                <tr>
                    <th>Year</th>
                    <th>Total Annual Budget</th>
                </tr>

                <?php if(count($budgets)==0){ ?>
                    <tr><td colspan="2">No budget records yet.</td></tr>
                <?php } ?>

                <?php foreach($budgets as $b){ ?>
                <tr>
                    <td><?= $b['year'] ?></td>
                    <td>₱<?= number_format($b['total_amount'],2) ?></td>
                </tr>
                <?php } ?>
            </table>
        </div>
    </div>

</div>
</div>

</body>
</html>