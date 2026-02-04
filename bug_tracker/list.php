<?php
// /bug_tracker/list.php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit();
}

$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';

// ---------- Defaults ----------
$perPage = max(10, (int)($_GET['pp'] ?? 20));
$page    = max(1,  (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Filters (GET)
$q        = trim($_GET['q'] ?? '');
$status   = trim($_GET['status'] ?? '');
$severity = trim($_GET['severity'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$assignee = trim($_GET['assignee'] ?? '');
$reporter = trim($_GET['reporter'] ?? '');
$component= trim($_GET['component'] ?? '');
$release  = trim($_GET['release'] ?? '');
$mine     = trim($_GET['mine'] ?? '');       // 'assigned' or 'reported'
$sort     = trim($_GET['sort'] ?? 'updated'); // updated|created|priority|severity|id
$dir      = strtoupper($_GET['dir'] ?? 'DESC');
$dir      = in_array($dir, ['ASC','DESC'], true) ? $dir : 'DESC';

// Build WHERE (prepared)
$where = [];
$params = [];
$types  = '';

if ($q !== '') {
  $where[] = "(b.title LIKE CONCAT('%', ?, '%') OR b.description LIKE CONCAT('%', ?, '%'))";
  $params[] = $q; $params[] = $q; $types .= 'ss';
}
if ($status !== '') {
  $where[] = "b.status = ?";
  $params[] = $status; $types .= 's';
}
if ($severity !== '') {
  $where[] = "b.severity = ?";
  $params[] = $severity; $types .= 's';
}
if ($priority !== '') {
  $where[] = "b.priority = ?";
  $params[] = $priority; $types .= 's';
}
if ($assignee !== '') {
  if ($assignee === 'none') { $where[] = "b.assigned_to IS NULL"; }
  else { $where[] = "b.assigned_to = ?"; $params[] = (int)$assignee; $types .= 'i'; }
}
if ($reporter !== '') {
  $where[] = "b.reported_by = ?";
  $params[] = (int)$reporter; $types .= 'i';
}
if ($component !== '') {
  $where[] = "b.component = ?";
  $params[] = $component; $types .= 's';
}
if ($release !== '') {
  $where[] = "b.target_release = ?";
  $params[] = $release; $types .= 's';
}
if ($mine === 'assigned') {
  $where[] = "b.assigned_to = ?";
  $params[] = $userId; $types .= 'i';
}
if ($mine === 'reported') {
  $where[] = "b.reported_by = ?";
  $params[] = $userId; $types .= 'i';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Sorting
$sortMap = [
  'updated'  => 'COALESCE(b.updated_at, b.created_at)',
  'created'  => 'b.created_at',
  'priority' => "FIELD(b.priority,'Critical','High','Medium','Low')", // custom order
  'severity' => "FIELD(b.severity,'Blocker','Critical','Major','Minor')",
  'id'       => 'b.id'
];
$orderBy = $sortMap[$sort] ?? $sortMap['updated'];

// Count total
$sqlCount = "SELECT COUNT(*) AS c FROM bugs b $whereSql";
$total = 0;
if ($stmt = $conn->prepare($sqlCount)) {
  if ($params) { $stmt->bind_param($types, ...$params); }
  $stmt->execute();
  $res = $stmt->get_result();
  $total = (int)($res->fetch_assoc()['c'] ?? 0);
  $stmt->close();
}

$pages = max(1, (int)ceil($total / $perPage));

// Fetch list
$sqlList = "
SELECT
  b.id, b.title, b.status, b.severity, b.priority, b.component, b.target_release,
  b.created_at, b.updated_at,
  ua.name AS assignee_name, ur.name AS reporter_name
FROM bugs b
LEFT JOIN users ua ON ua.id = b.assigned_to
LEFT JOIN users ur ON ur.id = b.reported_by
$whereSql
ORDER BY $orderBy $dir, b.id DESC
LIMIT ? OFFSET ?
";
$listParams = $params;
$listTypes  = $types . 'ii';
$listParams[] = $perPage;
$listParams[] = $offset;

// Preload people for filters
$people = $conn->query("SELECT id, name, role FROM users ORDER BY name");

// Distinct components/releases for filters (optional)
$components = $conn->query("SELECT DISTINCT component FROM bugs WHERE component IS NOT NULL AND component <> '' ORDER BY component");
$releases   = $conn->query("SELECT DISTINCT target_release FROM bugs WHERE target_release IS NOT NULL AND target_release <> '' ORDER BY target_release");

// Execute list
$rows = [];
if ($stmt = $conn->prepare($sqlList)) {
  $stmt->bind_param($listTypes, ...$listParams);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $stmt->close();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function badgeStatus($s){
  $map = ['Open'=>'secondary','In Progress'=>'info','Blocked'=>'warning','Resolved'=>'success','Closed'=>'dark'];
  return '<span class="badge bg-'.($map[$s] ?? 'secondary').'">'.h($s).'</span>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Bugs List - Rofane</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>.table td, .table th { vertical-align: middle; }</style>
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
    <h3 class="mb-0">Bugs</h3>
    <div class="text-muted small">Total: <?= $total ?></div>
  </div>

  <!-- Filters -->
  <form class="card border-0 shadow-sm mb-3" method="get">
    <div class="card-body">
      <div class="row g-2">
        <div class="col-md-3">
          <input type="text" name="q" value="<?= h($q) ?>" class="form-control" placeholder="Search title/description">
        </div>
        <div class="col-md-2">
          <select name="status" class="form-select">
            <option value="">Status</option>
            <?php foreach(['Open','In Progress','Blocked','Resolved','Closed'] as $s): ?>
              <option value="<?= $s ?>" <?= $status===$s?'selected':''; ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="severity" class="form-select">
            <option value="">Severity</option>
            <?php foreach(['Minor','Major','Critical','Blocker'] as $s): ?>
              <option value="<?= $s ?>" <?= $severity===$s?'selected':''; ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="priority" class="form-select">
            <option value="">Priority</option>
            <?php foreach(['Low','Medium','High','Critical'] as $p): ?>
              <option value="<?= $p ?>" <?= $priority===$p?'selected':''; ?>><?= $p ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="assignee" class="form-select">
            <option value="">Assignee</option>
            <option value="none" <?= $assignee==='none'?'selected':''; ?>>— Unassigned —</option>
            <?php if ($people): $people->data_seek(0); while($p = $people->fetch_assoc()): ?>
              <option value="<?= (int)$p['id'] ?>" <?= ((string)$assignee===(string)$p['id'])?'selected':''; ?>>
                <?= h($p['name']).' ('.$p['role'].')' ?>
              </option>
            <?php endwhile; endif; ?>
          </select>
        </div>

        <div class="col-md-3">
          <select name="reporter" class="form-select">
            <option value="">Reporter</option>
            <?php if ($people): $people->data_seek(0); while($p = $people->fetch_assoc()): ?>
              <option value="<?= (int)$p['id'] ?>" <?= ((string)$reporter===(string)$p['id'])?'selected':''; ?>>
                <?= h($p['name']).' ('.$p['role'].')' ?>
              </option>
            <?php endwhile; endif; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="component" class="form-select">
            <option value="">Component</option>
            <?php if ($components): while($c = $components->fetch_assoc()): ?>
              <option value="<?= h($c['component']) ?>" <?= $component===$c['component']?'selected':''; ?>><?= h($c['component']) ?></option>
            <?php endwhile; endif; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="release" class="form-select">
            <option value="">Release</option>
            <?php if ($releases): while($r = $releases->fetch_assoc()): ?>
              <option value="<?= h($r['target_release']) ?>" <?= $release===$r['target_release']?'selected':''; ?>><?= h($r['target_release']) ?></option>
            <?php endwhile; endif; ?>
          </select>
        </div>

        <div class="col-md-2">
          <select name="mine" class="form-select">
            <option value="">My View…</option>
            <option value="assigned" <?= $mine==='assigned'?'selected':''; ?>>Assigned to me</option>
            <option value="reported" <?= $mine==='reported'?'selected':''; ?>>Reported by me</option>
          </select>
        </div>

        <div class="col-md-2">
          <select name="sort" class="form-select">
            <option value="updated"  <?= $sort==='updated'?'selected':''; ?>>Sort: Updated</option>
            <option value="created"  <?= $sort==='created'?'selected':''; ?>>Sort: Created</option>
            <option value="priority" <?= $sort==='priority'?'selected':''; ?>>Sort: Priority</option>
            <option value="severity" <?= $sort==='severity'?'selected':''; ?>>Sort: Severity</option>
            <option value="id"       <?= $sort==='id'?'selected':''; ?>>Sort: ID</option>
          </select>
        </div>
        <div class="col-md-1">
          <select name="dir" class="form-select">
            <option value="DESC" <?= $dir==='DESC'?'selected':''; ?>>DESC</option>
            <option value="ASC"  <?= $dir==='ASC'?'selected':''; ?>>ASC</option>
          </select>
        </div>
        <div class="col-md-1">
          <select name="pp" class="form-select">
            <?php foreach([10,20,50,100] as $pp): ?>
              <option value="<?= $pp ?>" <?= $perPage===$pp?'selected':''; ?>><?= $pp ?>/pg</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2 d-grid">
          <button class="btn btn-primary">Apply</button>
        </div>
        <div class="col-md-2 d-grid">
          <a class="btn btn-outline-secondary" href="list.php">Reset</a>
        </div>
      </div>
    </div>
  </form>

  <!-- Results -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:80px;">ID</th>
              <th>Title</th>
              <th>Status</th>
              <th>Priority</th>
              <th>Severity</th>
              <th>Assignee</th>
              <th>Component</th>
              <th>Release</th>
              <th>Updated</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php if ($rows): foreach($rows as $r): ?>


       


            <tr>
              <td>#<?= (int)$r['id'] ?></td>
                  <td><a href="../modules/bug_tracker/bug_detail.php?id=<?= (int)$r['id'] ?>"><?= h($r['title']) ?></a></td>
              <td><?= badgeStatus($r['status']) ?></td>
              <td><?= h($r['priority'] ?? '') ?></td>
              <td><?= h($r['severity'] ?? '') ?></td>
              <td><?= h($r['assignee_name'] ?? '—') ?></td>
              <td><?= h($r['component'] ?? '') ?></td>
              <td><?= h($r['target_release'] ?? '') ?></td>
              <td><small><?= h($r['updated_at'] ?? $r['created_at']) ?></small></td>
              <td>
            <a class="btn btn-sm btn-outline-primary" href="../modules/bug_tracker/bug_detail.php?id=<?= (int)$r['id'] ?>">Open</a>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="10" class="text-muted">No bugs found.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php
      // Build base query string without page
      $qs = $_GET; unset($qs['page']);
      $base = 'list.php?' . http_build_query($qs);
      ?>
      <nav class="mt-3">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item <?= $page<=1?'disabled':''; ?>">
            <a class="page-link" href="<?= $base.'&page='.max(1,$page-1) ?>">« Prev</a>
          </li>
          <?php
            // simple window
            $start = max(1, $page-2);
            $end   = min($pages, $page+2);
            for ($i=$start; $i<=$end; $i++): ?>
              <li class="page-item <?= $i===$page?'active':''; ?>">
                <a class="page-link" href="<?= $base.'&page='.$i ?>"><?= $i ?></a>
              </li>
          <?php endfor; ?>
          <li class="page-item <?= $page>=$pages?'disabled':''; ?>">
            <a class="page-link" href="<?= $base.'&page='.min($pages,$page+1) ?>">Next »</a>
          </li>
        </ul>
      </nav>

    </div>
  </div>
</div>
</body>
</html>
