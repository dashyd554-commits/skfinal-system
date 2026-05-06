<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$barangay_id   = $_SESSION['user']['barangay_id'];
$role          = $_SESSION['user']['role'] ?? 'user';
$barangay_name = '';

/* ── Get barangay name ── */
$stmt = $conn->prepare("SELECT barangay_name FROM barangays WHERE id = ?");
$stmt->execute([$barangay_id]);
$barangay_name = $stmt->fetchColumn() ?: 'Your Barangay';

/* ================================================================
   DATA COLLECTION — pull everything needed for analysis
   ================================================================ */

/* 1. Budget */
$stmt = $conn->prepare("SELECT * FROM budgets WHERE barangay_id = ? ORDER BY year DESC LIMIT 1");
$stmt->execute([$barangay_id]);
$budgetRow   = $stmt->fetch(PDO::FETCH_ASSOC);
$totalBudget = (float)($budgetRow['total_amount']     ?? 0);
$usedBudget  = (float)($budgetRow['used_amount']      ?? 0);
$remaining   = (float)($budgetRow['remaining_budget'] ?? ($totalBudget - $usedBudget));
$budgetYear  = $budgetRow['year'] ?? date('Y');
$usageRatio  = $totalBudget > 0 ? ($usedBudget / $totalBudget) * 100 : 0;

/* 2. Activities */
$stmt = $conn->prepare("
    SELECT COUNT(*)                        AS total_activities,
           COALESCE(SUM(participants),0)   AS total_participants,
           COALESCE(AVG(evaluation_score),0) AS avg_eval,
           COALESCE(SUM(allocated_budget),0) AS total_act_budget,
           COUNT(CASE WHEN status='completed' THEN 1 END) AS completed,
           COUNT(CASE WHEN status='planned'   THEN 1 END) AS planned
    FROM activities
    WHERE barangay_id = ?
");
$stmt->execute([$barangay_id]);
$actRow = $stmt->fetch(PDO::FETCH_ASSOC);

$totalActivities   = (int)($actRow['total_activities']   ?? 0);
$totalParticipants = (int)($actRow['total_participants']  ?? 0);
$avgEval           = (float)($actRow['avg_eval']          ?? 0);
$completedActs     = (int)($actRow['completed']           ?? 0);
$plannedActs       = (int)($actRow['planned']             ?? 0);
$completionRate    = $totalActivities > 0 ? ($completedActs / $totalActivities) * 100 : 0;

/* 3. Projects */
$stmt = $conn->prepare("
    SELECT COUNT(*)                                            AS total_projects,
           COUNT(CASE WHEN status='approved'  THEN 1 END)    AS approved,
           COUNT(CASE WHEN status='rejected'  THEN 1 END)    AS rejected,
           COUNT(CASE WHEN status='pending'   THEN 1 END)    AS pending,
           COALESCE(SUM(budget_allocated),0)                 AS total_allocated,
           COALESCE(AVG(budget_requested),0)                 AS avg_requested,
           COALESCE(AVG(target_participants),0)              AS avg_target_pax
    FROM projects
    WHERE barangay_id = ?
");
$stmt->execute([$barangay_id]);
$projRow = $stmt->fetch(PDO::FETCH_ASSOC);

$totalProjects    = (int)($projRow['total_projects']  ?? 0);
$approvedProjects = (int)($projRow['approved']        ?? 0);
$rejectedProjects = (int)($projRow['rejected']        ?? 0);
$pendingProjects  = (int)($projRow['pending']         ?? 0);
$totalAllocated   = (float)($projRow['total_allocated'] ?? 0);
$avgRequested     = (float)($projRow['avg_requested']   ?? 0);
$approvalRate     = $totalProjects > 0 ? ($approvedProjects / $totalProjects) * 100 : 0;

/* 4. Budget transactions */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS tx_count, COALESCE(SUM(amount),0) AS tx_total
    FROM budget_transactions WHERE barangay_id = ?
");
$stmt->execute([$barangay_id]);
$txRow     = $stmt->fetch(PDO::FETCH_ASSOC);
$txCount   = (int)($txRow['tx_count']  ?? 0);
$txTotal   = (float)($txRow['tx_total'] ?? 0);

/* 5. Monthly trend (last 6 months) */
$stmt = $conn->prepare("
    SELECT TO_CHAR(created_at,'YYYY-MM') AS month,
           COUNT(*)                      AS cnt,
           SUM(amount)                   AS total
    FROM budget_transactions
    WHERE barangay_id = ?
      AND created_at >= NOW() - INTERVAL '6 months'
    GROUP BY month
    ORDER BY month ASC
");
$stmt->execute([$barangay_id]);
$monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 6. Recent activities for trend */
$stmt = $conn->prepare("
    SELECT date, participants, evaluation_score, allocated_budget
    FROM activities
    WHERE barangay_id = ?
    ORDER BY date DESC
    LIMIT 10
");
$stmt->execute([$barangay_id]);
$recentActs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================================================================
   ML ENGINE — Composite Scoring System
   Produces a 0–100 performance score using weighted indicators
   ================================================================ */

/* ── Feature 1: Budget utilization score (0–100) ── */
/* Ideal range: 50–85%. Too low = underperforming, too high = overspending */
if ($usageRatio <= 0)       $budgetScore = 10;
elseif ($usageRatio <= 30)  $budgetScore = 30 + ($usageRatio / 30) * 20;
elseif ($usageRatio <= 85)  $budgetScore = 50 + (($usageRatio - 30) / 55) * 50;
elseif ($usageRatio <= 95)  $budgetScore = 100 - (($usageRatio - 85) / 10) * 30;
else                        $budgetScore = 70;

/* ── Feature 2: Activity completion score (0–100) ── */
$activityScore = 0;
if ($totalActivities > 0) {
    $activityScore  = ($completionRate * 0.60);           /* completion rate */
    $activityScore += min(40, ($totalActivities / 5) * 40); /* volume bonus up to 40 */
}

/* ── Feature 3: Evaluation/quality score (0–100) ── */
$evalScore = $avgEval > 0 ? min(100, $avgEval) : 20;

/* ── Feature 4: Project approval score (0–100) ── */
$approvalScore = 0;
if ($totalProjects > 0) {
    $approvalScore  = ($approvalRate * 0.70);
    $approvalScore += min(30, ($totalProjects / 3) * 30);
}

/* ── Feature 5: Participant engagement score (0–100) ── */
$engagementScore = 0;
if ($totalActivities > 0) {
    $avgPax          = $totalParticipants / $totalActivities;
    $engagementScore = min(100, ($avgPax / 100) * 100);
}

/* ── Weighted composite ML score ── */
$mlScore = (
    $budgetScore     * 0.25 +
    $activityScore   * 0.25 +
    $evalScore       * 0.20 +
    $approvalScore   * 0.15 +
    $engagementScore * 0.15
);
$mlScore = round(min(100, max(0, $mlScore)), 1);

/* ── Confidence: based on data richness ── */
$dataPoints  = min(1, $totalActivities / 5) * 30
             + min(1, $totalProjects    / 3) * 30
             + min(1, $txCount          / 5) * 20
             + ($avgEval > 0 ? 20 : 0);
$confidence  = max(15, min(95, round($dataPoints)));

/* ── Trend analysis: is engagement improving? ── */
$trend = 'stable';
if (count($recentActs) >= 4) {
    $half   = intdiv(count($recentActs), 2);
    $recent = array_slice($recentActs, 0, $half);
    $older  = array_slice($recentActs, $half);
    $avgRecent = array_sum(array_column($recent, 'participants')) / max(1, count($recent));
    $avgOlder  = array_sum(array_column($older,  'participants')) / max(1, count($older));
    if ($avgRecent > $avgOlder * 1.10)       $trend = 'improving';
    elseif ($avgRecent < $avgOlder * 0.90)   $trend = 'declining';
}

/* ── Budget efficiency: cost per participant ── */
$costPerPax = $totalParticipants > 0 ? $usedBudget / $totalParticipants : 0;

/* ── Projected next-year budget recommendation ── */
$projectedBudget = 0;
$projectedReason = '';

if ($totalBudget > 0) {
    if ($usageRatio >= 85) {
        $multiplier      = 1.10 + ($avgEval >= 80 ? 0.05 : 0);
        $projectedBudget = round($totalBudget * $multiplier / 1000) * 1000;
        $projectedReason = "High utilization ({$usageRatio}%) suggests demand exceeds current allocation.";
    } elseif ($usageRatio >= 50) {
        $multiplier      = 1.05;
        $projectedBudget = round($totalBudget * $multiplier / 1000) * 1000;
        $projectedReason = "Healthy utilization. Modest increase supports growth.";
    } elseif ($usageRatio >= 20) {
        $projectedBudget = round($totalBudget / 1000) * 1000;
        $projectedReason = "Moderate utilization. Maintain current allocation and improve execution.";
    } else {
        $multiplier      = 0.90;
        $projectedBudget = round($totalBudget * $multiplier / 1000) * 1000;
        $projectedReason = "Low utilization ({$usageRatio}%). A leaner budget encourages better planning.";
    }
}

/* ── Performance tier ── */
if      ($mlScore >= 80) { $tier = 'Excellent';    $tierColor = '#50c878'; $tierIcon = '🏆'; }
elseif  ($mlScore >= 60) { $tier = 'Good';         $tierColor = '#7ba4f8'; $tierIcon = '✅'; }
elseif  ($mlScore >= 40) { $tier = 'Moderate';     $tierColor = '#ffd166'; $tierIcon = '⚡'; }
elseif  ($mlScore >= 20) { $tier = 'Needs Work';   $tierColor = '#e07a7a'; $tierIcon = '⚠️'; }
else                     { $tier = 'Critical';     $tierColor = '#e05555'; $tierIcon = '🚨'; }

/* ── Generate smart recommendations ── */
$recommendations = [];

if ($usageRatio < 30) {
    $recommendations[] = [
        'priority' => 'high',
        'icon'     => '💸',
        'title'    => 'Increase Budget Utilization',
        'body'     => "Only " . round($usageRatio, 1) . "% of the budget has been used. Accelerate project execution and activity scheduling to maximize fund impact before the fiscal year ends.",
    ];
}

if ($usageRatio > 90) {
    $recommendations[] = [
        'priority' => 'high',
        'icon'     => '⚠️',
        'title'    => 'Budget Nearly Exhausted',
        'body'     => "Budget utilization is at " . round($usageRatio, 1) . "%. Prioritize remaining expenses carefully. Consider requesting a supplemental budget for next year.",
    ];
}

if ($completionRate < 50 && $totalActivities > 0) {
    $recommendations[] = [
        'priority' => 'high',
        'icon'     => '📋',
        'title'    => 'Improve Activity Completion Rate',
        'body'     => "Only " . round($completionRate, 1) . "% of activities are completed. Review planned activities and identify blockers. Consider reassigning resources to ongoing initiatives.",
    ];
}

if ($avgEval > 0 && $avgEval < 70) {
    $recommendations[] = [
        'priority' => 'medium',
        'icon'     => '📊',
        'title'    => 'Improve Activity Quality',
        'body'     => "Average evaluation score is " . round($avgEval, 1) . "%. Focus on post-activity evaluations and feedback loops to improve future program quality.",
    ];
}

if ($approvalRate < 50 && $totalProjects > 2) {
    $recommendations[] = [
        'priority' => 'medium',
        'icon'     => '📁',
        'title'    => 'Strengthen Project Proposals',
        'body'     => "Only " . round($approvalRate, 1) . "% of projects get approved. Improve proposal quality — include clearer objectives, realistic budgets, and strong community benefit justifications.",
    ];
}

if ($totalParticipants > 0 && $engagementScore < 40) {
    $recommendations[] = [
        'priority' => 'medium',
        'icon'     => '👥',
        'title'    => 'Boost Youth Participation',
        'body'     => "Average of " . round($totalParticipants / max(1,$totalActivities)) . " participants per activity. Launch more community-driven programs and use social media to increase awareness and attendance.",
    ];
}

if ($trend === 'declining') {
    $recommendations[] = [
        'priority' => 'high',
        'icon'     => '📉',
        'title'    => 'Participation is Declining',
        'body'     => "Recent activities show fewer participants than earlier ones. Investigate causes — consider changing activity timing, location, or format to re-engage the community.",
    ];
}

if ($trend === 'improving') {
    $recommendations[] = [
        'priority' => 'low',
        'icon'     => '📈',
        'title'    => 'Keep the Momentum',
        'body'     => "Participation is trending upward. Continue the current activity formats and look into replicating high-performing programs in the next quarter.",
    ];
}

if ($pendingProjects > 3) {
    $recommendations[] = [
        'priority' => 'medium',
        'icon'     => '⏳',
        'title'    => 'Clear Pending Proposals',
        'body'     => "{$pendingProjects} project proposals are still pending review. Timely decisions help maintain momentum and prevent budget underutilization.",
    ];
}

if (empty($recommendations)) {
    $recommendations[] = [
        'priority' => 'low',
        'icon'     => '🌟',
        'title'    => 'Strong Overall Performance',
        'body'     => "All key indicators are within healthy ranges. Continue current strategies and document best practices to replicate success.",
    ];
}

/* sort: high → medium → low */
$priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
usort($recommendations, fn($a,$b) => $priorityOrder[$a['priority']] <=> $priorityOrder[$b['priority']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Recommendation — <?= htmlspecialchars($barangay_name) ?></title>

<link rel="stylesheet" href="../assets/style.css">

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

/* ── Glass ── */
.glass {
    background: rgba(255,255,255,0.07);
    backdrop-filter: blur(18px);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 16px;
    padding: 22px;
    margin-bottom: 18px;
}

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

/* ── Score hero ── */
.score-hero {
    display: flex;
    align-items: center;
    gap: 28px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.score-ring-wrap { flex-shrink: 0; position: relative; width: 130px; height: 130px; }

.score-ring-wrap svg { transform: rotate(-90deg); }

.score-ring-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.score-number { font-size: 30px; font-weight: 800; color: #fff; line-height: 1; }
.score-label  { font-size: 10px; color: rgba(255,255,255,0.4); margin-top: 2px; }

.score-details { flex: 1; min-width: 200px; }

.tier-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 12px;
}

.score-meta { font-size: 13px; color: rgba(255,255,255,0.6); line-height: 1.8; }
.score-meta b { color: #fff; }

/* ── Feature breakdown bars ── */
.feature-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
    font-size: 12px;
}

.feature-label { color: rgba(255,255,255,0.5); width: 130px; flex-shrink: 0; }
.feature-track { flex: 1; height: 7px; border-radius: 4px; background: rgba(255,255,255,0.08); overflow: hidden; }
.feature-fill  { height: 100%; border-radius: 4px; transition: width 0.6s ease; }
.feature-val   { width: 36px; text-align: right; color: rgba(255,255,255,0.7); font-weight: 600; flex-shrink: 0; }

/* ── KPI grid ── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 18px;
}

.kpi {
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 12px;
    padding: 15px 14px;
    text-align: center;
}

.kpi-icon  { font-size: 18px; margin-bottom: 5px; }
.kpi-label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(255,255,255,0.4); margin-bottom: 5px; }
.kpi-value { font-size: 18px; font-weight: 700; color: #fff; }
.kpi-sub   { font-size: 10px; color: rgba(255,255,255,0.35); margin-top: 2px; }

/* ── Budget bar ── */
.budget-bar-wrap { margin: 14px 0; }
.budget-bar-labels { display: flex; justify-content: space-between; font-size: 11px; color: rgba(255,255,255,0.4); margin-bottom: 6px; }
.budget-bar-track  { height: 10px; border-radius: 5px; background: rgba(255,255,255,0.08); overflow: hidden; }
.budget-bar-fill   { height: 100%; border-radius: 5px; transition: width 0.5s ease; }

/* ── Projection box ── */
.projection-box {
    background: rgba(91,138,245,0.1);
    border: 1px solid rgba(91,138,245,0.25);
    border-radius: 12px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.projection-amount {
    font-size: 26px;
    font-weight: 800;
    color: #7ba4f8;
    flex-shrink: 0;
}

.projection-text { font-size: 13px; color: rgba(255,255,255,0.6); line-height: 1.5; }
.projection-text b { color: #fff; }

/* ── Trend chip ── */
.trend-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
}
.trend-up     { background: rgba(80,200,120,0.2); color: #50c878; border: 1px solid rgba(80,200,120,0.3); }
.trend-down   { background: rgba(224,122,122,0.2); color: #e07a7a; border: 1px solid rgba(224,122,122,0.3); }
.trend-stable { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.5); border: 1px solid rgba(255,255,255,0.1); }

/* ── Recommendation cards ── */
.rec-card {
    display: flex;
    gap: 14px;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 12px;
    border: 1px solid;
    transition: transform 0.12s;
}

.rec-card:hover { transform: translateX(4px); }

.rec-card:last-child { margin-bottom: 0; }

.rec-high   { background: rgba(224,122,122,0.08); border-color: rgba(224,122,122,0.25); }
.rec-medium { background: rgba(255,210,50,0.06);  border-color: rgba(255,210,50,0.2); }
.rec-low    { background: rgba(80,200,120,0.06);  border-color: rgba(80,200,120,0.2); }

.rec-icon { font-size: 22px; flex-shrink: 0; margin-top: 1px; }

.rec-title {
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 5px;
}

.rec-body { font-size: 13px; color: rgba(255,255,255,0.65); line-height: 1.55; }

.priority-pill {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-left: 8px;
    vertical-align: middle;
}

.pill-high   { background: rgba(224,122,122,0.25); color: #e07a7a; }
.pill-medium { background: rgba(255,210,50,0.25);  color: #ffd166; }
.pill-low    { background: rgba(80,200,120,0.25);  color: #50c878; }

/* ── Confidence ── */
.confidence-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid rgba(255,255,255,0.07);
    font-size: 12px;
    color: rgba(255,255,255,0.4);
}

.conf-track { flex: 1; height: 5px; border-radius: 3px; background: rgba(255,255,255,0.08); overflow: hidden; }
.conf-fill  { height: 100%; border-radius: 3px; background: #5b8af5; transition: width 0.5s ease; }

/* ── Footer ── */
.footer { text-align: center; padding: 14px; color: rgba(255,255,255,0.25); font-size: 12px; margin-top: 8px; }

/* ── Hamburger ── */
.hamburger {
    display: none;
    position: fixed; top: 14px; left: 14px;
    z-index: 200;
    background: #1a1f2e;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    width: 38px; height: 38px;
    cursor: pointer;
    flex-direction: column;
    align-items: center; justify-content: center;
    gap: 5px;
}
.hamburger span { display: block; width: 18px; height: 2px; background: #c5cad8; border-radius: 2px; }
.mob-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 99; }

/* ── Responsive ── */
@media (max-width: 900px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .sidebar { position: fixed !important; left: -260px !important; top: 0; bottom: 0; z-index: 100; width: 240px !important; transition: left 0.25s ease; overflow-y: auto; }
    .sidebar.open { left: 0 !important; }
    .mob-overlay.open { display: block; }
    .hamburger { display: flex; }
    .main { padding: 64px 14px 20px; }
    .score-hero { flex-direction: column; align-items: flex-start; gap: 16px; }
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .feature-label { width: 100px; }
}

@media (max-width: 480px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
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
            <h2>🤖 AI & ML Recommendation System</h2>
            <p><?= htmlspecialchars($barangay_name) ?> &nbsp;·&nbsp; FY <?= $budgetYear ?> Analysis</p>
        </div>

        <!-- ── ML Performance Score ── -->
        <div class="glass">
            <div class="glass-title">🧠 ML Performance Score</div>

            <div class="score-hero">
                <!-- Circular score ring -->
                <div class="score-ring-wrap">
                    <?php
                    $r = 54; $cx = 65; $cy = 65;
                    $circumference = 2 * M_PI * $r;
                    $dashOffset    = $circumference * (1 - $mlScore / 100);
                    ?>
                    <svg width="130" height="130" viewBox="0 0 130 130">
                        <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>"
                                fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="10"/>
                        <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $r ?>"
                                fill="none"
                                stroke="<?= htmlspecialchars($tierColor) ?>"
                                stroke-width="10"
                                stroke-linecap="round"
                                stroke-dasharray="<?= $circumference ?>"
                                stroke-dashoffset="<?= $dashOffset ?>"/>
                    </svg>
                    <div class="score-ring-center">
                        <div class="score-number" style="color:<?= htmlspecialchars($tierColor) ?>">
                            <?= $mlScore ?>
                        </div>
                        <div class="score-label">/ 100</div>
                    </div>
                </div>

                <!-- Details -->
                <div class="score-details">
                    <div class="tier-badge"
                         style="background:<?= htmlspecialchars($tierColor) ?>22;
                                color:<?= htmlspecialchars($tierColor) ?>;
                                border:1px solid <?= htmlspecialchars($tierColor) ?>55;">
                        <?= $tierIcon ?> <?= $tier ?>
                    </div>
                    <div class="score-meta">
                        <b>Participation Trend:</b>
                        <?= ucfirst($trend) ?>
                        <span class="trend-chip <?=
                            $trend === 'improving' ? 'trend-up' :
                            ($trend === 'declining' ? 'trend-down' : 'trend-stable')
                        ?>">
                            <?= $trend === 'improving' ? '↑ Up' : ($trend === 'declining' ? '↓ Down' : '→ Stable') ?>
                        </span>
                        <br>
                        <b>Approved Projects:</b> <?= $approvedProjects ?> of <?= $totalProjects ?>
                        (<?= round($approvalRate) ?>%)
                        <br>
                        <b>Avg Evaluation:</b> <?= round($avgEval, 1) ?>%
                        <br>
                        <b>Cost per Participant:</b>
                        <?= $costPerPax > 0 ? '₱' . number_format($costPerPax, 2) : '—' ?>
                    </div>
                </div>
            </div>

            <!-- Feature breakdown -->
            <div style="margin-top: 6px;">
                <?php
                $features = [
                    ['Budget Utilization', $budgetScore,     '#ffd166'],
                    ['Activity Score',     $activityScore,   '#7ba4f8'],
                    ['Quality / Eval',     $evalScore,       '#50c878'],
                    ['Project Approvals',  $approvalScore,   '#a78bfa'],
                    ['Engagement',         $engagementScore, '#fb923c'],
                ];
                foreach ($features as [$label, $val, $color]):
                ?>
                <div class="feature-row">
                    <div class="feature-label"><?= $label ?></div>
                    <div class="feature-track">
                        <div class="feature-fill"
                             style="width:<?= round(min(100,$val)) ?>%;background:<?= htmlspecialchars($color) ?>;"></div>
                    </div>
                    <div class="feature-val"><?= round($val) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Confidence -->
            <div class="confidence-row">
                <span>Model Confidence</span>
                <div class="conf-track">
                    <div class="conf-fill" style="width:<?= $confidence ?>%;"></div>
                </div>
                <span><?= $confidence ?>%</span>
                <span style="color:rgba(255,255,255,0.25);font-size:11px;">
                    (<?= $totalActivities ?> activities · <?= $totalProjects ?> projects · <?= $txCount ?> transactions)
                </span>
            </div>
        </div>

        <!-- ── KPI Summary ── -->
        <div class="kpi-grid">
            <div class="kpi">
                <div class="kpi-icon">💼</div>
                <div class="kpi-label">Total Budget</div>
                <div class="kpi-value">₱<?= number_format($totalBudget, 0) ?></div>
                <div class="kpi-sub">FY <?= $budgetYear ?></div>
            </div>
            <div class="kpi">
                <div class="kpi-icon">💸</div>
                <div class="kpi-label">Budget Used</div>
                <div class="kpi-value" style="color:#e07a7a;">₱<?= number_format($usedBudget, 0) ?></div>
                <div class="kpi-sub"><?= round($usageRatio, 1) ?>% utilized</div>
            </div>
            <div class="kpi">
                <div class="kpi-icon">🗓️</div>
                <div class="kpi-label">Activities</div>
                <div class="kpi-value"><?= $totalActivities ?></div>
                <div class="kpi-sub"><?= $completedActs ?> completed</div>
            </div>
            <div class="kpi">
                <div class="kpi-icon">👥</div>
                <div class="kpi-label">Participants</div>
                <div class="kpi-value"><?= number_format($totalParticipants) ?></div>
                <div class="kpi-sub">across all activities</div>
            </div>
        </div>

        <!-- ── Budget Analysis ── -->
        <div class="glass">
            <div class="glass-title">💰 Budget Analysis</div>

            <div class="budget-bar-wrap">
                <div class="budget-bar-labels">
                    <span>Used — ₱<?= number_format($usedBudget, 2) ?></span>
                    <span>Remaining — ₱<?= number_format($remaining, 2) ?></span>
                </div>
                <div class="budget-bar-track">
                    <div class="budget-bar-fill"
                         style="width:<?= min(100, round($usageRatio, 1)) ?>%;
                                background:<?= $usageRatio > 85 ? '#e07a7a' : ($usageRatio > 60 ? '#ffd166' : '#50c878') ?>;">
                    </div>
                </div>
                <div style="font-size:11px;color:rgba(255,255,255,0.3);margin-top:5px;">
                    <?= round($usageRatio, 1) ?>% of ₱<?= number_format($totalBudget, 2) ?> utilized
                </div>
            </div>

            <?php if ($projectedBudget > 0): ?>
            <div style="margin-top:16px;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:rgba(255,255,255,0.35);margin-bottom:10px;">
                    📌 Projected Next-Year Budget
                </div>
                <div class="projection-box">
                    <div class="projection-amount">₱<?= number_format($projectedBudget, 0) ?></div>
                    <div class="projection-text">
                        <b>Recommended for FY <?= $budgetYear + 1 ?></b><br>
                        <?= htmlspecialchars($projectedReason) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Smart Recommendations ── -->
        <div class="glass">
            <div class="glass-title">📌 Smart Recommendations
                <span style="margin-left:auto;font-size:10px;font-weight:400;letter-spacing:0;text-transform:none;color:rgba(255,255,255,0.25);">
                    <?= count($recommendations) ?> insight<?= count($recommendations) !== 1 ? 's' : '' ?> generated
                </span>
            </div>

            <?php foreach ($recommendations as $rec): ?>
            <div class="rec-card rec-<?= $rec['priority'] ?>">
                <div class="rec-icon"><?= $rec['icon'] ?></div>
                <div>
                    <div class="rec-title">
                        <?= htmlspecialchars($rec['title']) ?>
                        <span class="priority-pill pill-<?= $rec['priority'] ?>"><?= $rec['priority'] ?></span>
                    </div>
                    <div class="rec-body"><?= htmlspecialchars($rec['body']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>

            <div style="margin-top:16px;font-size:11px;color:rgba(255,255,255,0.2);text-align:center;line-height:1.6;">
                ⚠ These insights are generated automatically from your barangay's data.<br>
                All decisions remain at the discretion of authorized SK officials.
            </div>
        </div>

        <div class="footer">
            © 2026 SK Decision Support System &nbsp;|&nbsp; Powered by Local ML Engine
        </div>

    </div><!-- /.main -->
</div><!-- /.wrapper -->

<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
    document.getElementById('mobOverlay').classList.toggle('open');
}
</script>
</body>
</html>