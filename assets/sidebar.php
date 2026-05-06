<?php
// sidebar.php - Collapsible sub-sidebar navigation
// Usage: include this file in your pages
?>

<style>
.sidebar {
    width: 240px;
    min-height: 100vh;
    background:rgba(255,255,255,0.18);
    backdrop-filter:blur(500px);
    padding: 0;
    display: flex;
    flex-direction: column;
    font-family: 'Segoe UI', system-ui, sans-serif;
    position: relative;
    box-sizing: border-box;
}

.sidebar h2 {
    color: #1e3c72;
    font-size: 11px;
    font-weight: bold;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 24px 20px 12px;
    margin: 0;
}

/* ── Direct links (no sub-menu) ── */
.sidebar a.nav-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    color: #c5cad8 !important;
    text-decoration: none !important;
    font-size: 13.5px;
    transition: background 0.15s, color 0.15s;
    position: relative;
}

.sidebar a.nav-link:hover,
.sidebar a.nav-link.active {
    background: rgba(255,255,255,0.06);
    color: #ffffff !important;
}

.sidebar a.nav-link.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 6px;
    bottom: 6px;
    width: 3px;
    background: #5b8af5;
    border-radius: 0 3px 3px 0;
}

.nav-icon {
    font-size: 15px;
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}

/* ── Group (has sub-menu) — uses native <details>/<summary> ── */
.nav-group {
    display: flex;
    flex-direction: column;
}

.nav-group summary {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    color: #c5cad8 !important;
    font-size: 13.5px;
    cursor: pointer;
    user-select: none;
    list-style: none;
    transition: background 0.15s, color 0.15s;
    background: transparent;
}

.nav-group summary::-webkit-details-marker { display: none; }

.nav-group summary:hover {
    background: rgba(255,255,255,0.06);
    color: #ffffff !important;
}

.nav-group[open] summary {
    color: #ffffff !important;
    background: rgba(255,255,255,0.04);
}

.nav-group[open] summary .chevron {
    transform: rotate(90deg);
}

.chevron {
    margin-left: auto;
    font-size: 10px;
    color: #8b93a7;
    transition: transform 0.2s ease;
    flex-shrink: 0;
}

/* ── Sub-menu ── */
.sub-menu {
    background: #131722;
}

.sub-menu a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 20px 8px 44px;
    color: #9aa0b0 !important;
    text-decoration: none !important;
    font-size: 13px;
    transition: background 0.15s, color 0.15s;
    position: relative;
}

.sub-menu a:hover {
    background: rgba(255,255,255,0.06);
    color: #ffffff !important;
}

.sub-menu a.active {
    color: #7ba4f8 !important;
}

.sub-menu a::before {
    content: '·';
    position: absolute;
    left: 28px;
    color: #4a5068;
    font-size: 16px;
    line-height: 1;
}

/* ── Section divider ── */
.nav-divider {
    height: 1px;
    background: rgba(255,255,255,0.06);
    margin: 8px 16px;
}

/* ── Logout ── */
.sidebar .logout {
    margin-top: auto;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 20px;
    color: #e07a7a;
    text-decoration: none;
    font-size: 13.5px;
    border-top: 1px solid rgba(255,255,255,0.06);
    transition: background 0.15s;
}

.sidebar .logout:hover {
    background: rgba(224,122,122,0.1);
}
</style>

<div class="sidebar">
    <h2>MENU</h2>

    <?php
    $current = basename($_SERVER['PHP_SELF']);
    ?>

    <?php if (isset($_SESSION['admin'])) : ?>

        <a href="admin_dashboard.php" class="nav-link <?= $current === 'admin_dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏠</span><span>Dashboard</span>
        </a>

        <!-- Officials Management group -->
        <details class="nav-group" <?= in_array($current, ['admin_pending.php','admin_officials_information.php']) ? 'open' : '' ?>>
            <summary>
                <span class="nav-icon">👮</span>
                <span>Officials</span>
                <span class="chevron">▶</span>
            </summary>
            <div class="sub-menu">
                <a href="admin_pending.php" class="<?= $current === 'admin_pending.php' ? 'active' : '' ?>">
                    <span>Add SK Officials</span>
                </a>
                <a href="admin_officials_information.php" class="<?= $current === 'admin_officials_information.php' ? 'active' : '' ?>">
                    <span>Officials Information</span>
                </a>
            </div>
        </details>

        <!-- Monitoring group -->
        <!-- Barangay Monitoring group -->
<details class="nav-group" <?= in_array($current, [
    'admin_weekly_monitoring.php',
    'admin_monthly_monitoring.php',
    'admin_yearly_monitoring.php'
]) ? 'open' : '' ?>>

    <summary>
        <span class="nav-icon">📊</span>
        <span>Monitoring</span>
        <span class="chevron">▶</span>
    </summary>

    <div class="sub-menu">
    <a href="admin_barangay_proposal_view.php"
           class="<?= $current === 'admin_barangay_proposal_view.php' ? 'active' : '' ?>">
            <span>View Proposal</span>

    <a href="admin_barangay_monitoring.php"
           class="<?= $current === 'admin_barangay_monitoring.php' ? 'active' : '' ?>">
            <span>Barangay Monitoring</span>
        </a>

        <a href="admin_weekly_monitoring.php"
           class="<?= $current === 'admin_weekly_monitoring.php' ? 'active' : '' ?>">
            <span>Weekly Monitoring</span>
        </a>

        <a href="admin_monthly_monitoring.php"
           class="<?= $current === 'admin_monthly_monitoring.php' ? 'active' : '' ?>">
            <span>Monthly Monitoring</span>
        </a>

        <a href="admin_yearly_monitoring.php"
           class="<?= $current === 'admin_yearly_monitoring.php' ? 'active' : '' ?>">
            <span>Yearly Monitoring</span>
        </a>

    </div>
</details>
<a href="admin_audit_log.php"
   class="nav-link <?= $current === 'admin_audit_log.php' ? 'active' : '' ?>">
    <span class="nav-icon">🧾</span>
    <span>History / Audit Log</span>
</a>
    <?php elseif (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'chairman') : ?>

        <a href="../chairperson/chairperson_dashboard.php" class="nav-link <?= $current === 'chairperson_dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏠</span><span>Dashboard</span>
        </a>

        <!-- Proposals group -->
        <details class="nav-group" <?= in_array($current, ['chairperson_propose_activity.php','chairperson_propose_project.php','chairperson_status.php']) ? 'open' : '' ?>>
            <summary>
                <span class="nav-icon">📋</span>
                <span>Proposals</span>
                <span class="chevron">▶</span>
            </summary>
            <div class="sub-menu">
                <a href="../chairperson/chairperson_propose_activity.php" class="<?= $current === 'chairperson_propose_activity.php' ? 'active' : '' ?>">
                    <span>Propose Activity</span>
                </a>
                <a href="../chairperson/chairperson_propose_project.php" class="<?= $current === 'chairperson_propose_project.php' ? 'active' : '' ?>">
                    <span>Propose Project</span>
                </a>
                <a href="../chairperson/chairperson_status.php" class="<?= $current === 'chairperson_status.php' ? 'active' : '' ?>">
                    <span>Project / Activity Status</span>
                </a>
            </div>
        </details>

        <!-- Insights group -->
        <details class="nav-group" <?= in_array($current, ['chairperson_prediction.php','shared_reports.php','shared_recommendation.php']) ? 'open' : '' ?>>
            <summary>
                <span class="nav-icon">🤖</span>
                <span>Insights</span>
                <span class="chevron">▶</span>
            </summary>
            <div class="sub-menu">
                <a href="../chairperson/chairperson_prediction.php" class="<?= $current === 'chairperson_prediction.php' ? 'active' : '' ?>">
                    <span>AI Prediction</span>
                </a>
                <a href="../shared/shared_reports.php" class="<?= $current === 'shared_reports.php' ? 'active' : '' ?>">
                    <span>Reports</span>
                </a>
                <a href="../shared/shared_recommendation.php" class="<?= $current === 'shared_recommendation.php' ? 'active' : '' ?>">
                    <span>Recommendations</span>
                </a>
            </div>
        </details>

    <?php elseif (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'secretary') : ?>

        <a href="../secretary/secretary_dashboard.php" class="nav-link <?= $current === 'secretary_dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏠</span><span>Dashboard</span>
        </a>

        <!-- Records group -->
        <details class="nav-group" <?= in_array($current, ['secretary_sk_council.php','secretary_pending.php','secretary_history.php']) ? 'open' : '' ?>>
            <summary>
                <span class="nav-icon">📄</span>
                <span>Records</span>
                <span class="chevron">▶</span>
            </summary>
            <div class="sub-menu">
                <a href="../secretary/secretary_sk_council.php" class="<?= $current === 'secretary_sk_council.php' ? 'active' : '' ?>">
                    <span>Add SK Council</span>
                </a>
                <a href="../secretary/secretary_pending.php" class="<?= $current === 'secretary_pending.php' ? 'active' : '' ?>">
                    <span>Pending Activity & Project</span>
                </a>
                <a href="../secretary/secretary_history.php" class="<?= $current === 'secretary_history.php' ? 'active' : '' ?>">
                    <span>History</span>
                </a>
            </div>
        </details>

        <!-- Insights group -->
        <details class="nav-group" <?= in_array($current, ['shared_reports.php','shared_recommendation.php']) ? 'open' : '' ?>>
            <summary>
                <span class="nav-icon">📊</span>
                <span>Insights</span>
                <span class="chevron">▶</span>
            </summary>
            <div class="sub-menu">
                <a href="../shared/shared_reports.php" class="<?= $current === 'shared_reports.php' ? 'active' : '' ?>">
                    <span>Reports</span>
                </a>
                <a href="../shared/shared_recommendation.php" class="<?= $current === 'shared_recommendation.php' ? 'active' : '' ?>">
                    <span>Recommendations</span>
                </a>
            </div>
        </details>

    <?php elseif (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'treasurer') : ?>

        <a href="../treasurer/treasurer_dashboard.php" class="nav-link <?= $current === 'treasurer_dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏠</span><span>Dashboard</span>
        </a>

        <!-- Budget group -->
        <details class="nav-group" <?= in_array($current, ['treasurer_pending.php','treasurer_budget.php','treasurer_spending_history.php']) ? 'open' : '' ?>>
            <summary>
                <span class="nav-icon">💰</span>
                <span>Budget & Finance</span>
                <span class="chevron">▶</span>
            </summary>
            <div class="sub-menu">
                <a href="../treasurer/treasurer_pending.php" class="<?= $current === 'treasurer_pending.php' ? 'active' : '' ?>">
                    <span>Pending Activity & Project</span>
                </a>
                <a href="../treasurer/treasurer_budget.php" class="<?= $current === 'treasurer_budget.php' ? 'active' : '' ?>">
                    <span>Input Annual Budget</span>
                </a>
                <a href="../treasurer/treasurer_expenses_input.php" class="<?= $current === 'treasurer_expenses_input.php' ? 'active' : '' ?>">
                <span>Record Expenses</span>
                <a href="../treasurer/treasurer_spending_history.php" class="<?= $current === 'treasurer_spending_history.php' ? 'active' : '' ?>">
                    <span>History of Spending</span>
                </a>
            </div>
        </details>

        <!-- Insights group -->
        <details class="nav-group" <?= in_array($current, ['shared_reports.php','shared_recommendation.php']) ? 'open' : '' ?>>
            <summary>
                <span class="nav-icon">📊</span>
                <span>Insights</span>
                <span class="chevron">▶</span>
            </summary>
            <div class="sub-menu">
                <a href="../shared/shared_reports.php" class="<?= $current === 'shared_reports.php' ? 'active' : '' ?>">
                    <span>Reports</span>
                </a>
                <a href="../shared/shared_recommendation.php" class="<?= $current === 'shared_recommendation.php' ? 'active' : '' ?>">
                    <span>Recommendations</span>
                </a>
            </div>
        </details>

    <?php endif; ?>

    <div style="flex: 1;"></div>
    <a href="../auth/logout.php" class="logout">
        <span class="nav-icon">🚪</span><span>Logout</span>
    </a>
</div>