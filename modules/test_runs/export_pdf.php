<?php
session_start();
require_once '../../includes/config.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../../login.php"); exit(); }

$run_id = isset($_GET['run_id']) ? (int)$_GET['run_id'] : 0;
if ($run_id <= 0) die('Invalid run');

$conn->query("CREATE TABLE IF NOT EXISTS bug_attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bug_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$run = null;
if ($s = $conn->prepare("SELECT r.id, r.name, r.environment, r.brs_id, f.original_name, r.started_at, r.ended_at, r.created_at
                         FROM test_runs r
                         LEFT JOIN brs_files f ON f.id=r.brs_id
                         WHERE r.id=?")) {
  $s->bind_param("i", $run_id);
  $s->execute();
  $run = $s->get_result()->fetch_assoc();
  $s->close();
}
if (!$run) die('Run not found');

$cases = [];
$sql = "SELECT c.id AS case_id, c.requirement_id, c.title, c.priority, c.bug_id,
        (SELECT rr.status FROM test_run_results rr WHERE rr.run_id=? AND rr.case_id=c.id ORDER BY rr.executed_at DESC LIMIT 1) AS latest_status,
        (SELECT rr.actual FROM test_run_results rr WHERE rr.run_id=? AND rr.case_id=c.id ORDER BY rr.executed_at DESC LIMIT 1) AS latest_actual,
        (SELECT rr.executed_at FROM test_run_results rr WHERE rr.run_id=? AND rr.case_id=c.id ORDER BY rr.executed_at DESC LIMIT 1) AS last_executed_at
        FROM test_run_cases rc
        JOIN brs_test_cases c ON c.id = rc.case_id
        WHERE rc.run_id=?
        ORDER BY c.id ASC";
if ($st = $conn->prepare($sql)) {
  $st->bind_param("iiii", $run_id, $run_id, $run_id, $run_id);
  $st->execute();
  $res = $st->get_result();
  while ($row = $res->fetch_assoc()) $cases[] = $row;
  $st->close();
}

// Stats
$tot = count($cases); $counts = ['Passed'=>0,'Failed'=>0,'Blocked'=>0,'In Progress'=>0,'Draft'=>0,'Ready'=>0,'Other'=>0];
foreach ($cases as $c) {
  $ls = $c['latest_status'] ?: 'Other';
  if (!isset($counts[$ls])) $counts['Other']++;
  else $counts[$ls]++;
}
$pass_rate = $tot ? round(($counts['Passed'] / $tot) * 100, 1) : 0.0;

// Build HTML
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Run #<?= (int)$run['id'] ?> — <?= htmlspecialchars($run['name']) ?> (Report)</title>
<style>
  body { font-family: Arial, Helvetica, sans-serif; margin: 32px; color:#222; }
  h1,h2,h3 { margin: 0 0 8px; }
  h1 { font-size: 22px; }
  h2 { font-size: 18px; margin-top: 24px; }
  .muted { color:#666; }
  .summary { display:flex; flex-wrap:wrap; gap:16px; margin:16px 0; }
  .card { border:1px solid #ddd; border-radius:8px; padding:12px 16px; }
  .pill { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; color:#fff; }
  .pill.pass { background:#198754; } .pill.fail { background:#dc3545; }
  .pill.block { background:#343a40; } .pill.prog { background:#ffc107; color:#111; }
  table { width:100%; border-collapse:collapse; margin-top:12px; }
  th, td { border:1px solid #e8e8e8; padding:8px; vertical-align:top; }
  th { background:#111; color:#fff; text-align:left; }
  pre { white-space: pre-wrap; margin:0; }
  .ev-list { margin:6px 0 0; padding-left:16px; }
  .ev-list li { margin:2px 0; }
  .actions { margin-bottom: 16px; }
  .btn { display:inline-block; padding:8px 12px; border:1px solid #222; border-radius:6px; text-decoration:none; color:#222; margin-right:8px; }
  @media print {.actions { display:none; }}
</style>
</head>
<body>
<div class="actions">
  <a href="export_csv.php?run_id=<?= (int)$run_id ?>" class="btn">Download CSV</a>
  <a href="#" class="btn" onclick="window.print();return false;">Print / Save as PDF</a>
</div>

<h1>Rofane — Test Run Report</h1>
<div class="muted">Generated: <?= date('Y-m-d H:i') ?></div>

<div class="summary">
  <div class="card"><strong>Run:</strong> #<?= (int)$run['id'] ?> — <?= htmlspecialchars($run['name']) ?></div>
  <div class="card"><strong>Environment:</strong> <?= htmlspecialchars($run['environment']) ?></div>
  <div class="card"><strong>BRS:</strong> <?= htmlspecialchars($run['original_name']) ?> (#<?= (int)$run['brs_id'] ?>)</div>
  <div class="card"><strong>Started:</strong> <?= $run['started_at'] ?: '—' ?></div>
  <div class="card"><strong>Ended:</strong> <?= $run['ended_at'] ?: '—' ?></div>
  <div class="card"><strong>Total Cases:</strong> <?= $tot ?></div>
  <div class="card"><strong>Pass Rate:</strong> <?= $pass_rate ?>%</div>
</div>

<h2>Status Totals</h2>
<div class="summary">
  <div class="card"><span class="pill pass">Passed</span> <?= (int)$counts['Passed'] ?></div>
  <div class="card"><span class="pill fail">Failed</span> <?= (int)$counts['Failed'] ?></div>
  <div class="card"><span class="pill block">Blocked</span> <?= (int)$counts['Blocked'] ?></div>
  <div class="card"><span class="pill prog">In Progress</span> <?= (int)$counts['In Progress'] ?></div>
</div>

<h2>Cases</h2>
<table>
  <thead>
    <tr>
      <th style="width:70px;">Case</th>
      <th style="width:120px;">Requirement</th>
      <th>Title</th>
      <th style="width:90px;">Priority</th>
      <th style="width:120px;">Latest Status</th>
      <th>Notes / Actual</th>
      <th style="width:140px;">Last Executed</th>
      <th style="width:120px;">Bug</th>
      <th>Evidence</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!$cases): ?>
    <tr><td colspan="9" class="muted" style="text-align:center;">No cases in this run.</td></tr>
  <?php else: foreach ($cases as $c): ?>
    <?php
      // evidence from step executions
      $step_ev = [];
      if ($q = $conn->prepare("SELECT step_index, COALESCE(NULLIF(step_label,''), CONCAT('#',step_index)) AS lbl, attachment_path
                               FROM test_run_step_results
                               WHERE run_id=? AND case_id=? AND attachment_path IS NOT NULL
                               ORDER BY step_index ASC")) {
        $q->bind_param("ii", $run_id, $c['case_id']);
        $q->execute();
        $r = $q->get_result();
        while ($er = $r->fetch_assoc()) $step_ev[] = $er;
        $q->close();
      }
      // bug attachments
      $bug_ev = [];
      if (!empty($c['bug_id'])) {
        if ($q = $conn->prepare("SELECT file_path FROM bug_attachments WHERE bug_id=?")) {
          $q->bind_param("i", $c['bug_id']);
          $q->execute();
          $r = $q->get_result();
          while ($br = $r->fetch_assoc()) $bug_ev[] = $br['file_path'];
          $q->close();
        }
      }
    ?>
    <tr>
      <td>#<?= (int)$c['case_id'] ?></td>
      <td><?= htmlspecialchars($c['requirement_id'] ?: '') ?></td>
      <td><?= htmlspecialchars($c['title']) ?></td>
      <td><?= htmlspecialchars($c['priority']) ?></td>
      <td>
        <?php $ls = $c['latest_status'] ?: '—'; ?>
        <?php if ($ls==='Passed'): ?><span class="pill pass">Passed</span>
        <?php elseif ($ls==='Failed'): ?><span class="pill fail">Failed</span>
        <?php elseif ($ls==='Blocked'): ?><span class="pill block">Blocked</span>
        <?php elseif ($ls==='In Progress'): ?><span class="pill prog">In Progress</span>
        <?php else: ?><?= htmlspecialchars($ls) ?><?php endif; ?>
      </td>
      <td><pre><?= htmlspecialchars($c['latest_actual'] ?: '') ?></pre></td>
      <td><?= htmlspecialchars($c['last_executed_at'] ?: '') ?></td>
      <td>
        <?php if (!empty($c['bug_id'])): ?>
          Bug #<?= (int)$c['bug_id'] ?>
        <?php else: ?>—<?php endif; ?>
      </td>
      <td>
        <?php if (empty($step_ev) && empty($bug_ev)): ?>
          <span class="muted">—</span>
        <?php else: ?>
          <ul class="ev-list">
            <?php foreach ($step_ev as $e): ?>
              <li>Step <?= htmlspecialchars($e['lbl']) ?> — <?= htmlspecialchars($e['attachment_path']) ?></li>
            <?php endforeach; ?>
            <?php foreach ($bug_ev as $p): ?>
              <li>Bug evidence — <?= htmlspecialchars($p) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>

</body>
</html>
<?php
$html = ob_get_clean();

/* Try dompdf if installed; otherwise show HTML */
$hasDompdf = false;
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload)) {
  require_once $autoload;
  if (class_exists('\\Dompdf\\Dompdf')) $hasDompdf = true;
}

if ($hasDompdf) {
  $dompdf = new \Dompdf\Dompdf(["isRemoteEnabled"=>true]);
  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();
  $dompdf->stream("run_{$run_id}_report.pdf", ["Attachment"=>true]);
  exit;
} else {
  // Fallback: print-ready HTML
  echo $html;
  exit;
}
