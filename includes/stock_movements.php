<?php
protect('admin');
$title = 'Stock Movements & Ledger';
$subtitle = 'Record stock additions, deductions, adjustments, and review the immutable inventory audit log.';
require __DIR__.'/header.php';

// Filter parameters
$filterItemId = (int)($_GET['item_id'] ?? 0);
$filterType = trim((string)($_GET['type'] ?? ''));

// Fetch items and suppliers for dropdowns
$allItems = query("SELECT id, name, sku, unit, current_stock FROM inventory_items ORDER BY name ASC")->fetchAll();
$suppliers = query("SELECT id, name FROM inventory_suppliers WHERE is_active=1 ORDER BY name ASC")->fetchAll();

// Selected item if query param passed
$selectedItem = $filterItemId > 0 ? one("SELECT * FROM inventory_items WHERE id=?", [$filterItemId]) : null;

// Query movements history
$sql = "SELECT m.*, i.name as item_name, i.sku as item_sku, u.name as user_name, s.name as supplier_name 
        FROM inventory_movements m 
        JOIN inventory_items i ON i.id = m.item_id 
        JOIN users u ON u.id = m.performed_by 
        LEFT JOIN inventory_suppliers s ON s.id = m.supplier_id 
        WHERE 1=1";
$params = [];

if ($filterItemId > 0) {
    $sql .= " AND m.item_id = ?";
    $params[] = $filterItemId;
}

if ($filterType !== '' && in_array($filterType, ['IN', 'OUT', 'ADJUST'], true)) {
    $sql .= " AND m.movement_type = ?";
    $params[] = $filterType;
}

$sql .= " ORDER BY m.id DESC LIMIT 100";
$movements = query($sql, $params)->fetchAll();

// Movement Type statistics
$totalIn = (float)one("SELECT COALESCE(SUM(quantity), 0) val FROM inventory_movements WHERE movement_type='IN'")['val'];
$totalOut = (float)one("SELECT COALESCE(SUM(quantity), 0) val FROM inventory_movements WHERE movement_type='OUT'")['val'];
$totalMovements = (int)one("SELECT COUNT(*) cnt FROM inventory_movements")['cnt'];
?>

<!-- Top KPI Summary Cards -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-top"><span>Total Movements</span><i class="bi bi-clock-history"></i></div>
    <strong><?= $totalMovements ?></strong>
    <small>Immutable audit transactions recorded</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Total Inflow Units</span><i class="bi bi-box-arrow-in-down"></i></div>
    <strong class="text-success"><?= number_format($totalIn, 2) ?></strong>
    <small>Units received / restocked</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Total Outflow Units</span><i class="bi bi-box-arrow-up"></i></div>
    <strong class="text-danger"><?= number_format($totalOut, 2) ?></strong>
    <small>Units dispensed or consumed</small>
  </div>
</div>

<div class="row g-4">
  <!-- Stock Movement Action Form -->
  <div class="col-lg-4">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2>Record Stock Movement</h2>
          <p>Atomic transaction with non-negative balance validation.</p>
        </div>
      </div>

      <form action="<?= e(url('actions/handle.php')) ?>" method="post" class="p-3">
        <?= csrf() ?>
        <input type="hidden" name="action" value="inventory_movement_save">

        <div class="mb-3">
          <label class="form-label fw-bold">Select Inventory Item <span class="text-danger">*</span></label>
          <select name="item_id" id="itemSelect" class="form-select" required>
            <option value="">-- Choose Item --</option>
            <?php foreach ($allItems as $itm): ?>
              <option value="<?= $itm['id'] ?>" <?= ($filterItemId === (int)$itm['id']) ? 'selected' : '' ?> data-unit="<?= e($itm['unit']) ?>" data-stock="<?= (float)$itm['current_stock'] ?>">
                <?= e($itm['name']) ?> (<?= e($itm['sku']) ?>) — Current: <?= number_format((float)$itm['current_stock'], 2) ?> <?= e($itm['unit']) ?>
              </option>
            <?php endforeach ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Movement Type <span class="text-danger">*</span></label>
          <select name="movement_type" id="movementType" class="form-select" required>
            <option value="IN" <?= ($filterType === 'IN' || !$filterType) ? 'selected' : '' ?>>Stock In (Receive / Restock)</option>
            <option value="OUT" <?= ($filterType === 'OUT') ? 'selected' : '' ?>>Stock Out (Dispense / Consume)</option>
            <option value="ADJUST" <?= ($filterType === 'ADJUST') ? 'selected' : '' ?>>Manual Stock Adjustment (Set Exact Balance)</option>
          </select>
          <div class="form-text" id="typeHelp">Stock In increases on-hand balance.</div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold" id="qtyLabel">Quantity <span class="text-danger">*</span></label>
          <div class="input-group">
            <input type="number" step="0.01" min="0.01" name="quantity" id="qtyInput" class="form-control" required placeholder="0.00">
            <span class="input-group-text" id="unitSpan"><?= $selectedItem ? e($selectedItem['unit']) : 'Units' ?></span>
          </div>
          <div class="form-text text-muted" id="qtyHelp">Enter positive quantity to move.</div>
        </div>

        <div class="mb-3" id="supplierWrapper">
          <label class="form-label fw-bold">Supplier (for Stock In)</label>
          <select name="supplier_id" class="form-select">
            <option value="">-- No Supplier --</option>
            <?php foreach ($suppliers as $sup): ?>
              <option value="<?= $sup['id'] ?>" <?= ($selectedItem && (int)$selectedItem['supplier_id'] === (int)$sup['id']) ? 'selected' : '' ?>>
                <?= e($sup['name']) ?>
              </option>
            <?php endforeach ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Reference / PO #</label>
          <input type="text" name="reference" class="form-control" maxlength="100" placeholder="e.g. PO-8921, INV-443, CycleCount-Oct">
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Reason / Notes</label>
          <textarea name="notes" class="form-control" rows="2" maxlength="1000" placeholder="Optional audit memo or supplier remarks"></textarea>
        </div>

        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-check-circle me-1"></i> Submit Transaction
        </button>
      </form>
    </section>
  </div>

  <!-- Movements Audit History Table -->
  <div class="col-lg-8">
    <section class="panel">
      <div class="panel-heading d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <div>
          <h2>Stock Movement Audit Trail</h2>
          <p>Chronological transaction log with user accountability.</p>
        </div>

        <!-- Filter Form -->
        <form method="get" class="d-flex gap-2 align-items-center m-0">
          <select name="item_id" class="form-select form-select-sm" style="max-width: 160px;" onchange="this.form.submit()">
            <option value="0">All Items</option>
            <?php foreach ($allItems as $itm): ?>
              <option value="<?= $itm['id'] ?>" <?= ($filterItemId === (int)$itm['id']) ? 'selected' : '' ?>>
                <?= e($itm['name']) ?>
              </option>
            <?php endforeach ?>
          </select>

          <select name="type" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
            <option value="">All Types</option>
            <option value="IN" <?= ($filterType === 'IN') ? 'selected' : '' ?>>Stock In</option>
            <option value="OUT" <?= ($filterType === 'OUT') ? 'selected' : '' ?>>Stock Out</option>
            <option value="ADJUST" <?= ($filterType === 'ADJUST') ? 'selected' : '' ?>>Adjust</option>
          </select>

          <?php if ($filterItemId > 0 || $filterType !== ''): ?>
            <a href="<?= e(url('admin/stock_movements.php')) ?>" class="btn btn-sm btn-light" title="Clear Filters">
              <i class="bi bi-x-circle"></i>
            </a>
          <?php endif ?>
        </form>
      </div>

      <?php if (!$movements): ?>
        <div class="empty py-5">
          <i class="bi bi-clock-history text-muted" style="font-size:36px;"></i>
          <p class="mt-2 mb-0">No stock movements recorded yet.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Date / Time</th>
                <th>Item & SKU</th>
                <th>Type</th>
                <th>Quantity</th>
                <th>New Balance</th>
                <th>Reference</th>
                <th>Staff</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($movements as $m): ?>
                <tr>
                  <td>
                    <strong><?= date('d M Y', strtotime($m['created_at'])) ?></strong>
                    <small class="d-block text-muted"><?= date('h:i A', strtotime($m['created_at'])) ?></small>
                  </td>
                  <td>
                    <strong><?= e($m['item_name']) ?></strong>
                    <small class="d-block text-muted"><code><?= e($m['item_sku']) ?></code></small>
                  </td>
                  <td>
                    <?php if ($m['movement_type'] === 'IN'): ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="bi bi-box-arrow-in-down"></i> IN
                      </span>
                    <?php elseif ($m['movement_type'] === 'OUT'): ?>
                      <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                        <i class="bi bi-box-arrow-up"></i> OUT
                      </span>
                    <?php else: ?>
                      <span class="badge bg-info-subtle text-info border border-info-subtle">
                        <i class="bi bi-sliders"></i> ADJUST
                      </span>
                    <?php endif ?>
                  </td>
                  <td>
                    <span class="fw-bold <?= $m['movement_type'] === 'IN' ? 'text-success' : ($m['movement_type'] === 'OUT' ? 'text-danger' : 'text-primary') ?>">
                      <?= ($m['movement_type'] === 'IN' ? '+' : ($m['movement_type'] === 'OUT' ? '-' : '')) . number_format((float)$m['quantity'], 2) ?> <?= e($m['unit']) ?>
                    </span>
                  </td>
                  <td>
                    <span class="fw-bold text-dark"><?= number_format((float)$m['balance_after'], 2) ?> <?= e($m['unit']) ?></span>
                  </td>
                  <td>
                    <?php if (!empty($m['reference'])): ?>
                      <span class="badge bg-light text-dark"><?= e($m['reference']) ?></span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif ?>
                    <?php if (!empty($m['notes'])): ?>
                      <small class="d-block text-secondary text-truncate" style="max-width: 140px;" title="<?= e($m['notes']) ?>"><?= e($m['notes']) ?></small>
                    <?php endif ?>
                  </td>
                  <td>
                    <small class="fw-bold"><?= e($m['user_name']) ?></small>
                  </td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      <?php endif ?>
    </section>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var itemSelect = document.getElementById('itemSelect');
  var movementType = document.getElementById('movementType');
  var unitSpan = document.getElementById('unitSpan');
  var typeHelp = document.getElementById('typeHelp');
  var qtyLabel = document.getElementById('qtyLabel');
  var qtyInput = document.getElementById('qtyInput');
  var supplierWrapper = document.getElementById('supplierWrapper');

  function updateFormState() {
    var opt = itemSelect.options[itemSelect.selectedIndex];
    if (opt && opt.dataset.unit) {
      unitSpan.textContent = opt.dataset.unit;
    }

    var t = movementType.value;
    if (t === 'IN') {
      typeHelp.textContent = 'Stock In increases on-hand balance.';
      qtyLabel.textContent = 'Quantity to Add *';
      qtyInput.min = '0.01';
      supplierWrapper.style.display = '';
    } else if (t === 'OUT') {
      typeHelp.textContent = 'Stock Out deducts from current stock. System prevents negative balance.';
      qtyLabel.textContent = 'Quantity to Deduct *';
      qtyInput.min = '0.01';
      supplierWrapper.style.display = 'none';
    } else if (t === 'ADJUST') {
      typeHelp.textContent = 'Manual adjustment sets the new exact on-hand balance.';
      qtyLabel.textContent = 'New Target Balance *';
      qtyInput.min = '0.00';
      supplierWrapper.style.display = 'none';
    }
  }

  if (itemSelect && movementType) {
    itemSelect.addEventListener('change', updateFormState);
    movementType.addEventListener('change', updateFormState);
    updateFormState();
  }
});
</script>

