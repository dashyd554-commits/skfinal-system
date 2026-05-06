<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'treasurer') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];
$message     = "";
$messageType = "";
$f_amount    = $_POST['total_amount'] ?? '';
$f_year      = $_POST['year']         ?? date('Y');

/* ================= INSERT BUDGET ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $f_amount = trim($f_amount);
    $f_year   = trim($f_year);

    if ($f_amount === "" || $f_year === "") {
        $message = "All fields are required.";
        $messageType = "error";
    } elseif (!is_numeric($f_amount) || (float)$f_amount <= 0) {
        $message = "Budget amount must be greater than zero.";
        $messageType = "error";
    } elseif (!is_numeric($f_year) || strlen($f_year) != 4 || $f_year < 2020 || $f_year > 2100) {
        $message = "Please enter a valid 4-digit year (2020–2100).";
        $messageType = "error";
    } else {
        try {
            $check = $conn->prepare("SELECT id FROM budgets WHERE barangay_id = ? AND year = ?");
            $check->execute([$barangay_id, $f_year]);

            if ($check->fetch()) {
                $message = "A budget for {$f_year} already exists. Please use a different year.";
                $messageType = "error";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO budgets (barangay_id, total_amount, used_amount, remaining_budget, year)
                    VALUES (?, ?, 0, ?, ?)
                ");
                $stmt->execute([$barangay_id, $f_amount, $f_amount, $f_year]);

                $message     = "Annual budget for {$f_year} saved successfully!";
                $messageType = "success";
                $f_amount    = "";
                $f_year      = date('Y') + 1;
            }
        } catch (PDOException $e) {
            $message     = "Database error. Please try again.";
            $messageType = "error";
        }
    }
}

/* ================= BUDGET HISTORY ================= */
$stmt = $conn->prepare("
    SELECT b.id, b.year, b.total_amount, b.used_amount,
           COALESCE(b.remaining_budget, b.total_amount - b.used_amount) AS remaining_budget
    FROM budgets b
    WHERE b.barangay_id = ?
    ORDER BY b.year ASC
");
$stmt->execute([$barangay_id]);
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ACTIVITY STATS PER YEAR ================= */
$stmt = $conn->prepare("
    SELECT EXTRACT(YEAR FROM date)::int          AS year,
           COUNT(*)                              AS total_activities,
           COALESCE(SUM(participants), 0)        AS total_participants,
           COALESCE(AVG(evaluation_score), 0)   AS avg_eval,
           COALESCE(SUM(allocated_budget), 0)   AS activity_spend,
           COUNT(CASE WHEN status='completed' THEN 1 END) AS completed
    FROM activities
    WHERE barangay_id = ?
    GROUP BY year
    ORDER BY year ASC
");
$stmt->execute([$barangay_id]);
$actsByYear = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $actsByYear[$r['year']] = $r;
}

/* ===============================================================
   ML ENGINE — Linear Regression + Multi-Factor Forecasting
   ================================================================
   Uses:
   1. Linear regression on budget history → base trend forecast
   2. Utilization rate adjustment (under/over-use penalty/bonus)
   3. Activity growth multiplier (participant + activity trends)
   4. Evaluation quality bonus
   5. Confidence scoring based on data richness
   =============================================================== */

$n = count($budgets);
$ml = [
    'forecast'        => 0,
    'trend'           => 'insufficient',
    'trend_label'     => 'Insufficient Data',
    'trend_color'     => '#8b93a7',
    'confidence'      => 0,
    'insight'         => 'Add at least 2 years of budget data to enable ML analysis.',
    'growth_rate'     => 0,
    'avg_utilization' => 0,
    'r_squared'       => 0,
    'factors'         => [],
    'range_low'       => 0,
    'range_high'      => 0,
];

if ($n >= 2) {

    /* ── Step 1: Linear Regression on budget amounts ── */
    $years   = array_column($budgets, 'year');
    $amounts = array_column($budgets, 'total_amount');

    $xBar = array_sum($years)   / $n;
    $yBar = array_sum($amounts) / $n;

    $num = 0; $den = 0;
    foreach ($budgets as $b) {
        $xDiff  = $b['year'] - $xBar;
        $num   += $xDiff * ($b['total_amount'] - $yBar);
        $den   += $xDiff * $xDiff;
    }

    $slope     = $den != 0 ? $num / $den : 0;
    $intercept = $yBar - $slope * $xBar;
    $nextYear  = (int)max($years) + 1;

    $baseForecast = $intercept + $slope * $nextYear;
    $baseForecast = max($baseForecast, $yBar * 0.5); /* floor at 50% of avg */

    /* R² — goodness of fit */
    $ssTot = 0; $ssRes = 0;
    foreach ($budgets as $b) {
        $predicted = $intercept + $slope * $b['year'];
        $ssTot    += pow($b['total_amount'] - $yBar, 2);
        $ssRes    += pow($b['total_amount'] - $predicted, 2);
    }
    $rSquared = $ssTot > 0 ? max(0, 1 - $ssRes / $ssTot) : 0;

    /* ── Step 2: Utilization analysis ── */
    $totalUtil  = 0; $utilCount  = 0;
    foreach ($budgets as $b) {
        if ($b['total_amount'] > 0) {
            $totalUtil += ($b['used_amount'] / $b['total_amount']) * 100;
            $utilCount++;
        }
    }
    $avgUtil = $utilCount > 0 ? $totalUtil / $utilCount : 0;

    /* utilization multiplier: ideal = 70–85% */
    if      ($avgUtil >= 85)  $utilMult = 1.10; /* consistently high → need more */
    elseif  ($avgUtil >= 70)  $utilMult = 1.05; /* healthy → slight increase */
    elseif  ($avgUtil >= 50)  $utilMult = 1.00; /* moderate → maintain */
    elseif  ($avgUtil >= 30)  $utilMult = 0.97; /* low → trim slightly */
    else                      $utilMult = 0.90; /* very low → reduce budget */

    /* ── Step 3: Activity growth multiplier ── */
    $actMult = 1.00;
    $actYears = array_keys($actsByYear);
    if (count($actYears) >= 2) {
        $lastActYear = max($actYears);
        $prevActYear = $actYears[array_search($lastActYear, $actYears) - 1] ?? null;

        if ($prevActYear !== null) {
            $lastPax = (float)($actsByYear[$lastActYear]['total_participants'] ?? 0);
            $prevPax = (float)($actsByYear[$prevActYear]['total_participants'] ?? 0);

            if ($prevPax > 0) {
                $paxGrowth = ($lastPax - $prevPax) / $prevPax;
                /* cap growth influence at ±8% */
                $actMult = 1 + max(-0.08, min(0.08, $paxGrowth * 0.5));
            }
        }
    }

    /* ── Step 4: Evaluation quality bonus ── */
    $allEvals = array_filter(array_column($actsByYear, 'avg_eval'));
    $avgEval  = count($allEvals) > 0 ? array_sum($allEvals) / count($allEvals) : 0;
    /* high-quality programs deserve sustained/increased funding */
    $evalBonus = $avgEval >= 85 ? 1.03 : ($avgEval >= 70 ? 1.01 : 1.00);

    /* ── Step 5: Combined forecast ── */
    $adjustedForecast = $baseForecast * $utilMult * $actMult * $evalBonus;
    $adjustedForecast = round($adjustedForecast / 1000) * 1000; /* round to nearest 1000 */

    $lastBudget  = (float)end($amounts);
    $growthRate  = $lastBudget > 0 ? (($adjustedForecast - $lastBudget) / $lastBudget) * 100 : 0;

    /* ── Step 6: Confidence score ── */
    $conf  = min(40, $n * 8);                          /* data volume: max 40 */
    $conf += round($rSquared * 30);                    /* regression fit: max 30 */
    $conf += min(20, count($actsByYear) * 4);          /* activity data: max 20 */
    $conf += $avgEval > 0 ? 10 : 0;                   /* eval data: max 10 */
    $confidence = max(15, min(95, (int)$conf));

    /* ── Trend label ── */
    if      ($growthRate >  8)  { $trend = 'strong_up';   $tLabel = 'Strong Uptrend';  $tColor = '#50c878'; }
    elseif  ($growthRate >  2)  { $trend = 'up';          $tLabel = 'Uptrend';         $tColor = '#7ba4f8'; }
    elseif  ($growthRate > -2)  { $trend = 'stable';      $tLabel = 'Stable';          $tColor = '#ffd166'; }
    elseif  ($growthRate > -8)  { $trend = 'down';        $tLabel = 'Downtrend';       $tColor = '#e07a7a'; }
    else                        { $trend = 'strong_down'; $tLabel = 'Strong Downtrend'; $tColor = '#c0392b'; }

    /* ── Insight message ── */
    $insight = match(true) {
        $avgUtil >= 85 && $growthRate > 0 =>
            "High budget utilization ({$avgUtil}%) with a growing trend. The barangay is actively executing programs. An increased allocation is recommended to sustain momentum.",
        $avgUtil >= 85 && $growthRate <= 0 =>
            "Budget utilization is high ({$avgUtil}%) but the annual budget is declining. Consider requesting additional funds to avoid program cutbacks.",
        $avgUtil >= 50 && $growthRate > 0 =>
            "Healthy utilization rate ({$avgUtil}%) with a positive budget trend. Continue current financial strategies while planning for modest growth.",
        $avgUtil >= 50 && $growthRate <= 0 =>
            "Moderate utilization ({$avgUtil}%) with a flat or declining trend. Focus on improving project execution efficiency before requesting budget increases.",
        $avgUtil < 30 =>
            "Low budget utilization ({$avgUtil}%). A significant portion of funds goes unused. Strengthen program planning and execution before scaling the budget.",
        default =>
            "Budget utilization is at {$avgUtil}%. Review spending patterns and align future budgets with actual program needs."
    };

    /* ── Forecast range ± uncertainty ── */
    $uncertainty = max(0.05, 0.20 - ($confidence / 100) * 0.15);
    $rangeLow    = round($adjustedForecast * (1 - $uncertainty) / 1000) * 1000;
    $rangeHigh   = round($adjustedForecast * (1 + $uncertainty) / 1000) * 1000;

    /* ── Factor breakdown for display ── */
    $factors = [
        ['Regression Baseline',     '₱' . number_format($baseForecast, 0),   round($rSquared * 100) . '% fit'],
        ['Utilization Adjustment',  ($utilMult >= 1 ? '+' : '') . round(($utilMult-1)*100,1) . '%',  round($avgUtil,1) . '% avg usage'],
        ['Activity Growth',         ($actMult  >= 1 ? '+' : '') . round(($actMult -1)*100,1) . '%',  count($actsByYear) . ' yrs of data'],
        ['Quality Bonus',           ($evalBonus>= 1 ? '+' : '') . round(($evalBonus-1)*100,1) . '%', $avgEval > 0 ? round($avgEval,1) . '% avg eval' : 'No eval data'],
    ];

    $ml = [
        'forecast'        => $adjustedForecast,
        'next_year'       => $nextYear,
        'trend'           => $trend,
        'trend_label'     => $tLabel,
        'trend_color'     => $tColor,
        'confidence'      => $confidence,
        'insight'         => $insight,
        'growth_rate'     => round($growthRate, 1),
        'avg_utilization' => round($avgUtil, 1),
        'r_squared'       => round($rSquared * 100, 1),
        'factors'         => $factors,
        'range_low'       => $rangeLow,
        'range_high'      => $rangeHigh,
        'avg_eval'        => round($avgEval, 1),
        'last_budget'     => $lastBudget,
    ];
}

/* chart data for JS */
$chartYears   = array_column($budgets, 'year');
$chartAmounts = array_column($budgets, 'total_amount');
$chartUsed    = array_column($budgets, 'used_amount');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Annual Budget Management</title>

<link rel="stylesheet" href="../assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: url('../assets/bg.jpg') no-repeat center center fixed;
    background-size: cover;
    min-height: 100vh;
    display: flex;
}

.wrapper { display: flex; width: 100%; min-height: 100vh; }

.main {
    flex: 1;
    min-width: 0;
    padding: 28px 24px;
    overflow-y: auto;
}

/* ── Header ── */
.page-header { margin-bottom: 24px; }
.page-header h2 { font-size: 22px; font-weight: 700; color: #fff; text-shadow: 0 1px 6px rgba(0,0,0,0.4); }
.page-header p  { color: rgba(255,255,255,0.6); font-size: 13px; margin-top: 4px; }

/* ── Alert ── */
.alert {
    border-radius: 10px;
    padding: 13px 16px;
    font-size: 13px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
}
.alert-success { background: rgba(80,200,120,0.15);  border: 1px solid rgba(80,200,120,0.35);  color: #6de89a; }
.alert-error   { background: rgba(224,122,122,0.15); border: 1px solid rgba(224,122,122,0.35); color: #e07a7a; }

/* ── Top grid: form + ML card ── */
.top-grid {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 18px;
    margin-bottom: 18px;
    align-items: start;
}

/* ── Glass ── */
.glass {
    background: rgba(255,255,255,0.07);
    backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 16px;
    padding: 22px;
    margin-bottom: 18px;
}
.glass:last-child { margin-bottom: 0; }

.glass-title {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255,255,255,0.5);
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ── Form ── */
.form-group { margin-bottom: 14px; }
.form-group label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: rgba(255,255,255,0.5);
    margin-bottom: 6px;
}
.form-group input {
    width: 100%;
    padding: 10px 13px;
    border-radius: 9px;
    border: 1px solid rgba(255,255,255,0.15);
    background: rgba(255,255,255,0.08);
    color: #fff;
    font-size: 13.5px;
    outline: none;
    transition: border 0.15s, background 0.15s;
}
.form-group input::placeholder { color: rgba(255,255,255,0.3); }
.form-group input:focus { border-color: #5b8af5; background: rgba(255,255,255,0.12); }
.form-group input.is-error { border-color: #e07a7a; }

.btn-submit {
    width: 100%;
    padding: 11px;
    border: none;
    border-radius: 9px;
    background: #5b8af5;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, transform 0.1s;
    margin-top: 4px;
}
.btn-submit:hover { background: #3a6fd8; transform: translateY(-1px); }

/* ── ML card ── */
.ml-forecast {
    text-align: center;
    padding: 18px 0 14px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    margin-bottom: 16px;
}

.ml-forecast-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255,255,255,0.4);
    margin-bottom: 6px;
}

.ml-forecast-amount {
    font-size: 32px;
    font-weight: 800;
    color: #a78bfa;
    line-height: 1;
    margin-bottom: 4px;
}

.ml-forecast-range {
    font-size: 12px;
    color: rgba(255,255,255,0.35);
}

/* ── Trend badge ── */
.trend-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 12px;
}

/* ── Insight box ── */
.insight-box {
    background: rgba(255,255,255,0.04);
    border-radius: 10px;
    padding: 13px 15px;
    font-size: 13px;
    color: rgba(255,255,255,0.65);
    line-height: 1.6;
    margin-bottom: 14px;
}

/* ── Factor breakdown ── */
.factor-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    font-size: 12px;
    gap: 12px;
}
.factor-row:last-child { border-bottom: none; }
.factor-name  { color: rgba(255,255,255,0.5); flex: 1; }
.factor-value { color: #fff; font-weight: 600; }
.factor-note  { color: rgba(255,255,255,0.3); font-size: 11px; text-align: right; }

/* ── Confidence bar ── */
.conf-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid rgba(255,255,255,0.07);
    font-size: 12px;
    color: rgba(255,255,255,0.4);
}
.conf-track { flex: 1; height: 6px; border-radius: 3px; background: rgba(255,255,255,0.08); overflow: hidden; }
.conf-fill  { height: 100%; border-radius: 3px; background: #a78bfa; transition: width 0.5s ease; }

/* ── Chart ── */
.chart-wrap { position: relative; height: 240px; }

/* ── Table ── */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 500px; }
thead th {
    padding: 11px 14px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: rgba(255,255,255,0.4);
    border-bottom: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.04);
    white-space: nowrap;
}
tbody td {
    padding: 11px 14px;
    font-size: 13px;
    color: rgba(255,255,255,0.85);
    border-bottom: 1px solid rgba(255,255,255,0.05);
    vertical-align: middle;
}
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,0.04); }

.util-bar-wrap { min-width: 100px; }
.util-track { height: 6px; border-radius: 3px; background: rgba(255,255,255,0.08); overflow: hidden; }
.util-fill  { height: 100%; border-radius: 3px; }

.empty-state { text-align: center; padding: 32px; color: rgba(255,255,255,0.3); font-size: 13px; }

/* ── Footer ── */
.footer { text-align: center; padding: 14px; color: rgba(255,255,255,0.25); font-size: 12px; margin-top: 8px; }

/* ── Hamburger ── */
.hamburger {
    display: none;
    position: fixed; top: 14px; left: 14px; z-index: 200;
    background: #1a1f2e; border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px; width: 38px; height: 38px; cursor: pointer;
    flex-direction: column; align-items: center; justify-content: center; gap: 5px;
}
.hamburger span { display: block; width: 18px; height: 2px; background: #c5cad8; border-radius: 2px; }
.mob-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 99; }

/* ── Responsive ── */
@media (max-width: 960px)  { .top-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) {
    .sidebar { position: fixed !important; left: -260px !important; top: 0; bottom: 0; z-index: 100; width: 240px !important; transition: left 0.25s ease; overflow-y: auto; }
    .sidebar.open { left: 0 !important; }
    .mob-overlay.open { display: block; }
    .hamburger { display: flex; }
    .main { padding: 64px 14px 20px; }
}
</style>
</head>
<body>

<button class="hamburger" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
<div class="mob-overlay" id="mobOverlay" onclick="toggleSidebar()"></div>

<div class="wrapper">
    <?php include '../assets/sidebar.php'; ?>

    <div class="main">

        <div class="page-header">
            <h2>💰 Annual Budget Management</h2>
            <p>Set annual allocations and view ML-powered budget forecasts</p>
        </div>

        <?php if ($message !== ''): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= $messageType === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="top-grid">

            <!-- ── Form ── -->
            <div class="glass">
                <div class="glass-title">➕ New Budget Entry</div>

                <form method="POST" action="" onsubmit="return confirm('Save this annual budget record?')">
                    <div class="form-group">
                        <label>Total Budget Amount (₱)</label>
                        <input type="number" name="total_amount" step="0.01" min="1"
                               placeholder="e.g. 500000"
                               value="<?= htmlspecialchars($f_amount) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Budget Year</label>
                        <input type="number" name="year" min="2020" max="2100"
                               placeholder="e.g. <?= date('Y') ?>"
                               value="<?= htmlspecialchars($f_year) ?>" required>
                    </div>
                    <button type="submit" class="btn-submit">💾 Save Annual Budget</button>
                </form>
            </div>

            <!-- ── ML Forecast ── -->
            <div class="glass">
                <div class="glass-title" style="color:rgba(167,139,250,0.8);">🤖 ML Budget Forecast
                    <span style="margin-left:auto;font-size:10px;font-weight:400;letter-spacing:0;text-transform:none;color:rgba(255,255,255,0.25);">
                        Linear Regression + Multi-Factor Analysis
                    </span>
                </div>

                <?php if ($ml['forecast'] > 0): ?>

                <!-- Forecast amount -->
                <div class="ml-forecast">
                    <div class="ml-forecast-label">
                        Recommended Budget for FY <?= $ml['next_year'] ?>
                    </div>
                    <div class="ml-forecast-amount">
                        ₱<?= number_format($ml['forecast'], 0) ?>
                    </div>
                    <div class="ml-forecast-range">
                        Range: ₱<?= number_format($ml['range_low'], 0) ?> — ₱<?= number_format($ml['range_high'], 0) ?>
                    </div>
                </div>

                <!-- Trend badge -->
                <div style="text-align:center;margin-bottom:14px;">
                    <span class="trend-badge"
                          style="background:<?= htmlspecialchars($ml['trend_color']) ?>22;
                                 color:<?= htmlspecialchars($ml['trend_color']) ?>;
                                 border:1px solid <?= htmlspecialchars($ml['trend_color']) ?>55;">
                        <?php
                        $tIcon = match($ml['trend']) {
                            'strong_up'   => '🚀',
                            'up'          => '📈',
                            'stable'      => '➡️',
                            'down'        => '📉',
                            'strong_down' => '⚠️',
                            default       => '—'
                        };
                        echo $tIcon . ' ' . $ml['trend_label'];
                        ?>
                        &nbsp;
                        <?php if ($ml['growth_rate'] != 0): ?>
                        (<?= $ml['growth_rate'] > 0 ? '+' : '' ?><?= $ml['growth_rate'] ?>%)
                        <?php endif; ?>
                    </span>
                </div>

                <!-- Insight -->
                <div class="insight-box"><?= htmlspecialchars($ml['insight']) ?></div>

                <!-- Factor breakdown -->
                <div style="margin-bottom:6px;">
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.25);margin-bottom:8px;">
                        Forecast Factors
                    </div>
                    <?php foreach ($ml['factors'] as [$name, $val, $note]): ?>
                    <div class="factor-row">
                        <span class="factor-name"><?= $name ?></span>
                        <span class="factor-value"><?= $val ?></span>
                        <span class="factor-note"><?= $note ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Confidence -->
                <div class="conf-row">
                    <span>Confidence</span>
                    <div class="conf-track">
                        <div class="conf-fill" style="width:<?= $ml['confidence'] ?>%;"></div>
                    </div>
                    <span><?= $ml['confidence'] ?>%</span>
                </div>

                <div style="font-size:10.5px;color:rgba(255,255,255,0.2);text-align:center;margin-top:12px;line-height:1.5;">
                    ⚠ For planning reference only. All decisions remain at the treasurer's discretion.
                </div>

                <?php else: ?>
                <div style="text-align:center;padding:32px;color:rgba(255,255,255,0.3);">
                    <div style="font-size:32px;margin-bottom:10px;">📊</div>
                    <p style="font-size:13px;">Add at least <b style="color:rgba(255,255,255,0.5);">2 years</b> of budget data to enable ML forecasting.</p>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /.top-grid -->

        <!-- ── Budget Trend Chart ── -->
        <?php if (count($budgets) >= 2): ?>
        <div class="glass">
            <div class="glass-title">📈 Budget Trend Chart</div>
            <div class="chart-wrap">
                <canvas id="budgetChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── History Table ── -->
        <div class="glass">
            <div class="glass-title">📋 Budget History
                <span style="margin-left:auto;font-size:11px;font-weight:400;letter-spacing:0;text-transform:none;color:rgba(255,255,255,0.3);">
                    <?= count($budgets) ?> record<?= count($budgets) !== 1 ? 's' : '' ?>
                </span>
            </div>

            <div class="table-wrap">
                <?php if (!empty($budgets)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Total Budget</th>
                            <th>Amount Used</th>
                            <th>Remaining</th>
                            <th>Utilization</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($budgets as $b):
                            $util = $b['total_amount'] > 0
                                ? round($b['used_amount'] / $b['total_amount'] * 100, 1)
                                : 0;
                            $utilColor = $util >= 85 ? '#e07a7a' : ($util >= 50 ? '#50c878' : '#ffd166');
                        ?>
                        <tr>
                            <td style="font-weight:700;color:#fff;"><?= $b['year'] ?></td>
                            <td style="color:#ffd166;font-weight:600;">₱<?= number_format($b['total_amount'], 2) ?></td>
                            <td style="color:#e07a7a;">₱<?= number_format($b['used_amount'], 2) ?></td>
                            <td style="color:#50c878;">₱<?= number_format($b['remaining_budget'], 2) ?></td>
                            <td>
                                <div class="util-bar-wrap">
                                    <div style="font-size:11px;color:rgba(255,255,255,0.5);margin-bottom:4px;"><?= $util ?>%</div>
                                    <div class="util-track">
                                        <div class="util-fill"
                                             style="width:<?= $util ?>%;background:<?= $utilColor ?>;"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <div style="font-size:32px;margin-bottom:10px;">📂</div>
                    <p>No budget records yet. Add your first annual budget above.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer">
            © 2026 SK Decision Support System &nbsp;|&nbsp; Responsive Community Planning Platform
        </div>

    </div><!-- /.main -->
</div><!-- /.wrapper -->

<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
    document.getElementById('mobOverlay').classList.toggle('open');
}

<?php if (count($budgets) >= 2): ?>
const years   = <?= json_encode(array_map('intval',  $chartYears))   ?>;
const amounts = <?= json_encode(array_map('floatval', $chartAmounts)) ?>;
const used    = <?= json_encode(array_map('floatval', $chartUsed))    ?>;

<?php if ($ml['forecast'] > 0): ?>
/* add forecast point */
years.push(<?= $ml['next_year'] ?>);
amounts.push(null);
used.push(null);
const forecastAmounts = amounts.map((v, i) => i === amounts.length - 1 ? <?= $ml['forecast'] ?> : null);
<?php endif; ?>

new Chart(document.getElementById('budgetChart'), {
    type: 'bar',
    data: {
        labels: years,
        datasets: [
            {
                label: 'Total Budget',
                data: amounts,
                backgroundColor: 'rgba(91,138,245,0.5)',
                borderColor: '#5b8af5',
                borderWidth: 1,
                borderRadius: 6,
                order: 2,
            },
            {
                label: 'Amount Used',
                data: used,
                backgroundColor: 'rgba(224,122,122,0.5)',
                borderColor: '#e07a7a',
                borderWidth: 1,
                borderRadius: 6,
                order: 2,
            },
            <?php if ($ml['forecast'] > 0): ?>
            {
                label: 'ML Forecast (<?= $ml['next_year'] ?>)',
                data: forecastAmounts,
                type: 'line',
                borderColor: '#a78bfa',
                backgroundColor: 'rgba(167,139,250,0.15)',
                borderWidth: 2,
                borderDash: [6,3],
                pointBackgroundColor: '#a78bfa',
                pointRadius: 6,
                order: 1,
                fill: false,
            },
            <?php endif; ?>
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#c5cad8', font: { size: 12 } } },
            tooltip: {
                callbacks: {
                    label: ctx => ' ₱' + Number(ctx.raw).toLocaleString('en-PH', {minimumFractionDigits:2})
                }
            }
        },
        scales: {
            x: { ticks: { color: '#8b93a7' }, grid: { color: 'rgba(255,255,255,0.05)' } },
            y: {
                beginAtZero: true,
                ticks: {
                    color: '#8b93a7',
                    callback: v => '₱' + Number(v).toLocaleString('en-PH')
                },
                grid: { color: 'rgba(255,255,255,0.05)' }
            }
        }
    }
});
<?php endif; ?>
</script>
</body>
</html>