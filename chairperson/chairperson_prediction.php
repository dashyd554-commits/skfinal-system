<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'chairman') {
    header("Location: ../index.php");
    exit();
}

$barangay_id = $_SESSION['user']['barangay_id'];

/* ================= LOAD ML JSON ================= */
$mlFile = "../ml/ml_results.json";
$results = [];

if(file_exists($mlFile)){
    $json = file_get_contents($mlFile);
    $decoded = json_decode($json,true);

    if(is_array($decoded)){
        foreach($decoded as $row){
            if(($row['barangay_id'] ?? 0) == $barangay_id){
                $results[] = $row;
            }
        }
    }
}

/* ================= DEFAULT VALUES ================= */
$totalParticipants = 0;
$totalActivities = count($results);
$topActivity = "No Data";
$topImpact = 0;
$avgImpact = 0;
$avgUtilization = 0;
$topRecommendation = "No Recommendation";

function normalizeScore($score){
    $score = floatval($score);
    if($score < 0) return 0;
    if($score > 100) return 100;
    return round($score,2);
}

/* ================= PROCESS ML ================= */
if(!empty($results)){

    usort($results, fn($a,$b)=>$b['predicted_impact'] <=> $a['predicted_impact']);

    $impactSum = 0;
    $utilSum = 0;

    foreach($results as $r){
        $totalParticipants += (int)($r['participants'] ?? 0);
        $impactSum += (float)($r['predicted_impact'] ?? 0);
        $utilSum += (float)($r['budget_utilization'] ?? 0);
    }

    $avgImpact = normalizeScore($impactSum / count($results));
    $avgUtilization = normalizeScore($utilSum / count($results));

    $topActivity = $results[0]['title'] ?? 'N/A';
    $topImpact = normalizeScore($results[0]['predicted_impact'] ?? 0);
    $topRecommendation = $results[0]['recommendation'] ?? 'Maintain';
}

/* ================= GET REAL BUDGET ================= */
$stmt = $conn->prepare("
    SELECT total_amount, used_amount, remaining_budget
    FROM budgets
    WHERE barangay_id = :bid
    ORDER BY year DESC
    LIMIT 1
");
$stmt->execute([':bid'=>$barangay_id]);
$budgetData = $stmt->fetch(PDO::FETCH_ASSOC);

$annualBudget = $budgetData['total_amount'] ?? 0;
$usedBudget = $budgetData['used_amount'] ?? 0;
$remainingBudget = $budgetData['remaining_budget'] ?? ($annualBudget - $usedBudget);

/* ================= AI CONCLUSION ================= */
if($avgImpact >= 80){
    $conclusion = "AI analysis shows this barangay is highly efficient in implementing youth-centered programs with strong projected municipal impact.";
    $impact = "Municipal funding may be expanded for larger youth development initiatives.";
}
elseif($avgImpact >= 60){
    $conclusion = "AI analysis shows moderate barangay performance with several successful activities requiring continuity and monitoring.";
    $impact = "Maintain current budget while strengthening participation strategy.";
}
else{
    $conclusion = "AI analysis shows low predicted impact. Existing activities require restructuring for stronger youth engagement.";
    $impact = "Budget optimization and improved project planning are strongly advised.";
}

/* ================= SMART AI SUGGESTIONS ================= */
$suggestions = [];
$suggestions[] = "Prioritize replication of high-performing activity: ".$topActivity;
$suggestions[] = "Increase youth attendance using digital survey and incentive campaigns.";
$suggestions[] = "Allocate more resources to programs with high AI impact score.";
$suggestions[] = "Reduce spending on low-engagement activity patterns.";
$suggestions[] = "Strengthen council project generation to improve implementation confidence.";

/* ================= BUDGET FORECAST ================= */
$growthRate = $avgImpact / 100;
$projectedIncrease = $remainingBudget * ($growthRate * 0.30);
$futureBudget = $remainingBudget + $projectedIncrease;
?>