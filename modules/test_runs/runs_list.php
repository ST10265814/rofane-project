<?php
require_once __DIR__.'/../../includes/auth_check.php';
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/_partials.php';

$q = trim($_GET['q'] ?? '');
$sql = "SELECT r.*, u.name as creator FROM test_runs r JOIN users u ON u.id=r.created_by WHERE 1";
$params=[]; $types='';
if($q!==''){ $sql .= " AND (r.name LIKE ? OR r.description LIKE ?)"; $params=['%'.$q.'%','%'.$q.'%']; $types='ss'; }
$sql .= " ORDER BY r.created_at DESC";
$stmt = $mysqli->prepare($sql);
if($types!=='') $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result();
?> 
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Test Runs</title>
  <link href="/assets/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 m-0">Test Runs</h1>
    <div><a class="btn btn-primary" href="run_create.php">+ New Run</a></div>
  </div>
  <form class="row g-2 mb-3">
    <div class="col-sm-8"><input class="form-control" name="q" placeholder="Search runs" value="<?=e($q)?>"></div>
    <div class="col-sm-4 d-grid"><button class="btn btn-outline-primary">Filter</button></div>
  </form>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light"><tr><th>#</th><th>Name</th><th>Due</th><th>Created By</th><th>Progress</th><th>Created</th></tr></thead>
      <tbody>
        <?php while($r = $rows->fetch_assoc()): ?>
          <?php
            $prog = $mysqli->query("SELECT 
                SUM(status='Pass') pass,
                SUM(status='Fail') fail,
                SUM(status='Blocked') blocked,
                SUM(status='In Progress') ip,
                SUM(status='Not Run') nr,
                COUNT(*) total
              FROM test_run_items WHERE run_id=".(int)$r['id'])->fetch_assoc();
            $total = (int)($prog['total'] ?? 0);
            $pass = (int)($prog['pass'] ?? 0);
            $pct = $total? round(($pass/$total)*100):0;
          ?>
          <tr>
            <td><a href="run_view.php?id=<?=$r['id']?>">#<?=$r['id']?></a></td>
            <td><a href="run_view.php?id=<?=$r['id']?>" class="fw-semibold"><?=e($r['name'])?></a></td>
            <td><?=e($r['due_date'] ?? '—')?></td>
            <td><?=e($r['creator'])?></td>
            <td>
              <div class="progress" style="height:10px"><div class="progress-bar" style="width: <?=$pct?>%"></div></div>
              <div class="small text-muted mt-1"><?=$pass?> / <?=$total?> passed</div>
            </td>
            <td><span class="small text-muted"><?=e($r['created_at'])?></span></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<script src="/assets/bootstrap.bundle.min.js"></script>
</body></html>