<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

/* ===================== SAFE DATA LOAD ===================== */
try {
    $stmt = $conn->prepare("SELECT title, participants FROM activities");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

/* ===================== INIT ===================== */
$labels = [];
$data = [];
$total = 0;
$mlResults = [];

/* ===================== PROCESS DATA ===================== */
if (!empty($rows)) {

    foreach ($rows as $row) {
        $participants = (int)($row['participants'] ?? 0);

        $labels[] = $row['title'] ?? 'Unknown';
        $data[] = $participants;
        $total += $participants;
    }

    /* ===================== STABLE ML SCORE ===================== */
    foreach ($rows as $row) {

        $participants = (int)($row['participants'] ?? 0);

        // smoother ML formula (prevents extreme imbalance)
        $score = ($total > 0)
            ? sqrt($participants / max($total, 1)) * 100
            : 0;

        $mlResults[] = [
            'title' => $row['title'] ?? 'Unknown',
            'participants' => $participants,
            'score' => round($score, 2)
        ];
    }

    /* SORT */
    usort($mlResults, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $topActivity = $mlResults[0]['title'] ?? 'No Data';
    $topScore = $mlResults[0]['score'] ?? 0;

} else {
    $topActivity = "No Data";
    $topScore = 0;
}

/* ===================== INSIGHT ===================== */
if ($total >= 200) {
    $mlInsight = "High engagement detected. Strong community participation.";
    $recommendation = "Maintain and expand successful activities.";
} elseif ($total >= 100) {
    $mlInsight = "Moderate engagement detected.";
    $recommendation = "Improve promotion and replicate successful programs.";
} elseif ($total > 0) {
    $mlInsight = "Low engagement detected.";
    $recommendation = "Increase outreach and improve event design.";
} else {
    $mlInsight = "No activity data available.";
    $recommendation = "Start recording activities to generate insights.";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Secretary Dashboard (ML)</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
.grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

@media (max-width: 768px) {
    .grid {
        grid-template-columns: 1fr;
    }
}

.insight {
    font-size: 14px;
    color: #555;
}

.highlight {
    font-weight: bold;
    color: #2d89ef;
}
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>🤖 Secretary Dashboard (ML Enhanced)</h2>
    </div>

    <!-- KPI -->
    <div class="grid">

        <div class="glass card">
            <h3>👥 Total Participants</h3>
            <h2><?= $total ?></h2>
        </div>

        <div class="glass card">
            <h3>📊 Activities Count</h3>
            <h2><?= count($labels) ?></h2>
        </div>

    </div>

    <!-- CHART -->
    <div class="glass" style="margin-top:20px;">
        <h3>📊 Participation per Activity</h3>
        <canvas id="chart"></canvas>
    </div>

    <!-- ML RANKING -->
    <div class="glass" style="margin-top:20px;">
        <h3>🤖 ML Activity Ranking</h3>

        <table width="100%">
            <tr>
                <th>Activity</th>
                <th>Participants</th>
                <th>ML Score</th>
            </tr>

            <?php if (!empty($mlResults)) { ?>
                <?php foreach ($mlResults as $r) { ?>
                <tr>
                    <td><?= htmlspecialchars($r['title']) ?></td>
                    <td><?= $r['participants'] ?></td>
                    <td><?= $r['score'] ?>%</td>
                </tr>
                <?php } ?>
            <?php } else { ?>
                <tr>
                    <td colspan="3" style="text-align:center;">No activity data found</td>
                </tr>
            <?php } ?>

        </table>
    </div>

    <!-- INSIGHT -->
    <div class="glass" style="margin-top:20px;">
        <h3>📢 AI Insight</h3>
        <p class="insight"><?= $mlInsight ?></p>
    </div>

    <!-- RECOMMENDATION -->
    <div class="glass" style="margin-top:20px;">
        <h3>💡 Recommendation</h3>
        <p class="highlight"><?= $recommendation ?></p>
    </div>

    <!-- TOP -->
    <div class="glass" style="margin-top:20px;">
        <h3>🏆 Top Activity (ML)</h3>
        <p class="highlight">
            <?= $topActivity ?> (<?= $topScore ?>%)
        </p>
    </div>

</div>

<script>
new Chart(document.getElementById('chart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Participants',
            data: <?= json_encode($data) ?>
        }]
    }
});
</script>

</body>
</html>