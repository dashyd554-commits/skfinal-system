<div class="sidebar">

    <h2>MENU</h2>

    <?php if (isset($_SESSION['admin'])) { ?>

    <a href="admin_dashboard.php">🏠 <span>Dashboard</span></a>
    <a href="admin_pending.php">✅ <span>Approve User</span></a>
    <a href="admin_barangay_monitoring.php">📊 <span>Barangay Monitoring</span></a>
    <a href="admin_officials_information.php">🧑‍💼 <span>Officials Information</span></a>
    <a href="admin_audit_log.php">🕘 <span>History / Audit Log</span></a>

    <?php } elseif (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'chairman') { ?>

    <a href="../chairperson/chairperson_dashboard.php">🏠 <span>Dashboard</span></a>
    <a href="../chairperson/chairperson_propose_activity.php">📋 <span>Propose Activity</span></a>
    <a href="../chairperson/chairperson_propose_project.php">📁 <span>Propose Project</span></a>
    <a href="../chairperson/chairperson_status.php">📊 <span>Project/Activity Status</span></a>
    <a href="../chairperson/chairperson_prediction.php">🤖 <span>AI Prediction</span></a>
    <a href="../shared/shared_reports.php">📈 <span>Reports</span></a>
    <a href="../shared/shared_recommendation.php">💡 <span>Recommendations</span></a>

    <?php } elseif (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'secretary') { ?>

        <a href="../secretary/secretary_dashboard.php">🏠 <span>Dashboard</span></a>
        <a href="../secretary/secretary_sk_council.php">📄 <span>Add SK Council</span></a>
<a href="../secretary/secretary_pending.php">📂 <span>Pending Activity & Project</span></a>
<a href="../secretary/secretary_history.php">🕘 <span>History</span></a>
<a href="../shared/shared_reports.php">📊 <span>Reports</span></a>
<a href="../shared/shared_recommendation.php">💡 <span>Recommendations</span></a>

    <?php } elseif (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'treasurer') { ?>

        <a href="../treasurer/treasurer_dashboard.php">🏠 <span>Dashboard</span></a>
        <a href="../treasurer/treasurer_pending.php">📂 <span>Pending Activity & Project</span></a>
        <a href="../treasurer/treasurer_budget.php">💰 <span>Input Annual Budget</span></a>
        <a href="../treasurer/treasurer_spending_history.php">💸 <span>History of Spending</span></a>
        <a href="../shared/shared_reports.php">📊 <span>Reports</span></a>
        <a href="../shared/shared_recommendation.php">🤖 <span>Recommendations</span></a>

    <?php } ?>

    <a href="../auth/logout.php" class="logout">🚪 <span>Logout</span></a>

</div>