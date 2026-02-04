<?php
// /manager/workload.php
session_start();
require_once '../includes/config.php';

$role = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId || !in_array($role, ['manager','test_manager','admin'], true)) {
    header("Location: ../login.php");
    exit();
}

/* Defensive: ensure columns exist (won’t error if already there) */
$conn->query("ALTER TABLE bugs
  ADD COLUMN IF NOT EXISTS assigned_to INT NULL,
  ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'Open'");

/* Workload query with all key statuses */
$sql = "
SELECT
  u.id,
  u.name,
  u.role,
  SUM(CASE WHEN b.status='Open' THEN 1 ELSE 0 END)          AS open_cnt,
  SUM(CASE WHEN b.status='In Progress' THEN 1 ELSE 0 END)   AS inprog_cnt,
  SUM(CASE WHEN b.status='Blocked' THEN 1 ELSE 0 END)       AS blocked_cnt,
  SUM(CASE WHEN b.status='Resolved' THEN 1 ELSE 0 END)      AS resolved_cnt,
  SUM(CASE WHEN b.status='Closed' THEN 1 ELSE 0 END)        AS closed_cnt,
  COUNT(b.id)                                               AS total_cnt
FROM users u
LEFT JOIN bugs b ON b.assigned_to = u.id
WHERE u.role IN ('developer','tester')
GROUP BY u.id, u.name, u.role
ORDER BY u.role, u.name
";
$res = $conn->query($sql);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Team Workload - Rofane</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .w-8 { width: 8%; } .w-12 { width: 12%; }
    .progress-xs { height: .75rem; }
  </style>
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
    <h3 class="mb-0">Team Workload</h3>
    <a class="btn btn-outline-secondary btn-sm" href="../dashboard/manager_dashboard.php">Back to Dashboard</a>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>Member</th>
              <th class="w-12">Role</th>
              <th class="w-8 text-center">Open</th>
              <th class="w-12 text-center">In Progress</th>
              <th class="w-8 text-center">Blocked</th>
              <th class="w-12 text-center">Resolved</th>
              <th class="w-8 text-center">Closed</th>
              <th>Active Mix</th>
              <th class="w-12 text-center">Completed %</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($res && $res->num_rows > 0): ?>
            <?php while($r = $res->fetch_assoc()):
              $open    = (int)$r['open_cnt'];
              $inprog  = (int)$r['inprog_cnt'];
              $blocked = (int)$r['blocked_cnt'];
              $resolved= (int)$r['resolved_cnt'];
              $closed  = (int)$r['closed_cnt'];
              $total   = (int)$r['total_cnt'];

              $active  = $open + $inprog + $blocked;
              $done    = $resolved + $closed;

              // Avoid divide-by-zero
              $pctDone = $total > 0 ? round(($done / $total) * 100) : 0;

              // Active stack percentages (sum to 100 if active > 0)
              $pctOpen   = $active > 0 ? ($open   / $active) * 100 : 0;
              $pctInprog = $active > 0 ? ($inprog / $active) * 100 : 0;
              $pctBlocked= $active > 0 ? ($blocked/ $active) * 100 : 0;
            ?>
              <tr>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><?= htmlspecialchars($r['role']) ?></td>
                <td class="text-center"><?= $open ?></td>
                <td class="text-center"><?= $inprog ?></td>
                <td class="text-center"><?= $blocked ?></td>
                <td class="text-center"><?= $resolved ?></td>
                <td class="text-center"><?= $closed ?></td>

                <!-- Active Mix stacked progress -->
                <td>
                  <?php if ($active > 0): ?>
                    <div class="progress progress-xs" title="Open/In Progress/Blocked">
                      <div class="progress-bar bg-secondary" role="progressbar" style="width: <?= $pctOpen ?>%" aria-label="Open"></div>
                      <div class="progress-bar bg-info" role="progressbar" style="width: <?= $pctInprog ?>%" aria-label="In Progress"></div>
                      <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $pctBlocked ?>%" aria-label="Blocked"></div>
                    </div>
                    <div class="small text-muted mt-1">
                      Open <?= $open ?> • In&nbsp;Prog <?= $inprog ?> • Blocked <?= $blocked ?>
                    </div>
                  <?php else: ?>
                    <span class="text-muted">No active items</span>
                  <?php endif; ?>
                </td>

                <!-- Completed % -->
                <td class="text-center">
                  <div class="progress progress-xs" title="(Resolved + Closed) / Total">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $pctDone ?>%"></div>
                  </div>
                  <div class="small mt-1"><?= $pctDone ?>%</div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="9" class="text-muted">No data yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="small text-muted mt-2">
        <span class="badge bg-secondary">&nbsp;</span> Open
        <span class="badge bg-info ms-2">&nbsp;</span> In Progress
        <span class="badge bg-warning ms-2">&nbsp;</span> Blocked
        <span class="badge bg-success ms-2">&nbsp;</span> Completed (for % bar)
      </div>
    </div>
  </div>
</div>
</body>
</html>
<!-- Manager workload placeholder -->