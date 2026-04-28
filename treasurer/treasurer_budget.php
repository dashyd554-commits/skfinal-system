<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'treasurer') {
    header("Location: ../index.php");
    exit();
}

$message = "";

/* ================= INSERT BUDGET ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount = $_POST['amount'] ?? '';
    $year = $_POST['year'] ?? '';

    if (!empty($amount) && !empty($year)) {

        try {
            $stmt = $conn->prepare("
                INSERT INTO budgets (amount, year)
                VALUES (?, ?)
            ");

            $stmt->execute([$amount, $year]);

            $message = "✅ Budget added successfully!";

        } catch (PDOException $e) {
            $message = "❌ Error: " . $e->getMessage();
        }

    } else {
        $message = "⚠️ All fields are required!";
    }
}

/* ================= GET BUDGET DATA ================= */
$stmt = $conn->prepare("SELECT year, amount FROM budgets ORDER BY year ASC");
$stmt->execute();
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$years = [];
$amounts = [];

foreach ($budgets as $b) {
    $years[] = $b['year'];
    $amounts[] = (float)$b['amount'];
}

/* ================= ML ANALYSIS ================= */
$trend = "no data";
$insight = "Not enough data for ML analysis.";
$forecast = 0;

$count = count($amounts);

if ($count >= 2) {

    $last = $amounts[$count - 1];
    $prev = $amounts[$count - 2];

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
        $insight = "Budget is stable across recent years.";
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
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
input {
    width: 100%;
    padding: 10px;
    margin-bottom: 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 14px;
}

button {
    padding: 10px 15px;
    background: #ff9800;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}

button:hover {
    background: #e68900;
}

.message {
    margin-top: 10px;
    font-size: 14px;
}

.form-container {
    max-width: 500px;
    margin: auto;
}

.badge {
    display:inline-block;
    padding:5px 10px;
    border-radius:8px;
    color:white;
    font-size:12px;
}

.up { background:green; }
.down { background:red; }
.stable { background:gray; }
.no { background:gray; }
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>💰 Budget Management</h2>
        <p>Input annual budget allocation with ML insights</p>
    </div>

    <!-- FORM -->
    <div class="glass form-container" style="padding:20px;">

        <h3>Input Budget</h3>

        <form method="POST">

            <input type="number" name="amount" placeholder="Budget Amount" required>

            <input type="number" name="year" placeholder="Year (e.g. 2026)" required>

            <button type="submit">➕ Save Budget</button>

        </form>

        <div class="message">
            <?= $message ?>
        </div>

    </div>

    <!-- ML OUTPUT -->
    <div class="glass" style="margin-top:20px; padding:20px;">

        <h3>🤖 ML Insight</h3>

        <p>
            Trend:
            <span class="badge <?= $trend ?>">
                <?= strtoupper($trend) ?>
            </span>
        </p>

        <p><?= $insight ?></p>

        <hr>

        <h3>📈 Forecast</h3>

        <p>Predicted Next Budget: <b>₱ <?= number_format($forecast) ?></b></p>

    </div>

</div>

</body>
</html>