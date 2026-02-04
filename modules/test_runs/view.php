<?php
session_start();
require_once '../../includes/config.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit(); }
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

// Ensure tables (defensive)
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
$conn->query("CREATE TABLE IF NOT EXISTS test_run_step_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_id INT NOT NULL,
    case_id INT NOT NULL,
    step_index INT NOT NULL,
    step_label VARCHAR(32) NULL,
    step_text TEXT NOT NULL,
    status VARCHAR(20) NOT NULL,
    note TEXT NULL,
    attachment_path VARCHAR(255) NULL,
    executed_by INT NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_step (run_id, case_id, step_index)
)");

$run_id = isset($_GET['run_id']) ? (int)$_GET['run_id'] : 0;
if ($run_id<=0) die("Invalid run");

// Helpers
function parse_steps_text($text) {
    $lines = preg_split('/\r\n|\r|\n/', (string)$text);
    $steps = [];
    foreach ($lines as $ln) {
        $t = trim($ln);
        if ($t === '') continue;
        $label = null;
        if (stripos($t, 'Given') === 0) $label = 'Given';
        elseif (stripos($t, 'When') === 0) $label = 'When';
        elseif (stripos($t, 'Then') === 0) $label = 'Then';
        $steps[] = ['idx'=>count($steps), 'label'=>$label, 'text'=>$t];
    }
    if (empty($steps)) $steps[] = ['idx'=>0,'label'=>null,'text'=>'Execute scenario'];
    return $steps;
}
function rollup_case_status($step_statuses) {
    $hasAny = false; $allPassed = true; $hasInProgress = false; $hasFailed = false; $hasBlocked = false;
    foreach ($step_statuses as $st) {
        $hasAny = true;
        if ($st === 'Failed') $hasFailed = true;
        if ($st === 'Blocked') $hasBlocked = true;
        if ($st !== 'Passed') $allPassed = false;
        if ($st === 'In Progress' or $st === 'Not Run') $hasInProgress = true;
    }
    if ($hasFailed) return 'Failed';
    if ($hasBlocked) return 'Blocked';
    if ($hasAny && $allPassed) return 'Passed';
    if ($hasInProgress) return 'In Progress';
    return 'In Progress';
}

// Fetch run meta
$r = null;
if ($s = $conn->prepare("SELECT r.id, r.name, r.environment, r.started_at, r.ended_at, r.created_by, r.brs_id, f.original_name
                         FROM test_runs r LEFT JOIN brs_files f ON f.id=r.brs_id
                         WHERE r.id=?")) {
    $s->bind_param("i", $run_id);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
}
if (!$r) die("Run not found.");

// Handle per-case execution (summary)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['do_execute'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token']!==$_SESSION['csrf_token']) {
        $exec_msg = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        $case_id = (int)$_POST['case_id'];
        $status = $_POST['status'];
        $actual = trim($_POST['actual'] ?? '');
        $allowed = ['In Progress','Passed','Failed','Blocked'];
        if (in_array($status, $allowed, true)) {
            if ($ins = $conn->prepare("INSERT INTO test_run_results (run_id, case_id, status, actual, executed_by) VALUES (?, ?, ?, ?, ?)")) {
                $ins->bind_param("iissi", $run_id, $case_id, $status, $actual, $_SESSION['user_id']);
                $ins->execute();
                $ins->close();
                $exec_msg = "<div class='alert alert-success'>Result saved.</div>";
                if ($status==='Failed') {
                    $exec_msg .= " <a class='btn btn-sm btn-danger ms-2' href='../bug_tracker/report_bug.php?source=tc&brs_id=".$r['brs_id']."&tc_id=".$case_id."&ref=brs&run_id=".$run_id."'>Log bug for this failure</a>";
                }
            }
        }
    }
}

// Handle step save (+ file upload) and roll-up
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['do_save_step'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token']!==$_SESSION['csrf_token']) {
        $exec_msg = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        $case_id = (int)$_POST['case_id'];
        $idx = (int)$_POST['step_index'];
        $label = trim($_POST['step_label'] ?? '');
        $text = trim($_POST['step_text'] ?? '');
        $status = $_POST['step_status'] ?? 'In Progress';
        $note = trim($_POST['step_note'] ?? '');

        // Attachment (image up to 5MB)
        $attach_path = null;
        if (isset($_FILES['step_attachment']) && $_FILES['step_attachment']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg','image/png','image/webp'];
            if (in_array($_FILES['step_attachment']['type'], $allowed) && $_FILES['step_attachment']['size'] <= 5*1024*1024) {
                $dir = __DIR__ . '/uploads';
                if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
                $ext = pathinfo($_FILES['step_attachment']['name'], PATHINFO_EXTENSION);
                $fname = 'run_'.$run_id.'_case_'.$case_id.'_step_'.$idx.'_'.time().'.'.$ext;
                $target = $dir . '/' . $fname;
                if (move_uploaded_file($_FILES['step_attachment']['tmp_name'], $target)) {
                    $attach_path = 'modules/test_runs/uploads/'.$fname;
                }
            }
        }

        // Upsert step
        if ($u = $conn->prepare("INSERT INTO test_run_step_results (run_id, case_id, step_index, step_label, step_text, status, note, attachment_path, executed_by)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                 ON DUPLICATE KEY UPDATE step_label=VALUES(step_label), step_text=VALUES(step_text), status=VALUES(status),
                                 note=VALUES(note), attachment_path=IFNULL(VALUES(attachment_path), attachment_path), executed_by=VALUES(executed_by), executed_at=CURRENT_TIMESTAMP")) {
            $u->bind_param("iiisssssi", $run_id, $case_id, $idx, $label, $text, $status, $note, $attach_path, $_SESSION['user_id']);
            $u->execute();
            $u->close();
        }

        // Roll-up to a new test_run_results row
        $statuses = [];
        if ($q = $conn->prepare("SELECT status FROM test_run_step_results WHERE run_id=? AND case_id=? ORDER BY step_index ASC")) {
            $q->bind_param("ii", $run_id, $case_id);
            $q->execute();
            $resS = $q->get_result();
            while ($row = $resS->fetch_assoc()) $statuses[] = $row['status'];
            $q->close();
        }
        $case_status = rollup_case_status($statuses);
        if ($ins = $conn->prepare("INSERT INTO test_run_results (run_id, case_id, status, actual, executed_by) VALUES (?, ?, ?, ?, ?)")) {
            $summary = 'Step update: '.implode(', ', $statuses);
            $ins->bind_param("iissi", $run_id, $case_id, $case_status, $summary, $_SESSION['user_id']);
            $ins->execute();
            $ins->close();
        }
        $exec_msg = "<div class='alert alert-success'>Step saved and status rolled up to <strong>{$case_status}</strong>.</div>";
    }
}

// Fetch cases in the run
$cases = null;
$sql = "SELECT c.id as case_id, c.requirement_id, c.title, c.priority,
        (SELECT status FROM test_run_results rr WHERE rr.run_id = ? AND rr.case_id = c.id ORDER BY rr.executed_at DESC LIMIT 1) as latest_status,
        (SELECT actual FROM test_run_results rr WHERE rr.run_id = ? AND rr.case_id = c.id ORDER BY rr.executed_at DESC LIMIT 1) as latest_actual
        FROM test_run_cases rc
        JOIN brs_test_cases c ON c.id = rc.case_id
        WHERE rc.run_id = ?
        ORDER BY c.id ASC";
if ($st = $conn->prepare($sql)) {
    $st->bind_param("iii", $run_id, $run_id, $run_id);
    $st->execute();
    $cases = $st->get_result();
    $st->close();
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Run #<?= (int)$r['id'] ?> - <?= htmlspecialchars($r['name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Run #<?= (int)$r['id'] ?> — <?= htmlspecialchars($r['name']) ?></h3>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="list.php">Runs</a>
    </div>
  </div>

  <?= isset($exec_msg)?$exec_msg:'' ?>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div><strong>BRS:</strong> <?= htmlspecialchars($r['original_name']) ?> (#<?= (int)$r['brs_id'] ?>)</div>
      <div><strong>Environment:</strong> <?= htmlspecialchars($r['environment']) ?></div>
      <div><strong>Started:</strong> <?= $r['started_at'] ?: '—' ?> &nbsp; <strong>Ended:</strong> <?= $r['ended_at'] ?: '—' ?></div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead class="table-dark">
          <tr>
            <th style="width:90px;">Case</th>
            <th>Requirement</th>
            <th>Title</th>
            <th style="width:120px;">Priority</th>
            <th style="width:140px;">Latest Status</th>
            <th style="width:420px;">Execute</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$cases || $cases->num_rows===0): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No cases in this run. Add from BRS View Cases (bulk action).</td></tr>
          <?php else: ?>
            <?php while ($row = $cases->fetch_assoc()): ?>
              <tr>
                <td>#<?= (int)$row['case_id'] ?></td>
                <td><?= htmlspecialchars($row['requirement_id']) ?></td>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['priority']) ?></td>
                <td>
                  <span class="badge <?= $row['latest_status']==='Passed' ? 'bg-success' : ($row['latest_status']==='Failed' ? 'bg-danger' : ($row['latest_status']==='Blocked' ? 'bg-dark' : ($row['latest_status']==='In Progress' ? 'bg-warning text-dark' : 'bg-secondary'))) ?>">
                    <?= htmlspecialchars($row['latest_status'] ?: '—') ?>
                  </span>
                </td>
                <td>
                  <!-- Summary execution -->
                  <form method="POST" class="row g-2 align-items-start">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="do_execute" value="1">
                    <input type="hidden" name="case_id" value="<?= (int)$row['case_id'] ?>">
                    <div class="col-md-4">
                      <select name="status" class="form-select form-select-sm">
                        <?php foreach (['In Progress','Passed','Failed','Blocked'] as $st): ?>
                          <option value="<?= $st ?>"><?= $st ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-8">
                      <textarea name="actual" rows="1" class="form-control form-control-sm" placeholder="Actual result / notes"><?= htmlspecialchars($row['latest_actual'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12 d-flex justify-content-between align-items-center mt-1">
                      <button class="btn btn-sm btn-primary" type="submit">Save</button>
                      <a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#steps-<?= (int)$row['case_id'] ?>" role="button" aria-expanded="false" aria-controls="steps-<?= (int)$row['case_id'] ?>">Steps</a>
                    </div>
                  </form>

                  <!-- Steps execution -->
                  <div class="collapse mt-2" id="steps-<?= (int)$row['case_id'] ?>">
                    <?php
                      // Load steps from case text
                      $steps = [];
                      if ($stp = $conn->prepare("SELECT steps FROM brs_test_cases WHERE id=?")) {
                          $stp->bind_param("i", $row['case_id']);
                          $stp->execute();
                          $stp_res = $stp->get_result()->fetch_assoc();
                          $stp->close();
                          $steps = parse_steps_text($stp_res['steps'] ?? '');
                      }
                      // Existing step results for this run/case
                      $existing = [];
                      if ($q = $conn->prepare("SELECT step_index, step_label, step_text, status, note, attachment_path
                                               FROM test_run_step_results WHERE run_id=? AND case_id=?")) {
                          $q->bind_param("ii", $run_id, $row['case_id']);
                          $q->execute();
                          $resE = $q->get_result();
                          while ($er = $resE->fetch_assoc()) { $existing[(int)$er['step_index']] = $er; }
                          $q->close();
                      }
                    ?>
                    <div class="list-group">
                      <?php foreach ($steps as $s): 
                        $idx = (int)$s['idx'];
                        $cur = $existing[$idx] ?? null;
                        $curStatus = $cur['status'] ?? 'Not Run';
                        $curNote = $cur['note'] ?? '';
                        $curLabel = $s['label'] ?? ($cur['step_label'] ?? '');
                        $curText = $s['text'] ?? ($cur['step_text'] ?? '');
                      ?>
                        <div class="list-group-item">
                          <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-center">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="do_save_step" value="1">
                            <input type="hidden" name="case_id" value="<?= (int)$row['case_id'] ?>">
                            <input type="hidden" name="step_index" value="<?= $idx ?>">

                            <div class="col-md-2">
                              <input type="text" name="step_label" class="form-control form-control-sm" placeholder="Label" value="<?= htmlspecialchars($curLabel) ?>">
                            </div>
                            <div class="col-md-4">
                              <input type="text" name="step_text" class="form-control form-control-sm" value="<?= htmlspecialchars($curText) ?>">
                            </div>
                            <div class="col-md-2">
                              <select name="step_status" class="form-select form-select-sm">
                                <?php foreach (['Not Run','In Progress','Passed','Failed','Blocked'] as $stp): ?>
                                  <option value="<?= $stp ?>" <?= $curStatus===$stp?'selected':'' ?>><?= $stp ?></option>
                                <?php endforeach; ?>
                              </select>
                            </div>
                            <div class="col-md-3">
                              <input type="text" name="step_note" class="form-control form-control-sm" placeholder="Note" value="<?= htmlspecialchars($curNote) ?>">
                            </div>
                            <div class="col-md-1 d-grid">
                              <button class="btn btn-sm btn-primary" type="submit">Save</button>
                            </div>

                            <div class="col-12 mt-1">
                              <div class="d-flex align-items-center gap-2">
                                <input type="file" name="step_attachment" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.webp,image/*" style="max-width:300px;">
                                <?php if (!empty($cur['attachment_path'])): ?>
                                  <a class="btn btn-sm btn-outline-secondary" target="_blank" href="../../<?= htmlspecialchars($cur['attachment_path']) ?>">View evidence</a>
                                <?php endif; ?>
                              </div>
                              <?php if (!empty($curText)): ?>
                              <a class="btn btn-sm btn-danger mt-2"
                                 href="../bug_tracker/report_bug.php?source=tc&brs_id=<?= (int)$r['brs_id'] ?>&tc_id=<?= (int)$row['case_id'] ?>&ref=brs&run_id=<?= (int)$run_id ?>&step_index=<?= $idx ?>">
                                Log bug from this step
                              </a>
                              <?php endif; ?>
                            </div>
                          </form>
                        </div>
                      <?php endforeach; ?>
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
<script>
document.addEventListener('DOMContentLoaded', function(){
  // Auto-open a case's steps if URL hash is present (#steps-<case_id>)
  var h = window.location.hash;
  if (h && h.startsWith('#steps-')) {
    var el = document.querySelector(h);
    if (el) {
      try { new bootstrap.Collapse(el, {toggle: true}); } catch(e) {}
      el.scrollIntoView({behavior: 'smooth', block: 'center'});
    }
  }
});
</script>
</body>
</html>
