<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

/* ================= SAFE ML LOAD ================= */
$mlFile = "../ml/ml_results.json";
$mlScores = [];

if (file_exists($mlFile)) {

    $json = file_get_contents($mlFile);
    $decoded = json_decode($json, true);

    // SUPPORT BOTH STRUCTURES:
    // 1. ["results" => [...]]
    // 2. direct array [...]

    $mlArray = [];

    if (isset($decoded['results']) && is_array($decoded['results'])) {
        $mlArray = $decoded['results'];
    } elseif (is_array($decoded)) {
        $mlArray = $decoded;
    }

    foreach ($mlArray as $ml) {

        $title = $ml['title'] ?? 'Unknown';
        $score = $ml['score'] ?? $ml['predicted_score'] ?? 0;

        $mlScores[$title] = $score;
    }
}

/* ================= ADD PROJECT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = $_POST['name'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $target = $_POST['target_participants'] ?? 0;
    $activity_id = $_POST['activity_id'] ?? '';

    if ($name && $activity_id) {

        $stmt = $conn->prepare("
            INSERT INTO projects (name, purpose, target_participants, activity_id, status)
            VALUES (:name, :purpose, :target, :activity_id, 'ongoing')
        ");

        $stmt->execute([
            ':name' => $name,
            ':purpose' => $purpose,
            ':target' => $target,
            ':activity_id' => $activity_id
        ]);
    }
}

/* ================= ACTIVITIES ================= */
$stmt = $conn->prepare("SELECT id, title FROM activities");
$stmt->execute();
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= PROJECT LIST ================= */
$stmt = $conn->prepare("
    SELECT p.*, a.title 
    FROM projects p
    LEFT JOIN activities a ON p.activity_id = a.id
    ORDER BY p.id DESC
");
$stmt->execute();
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ML ANALYSIS ================= */
$projectInsights = [];

foreach ($projects as $p) {

    $activityTitle = $p['title'] ?? 'Unknown';
    $mlScore = $mlScores[$activityTitle] ?? 0;

    if ($mlScore >= 70) {
        $status = "Very High Success";
        $suggestion = "Strong candidate for budget expansion.";
    } elseif ($mlScore >= 40) {
        $status = "Moderate Success";
        $suggestion = "Improve promotion and participation.";
    } else {
        $status = "Low Success";
        $suggestion = "Needs redesign or better engagement strategy.";
    }

    $projectInsights[] = [
        'project' => $p['name'],
        'activity' => $activityTitle,
        'score' => $mlScore,
        'status' => $status,
        'suggestion' => $suggestion
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Projects</title>

<link rel="stylesheet" href="../assets/style.css">
<link rel="stylesheet" href="../assets/sbstyle.css">

<style>
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    background: white;
}

th {
    background: #2d89ef;
    color: white;
    padding: 10px;
}

td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}

tr:hover {
    background: #f5f5f5;
}

.glass {
    padding: 20px;
    margin-bottom: 20px;
}
</style>

</head>

<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

<div class="header">
    <h2>📁 Projects with ML Analysis</h2>
</div>

<!-- FORM -->
<div class="glass">
    <h3>Add Project</h3>

    <form method="POST">

        <input type="text" name="name" placeholder="Project Name" required><br><br>

        <textarea name="purpose" placeholder="Project Purpose"></textarea><br><br>

        <input type="number" name="target_participants" placeholder="Target Participants"><br><br>

        <select name="activity_id" required>
            <option value="">Select Activity</option>

            <?php foreach ($activities as $a) { ?>
                <option value="<?= $a['id']; ?>">
                    <?= htmlspecialchars($a['title']); ?>
                </option>
            <?php } ?>

        </select><br><br>

        <button type="submit">➕ Add Project</button>

    </form>
</div>

<!-- TABLE -->
<div class="glass">
    <h3>📊 Project ML Evaluation</h3>

    <table>
        <tr>
            <th>Project</th>
            <th>Activity</th>
            <th>ML Score</th>
            <th>Status</th>
            <th>Recommendation</th>
        </tr>

        <?php if (!empty($projectInsights)) { ?>
            <?php foreach ($projectInsights as $p) { ?>
            <tr>
                <td><?= htmlspecialchars($p['project']) ?></td>
                <td><?= htmlspecialchars($p['activity']) ?></td>
                <td><?= $p['score'] ?>%</td>
                <td><?= $p['status'] ?></td>
                <td><?= $p['suggestion'] ?></td>
            </tr>
            <?php } ?>
        <?php } else { ?>
            <tr>
                <td colspan="5" style="text-align:center;">
                    No projects found
                </td>
            </tr>
        <?php } ?>

    </table>
</div>

</div>

</body>
</html>