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
if ($s = $conn->prepare("SELECT r.id, r.name, r.environment, r.brs_id, f.original_name
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

$filename = "run_{$run_id}_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');
fputcsv($out, ["Rofane Test Run Export"]);
fputcsv($out, ["Run ID", "#{$run['id']}"]);
fputcsv($out, ["Run Name", $run['name']]);
fputcsv($out, ["Environment", $run['environment']]);
fputcsv($out, ["BRS", "{$run['original_name']} (#{$run['brs_id']})"]);
fputcsv($out, []); // blank
fputcsv($out, ["Case ID","Requirement","Title","Priority","Latest Status","Latest Notes","Last Executed","Bug ID","Evidence (step & bug attachments)","Step Statuses"]);

foreach ($cases as $c) {
  // Evidence from step execution
  $evidence = [];
  if ($q = $conn->prepare("SELECT attachment_path FROM test_run_step_results WHERE run_id=? AND case_id=? AND attachment_path IS NOT NULL")) {
    $q->bind_param("ii", $run_id, $c['case_id']);
    $q->execute();
    $r = $q->get_result();
    while ($er = $r->fetch_assoc()) $evidence[] = $er['attachment_path'];
    $q->close();
  }
  // Evidence from bug attachments if any
  if (!empty($c['bug_id'])) {
    if ($q = $conn->prepare("SELECT file_path FROM bug_attachments WHERE bug_id=?")) {
      $q->bind_param("i", $c['bug_id']);
      $q->execute();
      $r = $q->get_result();
      while ($br = $r->fetch_assoc()) $evidence[] = $br['file_path'];
      $q->close();
    }
  }
  $evidence_str = implode(' | ', $evidence);

  // Step statuses summary
  $steps_str = '';
  if ($q = $conn->prepare("SELECT step_index, COALESCE(NULLIF(step_label,''), CONCAT('#',step_index)) AS lbl, status
                           FROM test_run_step_results
                           WHERE run_id=? AND case_id=?
                           ORDER BY step_index ASC")) {
    $q->bind_param("ii", $run_id, $c['case_id']);
    $q->execute();
    $r = $q->get_result();
    $parts = [];
    while ($sr = $r->fetch_assoc()) $parts[] = $sr['lbl'].': '.$sr['status'];
    $q->close();
    $steps_str = implode('; ', $parts);
  }

  fputcsv($out, [
    $c['case_id'],
    $c['requirement_id'],
    $c['title'],
    $c['priority'],
    $c['latest_status'] ?: '',
    $c['latest_actual'] ?: '',
    $c['last_executed_at'] ?: '',
    $c['bug_id'] ?: '',
    $evidence_str,
    $steps_str
  ]);
}
fclose($out);
exit;
