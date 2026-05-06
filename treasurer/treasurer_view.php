<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'treasurer') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

if (!isset($_GET['id'])) {
    header("Location: treasurer_pending.php");
    exit();
}

$project_id = (int)$_GET['id'];

/* ================= LOAD PROJECT ================= */
$stmt = $conn->prepare("
    SELECT p.*, b.barangay_name, a.title AS activity_title, a.date AS activity_date
    FROM projects p
    LEFT JOIN barangays b ON p.barangay_id = b.id
    LEFT JOIN activities a ON p.activity_id = a.id
    WHERE p.id = ? AND p.barangay_id = ?
");
$stmt->execute([$project_id, $barangay_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) { die("Project not found."); }

/* ================= BUDGET ================= */
$stmt = $conn->prepare("SELECT * FROM budgets WHERE barangay_id = ? ORDER BY year DESC LIMIT 1");
$stmt->execute([$barangay_id]);
$budget = $stmt->fetch(PDO::FETCH_ASSOC);

$totalBudget     = (float)($budget['total_amount'] ?? 0);
$usedBudget      = (float)($budget['used_amount']  ?? 0);
$remainingBudget = $totalBudget - $usedBudget;
$budgetRequested = (float)($project['budget_requested'] ?? 0);
$usedPct         = $totalBudget > 0 ? round($usedBudget / $totalBudget * 100, 1) : 0;
$canAfford       = ($remainingBudget - $budgetRequested) >= -0.001;

$isPending = in_array($project['status'], ['pending', 'pending_treasurer']);

$error = "";

/* ================= ML BUDGET RECOMMENDATION ================= */
$mlStmt = $conn->prepare("
    SELECT p.name, p.purpose, p.target_participants,
           p.budget_allocated, p.budget_requested,
           a.participants AS actual_participants,
           a.evaluation_score
    FROM projects p
    LEFT JOIN activities a ON p.activity_id = a.id
    WHERE p.barangay_id = ? AND p.status = 'approved'
      AND p.budget_allocated > 0
    ORDER BY p.id DESC LIMIT 50
");
$mlStmt->execute([$barangay_id]);
$historicalProjects = $mlStmt->fetchAll(PDO::FETCH_ASSOC);

function keywordScore(string $text, string $ref): float {
    $stop = ['ang','ng','sa','at','na','para','the','of','and','for','a','in','to','is','with'];
    $norm = fn($s) => array_diff(
        preg_split('/\s+/', strtolower(preg_replace('/[^a-z0-9\s]/i', ' ', $s))),
        $stop
    );
    $wA = $norm($text); $wB = $norm($ref);
    if (empty($wA) || empty($wB)) return 0;
    return count(array_intersect($wA, $wB)) / max(count($wA), count($wB));
}

$currentText = ($project['name'] ?? '') . ' ' . ($project['purpose'] ?? '');
$currentPax  = max(1, (int)($project['target_participants'] ?? 1));
$weightedSum = 0; $totalWeight = 0; $similarProjects = [];

foreach ($historicalProjects as $h) {
    $sim     = keywordScore($currentText, ($h['name'] ?? '') . ' ' . ($h['purpose'] ?? ''));
    $histPax = max(1, (int)($h['target_participants'] ?? $h['actual_participants'] ?? 1));
    $paxR    = min($currentPax, $histPax) / max($currentPax, $histPax);
    $evalB   = isset($h['evaluation_score']) ? ($h['evaluation_score'] / 100) : 0.5;
    $weight  = ($sim * 0.5) + ($paxR * 0.35) + ($evalB * 0.15);
    if ($weight > 0.10) {
        $bpp = $h['budget_allocated'] / max(1, $histPax);
        $weightedSum += $bpp * $weight;
        $totalWeight += $weight;
        $similarProjects[] = [
            'name'       => $h['name'],
            'budget'     => $h['budget_allocated'],
            'pax'        => $histPax,
            'similarity' => round($sim * 100),
            'weight'     => round($weight * 100),
        ];
    }
}

$ml = [];
if ($totalWeight > 0 && count($similarProjects) >= 1) {
    $avgBPP      = $weightedSum / $totalWeight;
    $recommended = round($avgBPP * $currentPax / 500) * 500;
    $sampleBonus = min(1, count($similarProjects) / 10);
    $avgWeight   = $totalWeight / count($similarProjects);
    $confidence  = max(20, min(95, round(($avgWeight * 0.7 + $sampleBonus * 0.3) * 100)));
    $diffAmt     = $recommended - $budgetRequested;
    $diffPct     = $budgetRequested > 0 ? round(abs($diffAmt) / $budgetRequested * 100, 1) : 0;
    $ml = [
        'recommended' => $recommended,
        'range_low'   => round($recommended * 0.85 / 500) * 500,
        'range_high'  => round($recommended * 1.15 / 500) * 500,
        'confidence'  => $confidence,
        'diff'        => $diffAmt,
        'diff_pct'    => $diffPct,
        'sample_size' => count($similarProjects),
        'similar'     => array_slice($similarProjects, 0, 5),
        'per_pax'     => round($avgBPP, 2),
    ];
} else {
    // Fallback: barangay-wide average cost per participant
    $fallStmt = $conn->prepare("
        SELECT AVG(p.budget_allocated / GREATEST(p.target_participants, 1)) AS avg_per_pax
        FROM projects p
        WHERE p.barangay_id = ? AND p.status = 'approved' AND p.budget_allocated > 0
    ");
    $fallStmt->execute([$barangay_id]);
    $fallRow   = $fallStmt->fetch(PDO::FETCH_ASSOC);
    $avgPerPax = (float)($fallRow['avg_per_pax'] ?? 0);
    if ($avgPerPax > 0) {
        $recommended = round($avgPerPax * $currentPax / 500) * 500;
        $diffAmt     = $recommended - $budgetRequested;
        $diffPct     = $budgetRequested > 0 ? round(abs($diffAmt) / $budgetRequested * 100, 1) : 0;
        $ml = [
            'recommended' => $recommended,
            'range_low'   => round($recommended * 0.80 / 500) * 500,
            'range_high'  => round($recommended * 1.20 / 500) * 500,
            'confidence'  => 25,
            'diff'        => $diffAmt,
            'diff_pct'    => $diffPct,
            'sample_size' => 0,
            'similar'     => [],
            'per_pax'     => round($avgPerPax, 2),
            'fallback'    => true,
        ];
    }
}

/* ================= APPROVE ================= */
if (isset($_POST['approve'])) {
    if ($canAfford) {
        $newUsed   = $usedBudget + $budgetRequested;
        $newRemain = $totalBudget - $newUsed;
        $conn->prepare("UPDATE budgets SET used_amount=?, remaining_budget=? WHERE id=?")->execute([$newUsed, $newRemain, $budget['id']]);
        $conn->prepare("UPDATE projects SET status='approved', budget_allocated=? WHERE id=?")->execute([$budgetRequested, $project_id]);
        $conn->prepare("INSERT INTO budget_transactions (barangay_id,project_id,amount,description) VALUES (?,?,?,?)")->execute([$barangay_id, $project_id, $budgetRequested, "Approved: " . $project['name']]);
        header("Location: treasurer_pending.php?success=1");
        exit();
    } else {
        $error = "Insufficient remaining budget. You need ₱" . number_format($budgetRequested, 2) . " but only ₱" . number_format($remainingBudget, 2) . " is available.";
    }
}

/* ================= REJECT ================= */
if (isset($_POST['reject'])) {
    $conn->prepare("UPDATE projects SET status='rejected' WHERE id=?")->execute([$project_id]);
    header("Location: treasurer_pending.php?rejected=1");
    exit();
}

function safe($v) { return htmlspecialchars($v ?? '—'); }
function money($v) { return '₱' . number_format((float)($v ?? 0), 2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Proposal Review — <?= safe($project['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Sora', 'Segoe UI', sans-serif;
    background: #0d1b2a;
    color: #e2e8f0;
    min-height: 100vh;
    background-image:
        radial-gradient(ellipse 80% 50% at 20% -10%, rgba(56,189,248,0.12) 0%, transparent 60%),
        radial-gradient(ellipse 60% 40% at 80% 100%, rgba(245,158,11,0.08) 0%, transparent 55%);
    background-attachment: fixed;
    overflow-y: auto;
}

.sidebar {
    position: fixed !important;
    top: 0 !important; left: 0 !important;
    height: 100vh !important;
    width: 240px !important;
    overflow-y: auto;
    z-index: 1000;
}

.main {
    margin-left: 240px;
    width: calc(100% - 240px);
    padding: 28px 24px 40px;
    min-height: 100vh;
}

/* Top bar */
.topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.topbar h2 { font-size:clamp(1.1rem,2.5vw,1.5rem); font-weight:700; color:#fff; }
.topbar p  { font-size:0.78rem; color:#64748b; margin-top:3px; }
.topbar-actions { display:flex; gap:10px; flex-wrap:wrap; }

.btn-back, .btn-print {
    padding:9px 18px; border-radius:9px; font-size:0.82rem; font-weight:600;
    font-family:'Sora',sans-serif; cursor:pointer;
    display:inline-flex; align-items:center; gap:6px;
    transition:opacity .15s, transform .15s;
    text-decoration:none; white-space:nowrap; border:none;
}
.btn-back  { background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); color:rgba(255,255,255,0.8); }
.btn-print { background:linear-gradient(135deg,#38bdf8,#0ea5e9); color:#0d1b2a; }
.btn-back:hover, .btn-print:hover { opacity:.85; transform:translateY(-1px); }

/* Alert */
.alert { border-radius:10px; padding:13px 16px; font-size:0.83rem; margin-bottom:20px; display:flex; align-items:flex-start; gap:10px; font-weight:500; line-height:1.5; }
.alert-error   { background:rgba(239,68,68,0.12);  border:1px solid rgba(239,68,68,0.3);  color:#fca5a5; }
.alert-warning { background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.3); color:#fcd34d; }

/* Grid */
.content-grid { display:grid; grid-template-columns:1fr 340px; gap:18px; align-items:start; }

/* Glass panel */
.glass {
    background:rgba(255,255,255,0.06);
    backdrop-filter:blur(18px); -webkit-backdrop-filter:blur(18px);
    border:1px solid rgba(255,255,255,0.11);
    border-radius:16px; padding:22px; margin-bottom:16px;
}
.glass:last-child { margin-bottom:0; }
.glass-title {
    font-size:0.7rem; font-weight:700; color:#94a3b8;
    margin-bottom:18px; padding-bottom:12px;
    border-bottom:1px solid rgba(255,255,255,0.07);
    display:flex; align-items:center; gap:8px;
    text-transform:uppercase; letter-spacing:0.08em;
}

/* Info rows */
.info-row { display:flex; justify-content:space-between; align-items:flex-start; padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05); gap:16px; font-size:0.85rem; }
.info-row:last-child { border-bottom:none; }
.info-label { color:#64748b; font-weight:500; flex-shrink:0; }
.info-value { color:#e2e8f0; font-weight:500; text-align:right; word-break:break-word; max-width:65%; }

/* Desc */
.desc-label { font-size:0.68rem; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#475569; margin-bottom:8px; }
.desc-block { background:rgba(255,255,255,0.04); border-radius:10px; padding:14px 16px; font-size:0.83rem; color:#94a3b8; line-height:1.7; margin-top:4px; }

/* Status badge */
.status-badge { display:inline-block; padding:3px 12px; border-radius:20px; font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; }
.status-pending  { background:rgba(245,158,11,0.15); color:#fcd34d; border:1px solid rgba(245,158,11,0.3); }
.status-approved { background:rgba(74,222,128,0.15); color:#4ade80; border:1px solid rgba(74,222,128,0.3); }
.status-rejected { background:rgba(239,68,68,0.15);  color:#f87171; border:1px solid rgba(239,68,68,0.3); }
.status-ongoing  { background:rgba(56,189,248,0.15);  color:#38bdf8; border:1px solid rgba(56,189,248,0.3); }

/* KPI mini */
.kpi-stack { display:flex; flex-direction:column; gap:10px; margin-bottom:16px; }
.kpi-mini { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:14px 16px; display:flex; align-items:center; gap:14px; }
.kpi-mini-icon  { font-size:20px; flex-shrink:0; }
.kpi-mini-label { font-size:0.65rem; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:0.07em; margin-bottom:3px; }
.kpi-mini-value { font-size:1.1rem; font-weight:700; font-family:'DM Mono',monospace; color:#e2e8f0; }

/* Bar */
.bar-label { display:flex; justify-content:space-between; font-size:0.7rem; color:#475569; margin-bottom:6px; }
.bar-track { height:8px; border-radius:4px; background:rgba(255,255,255,0.07); overflow:hidden; }
.bar-fill  { height:100%; border-radius:4px; transition:width .4s ease; }

/* Afford */
.afford-box { border-radius:10px; padding:13px 16px; font-size:0.83rem; font-weight:600; display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.afford-ok { background:rgba(74,222,128,0.1);  border:1px solid rgba(74,222,128,0.25); color:#4ade80; }
.afford-no { background:rgba(239,68,68,0.1);   border:1px solid rgba(239,68,68,0.25);  color:#f87171; }

/* ML card */
.ml-glass {
    background:rgba(167,139,250,0.05);
    border:1px solid rgba(167,139,250,0.2);
    border-radius:16px; padding:22px; margin-bottom:16px;
}

/* Action card */
.action-card { background:rgba(255,255,255,0.06); backdrop-filter:blur(18px); border:1px solid rgba(255,255,255,0.11); border-radius:16px; padding:22px; }
.action-title { font-size:0.7rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:16px; }

.btn-approve, .btn-reject {
    width:100%; padding:13px; border:none; border-radius:10px;
    font-size:0.9rem; font-weight:700; font-family:'Sora',sans-serif;
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    gap:8px; transition:transform .1s, opacity .15s; margin-bottom:10px;
}
.btn-approve { background:linear-gradient(135deg,#16a34a,#22c55e); color:#fff; box-shadow:0 4px 18px rgba(34,197,94,0.3); }
.btn-approve:hover:not(:disabled) { transform:translateY(-2px); opacity:.92; }
.btn-approve:disabled { background:rgba(255,255,255,0.07); color:rgba(255,255,255,0.25); cursor:not-allowed; box-shadow:none; }
.btn-reject { background:rgba(239,68,68,0.12); color:#f87171; border:1px solid rgba(239,68,68,0.3); }
.btn-reject:hover { transform:translateY(-2px); background:rgba(239,68,68,0.2); }
.action-note { font-size:0.72rem; color:#475569; text-align:center; line-height:1.5; margin-top:10px; }

/* Print styles */
@media print {
    .sidebar, .hamburger, .mob-overlay, .topbar-actions,
    .action-card, .afford-box, .pg-footer { display:none !important; }
    body { background:#fff !important; color:#1a1a1a !important; background-image:none !important; }
    .main { margin-left:0 !important; width:100% !important; padding:20px !important; }
    .print-header { display:block !important; text-align:center; margin-bottom:24px; padding-bottom:16px; border-bottom:2px solid #1e3a5f; }
    .print-header h1 { font-size:20px; color:#1e3a5f; }
    .print-header p  { font-size:12px; color:#555; }
    .content-grid { grid-template-columns:1fr !important; }
    .glass, .ml-glass {
        background:#fff !important; border:1px solid #dce3ec !important;
        backdrop-filter:none !important; border-radius:8px !important;
        padding:16px !important; margin-bottom:12px !important;
        break-inside:avoid; box-shadow:none !important;
    }
    .glass-title { color:#1e3a5f !important; border-color:#dce3ec !important; }
    .info-label { color:#555 !important; } .info-value { color:#111 !important; }
    .info-row { border-color:#eee !important; }
    .desc-block { background:#f5f7fa !important; color:#333 !important; }
    .kpi-mini { background:#f5f7fa !important; border:1px solid #dce3ec !important; }
    .kpi-mini-label { color:#555 !important; } .kpi-mini-value { color:#1e3a5f !important; }
    .bar-track { background:#e2e8f0 !important; }
    .print-signatures { display:grid !important; grid-template-columns:1fr 1fr 1fr; gap:20px; margin-top:40px; padding-top:20px; border-top:1px solid #dce3ec; }
    .sig-block { text-align:center; }
    .sig-line  { border-top:1px solid #333; margin:40px 10px 6px; }
    .sig-name  { font-size:11px; font-weight:700; color:#1e3a5f; }
    .sig-role  { font-size:10px; color:#555; }
    .print-meta { display:flex !important; justify-content:space-between; font-size:10px; color:#888; margin-top:16px; padding-top:10px; border-top:1px solid #eee; }
}
.print-header, .print-signatures, .print-meta { display:none; }

/* Hamburger */
.hamburger { display:none; position:fixed; top:14px; left:14px; z-index:1100; background:#1b2d42; border:1px solid rgba(255,255,255,0.12); border-radius:8px; width:38px; height:38px; cursor:pointer; flex-direction:column; align-items:center; justify-content:center; gap:5px; }
.hamburger span { display:block; width:18px; height:2px; background:#c5cad8; border-radius:2px; }
.mob-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:999; }

.pg-footer { text-align:center; font-size:0.72rem; color:#334155; margin-top:24px; }

@media (max-width:960px) { .content-grid { grid-template-columns:1fr; } }
@media (max-width:768px) {
    .sidebar { left:-260px !important; transition:left .25s ease; }
    .sidebar.open { left:0 !important; }
    .mob-overlay.open { display:block; }
    .hamburger { display:flex; }
    .main { margin-left:0; width:100%; padding:64px 14px 24px; }
}
</style>
</head>
<body>

<button class="hamburger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
<div class="mob-overlay" id="mobOverlay" onclick="toggleSidebar()"></div>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <!-- Print header -->
    <div class="print-header">
        <h1>SK Project Proposal Review</h1>
        <p><?= safe($project['barangay_name']) ?> &nbsp;|&nbsp; SK Decision Support System</p>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <div>
            <h2>📄 Proposal Review</h2>
            <p>Review and take action on this project proposal</p>
        </div>
        <div class="topbar-actions">
            <button class="btn-print" onclick="window.print()">🖨️ Print Proposal</button>
            <a href="treasurer_pending.php" class="btn-back">⬅ Back to Pending</a>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$isPending): ?>
    <div class="alert alert-warning">
        ℹ️ This proposal has already been <b><?= strtoupper(str_replace('_',' ',$project['status'])) ?></b>. No further action can be taken.
    </div>
    <?php endif; ?>

    <div class="content-grid">

        <!-- LEFT: Project Details -->
        <div>
            <div class="glass">
                <div class="glass-title">📋 Project Information</div>
                <div class="info-row">
                    <span class="info-label">Project Name</span>
                    <span class="info-value"><b><?= safe($project['name']) ?></b></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="status-badge status-<?= in_array($project['status'],['pending','pending_treasurer']) ? 'pending' : strtolower($project['status']??'pending') ?>">
                            <?= strtoupper(str_replace('_',' ',$project['status'] ?? 'Pending')) ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Budget Requested</span>
                    <span class="info-value" style="color:#fcd34d;font-size:1rem;font-weight:700;font-family:'DM Mono',monospace;">
                        <?= money($budgetRequested) ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Target Participants</span>
                    <span class="info-value"><?= safe($project['target_participants']) ?> people</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Barangay</span>
                    <span class="info-value"><?= safe($project['barangay_name']) ?></span>
                </div>
                <?php if ($project['activity_title']): ?>
                <div class="info-row">
                    <span class="info-label">Related Activity</span>
                    <span class="info-value"><?= safe($project['activity_title']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Activity Date</span>
                    <span class="info-value"><?= $project['activity_date'] ? date('F d, Y', strtotime($project['activity_date'])) : '—' ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="glass">
                <div class="glass-title">📝 Purpose & Description</div>
                <div style="margin-bottom:14px;">
                    <div class="desc-label">Purpose</div>
                    <div class="desc-block"><?= safe($project['purpose']) ?></div>
                </div>
                <?php if (!empty($project['description'])): ?>
                <div style="margin-bottom:14px;">
                    <div class="desc-label">Description</div>
                    <div class="desc-block"><?= safe($project['description']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($project['expected_benefit'])): ?>
                <div>
                    <div class="desc-label">Expected Benefit</div>
                    <div class="desc-block"><?= safe($project['expected_benefit']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: Budget + ML + Actions -->
        <div>

            <!-- Budget Overview -->
            <div class="glass">
                <div class="glass-title">💰 Budget Overview</div>
                <div class="kpi-stack">
                    <div class="kpi-mini">
                        <div class="kpi-mini-icon">💼</div>
                        <div>
                            <div class="kpi-mini-label">Total Annual Budget</div>
                            <div class="kpi-mini-value"><?= money($totalBudget) ?></div>
                        </div>
                    </div>
                    <div class="kpi-mini">
                        <div class="kpi-mini-icon">💸</div>
                        <div>
                            <div class="kpi-mini-label">Already Used</div>
                            <div class="kpi-mini-value" style="color:#f87171;"><?= money($usedBudget) ?></div>
                        </div>
                    </div>
                    <div class="kpi-mini">
                        <div class="kpi-mini-icon">💰</div>
                        <div>
                            <div class="kpi-mini-label">Remaining</div>
                            <div class="kpi-mini-value" style="color:#4ade80;"><?= money($remainingBudget) ?></div>
                        </div>
                    </div>
                    <div class="kpi-mini" style="border-color:rgba(252,211,77,0.3);background:rgba(252,211,77,0.05);">
                        <div class="kpi-mini-icon">📌</div>
                        <div>
                            <div class="kpi-mini-label">This Request</div>
                            <div class="kpi-mini-value" style="color:#fcd34d;"><?= money($budgetRequested) ?></div>
                        </div>
                    </div>
                </div>
                <div class="bar-label">
                    <span>Used <?= $usedPct ?>%</span>
                    <span>Available <?= round(100 - $usedPct, 1) ?>%</span>
                </div>
                <div class="bar-track">
                    <div class="bar-fill" style="width:<?= min($usedPct,100) ?>%;background:<?= $usedPct>85?'#ef4444':($usedPct>60?'#f59e0b':'#22c55e') ?>;"></div>
                </div>
            </div>

            <!-- Affordability -->
            <div class="afford-box <?= $canAfford ? 'afford-ok' : 'afford-no' ?>">
                <?= $canAfford
                    ? '✅ Remaining budget is sufficient for this proposal'
                    : '❌ Insufficient — short by ' . money($budgetRequested - $remainingBudget) ?>
            </div>

            <!-- ── ML Budget Recommendation ── -->
            <?php if (!empty($ml)): ?>
            <div class="ml-glass">
                <div class="glass-title" style="color:#a78bfa;border-color:rgba(167,139,250,0.15);">
                    🤖 AI Budget Recommendation
                    <span style="margin-left:auto;font-size:0.62rem;font-weight:400;letter-spacing:0;text-transform:none;color:rgba(167,139,250,0.5);">
                        For reference only
                    </span>
                </div>

                <!-- Suggested amount — big and clear -->
                <div style="text-align:center;padding:18px 0 16px;">
                    <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:rgba(167,139,250,0.55);margin-bottom:8px;">
                        💡 Suggested Budget for This Proposal
                    </div>
                    <div style="font-size:2.2rem;font-weight:800;color:#a78bfa;line-height:1;font-family:'DM Mono',monospace;">
                        ₱<?= number_format($ml['recommended'], 2) ?>
                    </div>
                    <div style="font-size:0.72rem;color:#475569;margin-top:6px;">
                        Based on <?= $ml['sample_size'] > 0 ? $ml['sample_size'].' similar approved project'.($ml['sample_size']!=1?'s':'') : 'barangay-wide average' ?>
                        <?= isset($ml['fallback']) ? ' (fallback mode)' : '' ?>
                    </div>
                </div>

                <!-- Requested vs Suggested -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                    <div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:12px;text-align:center;">
                        <div style="font-size:0.62rem;color:#475569;font-weight:600;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:4px;">Requested</div>
                        <div style="font-size:1rem;font-weight:700;color:#fcd34d;font-family:'DM Mono',monospace;">₱<?= number_format($budgetRequested, 2) ?></div>
                    </div>
                    <div style="background:rgba(167,139,250,0.08);border:1px solid rgba(167,139,250,0.2);border-radius:10px;padding:12px;text-align:center;">
                        <div style="font-size:0.62rem;color:rgba(167,139,250,0.6);font-weight:600;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:4px;">ML Suggested</div>
                        <div style="font-size:1rem;font-weight:700;color:#a78bfa;font-family:'DM Mono',monospace;">₱<?= number_format($ml['recommended'], 2) ?></div>
                    </div>
                </div>

                <!-- Difference note -->
                <div style="background:rgba(255,255,255,0.03);border-radius:9px;padding:11px 14px;font-size:0.78rem;color:#475569;margin-bottom:14px;text-align:center;line-height:1.5;">
                    <?php if (abs($ml['diff']) < 500): ?>
                        The requested amount is <b style="color:#4ade80;">very close</b> to the ML suggestion.
                    <?php else: ?>
                        Difference: <b style="color:#e2e8f0;">
                            <?= $ml['diff'] > 0 ? '+' : '-' ?>₱<?= number_format(abs($ml['diff']), 2) ?>
                            (<?= $ml['diff_pct'] ?>%)
                        </b>
                        — <?= $ml['diff'] > 0 ? 'ML suggests more budget may be needed.' : 'ML suggests the request may be slightly high.' ?>
                    <?php endif; ?>
                </div>

                <!-- Cost per participant -->
                <div style="display:flex;justify-content:space-between;align-items:center;font-size:0.78rem;padding:10px 0;border-top:1px solid rgba(167,139,250,0.1);border-bottom:1px solid rgba(167,139,250,0.1);margin-bottom:14px;">
                    <span style="color:#475569;">Historical avg cost per participant</span>
                    <span style="color:#a78bfa;font-weight:700;font-family:'DM Mono',monospace;">₱<?= number_format($ml['per_pax'], 2) ?></span>
                </div>

                <!-- Similar projects -->
                <?php if (!empty($ml['similar'])): ?>
                <div style="margin-bottom:14px;">
                    <div style="font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#334155;margin-bottom:8px;">
                        Similar Past Projects Used
                    </div>
                    <?php foreach ($ml['similar'] as $s): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.78rem;">
                        <span style="color:#64748b;max-width:60%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= htmlspecialchars($s['name']) ?>
                        </span>
                        <div style="display:flex;gap:10px;align-items:center;flex-shrink:0;">
                            <span style="color:#334155;font-size:0.68rem;"><?= $s['similarity'] ?>% match</span>
                            <span style="color:#a78bfa;font-weight:700;font-family:'DM Mono',monospace;">₱<?= number_format($s['budget'], 0) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Confidence bar -->
                <div style="display:flex;align-items:center;gap:10px;font-size:0.68rem;color:#334155;">
                    <span style="white-space:nowrap;flex-shrink:0;">Confidence</span>
                    <div style="flex:1;height:5px;border-radius:3px;background:rgba(255,255,255,0.06);overflow:hidden;">
                        <div style="height:100%;border-radius:3px;width:<?= $ml['confidence'] ?>%;background:<?= $ml['confidence']>=70?'#a78bfa':($ml['confidence']>=40?'#fcd34d':'#64748b') ?>;"></div>
                    </div>
                    <span style="white-space:nowrap;flex-shrink:0;"><?= $ml['confidence'] ?>%</span>
                </div>

                <div style="margin-top:12px;font-size:0.68rem;color:#334155;line-height:1.6;text-align:center;">
                    📌 AI-generated suggestion for future proposal planning. The treasurer has full discretion to approve or reject any amount.
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <?php if ($isPending): ?>
            <div class="action-card">
                <div class="action-title">⚡ Take Action</div>
                <form method="POST">
                    <button type="submit" name="approve" class="btn-approve"
                            <?= !$canAfford ? 'disabled' : '' ?>
                            onclick="return confirmAction('approve','<?= addslashes($project['name']) ?>','<?= money($budgetRequested) ?>')">
                        ✅ Approve Proposal
                    </button>
                    <button type="submit" name="reject" class="btn-reject"
                            onclick="return confirmAction('reject','<?= addslashes($project['name']) ?>','')">
                        ❌ Reject Proposal
                    </button>
                </form>
                <div class="action-note">
                    <?= $canAfford
                        ? 'Approving will immediately deduct <b style="color:#fcd34d;">' . money($budgetRequested) . '</b> from the remaining budget.'
                        : 'Cannot approve — insufficient funds. You may still reject.' ?>
                </div>
            </div>
            <?php else: ?>
            <div class="action-card" style="text-align:center;padding:32px 22px;">
                <div style="font-size:2.5rem;margin-bottom:12px;">
                    <?= $project['status']==='approved' ? '✅' : '❌' ?>
                </div>
                <div style="font-size:1rem;font-weight:700;color:#e2e8f0;margin-bottom:6px;">
                    Proposal <?= ucfirst(str_replace('_',' ',$project['status'])) ?>
                </div>
                <div style="font-size:0.8rem;color:#475569;">No further action required.</div>
            </div>
            <?php endif; ?>

        </div>
    </div><!-- /.content-grid -->

    <!-- Print signatures -->
    <div class="print-signatures">
        <div class="sig-block"><div class="sig-line"></div><div class="sig-name">Prepared by</div><div class="sig-role">SK Chairperson</div></div>
        <div class="sig-block"><div class="sig-line"></div><div class="sig-name">Reviewed by</div><div class="sig-role">SK Treasurer</div></div>
        <div class="sig-block"><div class="sig-line"></div><div class="sig-name">Noted by</div><div class="sig-role">SK Secretary</div></div>
    </div>
    <div class="print-meta">
        <span>Printed: <script>document.write(new Date().toLocaleString())</script></span>
        <span>SK Decision Support System © 2026</span>
        <span>Barangay: <?= safe($project['barangay_name']) ?></span>
    </div>

    <div class="pg-footer">© 2026 SK Decision Support System</div>

</div><!-- /.main -->

<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
    document.getElementById('mobOverlay').classList.toggle('open');
}
function confirmAction(action, name, amount) {
    if (action === 'approve')
        return confirm(`Approve "${name}"?\n\n${amount} will be deducted from the budget immediately.\n\nThis action cannot be undone.`);
    return confirm(`Reject "${name}"?\n\nThis action cannot be undone.`);
}
</script>
</body>
</html>