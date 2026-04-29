<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairperson') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

/* ================= GET PROPOSALS ================= */
$stmt = $conn->prepare("
    SELECT title, proposed_budget, target_participants, status, vote_yes, vote_no
    FROM proposals
    WHERE created_by = ?
");
$stmt->execute([$user_id]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= AI SCORING ================= */
$results = [];

foreach ($rows as $r) {

    $budget = (float)$r['proposed_budget'];
    $participants = (int)$r['target_participants'];
    $yes = (int)$r['vote_yes'];
    $no = (int)$r['vote_no'];

    // base score
    $score = 50;

    // budget factor (lower budget = higher approval chance)
    if ($budget <= 50000) {
        $score += 20;
    } elseif ($budget <= 100000) {
        $score += 10;
    } else {
        $score -= 15;
    }

    // participation factor
    if ($participants >= 100) {
        $score += 15;
    } elseif ($participants >= 50) {
        $score += 10;
    } else {
        $score += 5;
    }

    // voting influence
    $score += ($yes * 2);
    $score -= ($no * 2);

    // clamp
    $score = max(0, min(100, $score));

    $results[] = [
        'title' => $r['title'],
        'budget' => $budget,
        'participants' => $participants,
        'score' => round($score, 2)
    ];
}

/* sort highest first */
usort($results, function($a, $b) {
    return $b['score'] <=> $a['score'];
});
?>

<!DOCTYPE html>
<html>
<head>
<title>AI Prediction</title>

<link rel="stylesheet" href="../assets/style.css">

<style>
.card {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(20px);
    padding:20px;
    border-radius:15px;
    margin-bottom:10px;
}

.good { color:green; font-weight:bold; }
.mid { color:orange; font-weight:bold; }
.low { color:red; font-weight:bold; }

.glass {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(20px);
    padding:20px;
    border-radius:15px;
}
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<h2>🤖 AI Proposal Prediction</h2>

<div class="glass">

<?php foreach ($results as $r) { ?>

<?php
$class = "low";
if ($r['score'] >= 70) $class = "good";
elseif ($r['score'] >= 40) $class = "mid";
?>

<div class="card">
    <h3><?= htmlspecialchars($r['title']) ?></h3>

    <p>Budget: ₱ <?= number_format($r['budget']) ?></p>
    <p>Target Participants: <?= $r['participants'] ?></p>

    <p>
        Approval Probability:
        <span class="<?= $class ?>">
            <?= $r['score'] ?>%
        </span>
    </p>
</div>

<?php } ?>

</div>

</div>

</body>
</html>