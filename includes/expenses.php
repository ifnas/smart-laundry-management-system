<?php
protect('admin');
$title = 'Expenses & Operating Profit';
$subtitle = 'Track operational overhead, monitor operating margins, and export financial summaries.';
require __DIR__.'/header.php';

$today = date('Y-m-d');
$range = $_GET['range'] ?? 'month';
if ($range === 'today') {
    $from = $today; $to = $today;
} elseif ($range === '7d') {
    $from = date('Y-m-d', strtotime('-6 days')); $to = $today;
} elseif ($range === '30d') {
    $from = date('Y-m-d', strtotime('-29 days')); $to = $today;
} elseif ($range === 'month') {
    $from = date('Y-m-01'); $to = $today;
} elseif (isset($_GET['from'], $_GET['to']) && is_string($_GET['from']) && is_string($_GET['to'])) {
    $from = $_GET['from']; $to = $_GET['to']; $range = 'custom';
} else {
    $range = 'month';
    $from = date('Y-m-01'); $to = $today;
}

foreach ([$from, $to] as $d) {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    if (!$dt || $dt->format('Y-m-d') !== $d) {
        $from = date('Y-m-01'); $to = $today; $range = 'month'; break;
    }
}
if ($from > $to) [$from, $to] = [$to, $from];
$end = date('Y-m-d', strtotime($to . ' +1 day'));

$selectedCategory = (int)($_GET['category_id'] ?? 0);

// Financial summary for period
$summary = financial_summary($from, $end);

// Categories
$categories = query("SELECT * FROM expense_categories WHERE is_active=1 ORDER BY name ASC")->fetchAll();

// Category breakdown of expenses
$catBreakdown = query(
    "SELECT c.name, COALESCE(SUM(e.amount), 0) as total_amount 
     FROM expense_categories c 
     LEFT JOIN expenses e ON e.category_id=c.id AND e.is_active=1 AND e.expense_date >= ? AND e.expense_date < ? 
     GROUP BY c.id, c.name 
     HAVING total_amount > 0 
     ORDER BY total_amount DESC",
    [$from, $end]
)->fetchAll();

// Expenses list
$sql = "SELECT e.*, c.name as category_name, u.name as recorded_by_name 
        FROM expenses e 
        JOIN expense_categories c ON c.id=e.category_id 
        JOIN users u ON u.id=e.created_by 
        WHERE e.is_active=1 AND e.expense_date >= ? AND e.expense_date <= ?";
$params = [$from, $to];
if ($selectedCategory > 0) {
    $sql .= " AND e.category_id = ?";
    $params[] = $selectedCategory;
}
$sql .= " ORDER BY e.expense_date DESC, e.id DESC";
$expenses = query($sql, $params)->fetchAll();

$editId = (int)($_GET['edit'] ?? 0);
$editExpense = $editId > 0 ? one("SELECT * FROM expenses WHERE id=? AND is_active=1", [$editId]) : null;
?>

<!-- Financial Summary KPIs -->
<div class="stat-grid mb-4">
  <div class="stat-card">
    <div class="stat-top"><span>Collected Revenue</span><i class="bi bi-cash-coin text-success"></i></div>
    <strong style="color:var(--primary, #0f766e);"><?= money($summary['revenue']) ?></strong>
    <small>Paid Cash on Delivery orders (<?= date('d M', strtotime($from)) ?> - <?= date('d M', strtotime($to)) ?>)</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Recorded Expenses</span><i class="bi bi-wallet2 text-danger"></i></div>
    <strong class="text-danger"><?= money($summary['expenses']) ?></strong>
    <small>Operating overhead for selected period</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Estimated Operating Profit</span><i class="bi bi-graph-up-arrow <?= $summary['profit'] >= 0 ? 'text-success' : 'text-danger' ?>"></i></div>
    <strong class="<?= $summary['profit'] >= 0 ? 'text-success' : 'text-danger' ?>" style="font-size:26px;">
      <?= money($summary['profit']) ?>
    </strong>
    <small>Revenue minus operating expenses</small>
  </div>
</div>

<!-- Date Filter & Actions Toolbar -->
<section class="panel mb-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
    <!-- Presets -->
    <div class="btn-group" role="group">
      <a href="<?= e(url('admin/expenses.php?range=today')) ?>" class="btn btn-sm <?= $range==='today'?'btn-primary':'btn-outline-primary' ?>">Today</a>
      <a href="<?= e(url('admin/expenses.php?range=7d')) ?>" class="btn btn-sm <?= $range==='7d'?'btn-primary':'btn-outline-primary' ?>">7 Days</a>
      <a href="<?= e(url('admin/expenses.php?range=30d')) ?>" class="btn btn-sm <?= $range==='30d'?'btn-primary':'btn-outline-primary' ?>">30 Days</a>
      <a href="<?= e(url('admin/expenses.php?range=month')) ?>" class="btn btn-sm <?= $range==='month'?'btn-primary':'btn-outline-primary' ?>">This Month</a>
    </div>

    <!-- Custom Date Range Form -->
    <form method="get" action="<?= e(url('admin/expenses.php')) ?>" class="d-flex flex-wrap align-items-center gap-2 m-0">
      <input type="hidden" name="range" value="custom">
      <div class="d-flex align-items-center gap-1">
        <label class="small text-muted mb-0">From:</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>" required>
      </div>
      <div class="d-flex align-items-center gap-1">
        <label class="small text-muted mb-0">To:</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>" required>
      </div>
      <select name="category_id" class="form-select form-select-sm" style="max-width:160px;">
        <option value="0">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= $selectedCategory == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach ?>
      </select>
      <button class="btn btn-sm btn-light">Filter</button>
      <?php if ($selectedCategory > 0 || $range === 'custom'): ?>
        <a href="<?= e(url('admin/expenses.php')) ?>" class="btn btn-sm btn-link text-decoration-none">Reset</a>
      <?php endif ?>
    </form>

    <!-- CSV Export Button -->
    <form method="post" action="<?= e(url('actions/handle.php')) ?>" class="m-0">
      <?= csrf() ?>
      <input type="hidden" name="action" value="export_expenses_csv">
      <input type="hidden" name="from" value="<?= e($from) ?>">
      <input type="hidden" name="to" value="<?= e($to) ?>">
      <input type="hidden" name="category_id" value="<?= $selectedCategory ?>">
      <button type="submit" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
      </button>
    </form>
  </div>
</section>

<div class="row g-4">
  <!-- Left Column: Expenses Table & Category Breakdown -->
  <div class="col-lg-8">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2>Expense Records</h2>
          <p class="mb-0">Showing <?= count($expenses) ?> expenses between <?= date('d M Y', strtotime($from)) ?> and <?= date('d M Y', strtotime($to)) ?></p>
        </div>
      </div>

      <?php if (!$expenses): ?>
        <div class="empty py-4">
          <i class="bi bi-receipt text-muted" style="font-size:32px;"></i>
          <p class="mt-2 mb-0">No expenses recorded for this period. Use the form to record expenses.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Title / Notes</th>
                <th>Amount</th>
                <th>Recorded By</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($expenses as $ex): ?>
                <tr>
                  <td>
                    <span class="font-monospace small"><?= date('d M Y', strtotime($ex['expense_date'])) ?></span>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border"><?= e($ex['category_name']) ?></span>
                  </td>
                  <td>
                    <strong><?= e($ex['title']) ?></strong>
                    <?php if (!empty($ex['notes'])): ?>
                      <small class="text-muted d-block text-truncate" style="max-width:240px;"><?= e($ex['notes']) ?></small>
                    <?php endif ?>
                  </td>
                  <td>
                    <strong class="text-danger"><?= money($ex['amount']) ?></strong>
                  </td>
                  <td>
                    <small class="text-muted"><?= e($ex['recorded_by_name']) ?></small>
                  </td>
                  <td>
                    <div class="table-actions">
                      <a href="<?= e(url('admin/expenses.php?edit='.$ex['id'].'&from='.$from.'&to='.$to)) ?>" class="btn btn-sm btn-light" title="Edit Expense">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <form method="post" action="<?= e(url('actions/handle.php')) ?>" class="m-0" data-confirm="Are you sure you want to remove this expense entry?">
                        <?= csrf() ?>
                        <input type="hidden" name="action" value="expense_delete">
                        <input type="hidden" name="id" value="<?= $ex['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-light text-danger" title="Delete Expense">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </section>

    <!-- Expense Category Breakdown Panel -->
    <?php if ($catBreakdown): ?>
      <section class="panel mt-4">
        <div class="panel-heading">
          <div>
            <h2>Category Spending Breakdown</h2>
            <p>Expense allocation across business categories for the selected period.</p>
          </div>
        </div>
        <div class="row g-3">
          <?php foreach ($catBreakdown as $cb): 
            $pctOfTotal = $summary['expenses'] > 0 ? round(($cb['total_amount'] / $summary['expenses']) * 100, 1) : 0;
          ?>
            <div class="col-sm-6">
              <div class="p-3 rounded border" style="background:var(--surface-subtle, #f8fafc);">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <strong><?= e($cb['name']) ?></strong>
                  <span class="text-danger font-monospace"><strong><?= money($cb['total_amount']) ?></strong></span>
                </div>
                <div class="progress" style="height:6px;">
                  <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $pctOfTotal ?>%"></div>
                </div>
                <small class="text-muted d-block mt-1"><?= $pctOfTotal ?>% of total expenses</small>
              </div>
            </div>
          <?php endforeach ?>
        </div>
      </section>
    <?php endif ?>
  </div>

  <!-- Right Column: Add / Edit Expense & Category Management -->
  <div class="col-lg-4">
    <!-- Expense Form -->
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2><?= $editExpense ? 'Edit Expense' : 'Record Business Expense' ?></h2>
          <p><?= $editExpense ? 'Update recorded expenditure' : 'Log supplies, utility, or operational costs' ?></p>
        </div>
        <?php if ($editExpense): ?>
          <a href="<?= e(url('admin/expenses.php?from='.$from.'&to='.$to)) ?>" class="btn btn-sm btn-light">Cancel</a>
        <?php endif ?>
      </div>

      <form method="post" action="<?= e(url('actions/handle.php')) ?>">
        <?= csrf() ?>
        <input type="hidden" name="action" value="expense_save">
        <?php if ($editExpense): ?>
          <input type="hidden" name="id" value="<?= $editExpense['id'] ?>">
        <?php endif ?>

        <label>Expense Title / Description
          <input type="text" name="title" class="form-control" maxlength="200" placeholder="e.g. Detergent & softener bulk order" value="<?= e($editExpense['title'] ?? '') ?>" required>
        </label>

        <label>Expense Category
          <select name="category_id" class="form-select" required>
            <option value="">Select category...</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= ($editExpense['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach ?>
          </select>
        </label>

        <div class="form-grid">
          <label>Amount (LKR)
            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" placeholder="e.g. 4500.00" value="<?= e($editExpense['amount'] ?? '') ?>" required>
          </label>
          <label>Expense Date
            <input type="date" name="expense_date" class="form-control" value="<?= e($editExpense['expense_date'] ?? date('Y-m-d')) ?>" required>
          </label>
        </div>

        <label class="mt-2">Notes / Vendor details <span class="muted">(Optional)</span>
          <textarea name="notes" class="form-control" rows="2" maxlength="2000" placeholder="Invoice #, supplier name, receipt notes..."><?= e($editExpense['notes'] ?? '') ?></textarea>
        </label>

        <button type="submit" class="btn btn-primary w-100 mt-3">
          <i class="bi bi-check-circle"></i> <?= $editExpense ? 'Update Expense' : 'Record Expense' ?>
        </button>
      </form>
    </section>

    <!-- Add Category Sub-panel -->
    <section class="panel mt-4">
      <div class="panel-heading">
        <div>
          <h2>Add Expense Category</h2>
          <p>Create custom tags for business spending.</p>
        </div>
      </div>

      <form method="post" action="<?= e(url('actions/handle.php')) ?>">
        <?= csrf() ?>
        <input type="hidden" name="action" value="expense_category_save">

        <label>Category Name
          <input type="text" name="name" class="form-control" maxlength="100" placeholder="e.g. Packaging, Van Fuel" required>
        </label>
        <label>Description <span class="muted">(Optional)</span>
          <input type="text" name="description" class="form-control" maxlength="255" placeholder="Short description">
        </label>
        <button type="submit" class="btn btn-outline-primary btn-sm w-100 mt-2">
          <i class="bi bi-plus-circle"></i> Add Category
        </button>
      </form>
    </section>
  </div>
</div>

