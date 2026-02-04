<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'tester') {
    header("Location: ../login.php");
    exit();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ---------- Defensive: ensure tables exist so prepare() won’t fail ---------- */
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

$conn->query("CREATE TABLE IF NOT EXISTS test_run_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    run_id INT NOT NULL,
    case_id INT NOT NULL,
    status VARCHAR(20) NOT NULL,
    actual TEXT NULL,
    executed_by INT NOT NULL,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

$conn->query("CREATE TABLE IF NOT EXISTS bugs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    severity VARCHAR(20) DEFAULT 'Medium',
    status VARCHAR(20) DEFAULT 'Open',
    reported_by INT NOT NULL,
    assigned_to INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Tester Dashboard - Rofane</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">Rofane</a>
      <div class="d-flex">
        <a href="../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h3 class="mb-0">Tester Dashboard</h3>
    </div>

    <div class="row g-3">
      <!-- Quick Actions -->
      <div class="col-12">
        <div class="card border-0 shadow-sm">
          <div class="card-body d-flex flex-wrap gap-2 align-items-center">
            <h6 class="mb-0 me-3">Quick Actions</h6>
            <a href="../modules/bug_tracker/report_bug.php" class="btn btn-primary btn-sm">Report Bug</a>
            <a href="../modules/brs/upload_brs.php" class="btn btn-outline-primary btn-sm">Upload BRS</a>
            <a href="../modules/brs/index.php" class="btn btn-outline-secondary btn-sm">View BRS / Cases</a>
            <a href="../modules/test_runs/list.php" class="btn btn-success btn-sm">Open Test Runs</a>
            <a href="../modules/brs/traceability.php" class="btn btn-outline-dark btn-sm">Traceability Matrix</a>
          </div>
        </div>
      </div>

      <!-- Resume Last Run -->
      <div class="col-md-12">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">Resume Last Run</h5>
            <?php
            $uid = $_SESSION['user_id'];
            $last = null;

            if ($q = $conn->prepare("SELECT rr.run_id, rr.case_id, r.name, r.environment, r.brs_id
                                     FROM test_run_step_results rr
                                     JOIN test_runs r ON r.id=rr.run_id
                                     WHERE rr.executed_by=? ORDER BY rr.executed_at DESC LIMIT 1")) {
                $q->bind_param("i", $uid);
                $q->execute();
                $rs = $q->get_result();
                $last = $rs->fetch_assoc();
                $q->close();
            }
            if (!$last) {
                if ($q = $conn->prepare("SELECT rr.run_id, rr.case_id, r.name, r.environment, r.brs_id
                                         FROM test_run_results rr
                                         JOIN test_runs r ON r.id=rr.run_id
                                         WHERE rr.executed_by=? ORDER BY rr.executed_at DESC LIMIT 1")) {
                    $q->bind_param("i", $uid);
                    $q->execute();
                    $rs = $q->get_result();
                    $last = $rs->fetch_assoc();
                    $q->close();
                }
            }
            if ($last):
            ?>
              <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div>
                  <div><strong>Run:</strong> #<?= (int)$last['run_id'] ?> — <?= htmlspecialchars($last['name']) ?> (<?= htmlspecialchars($last['environment']) ?>)</div>
                  <div><strong>Case:</strong> #<?= (int)$last['case_id'] ?></div>
                </div>
                <div class="d-flex gap-2">
                  <a class="btn btn-primary" href="../modules/test_runs/view.php?run_id=<?= (int)$last['run_id'] ?>">Open Run</a>
                  <a class="btn btn-outline-secondary" href="../modules/test_runs/view.php?run_id=<?= (int)$last['run_id'] ?>#steps-<?= (int)$last['case_id'] ?>">Open steps for this case</a>
                </div>
              </div>
            <?php else: ?>
              <div class="text-muted">No recent activity. Start by adding cases to a run from your BRS.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Tiles -->
      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title">BRS Library</h5>
            <p class="card-text">Upload BRS, generate GWT test cases, manage and export.</p>
            <a href="../modules/brs/index.php" class="btn btn-outline-primary">Open BRS</a>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title">Test Runs</h5>
            <p class="card-text">Group test cases into runs (UAT/Sprint) and record execution.</p>
            <a href="../modules/test_runs/list.php" class="btn btn-primary">Open Runs</a>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title">Traceability Matrix</h5>
            <p class="card-text">Coverage from requirements → tests → bugs.</p>
            <a href="../modules/brs/traceability.php" class="btn btn-outline-dark">Open RTM</a>
          </div>
        </div>
      </div>

      <!-- My Work -->
      <div class="col-md-12">
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h5 class="card-title">My Work</h5>
            <?php
            $uid = $_SESSION['user_id'];
            $today = date('Y-m-d');

            // My runs today (safe prepare)
            $rres = false;
            if ($stmt = $conn->prepare("SELECT id, name, environment FROM test_runs WHERE created_by=? AND DATE(created_at)=? ORDER BY created_at DESC LIMIT 5")) {
                $stmt->bind_param("is", $uid, $today);
                $stmt->execute(); $rres = $stmt->get_result(); $stmt->close();
            }

            // Failed results without bug (safe prepare)
            $fails = false;
            $failSql = "SELECT rr.run_id, rr.case_id, c.title, c.brs_id
                        FROM test_run_results rr
                        JOIN brs_test_cases c ON c.id=rr.case_id
                        WHERE rr.executed_by=? AND rr.status='Failed' AND (c.bug_id IS NULL OR c.bug_id=0)
                        ORDER BY rr.executed_at DESC LIMIT 5";
            if ($stmt = $conn->prepare($failSql)) {
                $stmt->bind_param("i", $uid);
                $stmt->execute(); $fails = $stmt->get_result(); $stmt->close();
            }

            // Recent bugs by me (safe prepare)
            $bugs = false;
            if ($stmt = $conn->prepare("SELECT id, title FROM bugs WHERE reported_by=? ORDER BY id DESC LIMIT 5")) {
                $stmt->bind_param("i", $uid);
                $stmt->execute(); $bugs = $stmt->get_result(); $stmt->close();
            }
            ?>
            <div class="row">
              <div class="col-md-4">
                <h6>My Runs Today</h6>
                <ul class="list-group list-group-flush">
                  <?php if (!$rres || $rres->num_rows===0): ?>
                    <li class="list-group-item text-muted">None yet.</li>
                  <?php else: while($r=$rres->fetch_assoc()): ?>
                    <li class="list-group-item">
                      <a href="../modules/test_runs/view.php?run_id=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?> — <?= htmlspecialchars($r['name']) ?></a>
                      <small class="text-muted"> (<?= htmlspecialchars($r['environment']) ?>)</small>
                    </li>
                  <?php endwhile; endif; ?>
                </ul>
              </div>

              <div class="col-md-4">
                <h6>Failed cases (no bug yet)</h6>
                <ul class="list-group list-group-flush">
                  <?php if (!$fails || $fails->num_rows===0): ?>
                    <li class="list-group-item text-muted">All clear.</li>
                  <?php else: while($f=$fails->fetch_assoc()): ?>
                    <li class="list-group-item">
                      <a href="../modules/test_runs/view.php?run_id=<?= (int)$f['run_id'] ?>">Run #<?= (int)$f['run_id'] ?></a>
                      — <?= htmlspecialchars($f['title']) ?>
                      <div class="mt-1">
                        <a class="btn btn-sm btn-danger" href="../modules/bug_tracker/report_bug.php?source=tc&brs_id=<?= (int)$f['brs_id'] ?>&tc_id=<?= (int)$f['case_id'] ?>&ref=brs">Log bug</a>
                      </div>
                    </li>
                  <?php endwhile; endif; ?>
                </ul>
              </div>

              <div class="col-md-4">
                <h6>Recent bugs by me</h6>
                <ul class="list-group list-group-flush">
                  <?php if (!$bugs || $bugs->num_rows===0): ?>
                    <li class="list-group-item text-muted">No bugs yet.</li>
                  <?php else: while($b=$bugs->fetch_assoc()): ?>
                    <li class="list-group-item">
                      <a href="../modules/bug_tracker/bug_detail.php?id=<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['title']) ?></a>
                    </li>
                  <?php endwhile; endif; ?>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /row -->
  </div><!-- /container -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
