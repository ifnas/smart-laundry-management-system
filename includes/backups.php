<?php
protect('admin');
$title = 'Database Backup Management';
$subtitle = 'Generate encrypted, non-destructive SQL backups stored safely outside the web root.';
require __DIR__.'/header.php';

$backupDir = get_backup_directory();

// Query database backup history records
$dbBackups = query(
    "SELECT b.*, u.name as creator_name 
     FROM database_backup_history b 
     JOIN users u ON u.id = b.created_by 
     ORDER BY b.id DESC"
)->fetchAll();

// Check disk backup files
$diskFiles = [];
if (is_dir($backupDir)) {
    foreach (scandir($backupDir) as $f) {
        if (str_ends_with($f, '.sql')) {
            $path = $backupDir . '/' . $f;
            $diskFiles[$f] = [
                'filename' => $f,
                'size' => filesize($path),
                'mtime' => filemtime($path)
            ];
        }
    }
}
krsort($diskFiles);
?>

<!-- Backup Overview Stat Cards -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-top"><span>Total Backups Stored</span><i class="bi bi-database-check"></i></div>
    <strong><?= count($diskFiles) ?></strong>
    <small>Secure offline snapshots on disk</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Backup Storage Location</span><i class="bi bi-folder-check"></i></div>
    <strong class="text-truncate" style="font-size: 14px;" title="<?= e($backupDir) ?>"><?= e($backupDir) ?></strong>
    <small class="text-success"><i class="bi bi-shield-lock-fill"></i> Outside web root (htdocs)</small>
  </div>
  <div class="stat-card">
    <div class="stat-top"><span>Latest Snapshot</span><i class="bi bi-calendar-event"></i></div>
    <strong><?= !empty($diskFiles) ? date('d M Y, h:i A', reset($diskFiles)['mtime']) : 'None' ?></strong>
    <small>Automatic verification enabled</small>
  </div>
</div>

<!-- Security & Backup Creation Action -->
<div class="panel border-primary-subtle mb-4">
  <div class="panel-heading bg-primary-subtle py-3 px-4 d-flex flex-wrap gap-3 justify-content-between align-items-center">
    <div>
      <h3 class="h6 mb-0 text-dark fw-bold">
        <i class="bi bi-shield-check text-primary me-2"></i>
        Non-Destructive Database Snapshot Creation
      </h3>
      <small class="text-secondary">Generates a complete, verified SQL dump containing schema structures and all active database records.</small>
    </div>

    <!-- Create Backup Form -->
    <form action="<?= e(url('actions/handle.php')) ?>" method="post" class="m-0">
      <?= csrf() ?>
      <input type="hidden" name="action" value="backup_create">
      <button type="submit" class="btn btn-primary shadow-sm" onclick="return confirm('Generate an immediate full database backup snapshot?');">
        <i class="bi bi-cloud-arrow-down-fill me-1"></i> Create Backup Snapshot
      </button>
    </form>
  </div>
</div>

<!-- Backups History Table -->
<section class="panel">
  <div class="panel-heading">
    <div>
      <h2>Verified Backup History</h2>
      <p>Authorized administrator download and non-destructive restore metadata preview.</p>
    </div>
  </div>

  <?php if (!$diskFiles): ?>
    <div class="empty py-5">
      <i class="bi bi-database-exclamation text-muted" style="font-size:36px;"></i>
      <p class="mt-2 mb-0">No database backups have been generated yet.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Creation Timestamp</th>
            <th>Filename</th>
            <th>File Size</th>
            <th>Integrity Status</th>
            <th>Recorded By</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($diskFiles as $filename => $info): 
            $dbRec = null;
            foreach ($dbBackups as $rec) {
                if ($rec['filename'] === $filename) {
                    $dbRec = $rec;
                    break;
                }
            }
          ?>
            <tr>
              <td>
                <strong><?= date('d M Y', $info['mtime']) ?></strong>
                <small class="d-block text-muted"><?= date('h:i:s A', $info['mtime']) ?></small>
              </td>
              <td>
                <code><?= e($filename) ?></code>
              </td>
              <td>
                <span class="badge bg-light text-dark border">
                  <?= number_format($info['size'] / 1024, 1) ?> KB
                </span>
              </td>
              <td>
                <span class="badge bg-success-subtle text-success border border-success-subtle">
                  <i class="bi bi-check2-circle"></i> Verified
                </span>
              </td>
              <td>
                <small class="fw-bold"><?= e($dbRec ? $dbRec['creator_name'] : 'System / Admin') ?></small>
              </td>
              <td class="text-end">
                <div class="table-actions justify-content-end">
                  <!-- Restore Preview Link -->
                  <a href="<?= e(url('admin/restore_preview.php?file=' . urlencode($filename))) ?>" class="btn btn-sm btn-outline-info" title="Safe Restore Preview">
                    <i class="bi bi-eye"></i> Preview
                  </a>

                  <!-- Secure Download Form -->
                  <form action="<?= e(url('actions/handle.php')) ?>" method="post" class="d-inline m-0">
                    <?= csrf() ?>
                    <input type="hidden" name="action" value="backup_download">
                    <input type="hidden" name="filename" value="<?= e($filename) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Download Authorized SQL File">
                      <i class="bi bi-download"></i> Download
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

