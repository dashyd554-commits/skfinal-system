<?php
session_start();
include '../config/db.php';

if ($_SESSION['user']['role'] != 'chairperson') {
    header("Location: ../index.php");
    exit();
}

if ($_POST) {

    $stmt = $conn->prepare("
        INSERT INTO proposals
        (barangay_id, created_by, type, title, description, purpose, target_participants, proposed_budget, expected_benefit, proposed_date)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->execute([
        $_SESSION['user']['barangay_id'],
        $_SESSION['user']['id'],
        'project',
        $_POST['title'],
        $_POST['description'],
        $_POST['purpose'],
        $_POST['target_participants'],
        $_POST['budget'],
        $_POST['benefit'],
        $_POST['date']
    ]);

    echo "<script>alert('Project Proposed');</script>";
}
?>

<form method="POST">
<h2>Propose Project</h2>

<input name="title" placeholder="Title"><br>
<textarea name="description"></textarea><br>
<input name="purpose" placeholder="Purpose"><br>
<input name="target_participants" type="number"><br>
<input name="budget" type="number"><br>
<input name="benefit"><br>
<input name="date" type="date"><br>

<button type="submit">Submit</button>
</form>