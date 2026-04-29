<?php
session_start();
include '../config/db.php';

$barangay_id = $_SESSION['user']['barangay_id'];

$stmt = $conn->prepare("
    SELECT * FROM budget_transactions
    WHERE barangay_id = :bid
    ORDER BY created_at DESC
");
$stmt->execute([':bid' => $barangay_id]);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>💸 Spending History</h2>

<?php foreach($data as $d){ ?>
<div>
    ₱<?= number_format($d['amount']) ?> - <?= $d['description'] ?>
</div>
<?php } ?>