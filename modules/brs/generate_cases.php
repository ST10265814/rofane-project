<?php
session_start();
require_once '../../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tester') {
    header("Location: ../../login.php");
    exit();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ensure tables exist (defensive)
$conn->query("CREATE TABLE IF NOT EXISTS brs_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uploaded_by INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS brs_test_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brs_id INT NOT NULL,
    requirement_id VARCHAR(64) NULL,
    title VARCHAR(255) NOT NULL,
    steps TEXT NULL,
    expected TEXT NULL,
    priority VARCHAR(20) DEFAULT 'Medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");


function normalize_text($txt) {
    $txt = str_replace(["\xC2\xA0", "\xC2\xAD"], [' ', ''], $txt);
    $txt = str_replace(["\r\n", "\r"], "\n", $txt);
    return $txt;
}

function detect_requirements_from_text($text) {
    $text = normalize_text($text);
    $lines = preg_split('/\n+/', $text);
    $requirements = [];
    $seen = [];
    $idx = 1;
    foreach ($lines as $ln) {
        $t = trim($ln);
        if ($t === '') continue;
        $isReq = preg_match('/\bREQ[-_ ]?(\d+)/i', $t)
            || preg_match('/^(Must|Should|Shall|The system shall|The system should|User can|User should|User shall|System must|System should)\b/i', $t)
            || preg_match('/^[-*•]\s+/', $t)
            || preg_match('/^\d+[\.)]\s+/', $t);
        if (!$isReq) continue;
        $rid = null;
        if (preg_match('/\bREQ[-_ ]?(\d+)/i', $t, $m)) { $rid = 'REQ-' . $m[1]; }
        if (!$rid) { $rid = 'R-' . $idx; }
        $hash = md5(mb_strtolower($t));
        if (isset($seen[$hash])) continue;
        $seen[$hash] = true;
        $requirements[] = ['requirement_id'=>$rid, 'text'=>$t];
        $idx++;
    }
    return $requirements;
}

function auto_priority($t) {
    $s = mb_strtolower($t);
    if (preg_match('/\b(critical|must|shall|required|security|payment|login|authentication|authori[sz]ation)\b/', $s)) return 'High';
    if (preg_match('/\b(should|recommended|performance|report|export)\b/', $s)) return 'Medium';
    if (preg_match('/\b(may|optional|nice to have|cosmetic)\b/', $s)) return 'Low';
    return 'Medium';
}

function gwt_positive($t) {
    $given = "Given the system is available";
    $when = "When the user performs the action described: \"$t\"";
    $then = "Then the system behaves as specified: \"$t\"";
    return $given."\n".$when."\n".$then;
}
function gwt_negative($t) {
    $given = "Given the system is available";
    $when = "When the user attempts invalid or edge inputs related to: \"$t\"";
    $then = "Then the system prevents the action or handles it gracefully with clear feedback";
    return $given."\n".$when."\n".$then;
}

$brs_id = isset($_GET['brs_id']) ? (int)$_GET['brs_id'] : 0;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['brs_id'])) { $brs_id = (int)$_POST['brs_id']; }
if ($brs_id <= 0) { die("Invalid BRS id."); }

// Fetch BRS file
$file = null;
if ($s = $conn->prepare("SELECT id, original_name, file_path, created_at FROM brs_files WHERE id = ?")) {
    $s->bind_param("i", $brs_id);
    $s->execute();
    $file = $s->get_result()->fetch_assoc();
    $s->close();
}
if (!$file) { die("BRS not found."); }

// If the user pasted text, we treat that as the source
$text = '';
$requirements = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_text'])) {
    $text = trim($_POST['manual_text']);
    $requirements = $text ? detect_requirements_from_text($text) : [];
} else {
    // Load from file for preview (DOCX/TXT/PDF flow uses code from previous version via helper)
    $path = realpath(__DIR__ . '/../../..' . '/' . $file['file_path']);
    if ($path && file_exists($path)) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'txt') {
            $text = file_get_contents($path);
        } elseif ($ext === 'docx') {
            $zip = new ZipArchive();
            if ($zip->open($path) === TRUE) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml) {
                    $xml = preg_replace('/<w:p[^>]*>/', "\n", $xml);
                    $xml = preg_replace('/<w:tab[^>]*\/>/', "\t", $xml);
                    $xml = strip_tags($xml);
                    $text = html_entity_decode($xml, ENT_QUOTES | ENT_XML1);
                }
            }
        } elseif ($ext === 'pdf') {
            $text = '';
            if (function_exists('shell_exec')) {
                $exe = null;
                if (defined('PDFTOTEXT_PATH') && PDFTOTEXT_PATH && file_exists(PDFTOTEXT_PATH)) $exe = PDFTOTEXT_PATH;
                if (!$exe) {
                    $out = @shell_exec('where pdftotext');
                    if ($out) {
                        $lines = preg_split('/\r?\n/', trim($out));
                        if (!empty($lines[0]) && file_exists($lines[0])) $exe = $lines[0];
                    }
                }
                if ($exe) {
                    $cmd = '"' . $exe . '" -layout "' . $path . '" -';
                    $txt = @shell_exec($cmd . ' 2>&1');
                    if ($txt && trim($txt) !== '') $text = $txt;
                }
            }
        }
    }
    $requirements = $text ? detect_requirements_from_text($text) : [];
}

// If user clicks Save from editor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_cases'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $flash = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        $count = isset($_POST['rows']) ? (int)$_POST['rows'] : 0;
        $saved = 0;
        if ($count > 0) {
            if ($ins = $conn->prepare("INSERT INTO brs_test_cases (brs_id, requirement_id, title, steps, expected, priority) VALUES (?, ?, ?, ?, ?, ?)")) {
                for ($i=0; $i<$count; $i++) {
                    if (!isset($_POST['use_'.$i])) continue;
                    $rid = trim($_POST['req_'.$i] ?? '');
                    $title = trim($_POST['title_'.$i] ?? '');
                    $steps = trim($_POST['steps_'.$i] ?? '');
                    $expected = trim($_POST['expected_'.$i] ?? '');
                    $prio = trim($_POST['prio_'.$i] ?? 'Medium');
                    if ($title === '' || $steps === '') continue;
                    $ins->bind_param("isssss", $brs_id, $rid, $title, $steps, $expected, $prio);
                    if ($ins->execute()) { $saved++; } else { $last_err = $conn->error; }
                }
                $ins->close();
            }
        }
        header("Location: view_cases.php?brs_id=" . (int)$brs_id . "&saved=" . (int)$saved . ($saved===0 && isset($last_err) ? ('&err='.urlencode($last_err)) : ''));
        exit();
    }
}

// Build draft cases for editor
$draft_cases = [];
if (!empty($requirements)) {
    foreach ($requirements as $r) {
        $rid = $r['requirement_id'];
        $sent = $r['text'];
        $prio = auto_priority($sent);
        $title1 = "GWT: " . mb_substr($sent, 0, 140);
        $steps1 = gwt_positive($sent);
        $expected1 = "Behavior conforms to requirement: " . $sent;
        $draft_cases[] = ['req'=>$rid,'title'=>$title1,'steps'=>$steps1,'expected'=>$expected1,'prio'=>$prio];
        $title2 = "GWT Negative: " . mb_substr($sent, 0, 140);
        $steps2 = gwt_negative($sent);
        $expected2 = "System blocks or handles invalid/edge inputs for: " . $sent;
        $draft_cases[] = ['req'=>$rid,'title'=>$title2,'steps'=>$steps2,'expected'=>$expected2,'prio'=>$prio==='High'?'High':'Medium'];
    }
}

?>
<!DOCTYPE html>
<html>
<head>
  <title>Generate Test Cases - Rofane</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Generate Test Cases (Preview & Edit)</h3>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="upload_brs.php">Upload another</a>
        <a class="btn btn-primary" href="view_cases.php?brs_id=<?= (int)$brs_id ?>">View saved cases</a>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <h5 class="mb-2">File</h5>
      <div><strong>Name:</strong> <?= htmlspecialchars($file['original_name']) ?></div>
      <div><strong>Uploaded:</strong> <?= $file['created_at'] ?></div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <h5 class="mb-3">Extracted Requirements</h5>
      <?php if (empty($requirements)): ?>
        <div class="alert alert-warning">
            Could not detect requirements automatically.<br>
            <small>shell_exec: <?php echo function_exists('shell_exec') ? 'ENABLED' : 'DISABLED'; ?>, disabled_functions: <?php echo htmlspecialchars(ini_get('disable_functions') ?: '(none)'); ?></small><br>
            On Windows/XAMPP, PDF extraction requires Poppler's <code>pdftotext.exe</code>. Set <code>PDFTOTEXT_PATH</code> in <code>includes/config.php</code> or paste requirements below.
            <div class="mt-3">
                <form method="POST">
                    <textarea name="manual_text" class="form-control" rows="8" placeholder="Paste requirements here..."></textarea>
                    <button class="btn btn-success mt-2" type="submit">Detect & Preview</button>
                  <div class="mt-2 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary btn-sm">Save Selected (bottom)</button>
          </div>
      </form>
            </div>
        </div>
      <?php else: ?>
        <ol class="mb-0">
          <?php foreach ($requirements as $r): ?>
            <li><strong><?= htmlspecialchars($r['requirement_id']) ?>:</strong> <?= htmlspecialchars($r['text']) ?></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Inline Editor (Given/When/Then)</h5>
        <div>
            <button form="saveForm" type="submit" class="btn btn-primary btn-sm">Save Selected</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAll(true)">Select All</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAll(false)">Unselect All</button>
        </div>
      </div>
      <form method="POST" id="saveForm">
        <input type="hidden" name="brs_id" value="<?= (int)$brs_id ?>">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="save_cases" value="1">
        <input type="hidden" name="rows" value="<?= count($draft_cases) ?>">
        <?php if (empty($draft_cases)): ?>
            <div class="text-muted">No draft cases. Paste requirements above to generate.</div>
        <?php else: ?>
            <?php foreach ($draft_cases as $i=>$c): ?>
                <div class="border rounded p-3 mb-3">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="use_<?= $i ?>" name="use_<?= $i ?>" checked>
                        <label class="form-check-label" for="use_<?= $i ?>">Include this case</label>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label">Requirement ID</label>
                            <input type="text" class="form-control" name="req_<?= $i ?>" value="<?= htmlspecialchars($c['req']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title_<?= $i ?>" value="<?= htmlspecialchars($c['title']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Priority</label>
                            <select name="prio_<?= $i ?>" class="form-select">
                                <?php foreach (['High','Medium','Low'] as $p): ?>
                                    <option value="<?= $p ?>" <?= $c['prio']===$p?'selected':'' ?>><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Steps (G/W/T)</label>
                            <textarea class="form-control" name="steps_<?= $i ?>" rows="3"><?= htmlspecialchars($c['steps']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Expected Result</label>
                            <textarea class="form-control" name="expected_<?= $i ?>" rows="2"><?= htmlspecialchars($c['expected']) ?></textarea>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="mt-2 d-flex justify-content-end">
            <button type="submit" class="btn btn-primary btn-sm">Save Selected (bottom)</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function toggleAll(val) {
    document.querySelectorAll('input[type=checkbox][id^=use_]').forEach(cb => cb.checked = val);
}
</script>
</body>
</html>
