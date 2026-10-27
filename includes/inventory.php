<?php
protect('admin');
$title = 'Inventory Management';
$subtitle = 'Monitor stock levels, manage laundry supplies, and prevent shortages with automated reorder alerts.';
require __DIR__.'/header.php';

// Filter parameters
$search = trim((string)($_GET['search'] ?? ''));
$filterCategory = (int)($_GET['category_id'] ?? 0);
$filterStatus = trim((string)($_GET['filter'] ?? 'all'));

// Categories & Suppliers
$categories = query("SELECT * FROM inventory_categories ORDER BY name ASC")->fetchAll();
$suppliers = query("SELECT * FROM inventory_suppliers ORDER BY name ASC")->fetchAll();

// KPI Stats
$totalItems = (int)one("SELECT COUNT(*) cnt FROM inventory_items")['cnt'];
$activeItems = (int)one("SELECT COUNT(*) cnt FROM inventory_items WHERE is_active=1")['cnt'];
$lowStockCount = (int)one("SELECT COUNT(*) cnt FROM inventory_items WHERE is_active=1 AND current_stock <= min_stock AND current_stock > 0")['cnt'];
$outOfStockCount = (int)one("SELECT COUNT(*) cnt FROM inventory_items WHERE is_active=1 AND current_stock <= 0")['cnt'];
$totalValuation = (float)one("SELECT COALESCE(SUM(current_stock * purchase_cost), 0) val FROM inventory_items WHERE is_active=1")['val'];

// Base query for items
$sql = "SELECT i.*, c.name as category_name, s.name as supplier_name 
        FROM inventory_items i 
        JOIN inventory_categories c ON c.id = i.category_id 
        LEFT JOIN inventory_suppliers s ON s.id = i.supplier_id 
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (i.name LIKE ? OR i.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filterCategory > 0) {
    $sql .= " AND i.category_id = ?";
    $params[] = $filterCategory;
}

if ($filterStatus === 'low_stock') {
    $sql .= " AND i.is_active = 1 AND i.current_stock <= i.min_stock";
} elseif ($filterStatus === 'out_of_stock') {
    $sql .= " AND i.is_active = 1 AND i.current_stock <= 0";
} elseif ($filterStatus === 'in_stock') {
    $sql .= " AND i.is_active = 1 AND i.current_stock > i.min_stock";
} elseif ($filterStatus === 'inactive') {
    $sql .= " AND i.is_active = 0";
}

$sql .= " ORDER BY (i.current_stock <= i.min_stock) DESC, i.name ASC";
$items = query($sql, $params)->fetchAll();

// Dedicated Low Stock Items List for Alert Section
$criticalItems = query(
    "SELECT i.*, c.name as category_name 
     FROM inventory_items i 
     JOIN inventory_categories c ON c.id=i.category_id 
     WHERE i.is_active=1 AND i.current_stock <= i.min_stock 
     ORDER BY i.current_stock ASC"
)->fetchAll();
?>

<!-- KPI Stat Cards -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-top"><span>Total Inventory Items</span><i class="bi bi-boxes"></i></div>
    <strong><?= $totalItems ?></strong>
    <small><?= $activeItems ?> active supplies tracked</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Low Stock Warning</span><i class="bi bi-exclamation-triangle"></i></div>
    <strong class="<?= $lowStockCount > 0 ? 'text-warning' : '' ?>"><?= $lowStockCount ?></strong>
    <small>At or below reorder threshold</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Out of Stock</span><i class="bi bi-x-circle"></i></div>
    <strong class="<?= $outOfStockCount > 0 ? 'text-danger' : '' ?>"><?= $outOfStockCount ?></strong>
    <small>Requires immediate restock</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Inventory Valuation</span><i class="bi bi-cash-stack"></i></div>
    <strong><?= money($totalValuation) ?></strong>
    <small>Estimated asset purchase cost</small>
  </div>
</div>

<!-- Critical Low Stock Alert Section -->
<?php if ($criticalItems): ?>
  <div class="panel border-warning mb-4">
    <div class="panel-heading bg-warning-subtle py-3 px-4 d-flex justify-content-between align-items-center">
      <div>
        <h3 class="h6 mb-0 text-dark fw-bold">
          <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>
          Low Stock & Reorder Requirements (<?= count($criticalItems) ?>)
        </h3>
        <small class="text-secondary">Supplies that have reached or fallen below configured minimum safety thresholds.</small>
      </div>
      <a href="<?= e(url('admin/stock_movements.php')) ?>" class="btn btn-sm btn-dark">
        <i class="bi bi-box-arrow-in-down me-1"></i> Quick Stock In
      </a>
    </div>
    <div class="table-responsive p-0">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>SKU</th>
            <th>Item Name</th>
            <th>Category</th>
            <th>Current Stock</th>
            <th>Minimum Threshold</th>
            <th>Status</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($criticalItems as $crit): ?>
            <tr>
              <td><code><?= e($crit['sku']) ?></code></td>
              <td><strong><?= e($crit['name']) ?></strong></td>
              <td><span class="badge bg-light text-dark"><?= e($crit['category_name']) ?></span></td>
              <td>
                <span class="fw-bold <?= (float)$crit['current_stock'] <= 0 ? 'text-danger' : 'text-warning' ?>">
                  <?= number_format((float)$crit['current_stock'], 2) ?> <?= e($crit['unit']) ?>
                </span>
              </td>
              <td><?= number_format((float)$crit['min_stock'], 2) ?> <?= e($crit['unit']) ?></td>
              <td>
                <?php if ((float)$crit['current_stock'] <= 0): ?>
                  <span class="badge bg-danger">Out of Stock</span>
                <?php else: ?>
                  <span class="badge bg-warning text-dark">Low Stock</span>
                <?php endif ?>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/stock_movements.php?item_id=' . $crit['id'] . '&type=IN')) ?>">
                  <i class="bi bi-plus-circle"></i> Restock
                </a>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif ?>

<!-- Controls and Filtering Header -->
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= e(url('admin/inventory_item.php')) ?>" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i> Add Inventory Item
    </a>
    <a href="<?= e(url('admin/stock_movements.php')) ?>" class="btn btn-outline-secondary">
      <i class="bi bi-arrow-left-right me-1"></i> Stock Movements
    </a>
    <a href="<?= e(url('admin/suppliers.php')) ?>" class="btn btn-outline-secondary">
      <i class="bi bi-building me-1"></i> Manage Suppliers
    </a>
  </div>

  <!-- Search and Filter Form -->
  <form method="get" class="d-flex flex-wrap gap-2 align-items-center m-0">
    <div class="input-group input-group-sm" style="max-width: 200px;">
      <input type="text" name="search" class="form-control" placeholder="Search item or SKU..." value="<?= e($search) ?>">
      <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
    </div>

    <select name="category_id" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
      <option value="0">All Categories</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= $filterCategory === (int)$cat['id'] ? 'selected' : '' ?>>
          <?= e($cat['name']) ?>
        </option>
      <?php endforeach ?>
    </select>

    <select name="filter" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
      <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Status</option>
      <option value="in_stock" <?= $filterStatus === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
      <option value="low_stock" <?= $filterStatus === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
      <option value="out_of_stock" <?= $filterStatus === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
      <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>

    <?php if ($search !== '' || $filterCategory > 0 || $filterStatus !== 'all'): ?>
      <a href="<?= e(url('admin/inventory.php')) ?>" class="btn btn-sm btn-light" title="Reset Filters">
        <i class="bi bi-x-circle"></i>
      </a>
    <?php endif ?>
  </form>
</div>

<!-- Main Inventory Table Panel -->
<section class="panel">
  <div class="panel-heading">
    <div>
      <h2>Inventory Catalogue & Stock Levels</h2>
      <p>Real-time tracked balance and safety thresholds for laundry consumables and supplies.</p>
    </div>
  </div>

  <?php if (!$items): ?>
    <div class="empty py-5">
      <i class="bi bi-boxes text-muted" style="font-size:36px;"></i>
      <p class="mt-2 mb-0">No inventory items matched your search criteria.</p>
      <a href="<?= e(url('admin/inventory_item.php')) ?>" class="btn btn-sm btn-primary mt-3">Add Your First Item</a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Item Details</th>
            <th>Category</th>
            <th>Stock Balance</th>
            <th>Min. Threshold</th>
            <th>Unit Cost</th>
            <th>Total Value</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): 
            $curr = (float)$item['current_stock'];
            $min = (float)$item['min_stock'];
            $cost = (float)$item['purchase_cost'];
            $val = $curr * $cost;
          ?>
            <tr class="<?= !$item['is_active'] ? 'opacity-50' : '' ?>">
              <td><code><?= e($item['sku']) ?></code></td>
              <td>
                <strong><?= e($item['name']) ?></strong>
                <?php if (!empty($item['supplier_name'])): ?>
                  <small class="d-block text-muted"><i class="bi bi-building"></i> <?= e($item['supplier_name']) ?></small>
                <?php endif ?>
              </td>
              <td><span class="badge bg-light text-dark"><?= e($item['category_name']) ?></span></td>
              <td>
                <span class="fw-bold <?= $curr <= 0 ? 'text-danger' : ($curr <= $min ? 'text-warning' : 'text-success') ?>">
                  <?= number_format($curr, 2) ?> <?= e($item['unit']) ?>
                </span>
              </td>
              <td><?= number_format($min, 2) ?> <?= e($item['unit']) ?></td>
              <td><?= money($cost) ?></td>
              <td><?= money($val) ?></td>
              <td>
                <?php if (!$item['is_active']): ?>
                  <span class="badge bg-secondary">Inactive</span>
                <?php elseif ($curr <= 0): ?>
                  <span class="badge bg-danger">Out of Stock</span>
                <?php elseif ($curr <= $min): ?>
                  <span class="badge bg-warning text-dark">Low Stock</span>
                <?php else: ?>
                  <span class="badge bg-success">In Stock</span>
                <?php endif ?>
              </td>
              <td class="text-end">
                <div class="table-actions justify-content-end">
                  <a class="btn btn-sm btn-light" href="<?= e(url('admin/stock_movements.php?item_id=' . $item['id'])) ?>" title="View / Record Stock Movements">
                    <i class="bi bi-arrow-left-right"></i>
                  </a>
                  <a class="btn btn-sm btn-light" href="<?= e(url('admin/inventory_item.php?id=' . $item['id'])) ?>" title="Edit Item Details">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <form action="<?= e(url('actions/handle.php')) ?>" method="post" class="d-inline m-0">
                    <?= csrf() ?>
                    <input type="hidden" name="action" value="inventory_item_toggle">
                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-light" title="<?= $item['is_active'] ? 'Deactivate' : 'Activate' ?>">
                      <i class="bi bi-<?= $item['is_active'] ? 'toggle-on text-success' : 'toggle-off text-muted' ?>"></i>
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

