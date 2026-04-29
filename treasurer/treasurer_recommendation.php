<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'treasurer') {
    header("Location: ../index.php");
    exit();
}

/* ================= TOP ACTIVITIES ================= */
$stmt = $conn->prepare("
    SELECT title, participants 
    FROM activities 
    ORDER BY participants DESC 
    LIMIT 5
");
$stmt->execute();
$topActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= TOTAL BUDGET ================= */
$stmt = $conn->prepare("SELECT SUM(total_amount) AS total FROM budgets");
$stmt->execute();
$b = $stmt->fetch(PDO::FETCH_ASSOC);
$totalBudget = $b['total'] ?? 0;

/* ================= TOTAL PARTICIPANTS ================= */
$stmt = $conn->prepare("SELECT SUM(participants) AS total FROM activities");
$stmt->execute();
$p = $stmt->fetch(PDO::FETCH_ASSOC);
$totalParticipants = $p['total'] ?? 0;

/* ================= ML RATIO ================= */
$ratio = ($totalParticipants > 0)
    ? $totalBudget / $totalParticipants
    : 0;

/* ================= ML RANKING ================= */
$mlRank = [];
$highest = 0;
$bestActivity = "N/A";

foreach ($topActivities as $a) {

    $participants = (int)$a['participants'];

    $score = ($totalParticipants > 0)
        ? ($participants / $totalParticipants) * 100
        : 0;

    $score = round($score, 2);

    $mlRank[] = [
        'title' => $a['title'],
        'participants' => $participants,
        'score' => $score
    ];

    if ($score > $highest) {
        $highest = $score;
        $bestActivity = $a['title'];
    }
}

/* ================= ML INSIGHT ================= */
if ($totalParticipants >= 200) {
    $insight = "High engagement detected. Strong community participation supports effective budget utilization.";
} elseif ($totalParticipants >= 100) {
    $insight = "Moderate engagement detected. Some activities perform well but can be improved.";
} else {
    $insight = "Low engagement detected. Activities need restructuring for better impact.";
}

/* ================= AI RECOMMENDATIONS ================= */
$suggestion = [];

if ($highest >= 40) {
    $suggestion[] = "Prioritize scaling the top activity: $bestActivity";
}

if ($ratio > 1000) {
    $suggestion[] = "Budget per participant is high. Consider increasing outreach programs.";
} else {
    $suggestion[] = "Budget usage is efficient. Maintain current allocation strategy.";
}

$suggestion[] = "Focus on activities with consistent high participation trends.";
?>

<!DOCTYPE html>
<html>
<head>
<title>Budget Recommendations (ML)</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
p {
    font-size: 14px;
    line-height: 1.6;
}

.item {
    padding: 6px 0;
}

hr {
    border: 0;
    border-top: 1px solid #eee;
    margin: 15px 0;
}

.glass {
    padding: 20px;
}

</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>🤖 Budget Recommendations (ML Enhanced)</h2>
        <p>AI-driven financial decision support</p>
    </div>

    <div class="glass">

        <!-- OVERVIEW -->
        <h3>📊 Overview</h3>
        <p>
            This system analyzes budget allocation and activity participation
            to generate intelligent recommendations.
        </p>

        <hr>

        <!-- TOP ACTIVITIES -->
        <h3>📌 ML Ranked Activities</h3>

        <?php foreach ($mlRank as $r) { ?>
            <div class="item">
                ✔ <b><?= htmlspecialchars($r['title']) ?></b>
                — <?= $r['participants'] ?> participants
                — Score: <?= $r['score'] ?>%
            </div>
        <?php } ?>

        <hr>

        <!-- BUDGET INFO -->
        <h3>📊 Budget Insight</h3>

        <p><b>Total Budget:</b> ₱ <?= number_format($totalBudget) ?></p>
        <p><b>Total Participants:</b> <?= number_format($totalParticipants) ?></p>
        <p><b>Budget per Participant:</b> ₱ <?= round($ratio, 2) ?></p>

        <hr>

        <!-- ML INSIGHT -->
        <h3>🤖 ML Insight</h3>
        <p><?= $insight ?></p>

        <hr>

        <!-- AI SUGGESTIONS -->
        <h3>💡 AI Recommendations</h3>

        <?php foreach ($suggestion as $s) { ?>
            <p>• <?= $s ?></p>
        <?php } ?>

    </div>

</div>

</body>
</html>