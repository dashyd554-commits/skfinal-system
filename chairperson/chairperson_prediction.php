<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= CALL FLASK API (pass barangay_id) ================= */
$apiUrl  = "https://skfinal-system.onrender.com/predict?barangay_id=" . (int)$barangay_id;
$apiBase = "https://skfinal-system.onrender.com/predict";

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FOLLOWLOCATION => true,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

/* ================= DEFAULTS ================= */
$apiOk              = false;
$apiError           = "";
$topScore           = 0;
$rowCount           = 0;
$category           = "No Data";
$recommendation     = "No recommendation available.";
$successProbability = 0;

/* ================= PARSE ================= */
if ($curlErr) {
    $apiError = "cURL error: " . $curlErr;
} elseif ($httpCode !== 200) {
    $apiError = "API returned HTTP $httpCode. Response: " . substr($response, 0, 300);
} else {
    $parsed = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $apiError = "Invalid JSON from API: " . substr($response, 0, 300);
    } elseif (isset($parsed['error']) || ($parsed['status'] ?? '') === 'error') {
        $apiError = $parsed['message'] ?? ($parsed['error'] ?? 'Unknown API error');
        $rowCount = (int)($parsed['row_count'] ?? 0);
    } else {
        $topScore = (float)($parsed['mean_score'] ?? 0);
        $rowCount = (int)($parsed['row_count']  ?? 0);
        $apiOk    = true;
    }
}

/* ================= AI INTERPRETATION ================= */
if ($topScore >= 70) {
    $category           = "High Performance";
    $recommendation     = "Excellent work! Maintain current youth programs and consider expanding funding to sustain momentum.";
    $successProbability = 0.85;
    $catClass           = "cat-high";
    $catEmoji           = "🟢";
} elseif ($topScore >= 40) {
    $category           = "Moderate Performance";
    $recommendation     = "Good progress. Improve participation rate and optimize project budgeting to reach higher performance.";
    $successProbability = 0.60;
    $catClass           = "cat-medium";
    $catEmoji           = "🟡";
} elseif ($topScore > 0) {
    $category           = "Low Performance";
    $recommendation     = "Needs attention. Reassess project planning, strengthen youth engagement, and review budget allocation.";
    $successProbability = 0.30;
    $catClass           = "cat-low";
    $catEmoji           = "🔴";
} else {
    $catClass = "cat-nodata";
    $catEmoji = "⚪";
}

/* ================= BUDGET ================= */
$stmt = $conn->prepare("SELECT total_amount, used_amount FROM budgets WHERE barangay_id = :bid ORDER BY year DESC LIMIT 1");
$stmt->execute([':bid' => $barangay_id]);
$budgetData = $stmt->fetch(PDO::FETCH_ASSOC);

$annualBudget    = (float)($budgetData['total_amount'] ?? 0);
$usedBudget      = (float)($budgetData['used_amount']  ?? 0);
$remainingBudget = $annualBudget - $usedBudget;
$usedPct         = $annualBudget > 0 ? round($usedBudget / $annualBudget * 100, 1) : 0;

/* ================= FORECAST ================= */
$growthRate        = $topScore / 100;
$projectedIncrease = $remainingBudget * ($growthRate * 0.25);
$futureBudget      = $remainingBudget + $projectedIncrease;

/* ================= ACTIVITY COUNT ================= */
$stmt = $conn->prepare("SELECT COUNT(*) FROM activities WHERE barangay_id = :bid");
$stmt->execute([':bid' => $barangay_id]);
$activityCount = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM projects WHERE barangay_id = :bid AND status = 'approved'");
$stmt->execute([':bid' => $barangay_id]);
$approvedProjects = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI ML Prediction | Chairperson</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
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
    margin:0;
    overflow:hidden;
    background:url('../assets/bg.jpg') no-repeat center center fixed;
    background-size:cover;
    font-family:Arial;
    background-size: cover;
    overflow-y: auto;
}

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

/* Header */
.page-header { display: flex; align-items: center; gap: 16px; }
.page-header .icon-wrap {
    width: 54px; height: 54px; border-radius: 14px; flex-shrink: 0;
    background: linear-gradient(135deg, #a855f7, #7c3aed);
    display: flex; align-items: center; justify-content: center;
    font-size: 26px;
    box-shadow: 0 4px 20px rgba(168,85,247,0.35);
}
.page-header h2 { font-size: clamp(1.1rem, 2.5vw, 1.55rem); font-weight: 700; color: #fff; margin: 0; }
.page-header p  { font-size: 0.78rem; color: var(--muted); margin-top: 3px; }

/* API error / warning */
.api-alert {
    border-radius: 12px;
    padding: 14px 18px;
    font-size: 0.83rem;
    line-height: 1.6;
    display: flex;
    flex-direction: column;
    gap: 6px;
    border: 1px solid;
}
.api-alert.err  { background: rgba(255,255,255,0.15);  border-color: rgba(255,255,255,0.15);  color: #fca5a5; }
.api-alert.warn { background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.15); color: #fcd34d; }
.api-alert strong { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.07em; }
.api-alert code { font-family: 'DM Mono', monospace; font-size: 0.75rem; opacity: 0.7; word-break: break-all; }

/* KPI grid */
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
    gap: 5px;
    position: relative;
    overflow: hidden;
}
.kpi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 16px 16px 0 0;
}
.kpi-card.c-blue::before   { background: var(--accent); }
.kpi-card.c-red::before    { background: var(--danger); }
.kpi-card.c-green::before  { background: var(--success); }
.kpi-card.c-purple::before { background: #a855f7; }
.kpi-icon  { font-size: 1.3rem; }
.kpi-label { font-size: 0.67rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; }
.kpi-value { font-size: 1.25rem; font-weight: 700; font-family: 'DM Mono', monospace; line-height: 1.1; }
.kpi-card.c-blue   .kpi-value { color: var(--accent); }
.kpi-card.c-red    .kpi-value { color: #f87171; }
.kpi-card.c-green  .kpi-value { color: #4ade80; }
.kpi-card.c-purple .kpi-value { color: #c084fc; }

/* Panel */
.panel {
    background: var(--glass);
    border: 1px solid var(--glass-bdr);
    border-radius: 18px;
    padding: 22px;
    backdrop-filter: blur(18px);
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

/* Two-column layout */
.two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

/* Category badge */
.category-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 700;
    border: 1px solid;
    margin-bottom: 18px;
}
.cat-high    { background: rgba(34,197,94,0.12);  color: #4ade80; border-color: rgba(34,197,94,0.3); }
.cat-medium  { background: rgba(245,158,11,0.12); color: #fcd34d; border-color: rgba(245,158,11,0.3); }
.cat-low     { background: rgba(239,68,68,0.12);  color: #f87171; border-color: rgba(239,68,68,0.3); }
.cat-nodata  { background: rgba(148,163,184,0.1); color: #94a3b8; border-color: rgba(148,163,184,0.2); }

/* Metric row */
.metric-row {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-bottom: 18px;
}
.metric-item {}
.metric-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.metric-label  { font-size: 0.72rem; color: var(--muted); font-weight: 600; }
.metric-val    { font-size: 0.85rem; font-weight: 700; font-family: 'DM Mono', monospace; }
.bar-track { height: 7px; border-radius: 4px; background: rgba(255,255,255,0.07); overflow: hidden; }
.bar-fill  { height: 100%; border-radius: 4px; transition: width 0.5s ease; }

/* Recommendation box */
.rec-box {
    background: rgba(56,189,248,0.07);
    border: 1px solid rgba(56,189,248,0.18);
    border-radius: 12px;
    padding: 14px 16px;
    font-size: 0.83rem;
    color: #94a3b8;
    line-height: 1.7;
}
.rec-box strong { color: var(--accent); display: block; margin-bottom: 5px; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.07em; }

/* No data state */
.nodata-state {
    text-align: center;
    padding: 30px 20px;
    color: var(--muted);
}
.nodata-state .emo { font-size: 2.2rem; margin-bottom: 10px; }
.nodata-state p { font-size: 0.85rem; }

/* Budget forecast */
.forecast-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}
.fc-item {
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--glass-bdr);
    border-radius: 12px;
    padding: 14px 16px;
    text-align: center;
}
.fc-label { font-size: 0.65rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 6px; }
.fc-value { font-size: 1rem; font-weight: 700; font-family: 'DM Mono', monospace; }

.fc-highlight {
    background: rgba(56,189,248,0.08);
    border-color: rgba(56,189,248,0.2);
}
.fc-highlight .fc-value { color: var(--accent); font-size: 1.15rem; }

/* Budget bar */
.budget-bar-wrap { margin-top: 4px; }
.budget-bar-label { display: flex; justify-content: space-between; font-size: 0.68rem; color: var(--muted); margin-bottom: 6px; }

/* Debug toggle */
.debug-section {
    background: rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 12px;
    padding: 16px;
    font-family: 'DM Mono', monospace;
    font-size: 0.72rem;
    color: #64748b;
}
.debug-section summary { cursor: pointer; color: #475569; margin-bottom: 8px; font-size: 0.75rem; }
.debug-section pre { white-space: pre-wrap; word-break: break-all; color: #475569; }

.pg-footer { text-align: center; font-size: 0.72rem; color: #1e3a5f; }

/* Budget bar colors */
<?php
$barColor = $usedPct > 85 ? '#ef4444' : ($usedPct > 60 ? '#f59e0b' : '#22c55e');
$spColor  = $successProbability >= 0.7 ? '#4ade80' : ($successProbability >= 0.4 ? '#fcd34d' : '#f87171');
$beScore  = min(100, $topScore);
$beColor  = $beScore >= 70 ? '#4ade80' : ($beScore >= 40 ? '#fcd34d' : '#f87171');
?>

@media (max-width: 1100px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .two-col  { grid-template-columns: 1fr; }
    .forecast-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
    :root { --sidebar-w: 60px; }
    .main { padding: 16px 14px 32px; }
    .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .forecast-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<?php include '../assets/sidebar.php'; ?>

<div class="main">

    <!-- Header -->
    <div class="page-header">
        <div class="icon-wrap">🤖</div>
        <div>
            <h2>AI / ML Prediction Dashboard</h2>
            <p>Machine learning performance analysis for your barangay</p>
        </div>
    </div>

    <!-- API Error / Warning -->
    <?php if (!$apiOk): ?>
    <div class="api-alert <?= $apiError ? 'err' : 'warn' ?>">
        <strong><?= $apiError ? '⚠️ API Connection Issue' : '⚠️ No Data Available' ?></strong>
        <?php if ($apiError): ?>
            <span><?= htmlspecialchars($apiError) ?></span>
            <code>Endpoint: <?= htmlspecialchars($apiUrl) ?></code>
            <span style="font-size:0.75rem;color:#64748b;">
                Tip: Make sure your Flask API is deployed and the activities/projects table has data.
                The API now accepts <code>?barangay_id=</code> for per-barangay scores.
            </span>
        <?php else: ?>
            <span>API responded but returned no score. Rows found: <b><?= $rowCount ?></b>. 
            Check that activities or projects have budget and participant data for barangay ID <b><?= $barangay_id ?></b>.</span>
        <?php endif; ?>
    </div>
    <?php elseif ($rowCount > 0): ?>
    <div class="api-alert warn" style="background:rgba(34,197,94,0.07);border-color:rgba(34,197,94,0.2);color:#86efac;">
        <strong>✅ API Connected</strong>
        <span>Score computed from <b><?= $rowCount ?></b> records for barangay ID <b><?= $barangay_id ?></b>.</span>
    </div>
    <?php endif; ?>

    <!-- KPI Row -->
    <div class="kpi-grid">
        <div class="kpi-card c-blue">
            <div class="kpi-icon">💼</div>
            <div class="kpi-label">Annual Budget</div>
            <div class="kpi-value">₱<?= number_format($annualBudget, 2) ?></div>
        </div>
        <div class="kpi-card c-red">
            <div class="kpi-icon">💸</div>
            <div class="kpi-label">Used Budget</div>
            <div class="kpi-value">₱<?= number_format($usedBudget, 2) ?></div>
        </div>
        <div class="kpi-card c-green">
            <div class="kpi-icon">💰</div>
            <div class="kpi-label">Remaining Budget</div>
            <div class="kpi-value">₱<?= number_format($remainingBudget, 2) ?></div>
        </div>
        <div class="kpi-card c-purple">
            <div class="kpi-icon">🎯</div>
            <div class="kpi-label">AI Score</div>
            <div class="kpi-value"><?= number_format($topScore, 2) ?>%</div>
        </div>
    </div>

    <!-- AI Result + Budget Overview -->
    <div class="two-col">

        <!-- AI Result Panel -->
        <div class="panel">
            <div class="panel-title">📊 AI Analysis Result</div>

            <?php if ($topScore > 0): ?>
                <div class="category-badge <?= $catClass ?>">
                    <?= $catEmoji ?> <?= htmlspecialchars($category) ?>
                </div>

                <div class="metric-row">
                    <div class="metric-item">
                        <div class="metric-header">
                            <span class="metric-label">Success Probability</span>
                            <span class="metric-val" style="color:<?= $spColor ?>"><?= round($successProbability * 100, 1) ?>%</span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width:<?= round($successProbability * 100) ?>%;background:<?= $spColor ?>;"></div>
                        </div>
                    </div>

                    <div class="metric-item">
                        <div class="metric-header">
                            <span class="metric-label">ML Efficiency Score</span>
                            <span class="metric-val" style="color:<?= $beColor ?>"><?= number_format($topScore, 1) ?>%</span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width:<?= min($topScore, 100) ?>%;background:<?= $beColor ?>;"></div>
                        </div>
                    </div>
                </div>

                <div class="rec-box">
                    <strong>💡 AI Recommendation</strong>
                    <?= htmlspecialchars($recommendation) ?>
                </div>

            <?php else: ?>
                <div class="nodata-state">
                    <div class="emo">📭</div>
                    <p>No AI score yet.</p>
                    <p style="margin-top:6px;font-size:0.78rem;">
                        The model needs activity or project data to compute a score.<br>
                        Make sure your barangay has activities/projects with budget and participant records.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Budget Overview Panel -->
        <div class="panel">
            <div class="panel-title">💰 Budget Overview</div>

            <div class="budget-bar-wrap">
                <div class="budget-bar-label">
                    <span>Used — <?= $usedPct ?>%</span>
                    <span>Available — <?= round(100 - $usedPct, 1) ?>%</span>
                </div>
                <div class="bar-track" style="height:10px;margin-bottom:18px;">
                    <div class="bar-fill" style="width:<?= min($usedPct, 100) ?>%;background:<?= $barColor ?>;"></div>
                </div>
            </div>

            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:18px;">
                <?php
                $bColors = ['#38bdf8', '#f87171', '#4ade80'];
                $bLabels = ['💼 Total Annual', '💸 Used', '💰 Remaining'];
                foreach([$annualBudget, $usedBudget, $remainingBudget] as $i => $amt): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.07);border-radius:10px;">
                    <span style="font-size:0.78rem;color:var(--muted);"><?= $bLabels[$i] ?></span>
                    <span style="font-family:'DM Mono',monospace;font-weight:700;color:<?= $bColors[$i] ?>;font-size:0.95rem;">₱<?= number_format($amt, 2) ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="display:flex;gap:10px;font-size:0.75rem;color:var(--muted);">
                <span>📌 Activities: <b style="color:var(--text);"><?= $activityCount ?></b></span>
                <span>✅ Approved Projects: <b style="color:#4ade80;"><?= $approvedProjects ?></b></span>
            </div>
        </div>

    </div>

    <!-- Budget Forecast -->
    <div class="panel">
        <div class="panel-title">📈 Budget Forecast
            <?php if ($topScore == 0): ?>
            <span style="font-size:0.68rem;color:#ef4444;font-weight:400;text-transform:none;letter-spacing:0;">(requires AI score &gt; 0)</span>
            <?php endif; ?>
        </div>

        <div class="forecast-grid">
            <div class="fc-item">
                <div class="fc-label">Current Remaining</div>
                <div class="fc-value" style="color:#4ade80;">₱<?= number_format($remainingBudget, 2) ?></div>
            </div>
            <div class="fc-item">
                <div class="fc-label">Projected Growth (<?= round($growthRate * 25, 1) ?>%)</div>
                <div class="fc-value" style="color:<?= $projectedIncrease > 0 ? '#fcd34d' : 'var(--muted)' ?>;">
                    <?= $projectedIncrease > 0 ? '+' : '' ?>₱<?= number_format($projectedIncrease, 2) ?>
                </div>
            </div>
            <div class="fc-item fc-highlight">
                <div class="fc-label">Projected Future Budget</div>
                <div class="fc-value">₱<?= number_format($futureBudget, 2) ?></div>
            </div>
        </div>

        <?php if ($topScore == 0): ?>
        <div style="font-size:0.78rem;color:var(--muted);padding:12px 14px;background:rgba(255,255,255,0.03);border-radius:10px;border:1px solid var(--glass-bdr);">
            ℹ️ Budget forecast projection is based on the AI score. Once the ML API returns a score above 0, this section will show meaningful projections.
        </div>
        <?php endif; ?>
    </div>

    <!-- Debug panel -->
    <details class="debug-section">
        <summary>🔧 Debug Info (click to expand)</summary>
        <pre>
API URL:       <?= htmlspecialchars($apiUrl) ?>

HTTP Code:     <?= $httpCode ?>

cURL Error:    <?= $curlErr ?: 'none' ?>

Raw Response:  <?= htmlspecialchars(substr($response ?? '', 0, 500)) ?>

Barangay ID:   <?= $barangay_id ?>

Rows used:     <?= $rowCount ?>

Computed score:<?= $topScore ?>
        </pre>
    </details>

    <div class="pg-footer">© 2026 SK Decision Support System</div>

</div><!-- /.main -->
</body>
</html>