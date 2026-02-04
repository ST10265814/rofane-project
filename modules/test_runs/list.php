<?php
session_start();
require_once '../../includes/config.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit(); }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// Defensive: ensure runs table exists
$conn->query("CREATE TABLE IF NOT EXISTS test_runs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  brs_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  environment VARCHAR(255) DEFAULT 'Default',
  created_by INT NOT NULL,
  started_at TIMESTAMP NULL DEFAULT NULL,
  ended_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$user_id = $_SESSION['user_id'];

// Fetch my runs
$sql = "SELECT r.id, r.name, r.environment, r.created_at, r.started_at, r.ended_at, r.brs_id,
               f.original_name
        FROM test_runs r
        LEFT JOIN brs_files f ON f.id = r.brs_id
        WHERE r.created_by = ?
        ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Test Runs - Rofane</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Test Runs</h3>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="../../dashboard/tester_dashboard.php">Dashboard</a>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead class="table-dark">
          <tr>
            <th style="width:80px;">ID</th>
            <th>Name</th>
            <th>BRS</th>
            <th style="width:120px;">Env</th>
            <th style="width:160px;">Started</th>
            <th style="width:160px;">Ended</th>
            <th style="width:260px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($res->num_rows===0): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">
              No runs yet.
              <a href="../brs/index.php" class="ms-2">Create one from your BRS cases</a>.
            </td></tr>
          <?php else: ?>
            <?php while ($r = $res->fetch_assoc()): ?>
              <tr>
                <td>#<?= (int)$r['id'] ?></td>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><?= htmlspecialchars($r['original_name'] ?? '') ?><?php if(!empty($r['brs_id'])): ?> (<?= (int)$r['brs_id'] ?>)<?php endif; ?></td>
                <td><?= htmlspecialchars($r['environment']) ?></td>
                <td><?= $r['started_at'] ?: '—' ?></td>
                <td><?= $r['ended_at'] ?: '—' ?></td>
                <td>
                  <div class="btn-group" role="group" aria-label="Actions">
                    <a class="btn btn-sm btn-primary" href="view.php?run_id=<?= (int)$r['id'] ?>">Open</a>

                    <div class="btn-group">
                      <button type="button" class="btn btn-sm btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        Export
                      </button>
                      <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="export_csv.php?run_id=<?= (int)$r['id'] ?>">CSV</a></li>
                        <li><a class="dropdown-item" target="_blank" href="export_pdf.php?run_id=<?= (int)$r['id'] ?>">PDF</a></li>
                      </ul>
                    </div>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
