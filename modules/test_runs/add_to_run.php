<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'tester') { header("Location: ../../login.php"); exit(); }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// Ensure tables
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
$conn->query("CREATE TABLE IF NOT EXISTS test_run_cases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  run_id INT NOT NULL,
  case_id INT NOT NULL,
  UNIQUE KEY uniq_run_case (run_id, case_id)
)");
$conn->query("CREATE TABLE IF NOT EXISTS test_run_results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  run_id INT NOT NULL,
  case_id INT NOT NULL,
  status VARCHAR(20) NOT NULL,
  actual TEXT NULL,
  executed_by INT NOT NULL,
  executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$brs_id   = isset($_POST['brs_id']) ? (int)$_POST['brs_id'] : (isset($_GET['brs_id']) ? (int)$_GET['brs_id'] : 0);
$case_ids = isset($_POST['case_ids']) ? array_map('intval', (array)$_POST['case_ids']) : [];
if ($brs_id<=0 || empty($case_ids)) { die("No cases selected."); }

// Load my existing runs for this BRS
$runs = [];
if ($s = $conn->prepare("SELECT id, name, environment FROM test_runs WHERE brs_id=? AND created_by=? ORDER BY created_at DESC")) {
  $s->bind_param("ii", $brs_id, $_SESSION['user_id']);
  $s->execute(); $res = $s->get_result(); while($row=$res->fetch_assoc()) $runs[]=$row; $s->close();
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['do_add'])) {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { die("Invalid request."); }
  $mode = $_POST['mode'] ?? 'new';
  $run_id = 0;

  if ($mode==='existing') {
    $run_id = (int)($_POST['run_id'] ?? 0);
  } else {
    $name = trim($_POST['name'] ?? '');
    $env  = trim($_POST['environment'] ?? 'Default');
    if ($name==='') $name = 'Run '.date('Y-m-d H:i');
    if ($ins = $conn->prepare("INSERT INTO test_runs (brs_id, name, environment, created_by, started_at) VALUES (?, ?, ?, ?, NOW())")) {
      $ins->bind_param("issi", $brs_id, $name, $env, $_SESSION['user_id']);
      if ($ins->execute()) $run_id = $ins->insert_id;
      $ins->close();
    }
  }

  if ($run_id>0) {
    if ($insc = $conn->prepare("INSERT IGNORE INTO test_run_cases (run_id, case_id) VALUES (?, ?)")) {
      foreach ($case_ids as $cid) { $insc->bind_param("ii", $run_id, $cid); $insc->execute(); }
      $insc->close();
    }
    header("Location: view.php?run_id=".$run_id);
    exit();
  } else {
    die("Could not create or select run.");
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Add to Test Run</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <h3 class="mb-3">Add <?= count($case_ids) ?> Test Case(s) to a Run</h3>
  <div class="row g-3">
    <div class="col-md-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h5>Use Existing Run</h5>
          <?php if (empty($runs)): ?>
            <div class="text-muted">No runs yet for this BRS.</div>
          <?php else: ?>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="brs_id" value="<?= (int)$brs_id ?>">
            <input type="hidden" name="mode" value="existing">
            <?php foreach ($case_ids as $cid): ?><input type="hidden" name="case_ids[]" value="<?= (int)$cid ?>"><?php endforeach; ?>
            <div class="mb-3">
              <label class="form-label">Select Run</label>
              <select name="run_id" class="form-select">
                <?php foreach ($runs as $r): ?>
                  <option value="<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?> — <?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['environment']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn btn-primary" type="submit" name="do_add" value="1">Add to Selected Run</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h5>Create New Run</h5>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="brs_id" value="<?= (int)$brs_id ?>">
            <input type="hidden" name="mode" value="new">
            <?php foreach ($case_ids as $cid): ?><input type="hidden" name="case_ids[]" value="<?= (int)$cid ?>"><?php endforeach; ?>
            <div class="mb-3">
              <label class="form-label">Run Name</label>
              <input type="text" name="name" class="form-control" placeholder="e.g., UAT Round 1">
            </div>
            <div class="mb-3">
              <label class="form-label">Environment</label>
              <input type="text" name="environment" class="form-control" placeholder="e.g., Staging">
            </div>
            <button class="btn btn-success" type="submit" name="do_add" value="1">Create & Add</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
