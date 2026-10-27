<?php
protect('admin');
$title = 'Database Restore Preview';
$subtitle = 'Safety-first verification and step-by-step restoration guide.';
require __DIR__.'/header.php';

$filename = trim((string)($_GET['file'] ?? ''));
$backupDir = get_backup_directory();
$filepath = $backupDir . '/' . basename($filename);

$isValid = false;
$meta = [];
$tablesInBackup = [];

if ($filename !== '' && file_exists($filepath) && str_ends_with($filename, '.sql')) {
    $realDir = realpath($backupDir);
    $realFile = realpath($filepath);
    if ($realFile !== false && str_starts_with($realFile, $realDir)) {
        $isValid = true;
        $size = filesize($filepath);
        $mtime = filemtime($filepath);

        // Read sample from file to detect tables
        $handle = fopen($filepath, 'r');
        $lines = 0;
        if ($handle) {
            while (($line = fgets($handle)) !== false && $lines < 1500) {
                $lines++;
                if (preg_match('/CREATE TABLE `?([a-zA-Z0-9_]+)`?/', $line, $matches)) {
                    $tablesInBackup[] = $matches[1];
                }
            }
            fclose($handle);
        }

        $meta = [
            'filename' => $filename,
            'size' => $size,
            'mtime' => $mtime,
            'tables' => array_unique($tablesInBackup)
        ];
    }
}
?>

<div class="row justify-content-center">
  <div class="col-lg-9">
    <!-- Back to Backups Navigation -->
    <div class="mb-3">
      <a href="<?= e(url('admin/backups.php')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Backup Management
      </a>
    </div>

    <?php if (!$isValid): ?>
      <div class="alert alert-danger py-4">
        <h4 class="alert-heading"><i class="bi bi-shield-x me-2"></i> Invalid or Missing Backup File</h4>
        <p class="mb-0">The specified backup snapshot could not be found in the secure storage directory, or access was restricted.</p>
      </div>
    <?php else: ?>
      <!-- Safety First Notice Banner -->
      <div class="alert alert-warning border-warning d-flex align-items-start gap-3 p-4 mb-4">
        <i class="bi bi-shield-exclamation text-warning display-6 mt-1"></i>
        <div>
          <h4 class="alert-heading fw-bold text-dark mb-1">Safety First: Restoring a Database is Destructive</h4>
          <p class="mb-2 text-dark">
            Restoring this backup over the live <code>smart_laundry_db</code> database will replace all current database state, bookings, payments, and users with the snapshot state.
          </p>
          <div class="small text-secondary">
            <strong>Safety Constraint:</strong> To protect production data and prevent accidental catastrophic overwrites, one-click destructive live restoration is strictly disabled in the web UI. Please follow the verified procedure below.
          </div>
        </div>
      </div>

      <!-- Backup File Metadata Card -->
      <section class="panel mb-4">
        <div class="panel-heading">
          <div>
            <h2>Snapshot Metadata</h2>
            <p>Verified structural parameters for <code><?= e($meta['filename']) ?></code></p>
          </div>
          <!-- Download Button -->
          <form action="<?= e(url('actions/handle.php')) ?>" method="post" class="m-0">
            <?= csrf() ?>
            <input type="hidden" name="action" value="backup_download">
            <input type="hidden" name="filename" value="<?= e($meta['filename']) ?>">
            <button type="submit" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-download me-1"></i> Download SQL File
            </button>
          </form>
        </div>

        <div class="p-4">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="small text-muted fw-bold">Created Date & Time</label>
              <div class="fw-bold"><?= date('d M Y, h:i:s A', $meta['mtime']) ?></div>
            </div>
            <div class="col-md-4">
              <label class="small text-muted fw-bold">File Size</label>
              <div class="fw-bold"><?= number_format($meta['size'] / 1024, 2) ?> KB (<?= number_format($meta['size']) ?> bytes)</div>
            </div>
            <div class="col-md-4">
              <label class="small text-muted fw-bold">Detected Tables</label>
              <div class="fw-bold text-success"><?= count($meta['tables']) ?> Table Definitions</div>
            </div>
          </div>

          <hr class="my-4">

          <h5 class="h6 fw-bold mb-3">Included Table Definitions in Snapshot:</h5>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($meta['tables'] as $tbl): ?>
              <span class="badge bg-light text-dark border px-2 py-1">
                <i class="bi bi-table text-primary me-1"></i> <?= e($tbl) ?>
              </span>
            <?php endforeach ?>
          </div>
        </div>
      </section>

      <!-- Documented Step-by-Step Recovery Procedure -->
      <section class="panel">
        <div class="panel-heading">
          <div>
            <h2>Standard Operating Procedure: Database Restoration</h2>
            <p>Follow these safe steps using the MySQL CLI in a controlled maintenance window.</p>
          </div>
        </div>

        <div class="p-4">
          <ol class="list-group list-group-numbered mb-4">
            <li class="list-group-item d-flex justify-content-between align-items-start border-0 px-0">
              <div class="ms-2 me-auto">
                <div class="fw-bold">Step 1: Create a safety backup of your current live database</div>
                <p class="text-muted small mb-1">Before performing any restore, ensure your current live state is backed up outside the web root.</p>
                <code class="d-block p-2 bg-light rounded text-dark">
                  C:\xampp\mysql\bin\mysqldump.exe -u root smart_laundry_db &gt; C:\xampp\backups\smart-laundry\safety_pre_restore.sql
                </code>
              </div>
            </li>

            <li class="list-group-item d-flex justify-content-between align-items-start border-0 px-0">
              <div class="ms-2 me-auto">
                <div class="fw-bold">Step 2: Recommended: Test restoration in a temporary staging database</div>
                <p class="text-muted small mb-1">Verify that the backup imports cleanly without syntax errors before touching the live database.</p>
                <code class="d-block p-2 bg-light rounded text-dark">
                  C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS smart_laundry_staging;"<br>
                  C:\xampp\mysql\bin\mysql.exe -u root smart_laundry_staging &lt; "<?= e($filepath) ?>"
                </code>
              </div>
            </li>

            <li class="list-group-item d-flex justify-content-between align-items-start border-0 px-0">
              <div class="ms-2 me-auto">
                <div class="fw-bold">Step 3: Execute authorized restore to the live database</div>
                <p class="text-muted small mb-1">When authorized, import the verified SQL snapshot directly into the target database:</p>
                <code class="d-block p-2 bg-light rounded text-dark">
                  C:\xampp\mysql\bin\mysql.exe -u root smart_laundry_db &lt; "<?= e($filepath) ?>"
                </code>
              </div>
            </li>
          </ol>

          <div class="p-3 bg-light rounded border text-muted small">
            <i class="bi bi-info-circle-fill text-primary me-1"></i>
            <strong>Post-Restoration Checklist:</strong> After importing, clear session caches, run <code>tests/smoke.php</code>, and verify admin/customer login credentials.
          </div>
        </div>
      </section>
    <?php endif ?>
  </div>
</div>

