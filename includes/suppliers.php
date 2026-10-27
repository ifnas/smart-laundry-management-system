<?php
protect('admin');
$title = 'Suppliers & Vendors';
$subtitle = 'Manage raw material suppliers, packaging vendors, and detergent partners.';
require __DIR__.'/header.php';

$editId = (int)($_GET['edit'] ?? 0);
$editSupplier = $editId > 0 ? one("SELECT * FROM inventory_suppliers WHERE id=?", [$editId]) : null;

// Query suppliers with item count
$suppliers = query(
    "SELECT s.*, (SELECT COUNT(*) FROM inventory_items i WHERE i.supplier_id=s.id) as items_count 
     FROM inventory_suppliers s 
     ORDER BY s.name ASC"
)->fetchAll();
?>

<div class="row g-4">
  <!-- Supplier Form (Create / Edit) -->
  <div class="col-lg-4">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2><?= $editSupplier ? 'Edit Supplier' : 'Add New Supplier' ?></h2>
          <p><?= $editSupplier ? 'Update vendor details and contact information.' : 'Register a new vendor for inventory purchases.' ?></p>
        </div>
      </div>

      <form action="<?= e(url('actions/handle.php')) ?>" method="post" class="p-3">
        <?= csrf() ?>
        <input type="hidden" name="action" value="inventory_supplier_save">
        <input type="hidden" name="id" value="<?= $editSupplier ? $editSupplier['id'] : 0 ?>">

        <div class="mb-3">
          <label class="form-label fw-bold">Company / Supplier Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" required maxlength="150" placeholder="e.g. CleanPro Chemical Supplies" value="<?= e($editSupplier['name'] ?? '') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Contact Person</label>
          <input type="text" name="contact_person" class="form-control" maxlength="100" placeholder="e.g. David Perera" value="<?= e($editSupplier['contact_person'] ?? '') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Email Address</label>
          <input type="email" name="email" class="form-control" maxlength="190" placeholder="sales@cleanpro.example" value="<?= e($editSupplier['email'] ?? '') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Phone Number</label>
          <input type="text" name="phone" class="form-control" maxlength="30" placeholder="+94 77 123 4567" value="<?= e($editSupplier['phone'] ?? '') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">Office Address</label>
          <textarea name="address" class="form-control" rows="2" maxlength="1000" placeholder="Warehouse address, Colombo"><?= e($editSupplier['address'] ?? '') ?></textarea>
        </div>

        <div class="mb-3 form-check form-switch">
          <input class="form-check-input" type="checkbox" name="is_active" id="suppActive" value="1" <?= (!$editSupplier || $editSupplier['is_active']) ? 'checked' : '' ?>>
          <label class="form-check-label fw-bold" for="suppActive">Supplier is Active</label>
        </div>

        <div class="d-flex gap-2">
          <?php if ($editSupplier): ?>
            <a href="<?= e(url('admin/suppliers.php')) ?>" class="btn btn-light w-50">Cancel</a>
          <?php endif ?>
          <button type="submit" class="btn btn-primary <?= $editSupplier ? 'w-50' : 'w-100' ?>">
            <i class="bi bi-check-lg me-1"></i> <?= $editSupplier ? 'Update Supplier' : 'Save Supplier' ?>
          </button>
        </div>
      </form>
    </section>
  </div>

  <!-- Suppliers List Table -->
  <div class="col-lg-8">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2>Registered Vendors (<?= count($suppliers) ?>)</h2>
          <p>Vendors associated with consumable supplies and purchase history.</p>
        </div>
      </div>

      <?php if (!$suppliers): ?>
        <div class="empty py-5">
          <i class="bi bi-building text-muted" style="font-size:36px;"></i>
          <p class="mt-2 mb-0">No suppliers registered yet.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Company</th>
                <th>Contact</th>
                <th>Phone / Email</th>
                <th>Supplied Items</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($suppliers as $s): ?>
                <tr class="<?= !$s['is_active'] ? 'opacity-50' : '' ?>">
                  <td>
                    <strong><?= e($s['name']) ?></strong>
                    <?php if (!empty($s['address'])): ?>
                      <small class="d-block text-muted text-truncate" style="max-width: 200px;"><?= e($s['address']) ?></small>
                    <?php endif ?>
                  </td>
                  <td><?= e($s['contact_person'] ?: '—') ?></td>
                  <td>
                    <div><?= e($s['phone'] ?: '—') ?></div>
                    <?php if (!empty($s['email'])): ?>
                      <small class="text-secondary"><?= e($s['email']) ?></small>
                    <?php endif ?>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border">
                      <?= (int)$s['items_count'] ?> item(s)
                    </span>
                  </td>
                  <td>
                    <span class="badge bg-<?= $s['is_active'] ? 'success' : 'secondary' ?>">
                      <?= $s['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-light" href="<?= e(url('admin/suppliers.php?edit=' . $s['id'])) ?>" title="Edit Supplier">
                      <i class="bi bi-pencil"></i>
                    </a>
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

