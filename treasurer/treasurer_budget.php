<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'treasurer') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$message = "";

/* ================= INSERT BUDGET ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $total_amount = $_POST['total_amount'] ?? '';
    $year = $_POST['year'] ?? '';

    if (!empty($total_amount) && !empty($year)) {

        try {

            /* check if year already exists for this barangay */
            $check = $conn->prepare("
                SELECT id FROM budgets
                WHERE barangay_id = ? AND year = ?
            ");
            $check->execute([$barangay_id, $year]);

            if ($check->fetch()) {
                $message = "❌ Budget for this year already exists.";
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
            }

        } catch (PDOException $e) {
            $message = "❌ Error: " . $e->getMessage();
        }

    } else {
        $message = "⚠️ All fields are required!";
    }
}

/* ================= GET BARANGAY BUDGET HISTORY ================= */
$stmt = $conn->prepare("
    SELECT year, total_amount
    FROM budgets
    WHERE barangay_id = ?
    ORDER BY year ASC
");
$stmt->execute([$barangay_id]);
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$years = [];
$total_amounts = [];

foreach ($budgets as $b) {
    $years[] = $b['year'];
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
        $insight = "Budget shows a decreasing trend. Review funding sources and allocations.";
        $forecast = $last * 0.90;
    } else {
        $trend = "stable";
        $insight = "Budget remains stable based on historical records.";
        $forecast = $last;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Budget Management</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
body{
    margin:0;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
}

.main{
    margin-left:190px;   /* moved dashboard 30px to left */
    padding:20px;
    width:calc(100% - 200px);
    overflow-x:hidden;
}

.glass{
    background:rgba(255,255,255,0.35);
    backdrop-filter:blur(30px);
    border-radius:15px;
    box-shadow:0 8px 25px rgba(0,0,0,0.08);
    padding:20px;
    margin-bottom:20px;
}

.form-container{
    max-width:500px;
}

input{
    width:100%;
    padding:12px;
    margin-bottom:12px;
    border:1px solid #ccc;
    border-radius:8px;
}

button{
    width:100%;
    padding:12px;
    background:#1e3c72;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
}

button:hover{
    background:#16305d;
}

.message{
    margin-top:10px;
    font-size:14px;
    color:#c0392b;
}

.badge{
    display:inline-block;
    padding:6px 12px;
    border-radius:8px;
    color:white;
    font-size:12px;
}

.up{background:green;}
.down{background:red;}
.stable{background:gray;}
.no{background:#555;}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

th{
    background:#1e3c72;
    color:white;
    padding:12px;
}

td{
    padding:10px;
    text-align:center;
    border-bottom:1px solid #ddd;
}
</style>

</head>
<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <h2>💰 Input Annual Budget Allocation</h2>

    <div class="glass form-container">
        <h3>Budget Entry Form</h3>

        <form method="POST">
            <input type="number" name="total_amount" placeholder="Enter Annual Budget Amount" required>
            <input type="number" name="year" placeholder="Enter Budget Year" required>
            <button type="submit">➕ Save Annual Budget</button>
        </form>

        <div class="message"><?= $message ?></div>
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

</body>
</html>