<?php
session_start();
include '../config/db.php';

if ($_SESSION['user']['role'] != 'treasurer') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* SAVE BUDGET */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $year = $_POST['year'];
    $amount = $_POST['amount'];

    $stmt = $conn->prepare("
        INSERT INTO budgets (barangay_id, treasurer_id, year, annual_budget, budget_used, remaining_budget)
        VALUES (:bid, :tid, :year, :amount, 0, :amount)
    ");

    $stmt->execute([
        ':bid' => $barangay_id,
        ':tid' => $_SESSION['user']['id'],
        ':year' => $year,
        ':amount' => $amount
    ]);
}
?>

<form method="POST">
    <input type="number" name="year" placeholder="Year" required>
    <input type="number" name="amount" placeholder="Annual Budget" required>
    <button type="submit">Save Budget</button>
</form>