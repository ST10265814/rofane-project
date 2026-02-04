<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit(); }

$role = $_SESSION['role'] ?? 'tester';
$user_id = $_SESSION['user_id'];

// Defensive: ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS brs_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uploaded_by INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS brs_test_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brs_id INT NOT NULL,
    requirement_id VARCHAR(64) NULL,
    title VARCHAR(255) NOT NULL,
    steps TEXT NULL,
    expected TEXT NULL,
    priority VARCHAR(20) DEFAULT 'Medium',
    status VARCHAR(20) DEFAULT 'Draft',
    bug_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$brs_id = isset($_GET['brs_id']) ? (int)$_GET['brs_id'] : 0;

// Load BRS list
$brs_list = [];
if ($role === 'manager') {
    $q = $conn->query("SELECT id, original_name FROM brs_files ORDER BY created_at DESC");
} else {
    if ($s = $conn->prepare("SELECT id, original_name FROM brs_files WHERE uploaded_by = ? ORDER BY created_at DESC")) {
        $s->bind_param("i", $user_id);
        $s->execute();
        $q = $s->get_result();
        $s->close();
    }
}
if (!empty($q) && $q->num_rows > 0) { while ($r=$q->fetch_assoc()) $brs_list[] = $r; }
if ($brs_id===0 && !empty($brs_list)) { $brs_id = (int)$brs_list[0]['id']; }

// Fetch coverage rows
$rows = [];
$brs_name = '';
if ($brs_id>0) {
    if ($s=$conn->prepare("SELECT original_name FROM brs_files WHERE id=?")) {
        $s->bind_param("i", $brs_id);
        $s->execute();
        $brs_name = ($s->get_result()->fetch_assoc()['original_name'] ?? '');
        $s->close();
    }

    $sql = "SELECT 
                COALESCE(NULLIF(TRIM(requirement_id),''),'(no id)') as req,
                COUNT(*) as test_count,
                SUM(CASE WHEN bug_id IS NOT NULL THEN 1 ELSE 0 END) as bug_count,
                SUM(CASE WHEN status='Passed' THEN 1 ELSE 0 END) as passed_count,
                SUM(CASE WHEN status='Failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status='Blocked' THEN 1 ELSE 0 END) as blocked_count
            FROM brs_test_cases
            WHERE brs_id = ?
            GROUP BY req
            ORDER BY req ASC";
    if ($st=$conn->prepare($sql)) {
        $st->bind_param("i", $brs_id);
        $st->execute();
        $res = $st->get_result();
        while ($r=$res->fetch_assoc()) $rows[]=$r;
        $st->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Traceability Matrix - Rofane</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Traceability Matrix</h3>
    <div class="d-flex gap-2">
      <a href="../../dashboard/tester_dashboard.php" class="btn btn-outline-secondary">Back</a>
      <?php if ($brs_id>0): ?>
        <a class="btn btn-primary" href="view_cases.php?brs_id=<?= (int)$brs_id ?>">View Cases</a>
      <?php endif; ?>
    </div>
  </div>

  <form class="card border-0 shadow-sm p-3 mb-3" method="GET">
    <div class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label">BRS</label>
        <select name="brs_id" class="form-select">
          <?php foreach ($brs_list as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= $brs_id===(int)$b['id']?'selected':'' ?>>
              <?= htmlspecialchars($b['original_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-outline-primary" type="submit">Load</button>
      </div>
    </div>
  </form>

  <?php if ($brs_id===0): ?>
    <div class="alert alert-info">Upload a BRS first to see coverage.</div>
  <?php else: ?>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-dark text-white">
        Coverage for <strong><?= htmlspecialchars($brs_name) ?></strong> (BRS #<?= (int)$brs_id ?>)
      </div>
      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead class="table-dark">
            <tr>
              <th style="width:220px;">Requirement</th>
              <th style="width:120px;"># Tests</th>
              <th style="width:120px;"># Bugs</th>
              <th style="width:120px;">Passed</th>
              <th style="width:120px;">Failed</th>
              <th style="width:120px;">Blocked</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">No test cases yet for this BRS.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['req']) ?></td>
                <td><span class="badge bg-secondary"><?= (int)$r['test_count'] ?></span></td>
                <td><span class="badge bg-primary"><?= (int)$r['bug_count'] ?></span></td>
                <td><span class="badge bg-success"><?= (int)$r['passed_count'] ?></span></td>
                <td><span class="badge bg-danger"><?= (int)$r['failed_count'] ?></span></td>
                <td><span class="badge bg-dark"><?= (int)$r['blocked_count'] ?></span></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
