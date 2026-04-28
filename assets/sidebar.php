<div class="sidebar">

    <h2>MENU</h2>

    <?php if (isset($_SESSION['admin'])) { ?>

        <a href="admin_dashboard.php">🏠 <span>Dashboard</span></a>
        <a href="admin_users.php">👥 <span>Manage Users</span></a>
        <a href="admin_pending.php">⏳ <span>Pending Approval</span></a>

    <?php } elseif (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'chairman') { ?>

        <a href="../chairperson/chairperson_dashboard.php">🏠 <span>Dashboard</span></a>
        <a href="../chairperson/chairperson_projects.php">📁 <span>Projects</span></a>
        <a href="../chairperson/chairperson_reports.php">📊 <span>Reports</span></a>
        <a href="../chairperson/chairperson_prediction.php">🤖 <span>Prediction</span></a>
        <a href="../chairperson/chairperson_recommendation.php">💡 <span>Recommendation</span></a>

    <?php } elseif (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'secretary') { ?>

        <a href="../secretary/secretary_dashboard.php">🏠 <span>Dashboard</span></a>
        <a href="../secretary/secretary_activities.php">📌 <span>Activities</span></a>
        <a href="../secretary/secretary_reports.php">📊 <span>Reports</span></a>
        <a href="../secretary/secretary_recommendation.php">💡 <span>Recommendation</span></a>

    <?php } elseif (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'treasurer') { ?>

        <a href="../treasurer/treasurer_dashboard.php">🏠 <span>Dashboard</span></a>
        <a href="../treasurer/treasurer_budget.php">💰 <span>Budget</span></a>
        <a href="../treasurer/treasurer_reports.php">📊 <span>Reports</span></a>
        <a href="../treasurer/treasurer_recommendation.php">💡 <span>Recommendation</span></a>

    <?php } ?>

    <a href="../auth/logout.php" class="logout">🚪 <span>Logout</span></a>

</div>