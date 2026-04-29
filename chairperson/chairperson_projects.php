<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= SAFE ML LOAD ================= */
$mlFile = "../ml/ml_results.json";
$mlScores = [];

if (file_exists($mlFile)) {
    $json = file_get_contents($mlFile);
    $decoded = json_decode($json, true);

    $mlArray = $decoded['results'] ?? $decoded ?? [];

    foreach ($mlArray as $ml) {

        $title = strtolower(trim($ml['title'] ?? ''));
        $score = floatval($ml['predicted_score'] ?? $ml['score'] ?? 0);

        // LIMIT SCORE TO 100%
        $score = min(100, max(0, $score));

        if ($title !== '') {
            $mlScores[$title] = $score;
        }
    }
}

/* ================= ADD PROJECT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = $_POST['name'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $target = $_POST['target_participants'] ?? 0;
    $activity_id = $_POST['activity_id'] ?? 0;
    $budget = $_POST['budget'] ?? 0;

    if ($name && $activity_id) {

        $stmt = $conn->prepare("
            INSERT INTO projects
            (barangay_id, name, purpose, target_participants, activity_id, budget_allocated, status, treasurer_status)
            VALUES
            (:barangay_id, :name, :purpose, :target, :activity_id, :budget, 'pending', 'pending')
        ");

        $stmt->execute([
            ':barangay_id' => $barangay_id,
            ':name' => $name,
            ':purpose' => $purpose,
            ':target' => $target,
            ':activity_id' => $activity_id,
            ':budget' => $budget
        ]);
    }
}

/* ================= ACTIVITIES (FILTERED) ================= */
$stmt = $conn->prepare("
    SELECT id, title 
    FROM activities
    WHERE barangay_id = :barangay_id
");
$stmt->bindValue(':barangay_id', $barangay_id);
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= PROJECT LIST (FILTERED) ================= */
$stmt = $conn->prepare("
    SELECT p.*, a.title 
    FROM projects p
    LEFT JOIN activities a ON p.activity_id = a.id
    WHERE p.barangay_id = :barangay_id
    ORDER BY p.id DESC
");
$stmt->bindValue(':barangay_id', $barangay_id);
$stmt->execute();
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ML ANALYSIS ================= */
$projectInsights = [];

foreach ($projects as $p) {

    $activityTitle = strtolower(trim($p['title'] ?? ''));

    $mlScore = $mlScores[$activityTitle] ?? 0;

    // fallback partial match
    if ($mlScore == 0) {
        foreach ($mlScores as $key => $value) {
            if (strpos($key, $activityTitle) !== false) {
                $mlScore = $value;
                break;
            }
        }
    }

    $mlScore = min(100, max(0, floatval($mlScore)));

    if ($mlScore >= 70) {
        $status = "Very High Success";
        $suggestion = "Strong candidate for expansion.";
    } elseif ($mlScore >= 40) {
        $status = "Moderate Success";
        $suggestion = "Improve engagement strategy.";
    } else {
        $status = "Low Success";
        $suggestion = "Needs redesign or better planning.";
    }

    $projectInsights[] = [
        'project' => $p['name'],
        'activity' => $p['title'],
        'budget' => $p['budget_allocated'] ?? 0,
        'treasurer' => $p['treasurer_status'] ?? 'pending',
        'score' => $mlScore,
        'ml_status' => $status,
        'suggestion' => $suggestion
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Chairman Projects</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
table{
    width:100%;
    border-collapse:collapse;
    background:white;
    margin-top:20px;
}

th{
    background:#2d89ef;
    color:white;
    padding:10px;
    text-align:center;
}

td{
    padding:10px;
    border-bottom:1px solid #ddd;
    text-align:center;
}

.glass{
    background:rgba(255,255,255,0.2);
    backdrop-filter:blur(500px);
    border-radius:15px;
    padding:20px;
    margin-top:20px;
}
</style>
</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>📁 Projects + Budget Allocation (Chairman)</h2>
</div>

<!-- FORM -->
<div class="glass">
    <h3>Add New Project</h3>

    <form method="POST">

        <input type="text" name="name" placeholder="Project Name" required><br><br>

        <textarea name="purpose" placeholder="Project Purpose"></textarea><br><br>

        <input type="number" name="target_participants" placeholder="Target Participants"><br><br>

        <input type="number" name="budget" placeholder="Budget Allocation (₱)" required><br><br>

        <select name="activity_id" required>
            <option value="">Select Activity</option>

            <?php foreach ($activities as $a) { ?>
                <option value="<?= $a['id']; ?>">
                    <?= htmlspecialchars($a['title']); ?>
                </option>
            <?php } ?>

        </select><br><br>

        <button type="submit">➕ Create Project</button>

    </form>
</div>

<!-- TABLE -->
<div class="glass">
    <h3>📊 Barangay Project Overview</h3>

    <table>
        <tr>
            <th>Project</th>
            <th>Activity</th>
            <th>Budget</th>
            <th>ML Score</th>
            <th>ML Status</th>
            <th>Treasurer Approval</th>
            <th>Recommendation</th>
        </tr>

        <?php if (!empty($projectInsights)) { ?>
            <?php foreach ($projectInsights as $p) { ?>
            <tr>
                <td><?= htmlspecialchars($p['project']) ?></td>
                <td><?= htmlspecialchars($p['activity']) ?></td>
                <td>₱<?= number_format($p['budget'],2) ?></td>
                <td><?= round($p['score'],2) ?>%</td>
                <td><?= $p['ml_status'] ?></td>
                <td><?= ucfirst($p['treasurer']) ?></td>
                <td><?= $p['suggestion'] ?></td>
            </tr>
            <?php } ?>
        <?php } else { ?>
            <tr>
                <td colspan="7">No projects found.</td>
            </tr>
        <?php } ?>

    </table>
</div>

</div>

</body>
</html>