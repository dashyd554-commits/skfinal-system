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

/* ── Preserve form values on error ── */
$f_title       = "";
$f_amount      = "";
$f_category    = "";
$f_date        = date('Y-m-d');
$f_description = "";

/* ================= SAVE EXPENSE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $f_title       = trim($_POST['title']       ?? '');
    $f_amount      = floatval($_POST['amount']   ?? 0);
    $f_category    = trim($_POST['category']     ?? '');
    $f_date        = trim($_POST['expense_date'] ?? date('Y-m-d'));
    $f_description = trim($_POST['description']  ?? '');

    if (empty($f_title)) {
        $message = "Expense title is required.";
        $messageType = "error";
    } elseif ($f_amount <= 0) {
        $message = "Please enter a valid amount greater than ₱0.";
        $messageType = "error";
    } elseif (empty($f_date)) {
        $message = "Expense date is required.";
        $messageType = "error";
    } else {

        /* get latest budget */
        $stmt = $conn->prepare("SELECT * FROM budgets WHERE barangay_id = ? ORDER BY year DESC LIMIT 1");
        $stmt->execute([$barangay_id]);
        $budget = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$budget) {
            $message = "No annual budget found. Please set up a budget first.";
            $messageType = "error";
        } else {
            $remaining = $budget['total_amount'] - $budget['used_amount'];

            if ($remaining < $f_amount) {
                $message = "Insufficient remaining budget. Available: ₱" . number_format($remaining, 2);
                $messageType = "error";
            } else {
                $newUsed    = $budget['used_amount'] + $f_amount;
                $newRemain  = $budget['total_amount'] - $newUsed;

                /* update budget */
                $stmt = $conn->prepare("UPDATE budgets SET used_amount = ?, remaining_budget = ? WHERE id = ?");
                $stmt->execute([$newUsed, $newRemain, $budget['id']]);

                /* build description string */
                $fullDesc = "[{$f_title}]";
                if ($f_category) $fullDesc .= " [{$f_category}]";
                if ($f_description) $fullDesc .= " — {$f_description}";

                /* insert transaction */
                $stmt = $conn->prepare("
                    INSERT INTO budget_transactions (barangay_id, project_id, amount, description, created_at)
                    VALUES (?, NULL, ?, ?, ?)
                ");
                $stmt->execute([$barangay_id, $f_amount, $fullDesc, $f_date . ' ' . date('H:i:s')]);

                $message     = "Expense successfully recorded and deducted from the budget.";
                $messageType = "success";
                /* reset form fields */
                $f_title = $f_amount = $f_category = $f_description = "";
                $f_date  = date('Y-m-d');

                /* reload budget totals after saving */
                $stmt = $conn->prepare("SELECT * FROM budgets WHERE barangay_id = ? ORDER BY year DESC LIMIT 1");
                $stmt->execute([$barangay_id]);
                $currentBudget   = $stmt->fetch(PDO::FETCH_ASSOC);
                $totalBudget     = $currentBudget['total_amount']     ?? 0;
                $usedBudget      = $currentBudget['used_amount']      ?? 0;
                $remainingBudget = $currentBudget['remaining_budget'] ?? ($totalBudget - $usedBudget);
                $usedPct         = $totalBudget > 0 ? round($usedBudget / $totalBudget * 100, 1) : 0;
            }
        }
    }
}

/* (success is now set inline after POST, no redirect needed) */

/* ================= CURRENT BUDGET ================= */
$stmt = $conn->prepare("SELECT * FROM budgets WHERE barangay_id = ? ORDER BY year DESC LIMIT 1");
$stmt->execute([$barangay_id]);
$currentBudget = $stmt->fetch(PDO::FETCH_ASSOC);

$totalBudget     = $currentBudget['total_amount']     ?? 0;
$usedBudget      = $currentBudget['used_amount']      ?? 0;
$remainingBudget = $currentBudget['remaining_budget'] ?? ($totalBudget - $usedBudget);
$usedPct         = $totalBudget > 0 ? round($usedBudget / $totalBudget * 100, 1) : 0;

/* ================= FILTERS ================= */
$filterMonth = $_GET['month'] ?? '';
$filterCat   = $_GET['cat']   ?? '';
$searchQ     = trim($_GET['q'] ?? '');

$sql    = "SELECT * FROM budget_transactions WHERE barangay_id = ? AND project_id IS NULL";
$params = [$barangay_id];

if ($filterMonth !== '') {
    $sql    .= " AND TO_CHAR(created_at, 'YYYY-MM') = ?";
    $params[] = $filterMonth;
}
if ($searchQ !== '') {
    $sql    .= " AND description ILIKE ?";
    $params[] = "%{$searchQ}%";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$manualExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* total of filtered results */
$filteredTotal = array_sum(array_column($manualExpenses, 'amount'));

/* categories for the form */
$categories = [
    'Office Supplies', 'Event/Activity', 'Transportation',
    'Food & Catering', 'Utilities', 'Maintenance', 'Livelihood', 'Other'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Treasurer — Input Expenses</title>

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

/* ── Page header ── */
.page-header { margin-bottom: 24px; }
.page-header h2 {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    text-shadow: 0 1px 6px rgba(0,0,0,0.4);
}
.page-header p { color: rgba(255,255,255,0.6); font-size: 13px; margin-top: 4px; }

/* ── Alert ── */
.alert {
    border-radius: 10px;
    padding: 13px 16px;
    font-size: 13px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
}
.alert-success { background: rgba(80,200,120,0.15); border: 1px solid rgba(80,200,120,0.35); color: #6de89a; }
.alert-error   { background: rgba(224,122,122,0.15); border: 1px solid rgba(224,122,122,0.35); color: #e07a7a; }

/* ── KPI grid ── */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}

.kpi {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 14px;
    padding: 18px 16px;
    text-align: center;
    backdrop-filter: blur(50px);
}

.kpi-icon  { font-size: 20px; margin-bottom: 6px; }
.kpi-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: rgba(255,255,255,0.5); margin-bottom: 6px; }
.kpi-value { font-size: 20px; font-weight: 700; color: #fff; }
.kpi-sub   { font-size: 11px; color: rgba(255,255,255,0.4); margin-top: 3px; }

/* ── Budget bar ── */
.budget-bar-wrap { margin-top: 10px; }
.budget-bar-track { height: 8px; border-radius: 4px; background: rgba(255,255,255,0.1); overflow: hidden; }
.budget-bar-fill  { height: 100%; border-radius: 4px; transition: width 0.4s ease; }

/* ── Content grid ── */
.content-grid {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 18px;
    align-items: start;
}

/* ── Glass card ── */
.glass {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(500px);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 16px;
    padding: 22px;
    margin-bottom: 18px;
}

.glass-title {
    font-size: 14px;
    font-weight: 700;
    color: #e8eaf0;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ── Form ── */
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

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

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 13px;
    border-radius: 9px;
    border: 1px solid rgba(255,255,255,0.15);
    background: rgba(255,255,255,0.08);
    color: #fff;
    font-size: 13.5px;
    outline: none;
    transition: border 0.15s, background 0.15s;
    font-family: inherit;
}

.form-group input::placeholder,
.form-group textarea::placeholder { color: rgba(255,255,255,0.3); }

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #5b8af5;
    background: rgba(255,255,255,0.12);
}

.form-group select option { background: #1a1f2e; color: #fff; }
.form-group textarea { resize: vertical; min-height: 80px; }

.hint { font-size: 11px; color: rgba(255,255,255,0.35); margin-top: 4px; }

.divider { height: 1px; background: rgba(255,255,255,0.07); margin: 16px 0; }

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
}
.btn-submit:hover { background: #3a6fd8; transform: translateY(-1px); }

/* ── Filter bar ── */
.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
    align-items: flex-end;
}

.filter-bar input,
.filter-bar select {
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.15);
    background: rgba(255,255,255,0.08);
    color: #fff;
    font-size: 13px;
    outline: none;
    min-width: 140px;
}

.filter-bar input::placeholder { color: rgba(255,255,255,0.3); }
.filter-bar select option { background: #1a1f2e; }

.btn-filter {
    padding: 8px 16px;
    border-radius: 8px;
    border: none;
    background: #5b8af5;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}
.btn-filter:hover { background: #3a6fd8; }

.btn-reset {
    padding: 8px 14px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.2);
    background: transparent;
    color: rgba(255,255,255,0.6);
    font-size: 13px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    transition: background 0.15s;
}
.btn-reset:hover { background: rgba(255,255,255,0.08); }

/* ── Table ── */
.table-wrap { overflow-x: auto; }

table { width: 100%; border-collapse: collapse; min-width: 580px; }

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
    padding: 12px 14px;
    font-size: 13px;
    color: rgba(255,255,255,0.85);
    border-bottom: 1px solid rgba(255,255,255,0.05);
    vertical-align: middle;
}

tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,0.04); }

tfoot td {
    padding: 11px 14px;
    font-size: 13px;
    font-weight: 700;
    color: #ffd166;
    border-top: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.03);
}

/* ── Category chip ── */
.cat-chip {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    background: rgba(91,138,245,0.2);
    color: #7ba4f8;
    border: 1px solid rgba(91,138,245,0.3);
}

.empty-state {
    text-align: center;
    padding: 32px;
    color: rgba(255,255,255,0.3);
    font-size: 13px;
}

/* ── Footer ── */
.footer {
    text-align: center;
    padding: 14px;
    color: rgba(255,255,255,0.3);
    font-size: 12px;
    margin-top: 8px;
}

/* ── Mobile hamburger ── */
.hamburger {
    display: none;
    position: fixed;
    top: 14px; left: 14px;
    z-index: 200;
    background: #1a1f2e;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px;
    width: 38px; height: 38px;
    cursor: pointer;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 5px;
}
.hamburger span { display: block; width: 18px; height: 2px; background: #c5cad8; border-radius: 2px; }
.mob-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 99; }

/* ── Responsive ── */
@media (max-width: 1100px) {
    .content-grid { grid-template-columns: 1fr; }
}

@media (max-width: 900px) {
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .sidebar {
        position: fixed !important;
        left: -260px !important;
        top: 0; bottom: 0;
        z-index: 100;
        width: 240px !important;
        transition: left 0.25s ease;
        overflow-y: auto;
    }
    .sidebar.open { left: 0 !important; }
    .mob-overlay.open { display: block; }
    .hamburger { display: flex; }
    .main { padding: 64px 14px 20px; }
    .form-row { grid-template-columns: 1fr; }
    .kpi-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 480px) {
    .kpi-grid { grid-template-columns: 1fr 1fr; }
    .kpi-value { font-size: 16px; }
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
            <h2>🧾 Manual Expense Input</h2>
            <p>Record and track all manual expenses against the annual budget</p>
        </div>

        <?php if ($message !== ''): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= $messageType === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi">
                <div class="kpi-icon">💼</div>
                <div class="kpi-label">Total Budget</div>
                <div class="kpi-value">₱<?= number_format($totalBudget, 2) ?></div>
                <div class="kpi-sub">FY <?= $currentBudget['year'] ?? date('Y') ?></div>
            </div>
            <div class="kpi">
                <div class="kpi-icon">💸</div>
                <div class="kpi-label">Total Spent</div>
                <div class="kpi-value" style="color:#e07a7a;">₱<?= number_format($usedBudget, 2) ?></div>
                <div class="kpi-sub"><?= $usedPct ?>% utilized</div>
            </div>
            <div class="kpi">
                <div class="kpi-icon">💰</div>
                <div class="kpi-label">Remaining</div>
                <div class="kpi-value" style="color:#50c878;">₱<?= number_format($remainingBudget, 2) ?></div>
                <div class="kpi-sub"><?= round(100 - $usedPct, 1) ?>% available</div>
            </div>
            <div class="kpi">
                <div class="kpi-icon">📋</div>
                <div class="kpi-label">Transactions</div>
                <div class="kpi-value"><?= count($manualExpenses) ?></div>
                <div class="kpi-sub">manual expenses</div>
            </div>
        </div>

        <!-- Budget utilization bar -->
        <div class="glass" style="padding:16px 22px;margin-bottom:18px;">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:rgba(255,255,255,0.5);margin-bottom:8px;">
                <span>Budget Utilization</span>
                <span><?= $usedPct ?>% used</span>
            </div>
            <div class="budget-bar-track">
                <div class="budget-bar-fill" style="width:<?= min($usedPct,100) ?>%;background:<?= $usedPct > 85 ? '#e07a7a' : ($usedPct > 60 ? '#ffd166' : '#50c878') ?>;"></div>
            </div>
        </div>

        <div class="content-grid">

            <!-- ── Form ── -->
            <div class="glass">
                <div class="glass-title">➕ New Expense</div>

                <form method="POST" action="" onsubmit="return confirmSubmit()">

                    <div class="form-group">
                        <label>Expense Title *</label>
                        <input type="text" name="title"
                               placeholder="e.g. Pasko Party Supplies"
                               value="<?= htmlspecialchars($f_title) ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Amount (₱) *</label>
                            <input type="number" name="amount" step="0.01" min="0.01"
                                   placeholder="0.00"
                                   value="<?= htmlspecialchars($f_amount ?: '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Date of Expense *</label>
                            <input type="date" name="expense_date"
                                   value="<?= htmlspecialchars($f_date) ?>"
                                   max="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="">— Select Category —</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= $f_category === $cat ? 'selected' : '' ?>>
                                <?= $cat ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Description / Notes</label>
                        <textarea name="description"
                                  placeholder="Additional details about this expense…"><?= htmlspecialchars($f_description) ?></textarea>
                        <div class="hint">Optional — use this for receipts, references, or remarks.</div>
                    </div>

                    <div class="divider"></div>

                    <div style="background:rgba(255,210,50,0.08);border:1px solid rgba(255,210,50,0.2);border-radius:9px;padding:12px 14px;margin-bottom:14px;font-size:12px;color:rgba(255,255,255,0.6);">
                        ⚠️ Available budget: <b style="color:#ffd166;">₱<?= number_format($remainingBudget, 2) ?></b>.
                        This expense will be immediately deducted.
                    </div>

                    <button type="submit" class="btn-submit">💾 Save Expense</button>

                </form>
            </div>

            <!-- ── History ── -->
            <div class="glass">
                <div class="glass-title">
                    📋 Expense History
                    <span style="margin-left:auto;font-size:11px;font-weight:400;color:rgba(255,255,255,0.3);">
                        <?= count($manualExpenses) ?> record<?= count($manualExpenses) !== 1 ? 's' : '' ?>
                    </span>
                </div>

                <!-- Filters -->
                <form method="GET" action="">
                    <div class="filter-bar">
                        <input type="text" name="q"
                               placeholder="Search title or description…"
                               value="<?= htmlspecialchars($searchQ) ?>">
                        <input type="month" name="month"
                               value="<?= htmlspecialchars($filterMonth) ?>"
                               title="Filter by month">
                        <button type="submit" class="btn-filter">🔍</button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn-reset">✕ Reset</a>
                    </div>
                </form>

                <div class="table-wrap">
                    <?php if (!empty($manualExpenses)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title / Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($manualExpenses as $i => $m):
                            /* parse stored description: [Title] [Category] — notes */
                            preg_match('/^\[([^\]]+)\](?:\s*\[([^\]]+)\])?(?:\s*—\s*(.*))?$/', $m['description'], $parts);
                            $t_title = $parts[1] ?? $m['description'];
                            $t_cat   = $parts[2] ?? '';
                            $t_notes = $parts[3] ?? '';
                        ?>
                        <tr>
                            <td style="color:rgba(255,255,255,0.3);font-size:12px;"><?= $i + 1 ?></td>
                            <td>
                                <div style="font-weight:600;color:#fff;"><?= htmlspecialchars($t_title) ?></div>
                                <?php if ($t_cat): ?>
                                <div style="margin-top:4px;"><span class="cat-chip"><?= htmlspecialchars($t_cat) ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td style="color:rgba(255,255,255,0.55);font-size:12px;">
                                <?= $t_notes ? htmlspecialchars($t_notes) : '—' ?>
                            </td>
                            <td style="color:#ffd166;font-weight:700;">
                                ₱<?= number_format($m['amount'], 2) ?>
                            </td>
                            <td style="color:#8b93a7;font-size:12px;white-space:nowrap;">
                                <?= date('M d, Y', strtotime($m['created_at'])) ?>
                                <div style="color:rgba(255,255,255,0.25);font-size:11px;">
                                    <?= date('h:i A', strtotime($m['created_at'])) ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="color:rgba(255,255,255,0.4);font-weight:500;">
                                    <?= $filterMonth || $searchQ ? 'Filtered Total' : 'Grand Total' ?>
                                </td>
                                <td>₱<?= number_format($filteredTotal, 2) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <div style="font-size:32px;margin-bottom:10px;">🧾</div>
                        <p>No expenses found<?= ($filterMonth || $searchQ) ? ' matching your filters' : ' yet' ?>.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /.content-grid -->

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

function confirmSubmit() {
    const title  = document.querySelector('[name=title]').value.trim();
    const amount = parseFloat(document.querySelector('[name=amount]').value || 0);
    const date   = document.querySelector('[name=expense_date]').value;
    if (!title || amount <= 0 || !date) return true; // let server validate
    return confirm(`Record expense "${title}" for ₱${amount.toLocaleString('en-PH', {minimumFractionDigits:2})} on ${date}?\n\nThis will be deducted from the budget immediately.`);
}
</script>
</body>
</html>