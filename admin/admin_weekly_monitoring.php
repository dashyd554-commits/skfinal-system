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
    WHERE type = 'weekly' 
    ORDER BY id DESC
");
$stmt->execute();
$records = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Weekly Monitoring</title>

    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: url('../assets/bg.jpg') no-repeat center center fixed;
            background-size: cover;
            display: flex;
        }

        /* SIDEBAR FIX */
        .sidebar {
            width: 240px;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
        }

        /* MAIN CONTENT */
        .main {
            margin-left: 240px;
            width: calc(100% - 240px);
            padding: 20px;
        }

        /* HEADER */
        .header {
            color:#e8eaf0;
    font-size:22px;
    font-weight:600;
    margin-bottom:18px;
    padding-bottom:10px;
    border-bottom:1px solid rgba(255,255,255,0.08);
        }

        .header h2 {
            margin: 0;
            color: #1e3c72;
        }

        /* CARD TABLE */
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

        /* STATUS BADGES */
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

        /* MOBILE FIX */
        @media (max-width: 768px) {
            .main {
                margin-left: 0;
                width: 100%;
            }

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<?php include __DIR__ . '/../assets/sidebar.php'; ?>

<!-- MAIN -->
<div class="main">

    <div class="header">
        <h2>📊 Weekly Monitoring</h2>
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
                    <td colspan="4" class="empty">No weekly monitoring records found.</td>
                </tr>
            <?php endif; ?>

        </table>
    </div>

</div>

</body>
</html>