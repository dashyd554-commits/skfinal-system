<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

/* ===================== GET ALL ACTIVITIES ===================== */
$stmt = $conn->prepare("
    SELECT title, participants 
    FROM activities
");
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===================== BASIC STATS ===================== */
$avg = 0;
$totalParticipants = 0;

if (!empty($activities)) {

    $stmt = $conn->prepare("SELECT AVG(participants) AS avg_part FROM activities");
    $stmt->execute();
    $avgData = $stmt->fetch(PDO::FETCH_ASSOC);
    $avg = round($avgData['avg_part'] ?? 0, 2);

    $stmt = $conn->prepare("SELECT COALESCE(SUM(participants),0) AS total FROM activities");
    $stmt->execute();
    $totalData = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalParticipants = (int)$totalData['total'];
}

/* ===================== ML PROCESSING ===================== */
$mlRanked = [];

foreach ($activities as $a) {

    $participants = (int)($a['participants'] ?? 0);

    $score = ($totalParticipants > 0)
        ? ($participants / max($totalParticipants, 1)) * 100
        : 0;

    // bonus only if above average
    if ($participants >= $avg && $avg > 0) {
        $score += 5;
    }

    // CAP SCORE (important fix)
    $score = min(100, $score);

    $mlRanked[] = [
        'title' => $a['title'] ?? 'Unknown',
        'participants' => $participants,
        'score' => round($score, 2)
    ];
}

/* ===================== SORT ===================== */
usort($mlRanked, function($a, $b) {
    return $b['score'] <=> $a['score'];
});

/* ===================== TOP ACTIVITY ===================== */
$topActivity = $mlRanked[0]['title'] ?? 'No Data';
$topScore = $mlRanked[0]['score'] ?? 0;

/* ===================== INSIGHT ===================== */
if ($avg >= 50) {
    $mlInsight = "High community engagement detected. Activities are performing strongly.";
    $mlSuggestion = "Scale successful programs and replicate high-performing events.";
} elseif ($avg >= 20) {
    $mlInsight = "Moderate engagement detected. Some activities perform well.";
    $mlSuggestion = "Improve promotion and increase participation incentives.";
} elseif ($avg > 0) {
    $mlInsight = "Low engagement detected. Community participation is weak.";
    $mlSuggestion = "Redesign activities and increase awareness campaigns.";
} else {
    $mlInsight = "No activity data available.";
    $mlSuggestion = "Start recording activities to generate insights.";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Secretary Recommendations (ML)</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.box-item { padding: 8px 0; }

hr {
    border: 0;
    border-top: 1px solid #eee;
    margin: 15px 0;
}

.highlight {
    font-weight: bold;
    color: #2d89ef;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

th {
    background: #28a745;
    color: white;
    padding: 10px;
}

td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}

tr:hover { background: #f5f5f5; }
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>🤖 ML Recommendations</h2>
    </div>

    <div class="glass" style="padding:20px;">

        <h3>📌 Overview</h3>
        <p>
            Machine learning analysis of participation trends helps identify the most effective SK activities.
        </p>

        <hr>

        <h3>🔥 ML Ranked Activities</h3>

        <table>
            <tr>
                <th>Activity</th>
                <th>Participants</th>
                <th>ML Score</th>
            </tr>

            <?php if (!empty($mlRanked)) { ?>
                <?php foreach ($mlRanked as $row) { ?>
                <tr>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= $row['participants'] ?></td>
                    <td><?= $row['score'] ?>%</td>
                </tr>
                <?php } ?>
            <?php } else { ?>
                <tr>
                    <td colspan="3" style="text-align:center;">No activity data found</td>
                </tr>
            <?php } ?>

        </table>

        <hr>

        <h3>📊 ML Insight</h3>
        <p class="highlight"><?= $mlInsight ?></p>

        <p><b>Top Activity:</b> <?= $topActivity ?> (<?= $topScore ?>%)</p>

        <hr>

        <h3>💡 AI Suggestions</h3>
        <p><?= $mlSuggestion ?></p>

    </div>

</div>

</body>
</html>