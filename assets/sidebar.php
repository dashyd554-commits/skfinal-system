<?php
if (!isset($_SESSION)) {
    session_start();
}

$user = $_SESSION['user'] ?? null;

if (!$user) {
    exit();
}

$role = $user['role'];
?>

<style>
.sidebar {
    width: 220px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    background: #1f2937;
    color: white;
    padding-top: 20px;
    overflow-y: auto;
}

.sidebar h3 {
    text-align: center;
    margin-bottom: 20px;
}

.sidebar a {
    display: block;
    padding: 12px 20px;
    color: white;
    text-decoration: none;
    font-size: 14px;
}

.sidebar a:hover {
    background: #374151;
}

.main {
    margin-left: 220px;
    padding: 20px;
}

.badge {
    font-size: 12px;
    background: #10b981;
    padding: 3px 8px;
    border-radius: 10px;
}
</style>

<div class="sidebar">

    <h3>SK System</h3>

    <?php if ($role == 'admin') { ?>

    <h3>🛡 SYSTEM ADMIN</h3>

    <a href="admin_dashboard.php">Dashboard</a>
    <a href="admin_profile.php">Personal Information</a>
    <a href="admin_approve_users.php">Approve User</a>
    <a href="admin_barangay_monitoring.php">Barangay Monitoring</a>
    <a href="admin_officials.php">Officials Information</a>
    <a href="admin_audit_logs.php">History / Audit Log</a>

    <?php } ?>

    <?php if ($role == 'chairman') { ?>

        <a href="../chairman/chairperson_dashboard.php">🏠 Dashboard</a>
        <a href="../chairman/chairperson_propose_activity.php">📌 Propose Activity</a>
        <a href="../chairman/chairperson_propose_project.php">📁 Propose Project</a>
        <a href="../chairman/chairperson_status.php">📊 Project Status</a>
        <a href="../chairman/chairperson_prediction.php">🤖 AI Prediction</a>

    <?php } ?>

    <?php if ($role == 'secretary') { ?>

        <a href="../secretary/secretary_dashboard.php">🏠 Dashboard</a>
        <a href="../secretary/secretary_pending.php">⏳ Pending Projects</a>
        <a href="../secretary/secretary_council_vote.php">🗳 Council Voting</a>
        <a href="../secretary/secretary_history.php">📜 History</a>

    <?php } ?>

    <?php if ($role == 'treasurer') { ?>

        <a href="../treasurer/treasurer_dashboard.php">🏠 Dashboard</a>
        <a href="../treasurer/treasurer_pending.php">⏳ Pending Budget</a>
        <a href="../treasurer/treasurer_budget_input.php">💰 Input Annual Budget</a>
        <a href="../treasurer/treasurer_history.php">📜 Spending History</a>

    <?php } ?>

    <hr style="border:1px solid #374151;">

    <a href="../shared/reports.php">📊 Reports</a>
    <a href="../shared/recommendations.php">🤖 Recommendations</a>

    <hr style="border:1px solid #374151;">

    <a href="../logout.php">🚪 Logout</a>

</div>