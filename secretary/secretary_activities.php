<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'secretary') {
    header("Location: ../index.php");
    exit();
}

/* ===================== MESSAGE (FIXED) ===================== */
$message = "";

/* ===================== INSERT ACTIVITY ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = $_POST['title'] ?? '';
    $participants = $_POST['participants'] ?? 0;
    $date = $_POST['date'] ?? '';

    if ($title && $participants && $date) {

        try {
            $stmt = $conn->prepare("
                INSERT INTO activities (title, participants, date)
                VALUES (:title, :participants, :date)
            ");

            $stmt->execute([
                ':title' => $title,
                ':participants' => (int)$participants,
                ':date' => $date
            ]);

            $message = "✅ Activity recorded successfully!";

        } catch (PDOException $e) {
            $message = "❌ Error saving activity.";
        }

    } else {
        $message = "⚠️ All fields are required!";
    }
}

/* ===================== GET ACTIVITIES ===================== */
try {
    $stmt = $conn->prepare("SELECT * FROM activities ORDER BY date DESC NULLS LAST");
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $activities = [];
}

/* ===================== DEFAULT VALUES ===================== */
$totalParticipants = 0;
$mlResults = [];
$topActivity = "No Data";
$topScore = 0;
$mlInsight = "";
$recommendation = "";

/* ===================== ML PROCESSING ===================== */
if (!empty($activities)) {

    foreach ($activities as $a) {
        $totalParticipants += (int)($a['participants'] ?? 0);
    }

    foreach ($activities as $a) {

        $participants = (int)($a['participants'] ?? 0);

        $score = ($totalParticipants > 0)
            ? ($participants / max($totalParticipants, 1)) * 100
            : 0;

        $mlResults[] = [
            'title' => $a['title'] ?? 'Unknown',
            'participants' => $participants,
            'score' => round($score, 2)
        ];
    }

    usort($mlResults, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $topActivity = $mlResults[0]['title'] ?? 'No Data';
    $topScore = $mlResults[0]['score'] ?? 0;
}

/* ===================== INSIGHT ===================== */
if ($totalParticipants >= 200) {
    $mlInsight = "High engagement detected. Strong community participation.";
    $recommendation = "Maintain and expand successful activities.";
} elseif ($totalParticipants >= 100) {
    $mlInsight = "Moderate engagement detected.";
    $recommendation = "Improve promotion and replicate successful events.";
} else {
    $mlInsight = "Low engagement detected.";
    $recommendation = "Increase outreach and redesign activities.";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Activities Management (ML)</title>

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
}

button {
    padding: 10px 15px;
    background: #28a745;
    color: white;
    border: none;
    border-radius: 8px;
}

button:hover { background: #1e7e34; }

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    background: white;
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

.message {
    margin-top: 10px;
    font-size: 14px;
}
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        <h2>🤖 Activities Management (ML Enhanced)</h2>
    </div>

    <!-- FORM -->
    <div class="glass" style="padding:20px; margin-bottom:20px;">

        <h3>Add New Activity</h3>

        <form method="POST">

            <input type="text" name="title" placeholder="Activity Title" required>
            <input type="number" name="participants" placeholder="Participants" required>
            <input type="date" name="date" required>

            <button type="submit">➕ Save Activity</button>

        </form>

        <!-- FIXED MESSAGE OUTPUT -->
        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>

    </div>

    <!-- INSIGHT -->
    <div class="glass" style="padding:20px; margin-bottom:20px;">

        <h3>🤖 ML Insight</h3>

        <p><b><?= htmlspecialchars($mlInsight) ?></b></p>
        <p><b>Top Activity:</b> <?= htmlspecialchars($topActivity) ?></p>
        <p><b>Top Score:</b> <?= $topScore ?>%</p>

    </div>

    <!-- TABLE -->
    <div class="glass" style="padding:20px;">

        <h3>📊 Activity List (ML Ranked)</h3>

        <table>
            <tr>
                <th>Title</th>
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
                    <td colspan="3" style="text-align:center;">No activities found</td>
                </tr>
            <?php } ?>

        </table>

    </div>

    <!-- RECOMMENDATION -->
    <div class="glass" style="padding:20px; margin-top:20px;">

        <h3>💡 Recommendation</h3>
        <p><?= htmlspecialchars($recommendation) ?></p>

    </div>

</div>

</body>
</html>