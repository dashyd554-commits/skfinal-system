<?php
session_start();
include '../../config/db.php';

header('Content-Type: application/json');

/* ================= KPI ================= */
function countData($conn, $sql) {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchColumn();
}

$data = [];

/* BASIC COUNTS */
$data['barangays'] = countData($conn, "SELECT COUNT(*) FROM barangays");
$data['users'] = countData($conn, "SELECT COUNT(*) FROM users");
$data['pending_users'] = countData($conn, "SELECT COUNT(*) FROM users WHERE status='pending'");
$data['approved_projects'] = countData($conn, "SELECT COUNT(*) FROM projects WHERE status='approved'");
$data['total_budget'] = countData($conn, "SELECT COALESCE(SUM(annual_budget),0) FROM budgets");

/* TOP BARANGAY */
$stmt = $conn->query("
    SELECT b.barangay_name,
           COALESCE(SUM(a.participants),0) AS total
    FROM barangays b
    LEFT JOIN activities a ON a.barangay_id = b.id
    GROUP BY b.id
    ORDER BY total DESC
    LIMIT 1
");

$top = $stmt->fetch(PDO::FETCH_ASSOC);

$data['top_barangay'] = $top['barangay_name'] ?? 'N/A';

/* AUDIT FEED */
$stmt = $conn->query("
    SELECT a.action, a.log_time, u.username
    FROM audit_logs a
    LEFT JOIN users u ON u.id = a.user_id
    ORDER BY a.log_time DESC
    LIMIT 5
");

$data['logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($data);