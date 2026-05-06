<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

/* FETCH DATA */
$stmt = $pdo->prepare("
    SELECT * FROM monitoring 
    WHERE type = 'yearly' 
    ORDER BY id DESC
");
$stmt->execute();
$records = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Yearly Monitoring</title>

    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: url('../assets/bg.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
        }

        .sidebar {
            width: 240px;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
        }

        .main {
            margin-left: 240px;
            width: calc(100% - 240px);
            padding: 20px;
        }

        .header {
            color:#e8eaf0;
            font-size:22px;
            font-weight:600;
            margin-bottom:18px;
            padding-bottom:10px;
            border-bottom:1px solid rgba(255,255,255,0.08);
        }

        .card {
            background: rgba(255,255,255,0.95);
            border-radius: 10px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        table {
            width: 100%;
            min-width: 700px;
            border-collapse: collapse;
        }

        th {
            background: #1e3c72;
            color: white;
            padding: 12px;
            text-align: left;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        tr:hover {
            background: #f5f8ff;
        }

        .empty {
            text-align: center;
            padding: 20px;
            color: gray;
        }

        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: white;
            display: inline-block;
        }

        .pending { background: #f39c12; }
        .completed { background: #2ecc71; }
        .ongoing { background: #3498db; }

        @media (max-width: 768px) {
            .main { margin-left: 0; width: 100%; }
            .sidebar { position: relative; width: 100%; height: auto; }
        }
    </style>
</head>

<body>

<?php include __DIR__ . '/../assets/sidebar.php'; ?>

<div class="main">

    <div class="header">
        📊 Yearly Monitoring
    </div>

    <div class="card">
        <table>
            <tr>
                <th>Title</th>
                <th>Description</th>
                <th>Status</th>
                <th>Date</th>
            </tr>

            <?php if (count($records) > 0): ?>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td>
                            <span class="status <?= strtolower($row['status']) ?>">
                                <?= htmlspecialchars($row['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="empty">No yearly monitoring records found.</td>
                </tr>
            <?php endif; ?>

        </table>
    </div>

</div>

</body>
</html>