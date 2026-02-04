<?php
// modules/bug_tracker/assigned_bugs.php
session_start();
require_once '../../includes/config.php';

$role   = $_SESSION['role']  ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!$userId || !in_array($role, ['developer','tester','manager','test_manager','admin'], true)) {
    header("Location: ../../login.php");
    exit();
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function badgeClass($status) {
  switch ($status) {
    case 'Open': return 'bg-danger';
    case 'In Progress': return 'bg-warning text-dark';
    case 'Resolved': return 'bg-success';
    case 'Closed': return 'bg-secondary';
    default: return 'bg-light text-dark';
  }
}

/* ----------------------- Filters & Pagination ----------------------- */
$isManagerLike = in_array($role, ['manager','test_manager','admin'], true);

$assignee = $isManagerLike ? trim($_GET['assignee'] ?? '') : (string)$userId; // managers can pick; others fixed to self
$status   = trim($_GET['status']   ?? '');
$severity = trim($_GET['severity'] ?? '');
$q        = trim($_GET['q']        ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$where = [];
$params = [];
$types  = "";

/* Assignee filter */
if ($isManagerLike) {
  if ($assignee !== '') {
    $where[] = "b.assigned_to = ?";
    $types  .= "i";
    $params[] = (int)$assignee;
  } else {
    $where[] = "b.assigned_to IS NOT NULL";
  }
} else {
  $where[] = "b.assigned_to = ?";
  $types  .= "i";
  $params[] = $userId;
}

/* Status filter */
if ($status !== '' && in_array($status, ['Open','In Progress','Resolved','Closed'], true)) {
  $where[] = "b.status = ?";
  $types  .= "s";
  $params[] = $status;
}

/* Severity filter */
if ($severity !== '' && in_array($severity, ['Minor','Major','Critical','Blocker','Low','Medium','High'], true)) {
  // support either set your data uses
  $where[] = "b.severity = ?";
  $types  .= "s";
  $params[] = $severity;
}

/* Search query */
if ($q !== '') {
  $where[] = "(b.title LIKE CONCAT('%',?,'%') OR b.description LIKE CONCAT('%',?,'%'))";
  $types  .= "ss";
  $params[] = $q;
  $params[] = $q;
}

$whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

/* ----------------------- Count for pagination ----------------------- */
$sqlCount = "
  SELECT COUNT(*)
  FROM bugs b
  $whereSql
";
$stmt = $conn->prepare($sqlCount);
if (!$stmt) { die("SQL error (count): ".$conn->error); }
if ($types) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$stmt->bind_result($totalRows);
$stmt->fetch();
$stmt->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));

/* ----------------------- Main list query ----------------------- */
$sqlList = "
  SELECT b.id, b.title, b.severity, b.status, b.created_at,
         ur.name AS reporter_name,
         ua.name AS assignee_name
  FROM bugs b
  LEFT JOIN users ur ON ur.id=b.reported_by
  LEFT JOIN users ua ON ua.id=b.assigned_to
  $whereSql
  ORDER BY FIELD(b.status,'Open','In Progress','Resolved','Closed'), b.created_at DESC
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sqlList);
if (!$stmt) { die("SQL error (list): ".$conn->error); }

$listTypes  = $types . "ii";
$listParams = $params;
$listParams[] = $perPage;
$listParams[] = $offset;

$stmt->bind_param($listTypes, ...$listParams);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

/* ----------------------- Assignee options (managers only) ----------------------- */
$assignees = [];
if ($isManagerLike) {
  $rs = $conn->query("SELECT id, name, role FROM users WHERE role IN ('tester','developer','manager','test_manager') ORDER BY role, name");
  while ($u = $rs->fetch_assoc()) $assignees[] = $u;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Assigned Bugs - Rofane</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Rofane</a>
    <div class="d-flex">
      <a href="../../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
    </div>
  </div>
</nav>

<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Assigned Bugs</h3>
    <div class="d-flex gap-2">
      <?php if ($role==='developer'): ?>
        <a class="btn btn-outline-secondary btn-sm" href="../../dashboard/developer_dashboard.php">Developer Dashboard</a>
      <?php endif; ?>
      <a class="btn btn-outline-secondary btn-sm" href="view_bugs.php">All Bugs</a>
    </div>
  </div>

  <form class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2">
      <?php if ($isManagerLike): ?>
      <div class="col-md-3">
        <label class="form-label">Assignee</label>
        <select name="assignee" class="form-select">
          <option value="">Any</option>
          <?php foreach ($assignees as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= ($assignee!=='' && (int)$assignee===(int)$u['id'])?'selected':''; ?>>
              <?= h($u['name']) ?> (<?= h($u['role']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
      <div class="col-md-3">
        <label class="form-label">Assignee</label>
        <input class="form-control" value="Me" disabled>
      </div>
      <?php endif; ?>

      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="">Any</option>
          <?php foreach (['Open','In Progress','Resolved','Closed'] as $s): ?>
            <option <?= ($status===$s)?'selected':''; ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Severity</label>
        <select name="severity" class="form-select">
          <option value="">Any</option>
          <?php foreach (['Minor','Major','Critical','Blocker','Low','Medium','High'] as $sev): ?>
            <option <?= ($severity===$sev)?'selected':''; ?>><?= $sev ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Search</label>
        <input type="text" class="form-control" name="q" placeholder="title / description" value="<?= h($q) ?>">
      </div>

      <div class="col-12 d-flex gap-2 mt-1">
        <button class="btn btn-primary">Apply</button>
        <a class="btn btn-outline-secondary" href="assigned_bugs.php">Reset</a>
      </div>
    </div>
  </form>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <?php if (!$rows): ?>
        <div class="text-muted">No bugs found for the selected filters.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:80px">ID</th>
                <th>Title</th>
                <th style="width:120px">Severity</th>
                <th style="width:140px">Status</th>
                <th style="width:200px">Assignee</th>
                <th style="width:200px">Reporter</th>
                <th style="width:170px">Created</th>
                <th style="width:90px"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
              <tr>
                <td>#<?= (int)$r['id'] ?></td>
                <td><a href="bug_detail.php?id=<?= (int)$r['id'] ?>"><?= h($r['title']) ?></a></td>
                <td><span class="badge bg-info"><?= h($r['severity']) ?></span></td>
                <td><span class="badge <?= badgeClass($r['status']) ?>"><?= h($r['status']) ?></span></td>
                <td><?= h($r['assignee_name'] ?? '-') ?></td>
                <td><?= h($r['reporter_name'] ?? '-') ?></td>
                <td><small><?= h($r['created_at']) ?></small></td>
                <td><a class="btn btn-sm btn-outline-primary" href="bug_detail.php?id=<?= (int)$r['id'] ?>">Open</a></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="mt-2">
          <ul class="pagination pagination-sm mb-0">
            <?php
              // helper to keep current filters in pagination links
              $qsBase = $_GET;
              foreach (['page'] as $k) unset($qsBase[$k]);
              $mk = function($p) use ($qsBase){ return 'assigned_bugs.php?'.http_build_query(array_merge($qsBase, ['page'=>$p])); };
            ?>
            <li class="page-item <?= ($page<=1)?'disabled':''; ?>">
              <a class="page-link" href="<?= $mk(max(1,$page-1)) ?>">&laquo;</a>
            </li>
            <?php for($p=1; $p<=$totalPages; $p++): ?>
              <li class="page-item <?= ($p===$page)?'active':''; ?>">
                <a class="page-link" href="<?= $mk($p) ?>"><?= $p ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= ($page>=$totalPages)?'disabled':''; ?>">
              <a class="page-link" href="<?= $mk(min($totalPages,$page+1)) ?>">&raquo;</a>
            </li>
          </ul>
        </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
