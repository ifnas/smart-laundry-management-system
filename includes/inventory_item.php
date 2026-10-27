<?php
protect('admin');
$id = (int)($_GET['id'] ?? 0);
$item = $id > 0 ? one("SELECT * FROM inventory_items WHERE id=?", [$id]) : null;

$title = $item ? 'Edit Inventory Item' : 'New Inventory Item';
$subtitle = $item ? 'Update stock parameters, supplier relations, and reorder alerts.' : 'Add consumables, detergents, or packaging supplies to the catalogue.';
require __DIR__.'/header.php';

$categories = query("SELECT * FROM inventory_categories WHERE is_active=1 ORDER BY name ASC")->fetchAll();
$suppliers = query("SELECT * FROM inventory_suppliers WHERE is_active=1 ORDER BY name ASC")->fetchAll();
$commonUnits = ['Litres', 'Kilograms', 'Pieces', 'Packs', 'Bottles', 'Rolls', 'Boxes'];
?>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2><?= e($title) ?></h2>
          <p><?= e($subtitle) ?></p>
        </div>
        <a href="<?= e(url('admin/inventory.php')) ?>" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-arrow-left"></i> Back to Inventory
        </a>
      </div>

      <form action="<?= e(url('actions/handle.php')) ?>" method="post" class="p-4">
        <?= csrf() ?>
        <input type="hidden" name="action" value="inventory_item_save">
        <input type="hidden" name="id" value="<?= $item ? $item['id'] : 0 ?>">

        <div class="row g-3">
          <!-- SKU -->
          <div class="col-md-6">
            <label class="form-label fw-bold">SKU / Item Code <span class="text-danger">*</span></label>
            <input type="text" name="sku" class="form-control" required maxlength="50" placeholder="e.g. DET-LQD-01" value="<?= e($item['sku'] ?? '') ?>">
            <small class="text-muted">Unique inventory stock-keeping unit identifier.</small>
          </div>

          <!-- Name -->
          <div class="col-md-6">
            <label class="form-label fw-bold">Item Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required maxlength="150" placeholder="e.g. Eco Laundry Detergent Liquid" value="<?= e($item['name'] ?? '') ?>">
          </div>

          <!-- Category -->
          <div class="col-md-6">
            <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
            <select name="category_id" class="form-select" required>
              <option value="">Select Category</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($item && (int)$item['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                  <?= e($cat['name']) ?>
                </option>
              <?php endforeach ?>
            </select>
          </div>

          <!-- Supplier -->
          <div class="col-md-6">
            <label class="form-label fw-bold">Preferred Supplier</label>
            <select name="supplier_id" class="form-select">
              <option value="">None / Multiple Suppliers</option>
              <?php foreach ($suppliers as $sup): ?>
                <option value="<?= $sup['id'] ?>" <?= ($item && (int)$item['supplier_id'] === (int)$sup['id']) ? 'selected' : '' ?>>
                  <?= e($sup['name']) ?>
                </option>
              <?php endforeach ?>
            </select>
          </div>

          <!-- Unit of Measurement -->
          <div class="col-md-4">
            <label class="form-label fw-bold">Unit of Measurement <span class="text-danger">*</span></label>
            <input type="text" name="unit" class="form-control" list="unitsList" required maxlength="30" placeholder="e.g. Litres, Kilograms" value="<?= e($item['unit'] ?? 'Pieces') ?>">
            <datalist id="unitsList">
              <?php foreach ($commonUnits as $uName): ?>
                <option value="<?= $uName ?>"></option>
              <?php endforeach ?>
            </datalist>
          </div>

          <!-- Minimum Stock Threshold -->
          <div class="col-md-4">
            <label class="form-label fw-bold">Minimum Safety Stock <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0" name="min_stock" class="form-control" required placeholder="0.00" value="<?= e($item['min_stock'] ?? '10.00') ?>">
            <small class="text-muted">Threshold triggering low-stock alert notifications.</small>
          </div>

          <!-- Purchase Cost -->
          <div class="col-md-4">
            <label class="form-label fw-bold">Purchase Cost (LKR) <span class="text-danger">*</span></label>
            <input type="number" step="0.01" min="0" name="purchase_cost" class="form-control" required placeholder="0.00" value="<?= e($item['purchase_cost'] ?? '0.00') ?>">
            <small class="text-muted">Estimated cost per unit.</small>
          </div>

          <?php if (!$item): ?>
            <!-- Initial Stock on Item Creation -->
            <div class="col-md-6">
              <label class="form-label fw-bold">Initial On-Hand Stock</label>
              <input type="number" step="0.01" min="0" name="initial_stock" class="form-control" placeholder="0.00" value="0.00">
              <small class="text-muted">Starting quantity in stock (creates initial audit movement).</small>
            </div>
          <?php else: ?>
            <div class="col-md-6">
              <label class="form-label fw-bold">Current Tracked Balance</label>
              <div class="form-control bg-light fw-bold">
                <?= number_format((float)$item['current_stock'], 2) ?> <?= e($item['unit']) ?>
              </div>
              <small class="text-muted">To adjust balance, record a stock movement transaction.</small>
            </div>
          <?php endif ?>

          <!-- Active Status -->
          <div class="col-12">
            <div class="form-check form-switch mt-2">
              <input class="form-check-input" type="checkbox" name="is_active" id="isActiveCheck" value="1" <?= (!$item || $item['is_active']) ? 'checked' : '' ?>>
              <label class="form-check-label fw-bold" for="isActiveCheck">Item is Active</label>
              <div class="form-text">Inactive items are archived but all historical stock audit records remain preserved.</div>
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="col-12 mt-4 pt-3 border-top d-flex gap-2 justify-content-end">
            <a href="<?= e(url('admin/inventory.php')) ?>" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">
              <i class="bi bi-check-lg me-1"></i> Save Inventory Item
            </button>
          </div>
        </div>
      </form>
    </section>
  </div>
</div>

