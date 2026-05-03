<?php
include '../config/db.php';

/* ================= KPI ================= */
function getCount($conn, $sql){
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchColumn();
}

$totalUsers = getCount($conn, "SELECT COUNT(*) FROM users");
$pendingUsers = getCount($conn, "SELECT COUNT(*) FROM users WHERE status='pending'");
$totalBarangays = getCount($conn, "SELECT COUNT(*) FROM barangays");
$totalProjects = getCount($conn, "SELECT COUNT(*) FROM projects WHERE status='approved'");
$totalBudget = getCount($conn, "SELECT COALESCE(SUM(total_amount),0) FROM budgets");

/* ================= BARANGAY ANALYTICS ================= */
$stmt = $conn->prepare("
    SELECT 
        b.id,
        b.barangay_name,
        COALESCE(SUM(a.participants),0) AS total_participants,
        COUNT(a.id) AS total_activities,
        COALESCE(SUM(a.allocated_budget),0) AS used_amount,
        COALESCE(bu.total_amount,0) AS total_amount
    FROM barangays b
    LEFT JOIN activities a ON a.barangay_id = b.id
    LEFT JOIN budgets bu ON bu.barangay_id = b.id
    GROUP BY b.id, bu.total_amount
");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= ML SCORE ================= */
function mlScore($d){
    $participants = $d['total_participants'];
    $activities = $d['total_activities'];
    $budgetUsed = $d['used_amount'];
    $budget = $d['total_amount'] ?: 1;

    $efficiency = ($budgetUsed > 0) ? ($participants / $budgetUsed) : 0;
    $budgetRatio = $budgetUsed / $budget;

    $score = ($efficiency * 50) + ($activities * 10) + ($budgetRatio * 40);
    return min(100, round($score,2));
}

foreach($data as $i => $d){
    $data[$i]['ml_score'] = mlScore($d);
}

usort($data, fn($a,$b) => $b['ml_score'] <=> $a['ml_score']);

$topBarangay = $data[0]['barangay_name'] ?? 'N/A';
$topScore = $data[0]['ml_score'] ?? 0;

/* ================= AUDIT ================= */
$stmt = $conn->prepare("
    SELECT action_type, action_time, username
    FROM audit_logs
    ORDER BY action_time DESC
    LIMIT 10
");
$stmt->execute();
$audit = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= RETURN JSON ================= */
echo json_encode([
    'kpi'=>[
        'users'=>$totalUsers,
        'pending'=>$pendingUsers,
        'barangays'=>$totalBarangays,
        'projects'=>$totalProjects,
        'budget'=>number_format($totalBudget)
    ],
    'top_barangay'=>$topBarangay,
    'top_score'=>$topScore,
    'labels'=>array_column($data,'barangay_name'),
    'scores'=>array_column($data,'ml_score'),
    'participants'=>array_column($data,'total_participants'),
    'activities'=>array_column($data,'total_activities'),
    'used_amount'=>array_column($data,'used_amount'),
    'audit'=>$audit
]);
?>