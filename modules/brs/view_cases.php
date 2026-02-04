<?php
session_start();
require_once '../../includes/config.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit(); }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// --- Defensive: ensure tables exist (won't error if already created)
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

// --- Input
$brs_id = isset($_GET['brs_id']) ? (int)$_GET['brs_id'] : 0;
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// If no BRS selected, pick the latest uploaded by me (or any if manager)
if ($brs_id <= 0) {
  if (($_SESSION['role'] ?? '') === 'manager') {
    $q = $conn->query("SELECT id FROM brs_files ORDER BY created_at DESC LIMIT 1");
  } else {
    $s = $conn->prepare("SELECT id FROM brs_files WHERE uploaded_by=? ORDER BY created_at DESC LIMIT 1");
    $s->bind_param("i", $_SESSION['user_id']);
    $s->execute(); $q = $s->get_result(); $s->close();
  }
  if ($q && $q->num_rows) { $brs_id = (int)$q->fetch_assoc()['id']; }
}

// --- Optional: quick status update per row
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['set_case_status'])) {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $flash = "<div class='alert alert-danger'>Invalid request.</div>";
  } else {
    $case_id = (int)$_POST['case_id'];
    $new_status = $_POST['set_case_status'];
    $allowed = ['Draft','Ready','In Progress','Passed','Failed','Blocked'];
    if (in_array($new_status, $allowed, true) && $case_id>0) {
      if ($u = $conn->prepare("UPDATE brs_test_cases SET status=? WHERE id=? AND brs_id=?")) {
        $u->bind_param("sii", $new_status, $case_id, $brs_id);
        $u->execute(); $u->close();
        $flash = "<div class='alert alert-success'>Case #{$case_id} set to <strong>".htmlspecialchars($new_status)."</strong>.</div>";
      }
    }
  }
}

// --- Load BRS name
$brs_name = '';
if ($brs_id > 0 && ($s = $conn->prepare("SELECT original_name FROM brs_files WHERE id=?"))) {
  $s->bind_param("i", $brs_id);
  $s->execute(); $brs_name = ($s->get_result()->fetch_assoc()['original_name'] ?? '');
  $s->close();
}

// --- Build cases query
$where = " WHERE c.brs_id = ? ";
$params = [$brs_id];
$types  = "i";

if ($status_filter !== '' && in_array($status_filter, ['Draft','Ready','In Progress','Passed','Failed','Blocked'], true)) {
  $where .= " AND c.status = ? ";
  $params[] = $status_filter; $types .= "s";
}
if ($search !== '') {
  $like = "%{$search}%";
  $where .= " AND (c.title LIKE ? OR c.steps LIKE ? OR c.expected LIKE ? OR c.requirement_id LIKE ?) ";
  array_push($params, $like, $like, $like, $like);
  $types .= "ssss";
}

$sql = "SELECT c.id, c.requirement_id, c.title, c.steps, c.expected, c.priority, c.status, c.bug_id
        FROM brs_test_cases c
        $where
        ORDER BY c.id ASC";

$cases = null;
if ($st = $conn->prepare($sql)) {
  $st->bind_param($types, ...$params);
  $st->execute();
  $cases = $st->get_result();
  $st->close();
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>BRS Test Cases - Rofane</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    pre { white-space: pre-wrap; }
    .status-chip { margin: 2px 2px 0 0; }
  </style>
</head>
<body class="bg-light">
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">BRS: <?= htmlspecialchars($brs_name ?: ('#'.$brs_id)) ?></h3>
    <div class="d-flex gap-2">
      <a href="index.php" class="btn btn-outline-secondary">Back</a>
      <a href="../../dashboard/tester_dashboard.php" class="btn btn-outline-secondary">Dashboard</a>
    </div>
  </div>

  <!-- Filters -->
  <form class="card border-0 shadow-sm p-3 mb-3" method="GET">
    <input type="hidden" name="brs_id" value="<?= (int)$brs_id ?>">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">All</option>
          <?php foreach (['Draft','Ready','In Progress','Passed','Failed','Blocked'] as $st): ?>
            <option value="<?= $st ?>" <?= $status_filter===$st?'selected':'' ?>><?= $st ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <label class="form-label">Search</label>
        <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Title, steps, expected, or requirement id">
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary" type="submit">Apply</button>
      </div>
      <div class="col-md-2 d-grid">
        <a class="btn btn-outline-secondary" href="view_cases.php?brs_id=<?= (int)$brs_id ?>">Reset</a>
      </div>
    </div>
  </form>

  <?= isset($flash)?$flash:'' ?>

  <!-- BEGIN: Bulk toolbar + open form -->
  <form method="POST" id="bulkForm">
    <input type="hidden" name="brs_id" value="<?= (int)$brs_id ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="d-flex justify-content-end gap-2 mb-2">
      <button class="btn btn-success btn-sm"
              type="submit"
              form="bulkForm"
              formaction="../test_runs/add_to_run.php">
        Add Selected to Run
      </button>

      <button class="btn btn-danger btn-sm"
              type="submit"
              form="bulkForm"
              formaction="../bug_tracker/bulk_log_bug.php">
        Bulk Log Bug for Selected
      </button>
    </div>

    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead class="table-dark">
          <tr>
            <th style="width:34px;"><input type="checkbox" id="checkAll"></th>
            <th style="width:120px;">Req ID</th>
            <th style="width:240px;">Title</th>
            <th>Steps</th>
            <th>Expected</th>
            <th style="width:90px;">Priority</th>
            <th style="width:120px;">Bug</th>
            <th style="width:110px;">Status</th>
            <th style="width:260px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$cases || $cases->num_rows===0): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No test cases found.</td></tr>
          <?php else: while ($row = $cases->fetch_assoc()): ?>
            <tr>
              <td>
                <input type="checkbox" class="case-check" name="case_ids[]" value="<?= (int)$row['id'] ?>">
              </td>
              <td><?= htmlspecialchars($row['requirement_id'] ?: '') ?></td>
              <td><?= htmlspecialchars($row['title']) ?></td>
              <td><pre class="mb-0 small"><?= htmlspecialchars($row['steps']) ?></pre></td>
              <td><pre class="mb-0 small"><?= htmlspecialchars($row['expected']) ?></pre></td>
              <td><?= htmlspecialchars($row['priority']) ?></td>
              <td>
                <?php if (!empty($row['bug_id'])): ?>
                  <a class="btn btn-sm btn-outline-primary" href="../bug_tracker/bug_detail.php?id=<?= (int)$row['bug_id'] ?>">Bug #<?= (int)$row['bug_id'] ?></a>
                <?php else: ?>
                  <a class="btn btn-sm btn-danger" href="../bug_tracker/report_bug.php?source=tc&brs_id=<?= (int)$brs_id ?>&tc_id=<?= (int)$row['id'] ?>&ref=brs">Log Bug</a>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge
                  <?= $row['status']==='Passed' ? 'bg-success' :
                     ($row['status']==='Failed' ? 'bg-danger' :
                     ($row['status']==='Blocked' ? 'bg-dark' :
                     ($row['status']==='In Progress' ? 'bg-warning text-dark' :
                     ($row['status']==='Ready' ? 'bg-info text-dark' : 'bg-secondary')))) ?>">
                  <?= htmlspecialchars($row['status']) ?>
                </span>
              </td>
              <td>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                  <input type="hidden" name="case_id" value="<?= (int)$row['id'] ?>">
                  <?php foreach (['Draft','Ready','In Progress','Passed','Failed','Blocked'] as $st): ?>
                    <button class="btn btn-sm btn-outline-secondary status-chip" name="set_case_status" value="<?= $st ?>" type="submit"><?= $st ?></button>
                  <?php endforeach; ?>
                </form>
              </td>
            </tr>
          <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </form>
  <!-- END: Bulk toolbar + form -->

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('checkAll')?.addEventListener('change', function(e){
  document.querySelectorAll('#bulkForm .case-check').forEach(cb => cb.checked = e.target.checked);
});
</script>
</body>
</html>
