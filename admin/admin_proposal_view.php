<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: ../index.php");
    exit();
}

$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    die("Invalid proposal ID.");
}

/* ================= FETCH PROJECT ================= */
$stmt = $conn->prepare("
    SELECT
        p.id,
        p.name,
        p.purpose,
        p.description,
        p.expected_benefit,
        p.target_participants,
        p.budget_requested,
        p.budget_allocated,
        p.status,
        p.created_at,
        p.vote_yes,
        p.vote_no,
        p.treasurer_status,

        b.barangay_name,

        a.title            AS activity_title,
        a.date             AS activity_date,
        a.participants     AS activity_participants,
        a.allocated_budget AS activity_budget,
        a.evaluation_score,
        a.status           AS activity_status,

        COALESCE(d.total_yes, 0) AS decision_yes,
        COALESCE(d.total_no,  0) AS decision_no,
        d.status                  AS decision_status

    FROM projects p
    LEFT JOIN barangays b         ON p.barangay_id = b.id
    LEFT JOIN activities a        ON p.activity_id = a.id
    LEFT JOIN project_decisions d ON p.id          = d.project_id

    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= COUNT VOTES FROM council_votes ================= */
$vstmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN UPPER(vote) = 'YES' THEN 1 ELSE 0 END) AS yes_count,
        SUM(CASE WHEN UPPER(vote) = 'NO'  THEN 1 ELSE 0 END) AS no_count,
        COUNT(*) AS total_count
    FROM council_votes
    WHERE project_id = ?
");
$vstmt->execute([$id]);
$voteRow = $vstmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    die("Proposal not found.");
}

/* ================= FETCH TRANSACTIONS ================= */
$stmt2 = $conn->prepare("
    SELECT amount, description, created_at
    FROM budget_transactions
    WHERE project_id = ?
    ORDER BY created_at DESC
");
$stmt2->execute([$id]);
$transactions = $stmt2->fetchAll(PDO::FETCH_ASSOC);

/* ================= HELPERS ================= */
function safe($v) {
    return htmlspecialchars($v ?? '—');
}

function money($v) {
    return '₱' . number_format((float)($v ?? 0), 2);
}

function statusBadge($status) {
    $s = strtolower($status ?? '');
    $map = [
        'approved' => ['#50c878', '#0d2b1a'],
        'rejected' => ['#e07a7a', '#2b0d0d'],
        'pending'  => ['#ffd166', '#2b2200'],
        'ongoing'  => ['#5b8af5', '#0d1a2b'],
        'completed'=> ['#a78bfa', '#1a0d2b'],
    ];
    [$bg, $text] = $map[$s] ?? ['#8b93a7', '#1a1f2e'];
    return "<span style='background:".htmlspecialchars($bg).";color:".htmlspecialchars($text).";padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;text-transform:uppercase;'>".safe($status)."</span>";
}

/* vote totals — priority: council_votes → project_decisions → projects columns */
$yesVotes = (int)($voteRow['yes_count'] ?? 0)
          ?: (int)($p['decision_yes']   ?? 0)
          ?: (int)($p['vote_yes']       ?? 0);

$noVotes  = (int)($voteRow['no_count']  ?? 0)
          ?: (int)($p['decision_no']    ?? 0)
          ?: (int)($p['vote_no']        ?? 0);
$totalVotes = $yesVotes + $noVotes;
$yesPct = $totalVotes > 0 ? round($yesVotes / $totalVotes * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proposal Report — <?= safe($p['name']) ?></title>

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: #0f1117;
    color: #c5cad8;
    min-height: 100vh;
    padding: 30px 20px;
}

.paper {
    max-width: 900px;
    margin: 0 auto;
}

/* ── Top bar ── */
.top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}

.top-bar h1 {
    font-size: 20px;
    font-weight: 700;
    color: #ffffff;
}

.top-bar p {
    font-size: 13px;
    color: #8b93a7;
    margin-top: 3px;
}

.top-actions { display: flex; gap: 10px; flex-wrap: wrap; }

.btn {
    padding: 9px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: opacity 0.15s, transform 0.1s;
}

.btn:hover { opacity: 0.85; transform: translateY(-1px); }
.btn-back  { background: rgba(255,255,255,0.08); color: #c5cad8; border: 1px solid rgba(255,255,255,0.12); }
.btn-print { background: #5b8af5; color: #fff; }

/* ── Grid ── */
.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

/* ── Glass card ── */
.card {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 14px;
    padding: 20px 22px;
    margin-bottom: 16px;
}

.card-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.09em;
    color: #8b93a7;
    margin-bottom: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    gap: 7px;
}

/* ── Info rows ── */
.info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 9px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    gap: 12px;
    font-size: 13.5px;
}

.info-row:last-child { border-bottom: none; }

.info-label {
    color: #8b93a7;
    font-weight: 500;
    white-space: nowrap;
    flex-shrink: 0;
}

.info-value {
    color: #ffffff;
    font-weight: 500;
    text-align: right;
    word-break: break-word;
}

/* ── KPI pills ── */
.kpi-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}

.kpi {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 12px;
    padding: 16px;
    text-align: center;
}

.kpi-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #8b93a7;
    margin-bottom: 6px;
}

.kpi-value {
    font-size: 22px;
    font-weight: 700;
    color: #ffffff;
}

.kpi-sub {
    font-size: 11px;
    color: #8b93a7;
    margin-top: 2px;
}

/* ── Vote bar ── */
.vote-bar-wrap { margin-top: 14px; }
.vote-bar-labels {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #8b93a7;
    margin-bottom: 6px;
}
.vote-bar-track {
    height: 10px;
    border-radius: 5px;
    background: rgba(224,122,122,0.3);
    overflow: hidden;
}
.vote-bar-fill {
    height: 100%;
    border-radius: 5px;
    background: #50c878;
    transition: width 0.5s ease;
}

/* ── Table ── */
.table-wrap { overflow-x: auto; }

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 440px;
}

thead th {
    padding: 10px 14px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255,255,255,0.4);
    border-bottom: 1px solid rgba(255,255,255,0.07);
    background: rgba(255,255,255,0.04);
}

tbody td {
    padding: 11px 14px;
    font-size: 13px;
    color: rgba(255,255,255,0.85);
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,0.03); }

.no-data {
    text-align: center;
    padding: 28px;
    color: rgba(255,255,255,0.3);
    font-size: 13px;
}

/* ── Footer ── */
.footer {
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,0.2);
    margin-top: 28px;
    padding-top: 16px;
    border-top: 1px solid rgba(255,255,255,0.06);
}

/* ── Print ── */
@media print {
    body { background: white; color: #1a1a2e; padding: 0; }
    .top-actions, .btn { display: none !important; }
    .card { border: 1px solid #ddd; background: white; }
    .card-title, .info-label, .kpi-label { color: #666; }
    .info-value, .kpi-value { color: #000; }
    thead th { background: #1e3c72; color: white; }
}

/* ── Responsive ── */
@media (max-width: 680px) {
    .grid-2 { grid-template-columns: 1fr; }
    .kpi-row { grid-template-columns: repeat(2, 1fr); }
    .top-bar h1 { font-size: 17px; }
}
</style>
</head>
<body>

<div class="paper">

    <!-- Top Bar -->
    <div class="top-bar">
        <div>
            <h1>📄 <?= safe($p['name']) ?></h1>
            <p>Barangay <?= safe($p['barangay_name']) ?>
                &nbsp;·&nbsp;
                Submitted <?= $p['created_at'] ? date('M d, Y', strtotime($p['created_at'])) : '—' ?>
            </p>
        </div>
        <div class="top-actions">
            <a href="javascript:history.back()" class="btn btn-back">⬅ Back</a>
            <a href="#" onclick="window.print()" class="btn btn-print">🖨 Print</a>
        </div>
    </div>

    <!-- KPI Pills -->
    <div class="kpi-row">
        <div class="kpi">
            <div class="kpi-label">Budget Requested</div>
            <div class="kpi-value" style="font-size:17px;"><?= money($p['budget_requested']) ?></div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Budget Allocated</div>
            <div class="kpi-value" style="font-size:17px;"><?= money($p['budget_allocated']) ?></div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Project Status</div>
            <div class="kpi-value" style="font-size:14px;margin-top:4px;"><?= statusBadge($p['status']) ?></div>
        </div>
    </div>

    <!-- Project Details + Activity Info -->
    <div class="grid-2">

        <!-- Project Details -->
        <div class="card">
            <div class="card-title">📋 Project Details</div>
            <div class="info-row">
                <span class="info-label">Project Name</span>
                <span class="info-value"><?= safe($p['name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Purpose</span>
                <span class="info-value"><?= safe($p['purpose']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Description</span>
                <span class="info-value"><?= safe($p['description']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Expected Benefit</span>
                <span class="info-value"><?= safe($p['expected_benefit']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Target Participants</span>
                <span class="info-value"><?= safe($p['target_participants']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Treasurer Status</span>
                <span class="info-value"><?= statusBadge($p['treasurer_status'] ?? 'pending') ?></span>
            </div>
        </div>

        <!-- Activity Info -->
        <div class="card">
            <div class="card-title">🗓️ Related Activity</div>
            <?php if ($p['activity_title']): ?>
            <div class="info-row">
                <span class="info-label">Activity Title</span>
                <span class="info-value"><?= safe($p['activity_title']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Activity Date</span>
                <span class="info-value">
                    <?= $p['activity_date'] ? date('F d, Y', strtotime($p['activity_date'])) : '—' ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Participants</span>
                <span class="info-value"><?= safe($p['activity_participants']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Activity Budget</span>
                <span class="info-value"><?= money($p['activity_budget']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Evaluation Score</span>
                <span class="info-value">
                    <?= $p['evaluation_score'] ? $p['evaluation_score'] . '%' : '—' ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Activity Status</span>
                <span class="info-value"><?= statusBadge($p['activity_status'] ?? 'N/A') ?></span>
            </div>
            <?php else: ?>
            <div class="no-data">No linked activity found.</div>
            <?php endif; ?>
        </div>

    </div>

    <!-- DEBUG: remove after confirming votes work -->
    <?php if (isset($_GET['debug'])): ?>
    <div class="card" style="border-color:rgba(255,210,50,0.3);background:rgba(255,210,50,0.05);">
        <div class="card-title" style="color:#ffd166;">🔍 Debug — Raw Vote Data</div>
        <div style="font-size:12px;color:#ffd166;line-height:2;">
            <b>council_votes table:</b>
            YES=<?= (int)($voteRow['yes_count']??0) ?>,
            NO=<?= (int)($voteRow['no_count']??0) ?>,
            TOTAL=<?= (int)($voteRow['total_count']??0) ?>
            <br>
            <b>project_decisions table:</b>
            YES=<?= (int)($p['decision_yes']??0) ?>,
            NO=<?= (int)($p['decision_no']??0) ?>
            <br>
            <b>projects columns:</b>
            vote_yes=<?= (int)($p['vote_yes']??0) ?>,
            vote_no=<?= (int)($p['vote_no']??0) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Voting Results -->
    <div class="card">
        <div class="card-title">🗳️ Council Voting Results</div>

        <div class="kpi-row" style="margin-bottom:0;">
            <div class="kpi">
                <div class="kpi-label">✅ YES Votes</div>
                <div class="kpi-value" style="color:#50c878;"><?= $yesVotes ?></div>
            </div>
            <div class="kpi">
                <div class="kpi-label">❌ NO Votes</div>
                <div class="kpi-value" style="color:#e07a7a;"><?= $noVotes ?></div>
            </div>
            <div class="kpi">
                <div class="kpi-label">Decision</div>
                <div class="kpi-value" style="font-size:14px;margin-top:4px;">
                    <?= statusBadge($p['decision_status'] ?? $p['status']) ?>
                </div>
            </div>
        </div>

        <?php if ($totalVotes > 0): ?>
        <div class="vote-bar-wrap">
            <div class="vote-bar-labels">
                <span>YES <?= $yesPct ?>%</span>
                <span>NO <?= 100 - $yesPct ?>%</span>
            </div>
            <div class="vote-bar-track">
                <div class="vote-bar-fill" style="width:<?= $yesPct ?>%;"></div>
            </div>
        </div>
        <?php else: ?>
        <p style="font-size:13px;color:#8b93a7;margin-top:14px;">No votes recorded yet.</p>
        <?php endif; ?>
    </div>

    <!-- Budget Transactions -->
    <div class="card">
        <div class="card-title">💰 Budget Transactions
            <span style="margin-left:auto;font-weight:400;color:rgba(255,255,255,0.3);font-size:11px;text-transform:none;letter-spacing:0;">
                <?= count($transactions) ?> record<?= count($transactions) !== 1 ? 's' : '' ?>
            </span>
        </div>

        <div class="table-wrap">
            <?php if (!empty($transactions)): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $runningTotal = 0;
                foreach ($transactions as $i => $t):
                    $runningTotal += (float)$t['amount'];
                ?>
                <tr>
                    <td style="color:rgba(255,255,255,0.3);font-size:12px;"><?= $i + 1 ?></td>
                    <td style="color:#ffd166;font-weight:600;"><?= money($t['amount']) ?></td>
                    <td><?= safe($t['description']) ?></td>
                    <td style="color:#8b93a7;font-size:12px;">
                        <?= $t['created_at'] ? date('M d, Y g:i A', strtotime($t['created_at'])) : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="1" style="padding:10px 14px;color:#8b93a7;font-size:12px;">Total</td>
                        <td style="padding:10px 14px;color:#50c878;font-weight:700;font-size:14px;">
                            <?= money($runningTotal) ?>
                        </td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
            <?php else: ?>
            <div class="no-data">
                <div style="font-size:28px;margin-bottom:8px;">💸</div>
                No budget transactions recorded yet.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        © 2026 SK Decision Support System &nbsp;|&nbsp; Barangay <?= safe($p['barangay_name']) ?>
    </div>

</div><!-- /.paper -->

</body>
</html>