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
:root{
    --sidebar-w:240px;
}

body{
    margin:0;
    min-height:100vh;
    overflow-y:auto;
    background:
    linear-gradient(rgba(13,27,42,0.30), rgba(13,27,42,0.40)),
    url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    font-family:'Sora',sans-serif;
    color:white;
}

/* sidebar */
.sidebar{
    position:fixed !important;
    top:0;
    left:0;
    width:var(--sidebar-w);
    height:100vh;
    overflow-y:auto;
    z-index:999;
}

/* MAIN FITTED */
.main{
    margin-left:var(--sidebar-w);
    width:calc(100% - var(--sidebar-w));
    min-height:100vh;
    padding:15px 18px;
}

/* header */
.page-header{
    margin-bottom:14px;
}

.page-header h2{
    font-size:24px;
    margin:0;
}

.page-header p{
    margin-top:3px;
    font-size:13px;
    color:#dbeafe;
}

/* glass */
.kpi-card,.panel{
    background:rgba(255,255,255,0.11);
    border:1px solid rgba(255,255,255,0.17);
    backdrop-filter:blur(14px);
    border-radius:14px;
    box-shadow:0 5px 18px rgba(0,0,0,0.20);
}

/* KPI */
.kpi-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:12px;
    margin-bottom:14px;
}

.kpi-card{
    padding:14px;
    text-align:center;
}

.kpi-icon{font-size:22px;margin-bottom:5px;}
.kpi-label{font-size:11px;color:#d1d5db;}
.kpi-value{font-size:24px;font-weight:700;margin:5px 0;}
.kpi-sub{font-size:10px;color:#f1f5f9;}

/* budget */
.budget-row{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:12px;
    margin-bottom:14px;
}

.panel{
    padding:14px;
    margin-bottom:14px;
}

.panel-title{
    font-size:14px;
    font-weight:700;
    margin-bottom:10px;
}

.bk-label,.ml-lbl{
    font-size:11px;
    color:#d1d5db;
}

.bk-value,.ml-val{
    font-size:20px;
    font-weight:700;
    margin-top:4px;
}

.bar-track,.ml-bar-track{
    width:100%;
    height:7px;
    background:rgba(255,255,255,0.15);
    border-radius:10px;
    margin-top:8px;
    overflow:hidden;
}

/* ML */
.ml-main-row{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:12px;
    margin-top:10px;
}

.ml-metric{
    background:rgba(255,255,255,0.06);
    padding:12px;
    border-radius:10px;
}

.ml-rec{
    margin-top:12px;
    background:rgba(56,189,248,0.08);
    padding:12px;
    border-radius:10px;
    line-height:1.5;
    font-size:13px;
}

/* bottom */
.bottom-row{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:14px;
    align-items:start;
}

.chart-wrap{
    min-height:250px;
}

.chart-wrap canvas{
    width:100% !important;
    height:190px !important;
}

/* donut */
.approval-wrap{
    display:flex;
    flex-direction:column;
    align-items:center;
}

.donut-wrap{
    width:130px;
    height:130px;
    position:relative;
    margin-bottom:12px;
}

.donut-wrap canvas{
    width:130px !important;
    height:130px !important;
}

.donut-center{
    position:absolute;
    top:50%;
    left:50%;
    transform:translate(-50%,-50%);
    text-align:center;
}

.pct{
    font-size:20px;
    font-weight:700;
}

.lbl{
    font-size:10px;
    color:#d1d5db;
}

.legend-item{
    display:flex;
    justify-content:space-between;
    margin:7px 0;
    font-size:12px;
}

.legend-left{
    display:flex;
    gap:6px;
    align-items:center;
}

.legend-dot{
    width:8px;
    height:8px;
    border-radius:50%;
}

.pg-footer{
    text-align:center;
    margin-top:10px;
    padding-bottom:15px;
    font-size:11px;
}

/* responsive */
@media(max-width:1000px){
    :root{--sidebar-w:70px;}

    .kpi-grid,
    .budget-row,
    .ml-main-row,
    .bottom-row{
        grid-template-columns:1fr;
    }
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