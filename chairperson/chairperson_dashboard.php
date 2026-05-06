<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$user_id     = $_SESSION['user']['id'];

$python_file = "../ml/train_ml.py";
if (file_exists($python_file)) { @exec("python $python_file"); }

$stmt = $conn->prepare("SELECT COUNT(*) FROM projects WHERE barangay_id=:bid AND created_by=:uid AND status!='cancelled'");
$stmt->execute([':bid'=>$barangay_id,':uid'=>$user_id]);
$totalProposals = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM projects WHERE barangay_id=:bid AND created_by=:uid AND status='approved'");
$stmt->execute([':bid'=>$barangay_id,':uid'=>$user_id]);
$approved = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM projects WHERE barangay_id=:bid AND created_by=:uid AND status='rejected'");
$stmt->execute([':bid'=>$barangay_id,':uid'=>$user_id]);
$rejected = (int)$stmt->fetchColumn();

$pending = max(0, $totalProposals - ($approved + $rejected));

$stmt = $conn->prepare("SELECT total_amount, remaining_budget FROM budgets WHERE barangay_id=:bid ORDER BY id DESC LIMIT 1");
$stmt->execute([':bid'=>$barangay_id]);
$budgetInfo = $stmt->fetch(PDO::FETCH_ASSOC);
$totalAnnualBudget = (float)($budgetInfo['total_amount'] ?? 0);
$remainingBudget   = (float)($budgetInfo['remaining_budget'] ?? 0);

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM budget_transactions WHERE barangay_id=:bid");
$stmt->execute([':bid'=>$barangay_id]);
$totalUsedBudget = (float)$stmt->fetchColumn();

$usedPct = $totalAnnualBudget > 0 ? round($totalUsedBudget / $totalAnnualBudget * 100, 1) : 0;

$ml_online = false;
$mean_score = $category = $recommendation = '';
$success_probability = $budget_efficiency = 0;
$mlFile = "../ml/ml_results.json";
if (file_exists($mlFile)) {
    $mlData = json_decode(file_get_contents($mlFile), true);
    if (json_last_error()===JSON_ERROR_NONE && isset($mlData[$barangay_id])) {
        $b = $mlData[$barangay_id];
        $mean_score          = (float)($b['mean_score'] ?? 0);
        $category            = $b['category'] ?? 'No Data';
        $success_probability = (float)($b['success_probability'] ?? 0);
        $budget_efficiency   = (float)($b['budget_efficiency_score'] ?? 0);
        $recommendation      = $b['recommendation'] ?? 'No recommendation';
        $ml_online = true;
    }
}

$stmt = $conn->prepare("SELECT DATE(created_at) as date, COUNT(*) as total FROM projects WHERE barangay_id=:bid AND created_by=:uid AND status!='cancelled' GROUP BY DATE(created_at) ORDER BY date ASC");
$stmt->execute([':bid'=>$barangay_id,':uid'=>$user_id]);
$trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
$labels = array_column($trend,'date');
$data   = array_column($trend,'total');

$approvalRate = $totalProposals > 0 ? round($approved / $totalProposals * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chairperson Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root {
    --navy:      #0d1b2a;
    --navy-mid:  #1b2d42;
    --accent:    #38bdf8;
    --accent2:   #f59e0b;
    --glass:     rgba(255,255,255,0.06);
    --glass-bdr: rgba(255,255,255,0.11);
    --text:      #e2e8f0;
    --muted:     #64748b;
    --success:   #22c55e;
    --danger:    #ef4444;
    --sidebar-w: 240px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Sora', 'Segoe UI', sans-serif;
    color: var(--text);
    min-height: 100vh;

    /* background image + overlay (clean + consistent) */
    background:
        linear-gradient(rgba(13, 27, 42, 0.75), rgba(13, 27, 42, 0.85)),
        url('../assets/bg.jpg') no-repeat center center fixed;

    background-size: cover;
    overflow-y: auto;
}

/* Sidebar fixed */
.sidebar {
    position: fixed !important;
    top: 0 !important; left: 0 !important;
    height: 100vh !important;
    width: var(--sidebar-w) !important;
    overflow-y: auto;
    z-index: 1000;
}

.main {
    margin-left: var(--sidebar-w);
    width: calc(100% - var(--sidebar-w));
    padding: 28px 24px 40px;
    display: flex;
    flex-direction: column;
    gap: 22px;
    min-height: 100vh;
}

/* ── Page header ── */
.page-header {
    display: flex;
    align-items: center;
    gap: 16px;
}
.page-header .icon-wrap {
    width: 54px; height: 54px; border-radius: 14px; flex-shrink: 0;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    display: flex; align-items: center; justify-content: center;
    font-size: 26px;
    box-shadow: 0 4px 20px rgba(245,158,11,0.35);
}
.page-header h2 {
    font-size: clamp(1.1rem, 2.5vw, 1.55rem);
    font-weight: 700; letter-spacing: -0.3px; color: #fff; margin: 0;
}
.page-header p { font-size: 0.78rem; color: var(--muted); margin-top: 3px; }

/* ── KPI grid ── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
}

.kpi-card {
    background: var(--glass);
    border: 1px solid var(--glass-bdr);
    border-radius: 16px;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    position: relative;
    overflow: hidden;
    transition: transform 0.15s;
}
.kpi-card:hover { transform: translateY(-2px); }

.kpi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 16px 16px 0 0;
}
.kpi-card.c-blue::before   { background: var(--accent); }
.kpi-card.c-green::before  { background: var(--success); }
.kpi-card.c-red::before    { background: var(--danger); }
.kpi-card.c-amber::before  { background: var(--accent2); }

.kpi-icon { font-size: 1.4rem; margin-bottom: 2px; }
.kpi-label { font-size: 0.68rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600; }
.kpi-value {
    font-size: 1.7rem; font-weight: 700;
    font-family: 'DM Mono', monospace;
    line-height: 1;
}
.kpi-card.c-blue  .kpi-value { color: var(--accent); }
.kpi-card.c-green .kpi-value { color: var(--success); }
.kpi-card.c-red   .kpi-value { color: var(--danger); }
.kpi-card.c-amber .kpi-value { color: var(--accent2); }

.kpi-sub { font-size: 0.7rem; color: var(--muted); margin-top: 2px; }

/* ── Budget row ── */
.budget-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}

/* ── Glass panel ── */
.panel {
    background: var(--glass);
    border: 1px solid var(--glass-bdr);
    border-radius: 18px;
    padding: 22px;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}
.panel-title {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--glass-bdr);
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Budget panel */
.budget-kpi {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.budget-kpi .bk-label { font-size: 0.65rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.7px; }
.budget-kpi .bk-value { font-size: 1.25rem; font-weight: 700; font-family: 'DM Mono', monospace; }

.bar-label { display: flex; justify-content: space-between; font-size: 0.68rem; color: var(--muted); margin: 14px 0 6px; }
.bar-track { height: 8px; border-radius: 4px; background: rgba(255,255,255,0.07); overflow: hidden; }
.bar-fill  { height: 100%; border-radius: 4px; }

/* ── ML panel ── */
.ml-main-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

.ml-metric {
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--glass-bdr);
    border-radius: 12px;
    padding: 14px 16px;
}
.ml-metric .ml-lbl { font-size: 0.65rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.7px; margin-bottom: 6px; }
.ml-metric .ml-val { font-size: 1.2rem; font-weight: 700; font-family: 'DM Mono', monospace; }

.ml-bar-wrap { margin-top: 8px; }
.ml-bar-track { height: 5px; border-radius: 4px; background: rgba(255,255,255,0.07); overflow: hidden; margin-top: 4px; }
.ml-bar-fill  { height: 100%; border-radius: 4px; }

.ml-rec {
    background: rgba(56,189,248,0.07);
    border: 1px solid rgba(56,189,248,0.18);
    border-radius: 12px;
    padding: 14px 16px;
    font-size: 0.83rem;
    color: #94a3b8;
    line-height: 1.6;
}
.ml-rec strong { color: var(--accent); display: block; margin-bottom: 4px; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.07em; }

.category-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    margin-bottom: 14px;
    border: 1px solid;
}
.cat-high     { background: rgba(34,197,94,0.12);  color: #4ade80; border-color: rgba(34,197,94,0.3); }
.cat-medium   { background: rgba(245,158,11,0.12); color: #fcd34d; border-color: rgba(245,158,11,0.3); }
.cat-low      { background: rgba(239,68,68,0.12);  color: #f87171; border-color: rgba(239,68,68,0.3); }
.cat-no-data  { background: rgba(148,163,184,0.1); color: #94a3b8; border-color: rgba(148,163,184,0.2); }

.ml-offline {
    text-align: center; padding: 30px 20px; color: var(--muted);
    font-size: 0.85rem;
}
.ml-offline .emo { font-size: 2rem; margin-bottom: 8px; }

/* ── Bottom row ── */
.bottom-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 18px;
}

/* Chart */
.chart-wrap canvas { max-height: 240px; }

/* Approval donut */
.approval-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
}
.donut-wrap { position: relative; width: 160px; height: 160px; }
.donut-wrap canvas { width: 160px !important; height: 160px !important; }
.donut-center {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}
.donut-center .pct { font-size: 1.4rem; font-weight: 700; font-family: 'DM Mono', monospace; color: var(--success); }
.donut-center .lbl { font-size: 0.62rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }

.legend-list { width: 100%; display: flex; flex-direction: column; gap: 8px; }
.legend-item { display: flex; align-items: center; justify-content: space-between; font-size: 0.78rem; }
.legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-right: 8px; }
.legend-left { display: flex; align-items: center; color: var(--muted); }
.legend-right { font-family: 'DM Mono', monospace; font-weight: 600; }

/* Footer */
.pg-footer { text-align: center; font-size: 0.72rem; color: #1e3a5f; }

/* ── Responsive ── */
@media (max-width: 1100px) {
    .kpi-grid    { grid-template-columns: repeat(2, 1fr); }
    .bottom-row  { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    :root { --sidebar-w: 60px; }
    .main { padding: 16px 14px 32px; gap: 16px; }
    .kpi-grid    { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .budget-row  { grid-template-columns: 1fr; }
    .ml-main-row { grid-template-columns: 1fr; }
    .bottom-row  { grid-template-columns: 1fr; }
    .kpi-value   { font-size: 1.4rem; }
}
@media (max-width: 480px) {
    .kpi-grid { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <!-- Header -->
    <div class="page-header">
        <div class="icon-wrap">👑</div>
        <div>
            <h2>Chairperson Dashboard</h2>
            <p>Overview of your barangay's proposals and performance</p>
        </div>
    </div>

    <!-- Proposal KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card c-blue">
            <div class="kpi-icon">📋</div>
            <div class="kpi-label">Total Proposals</div>
            <div class="kpi-value"><?= $totalProposals ?></div>
            <div class="kpi-sub">All submitted projects</div>
        </div>
        <div class="kpi-card c-green">
            <div class="kpi-icon">✅</div>
            <div class="kpi-label">Approved</div>
            <div class="kpi-value"><?= $approved ?></div>
            <div class="kpi-sub"><?= $approvalRate ?>% approval rate</div>
        </div>
        <div class="kpi-card c-red">
            <div class="kpi-icon">❌</div>
            <div class="kpi-label">Rejected</div>
            <div class="kpi-value"><?= $rejected ?></div>
            <div class="kpi-sub">Declined proposals</div>
        </div>
        <div class="kpi-card c-amber">
            <div class="kpi-icon">⏳</div>
            <div class="kpi-label">Pending</div>
            <div class="kpi-value"><?= $pending ?></div>
            <div class="kpi-sub">Awaiting review</div>
        </div>
    </div>

    <!-- Budget Row -->
    <div class="budget-row">
        <?php
        $barColor = $usedPct > 85 ? '#ef4444' : ($usedPct > 60 ? '#f59e0b' : '#22c55e');
        ?>
        <div class="panel">
            <div class="panel-title">💼 Total Annual Budget</div>
            <div class="budget-kpi">
                <div class="bk-label">Allocated this year</div>
                <div class="bk-value" style="color:var(--accent);">₱<?= number_format($totalAnnualBudget,2) ?></div>
            </div>
            <div class="bar-label">
                <span>Used <?= $usedPct ?>%</span>
                <span>Free <?= round(100-$usedPct,1) ?>%</span>
            </div>
            <div class="bar-track">
                <div class="bar-fill" style="width:<?= min($usedPct,100) ?>%;background:<?= $barColor ?>;"></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-title">💸 Used Budget</div>
            <div class="budget-kpi">
                <div class="bk-label">Total spent so far</div>
                <div class="bk-value" style="color:#f87171;">₱<?= number_format($totalUsedBudget,2) ?></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-title">💰 Remaining Budget</div>
            <div class="budget-kpi">
                <div class="bk-label">Still available</div>
                <div class="bk-value" style="color:#4ade80;">₱<?= number_format($remainingBudget,2) ?></div>
            </div>
        </div>
    </div>

    <!-- ML Panel -->
    <div class="panel">
        <div class="panel-title">🤖 AI / ML Analysis</div>

        <?php if ($ml_online):
            $catClass = match(true) {
                str_contains(strtolower($category),'high')   => 'cat-high',
                str_contains(strtolower($category),'medium') => 'cat-medium',
                str_contains(strtolower($category),'low')    => 'cat-low',
                default => 'cat-no-data'
            };
            $spPct = round($success_probability * 100, 1);
            $bePct = round($budget_efficiency, 1);
        ?>
            <div class="category-badge <?= $catClass ?>">
                <?= match($catClass) { 'cat-high'=>'🟢', 'cat-medium'=>'🟡', 'cat-low'=>'🔴', default=>'⚪' } ?>
                <?= htmlspecialchars($category) ?> Performance
            </div>

            <div class="ml-main-row">
                <div class="ml-metric">
                    <div class="ml-lbl">Success Probability</div>
                    <div class="ml-val" style="color:<?= $spPct>=70?'#4ade80':($spPct>=40?'#fcd34d':'#f87171') ?>">
                        <?= $spPct ?>%
                    </div>
                    <div class="ml-bar-wrap">
                        <div class="ml-bar-track">
                            <div class="ml-bar-fill" style="width:<?= min($spPct,100) ?>%;background:<?= $spPct>=70?'#4ade80':($spPct>=40?'#fcd34d':'#f87171') ?>;"></div>
                        </div>
                    </div>
                </div>

                <div class="ml-metric">
                    <div class="ml-lbl">Budget Efficiency</div>
                    <div class="ml-val" style="color:<?= $bePct>=70?'#4ade80':($bePct>=40?'#fcd34d':'#f87171') ?>">
                        <?= $bePct ?>%
                    </div>
                    <div class="ml-bar-wrap">
                        <div class="ml-bar-track">
                            <div class="ml-bar-fill" style="width:<?= min($bePct,100) ?>%;background:<?= $bePct>=70?'#4ade80':($bePct>=40?'#fcd34d':'#f87171') ?>;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ml-rec">
                <strong>💡 AI Recommendation</strong>
                <?= htmlspecialchars($recommendation) ?>
            </div>

        <?php else: ?>
            <div class="ml-offline">
                <div class="emo">🤖</div>
                <p>ML analysis unavailable — result file not found.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Chart + Donut -->
    <div class="bottom-row">

        <div class="panel chart-wrap">
            <div class="panel-title">📈 Proposal Submission Trend</div>
            <canvas id="trendChart"></canvas>
        </div>

        <div class="panel">
            <div class="panel-title">🎯 Approval Breakdown</div>
            <div class="approval-wrap">
                <div class="donut-wrap">
                    <canvas id="donutChart"></canvas>
                    <div class="donut-center">
                        <div class="pct"><?= $approvalRate ?>%</div>
                        <div class="lbl">Approved</div>
                    </div>
                </div>
                <div class="legend-list">
                    <div class="legend-item">
                        <div class="legend-left"><span class="legend-dot" style="background:#22c55e;"></span>Approved</div>
                        <div class="legend-right" style="color:#4ade80;"><?= $approved ?></div>
                    </div>
                    <div class="legend-item">
                        <div class="legend-left"><span class="legend-dot" style="background:#ef4444;"></span>Rejected</div>
                        <div class="legend-right" style="color:#f87171;"><?= $rejected ?></div>
                    </div>
                    <div class="legend-item">
                        <div class="legend-left"><span class="legend-dot" style="background:#f59e0b;"></span>Pending</div>
                        <div class="legend-right" style="color:#fcd34d;"><?= $pending ?></div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="pg-footer">© 2026 SK Decision Support System</div>

</div><!-- /.main -->

<script>
// Trend line chart
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Proposals',
            data: <?= json_encode($data) ?>,
            borderColor: '#38bdf8',
            backgroundColor: 'rgba(56,189,248,0.08)',
            borderWidth: 2.5,
            pointBackgroundColor: '#38bdf8',
            pointRadius: 4,
            tension: 0.35,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.y} proposal(s)` } }
        },
        scales: {
            x: { ticks: { color: '#64748b', font: { size: 11 } }, grid: { color: 'rgba(255,255,255,0.04)' } },
            y: { beginAtZero: true, ticks: { color: '#64748b', stepSize: 1 }, grid: { color: 'rgba(255,255,255,0.06)' } }
        }
    }
});

// Approval donut
new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: ['Approved', 'Rejected', 'Pending'],
        datasets: [{
            data: [<?= $approved ?>, <?= $rejected ?>, <?= $pending ?>],
            backgroundColor: ['rgba(34,197,94,0.8)', 'rgba(239,68,68,0.8)', 'rgba(245,158,11,0.8)'],
            borderColor: ['#16a34a', '#dc2626', '#d97706'],
            borderWidth: 1.5,
            hoverOffset: 6
        }]
    },
    options: {
        responsive: false,
        cutout: '68%',
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed}` } }
        }
    }
});
</script>
</body>
</html>