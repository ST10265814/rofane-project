<?php
// /manager/triage_queue.php
session_start();
require_once '../includes/config.php'; // must define $conn (mysqli)

$role = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

// ----- Role gate: manager/test_manager/admin only -----
if (!$userId || !in_array($role, ['manager','test_manager','admin'], true)) {
    header("Location: ../login.php");
    exit();
}

// ----- CSRF token -----
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function require_csrf() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(400);
        die('Invalid request token');
    }
}

// ----- Flash helpers -----
function set_flash($type, $msg){ $_SESSION['flash'] = ['type'=>$type,'msg'=>$msg]; }
function get_flash(){ $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }

// ----- Optional: ensure columns exist (defensive) -----
$conn->query("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('tester','test_manager','manager','client','developer','admin') NOT NULL DEFAULT 'tester',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS bugs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  severity VARCHAR(20) DEFAULT 'Medium',
  priority VARCHAR(20) DEFAULT 'Medium',
  status VARCHAR(20) DEFAULT 'Open',
  resolution VARCHAR(40) NULL,
  component VARCHAR(80) NULL,
  target_release VARCHAR(40) NULL,
  reported_by INT NOT NULL,
  assigned_to INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  closed_by INT NULL,
  closed_at DATETIME NULL
)");

// ===== Handle POST actions in one file =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $bugId  = (int)($_POST['bug_id'] ?? 0);

    if ($bugId < 1) { set_flash('danger','Invalid bug id.'); header("Location: triage_queue.php"); exit(); }

    // Ensure bug exists
    $exists = 0;
    if ($s = $conn->prepare("SELECT id FROM bugs WHERE id=?")) {
        $s->bind_param("i", $bugId);
        $s->execute(); $r = $s->get_result();
        $exists = $r && $r->num_rows ? 1 : 0;
        $s->close();
    }
    if (!$exists) { set_flash('danger','Bug not found.'); header("Location: triage_queue.php"); exit(); }

    if ($action === 'update_meta') {
        $priority = trim($_POST['priority'] ?? '');
        $severity = trim($_POST['severity'] ?? '');
        $component = trim($_POST['component'] ?? '');
        $release = trim($_POST['target_release'] ?? '');

        $priorityList = ['Low','Medium','High','Critical'];
        $severityList = ['Minor','Major','Critical','Blocker'];
        if (!in_array($priority,$priorityList,true) || !in_array($severity,$severityList,true)) {
            set_flash('danger','Invalid priority/severity.');
            header("Location: triage_queue.php"); exit();
        }

        if ($stmt = $conn->prepare("UPDATE bugs SET priority=?, severity=?, component=?, target_release=?, updated_at=NOW() WHERE id=?")) {
            $stmt->bind_param("ssssi", $priority,$severity,$component,$release,$bugId);
            $ok = $stmt->execute(); $stmt->close();
            set_flash($ok?'success':'danger', $ok?'Bug metadata updated.':'Failed to update metadata.');
        } else {
            set_flash('danger','Failed to update metadata.');
        }

    } elseif ($action === 'assign') {
        $assignee = (int)($_POST['assignee_user_id'] ?? 0);
        if ($assignee < 1) { set_flash('danger','Choose a valid assignee.'); header("Location: triage_queue.php"); exit(); }

        // Verify assignee exists and is tester/dev
        $okUser = 0;
        if ($s = $conn->prepare("SELECT id FROM users WHERE id=? AND role IN ('developer','tester')")) {
            $s->bind_param("i", $assignee);
            $s->execute(); $res = $s->get_result();
            $okUser = ($res && $res->num_rows > 0) ? 1 : 0;
            $s->close();
        }
        if (!$okUser) { set_flash('danger','Assignee must be a Tester or Developer.'); header("Location: triage_queue.php"); exit(); }

        if ($stmt = $conn->prepare("UPDATE bugs SET assigned_to=?, updated_at=NOW() WHERE id=?")) {
            $stmt->bind_param("ii", $assignee,$bugId);
            $ok = $stmt->execute(); $stmt->close();
            set_flash($ok?'success':'danger', $ok?'Assigned successfully.':'Failed to assign.');
        } else {
            set_flash('danger','Failed to assign.');
        }

    } elseif ($action === 'close') {
        $resolution = trim($_POST['resolution'] ?? '');
        $resList = ["Fixed","Won't Fix","Duplicate","Cannot Reproduce"];
        if (!in_array($resolution, $resList, true)) {
            set_flash('danger','Invalid resolution.');
            header("Location: triage_queue.php"); exit();
        }
        if ($stmt = $conn->prepare("UPDATE bugs SET status='Closed', resolution=?, closed_by=?, closed_at=NOW(), updated_at=NOW() WHERE id=?")) {
            $stmt->bind_param("sii", $resolution,$userId,$bugId);
            $ok = $stmt->execute(); $stmt->close();
            set_flash($ok?'success':'danger', $ok?'Bug closed.':'Failed to close bug.');
        } else {
            set_flash('danger','Failed to close bug.');
        }

    } elseif ($action === 'reopen') {
        if ($stmt = $conn->prepare("UPDATE bugs SET status='Open', resolution=NULL, closed_by=NULL, closed_at=NULL, updated_at=NOW() WHERE id=?")) {
            $stmt->bind_param("i", $bugId);
            $ok = $stmt->execute(); $stmt->close();
            set_flash($ok?'success':'danger', $ok?'Bug reopened.':'Failed to reopen bug.');
        } else {
            set_flash('danger','Failed to reopen bug.');
        }

    } else {
        set_flash('danger','Unknown action.');
    }

    header("Location: triage_queue.php");
    exit();
}

// ===== Fetch triage list =====
// Items needing triage: Open/Blocked/In Progress AND missing assignee/priority/severity
$sql = "
SELECT b.id, b.title, b.status, b.priority, b.severity, b.component, b.target_release,
       u.name AS assignee_name
FROM bugs b
LEFT JOIN users u ON u.id = b.assigned_to
WHERE b.status IN ('Open','Blocked','In Progress')
  AND (b.assigned_to IS NULL OR b.priority IS NULL OR b.severity IS NULL)
ORDER BY COALESCE(b.updated_at, b.created_at) DESC
LIMIT 300";
$triage = $conn->query($sql);

// Team list (assignees)
$team = $conn->query("SELECT id, name, role FROM users WHERE role IN ('developer','tester') ORDER BY role, name");

$flash = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Triage Queue - Rofane</title>
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
    <h3 class="mb-0">Triage Queue</h3>
    <a class="btn btn-outline-secondary btn-sm" href="../dashboard/manager_dashboard.php">Back to Dashboard</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <p class="text-muted mb-3">Open/Blocked/In Progress items with missing <strong>assignee</strong> / <strong>priority</strong> / <strong>severity</strong>.</p>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:80px;">ID</th>
              <th>Title</th>
              <th>Status</th>
              <th>Assignee</th>
              <th>Priority</th>
              <th>Severity</th>
              <th>Component</th>
              <th>Release</th>
              <th style="width:260px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($triage && $triage->num_rows > 0): ?>
              <?php while($r = $triage->fetch_assoc()): ?>
                <tr>
                  <td>#<?= (int)$r['id'] ?></td>
                  <td><a href="../bug_tracker/bug_detail.php?id=<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['title']) ?></a></td>
                  <td><span class="badge bg-<?= $r['status']==='Blocked'?'warning':($r['status']==='In Progress'?'info':'secondary'); ?>">
                    <?= htmlspecialchars($r['status']) ?></span></td>
                  <td><?= htmlspecialchars($r['assignee_name'] ?? '—') ?></td>

                  <!-- Inline form: update meta -->
                  <td colspan="4">
                    <form class="row g-1" method="post" action="triage_queue.php">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="action" value="update_meta">
                      <input type="hidden" name="bug_id" value="<?= (int)$r['id'] ?>">

                      <div class="col-6 col-md-3">
                        <select class="form-select form-select-sm" name="priority" required>
                          <?php foreach(['Low','Medium','High','Critical'] as $p): ?>
                            <option value="<?= $p ?>" <?= ($r['priority']===$p)?'selected':''; ?>><?= $p ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-6 col-md-3">
                        <select class="form-select form-select-sm" name="severity" required>
                          <?php foreach(['Minor','Major','Critical','Blocker'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($r['severity']===$s)?'selected':''; ?>><?= $s ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-6 col-md-3">
                        <input class="form-control form-control-sm" name="component" placeholder="Component" value="<?= htmlspecialchars($r['component'] ?? '') ?>">
                      </div>
                      <div class="col-6 col-md-3">
                        <input class="form-control form-control-sm" name="target_release" placeholder="Release" value="<?= htmlspecialchars($r['target_release'] ?? '') ?>">
                      </div>
                      <div class="col-12 mt-1">
                        <button class="btn btn-sm btn-primary">Save Meta</button>
                        <a class="btn btn-sm btn-outline-secondary" href="../bug_tracker/bug_detail.php?id=<?= (int)$r['id'] ?>">Open</a>
                      </div>
                    </form>
                  </td>

                  <!-- Actions: assign / close / reopen -->
                  <td>
                    <form class="d-flex flex-column gap-1" method="post" action="triage_queue.php">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                      <input type="hidden" name="bug_id" value="<?= (int)$r['id'] ?>">
                      <div class="input-group input-group-sm">
                        <select class="form-select" name="assignee_user_id" required>
                          <option value="">Assign to…</option>
                          <?php if ($team && $team->num_rows > 0): ?>
                            <?php $team->data_seek(0); while($u = $team->fetch_assoc()): ?>
                              <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name'].' ('.$u['role'].')') ?></option>
                            <?php endwhile; ?>
                          <?php endif; ?>
                        </select>
                        <button class="btn btn-success" name="action" value="assign">Assign</button>
                      </div>

                      <div class="input-group input-group-sm">
                        <select class="form-select" name="resolution">
                          <?php foreach(["Fixed","Won't Fix","Duplicate","Cannot Reproduce"] as $res): ?>
                            <option value="<?= $res ?>"><?= $res ?></option>
                          <?php endforeach; ?>
                        </select>
                        <button class="btn btn-dark" name="action" value="close" <?= ($r['status']==='Closed')?'disabled':''; ?>>Close</button>
                        <button class="btn btn-outline-warning" name="action" value="reopen" <?= ($r['status']!=='Closed')?'disabled':''; ?>>Reopen</button>
                      </div>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="9" class="text-muted">Nothing to triage right now 🎉</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<!-- Manager triage queue placeholder -->