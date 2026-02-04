<?php
session_start();
require_once '../../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tester') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'tester';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------- Prefill from Test Case context (optional) ----------
$prefill_title = '';
$prefill_description = '';
$prefill_severity = 'Medium';
$link_brs_id = 0;
$link_tc_id = 0;
$ref = isset($_GET['ref']) ? $_GET['ref'] : '';

if (isset($_GET['source']) && $_GET['source'] === 'tc' && isset($_GET['tc_id']) && isset($_GET['brs_id'])) {
    $link_tc_id = (int)$_GET['tc_id'];
    $link_brs_id = (int)$_GET['brs_id'];
    if ($stmt = $conn->prepare("SELECT id, requirement_id, title, steps, expected, priority FROM brs_test_cases WHERE id=? AND brs_id=?")) {
        $stmt->bind_param("ii", $link_tc_id, $link_brs_id);
        $stmt->execute();
        $tc = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($tc) {
            $prefill_title = "[TC {$tc['id']}] " . $tc['title'];
            $prefill_severity = (strtolower($tc['priority'])==='high' ? 'High' : (strtolower($tc['priority'])==='low' ? 'Low' : 'Medium'));
            $prefill_description = "Linked Test Case: TC {$tc['id']} (BRS {$link_brs_id})\nRequirement: {$tc['requirement_id']}\n\nSteps (G/W/T):\n{$tc['steps']}\n\nExpected:\n{$tc['expected']}\n\nActual:\n[fill during execution]\n";
        }
    }
}

// ---------- Build BRS + Test Cases data for selector ----------
// Testers: only their own BRS; Managers: all
$brs_list = [];
if ($role === 'manager') {
    $q = $conn->query("SELECT f.id, f.original_name, u.name AS owner FROM brs_files f LEFT JOIN users u ON u.id=f.uploaded_by ORDER BY f.created_at DESC");
} else {
    if ($s = $conn->prepare("SELECT id, original_name FROM brs_files WHERE uploaded_by = ? ORDER BY created_at DESC")) {
        $s->bind_param("i", $user_id);
        $s->execute();
        $q = $s->get_result();
        $s->close();
    } else { $q = false; }
}
if ($q && $q->num_rows > 0) {
    while ($row = $q->fetch_assoc()) { $brs_list[] = $row; }
}

// Load test cases for those BRS (id, title, requirement)
$cases_by_brs = [];
if (!empty($brs_list)) {
    foreach ($brs_list as $b) {
        $bid = (int)$b['id'];
        $cases_by_brs[$bid] = [];
        if ($st = $conn->prepare("SELECT id, requirement_id, title FROM brs_test_cases WHERE brs_id=? ORDER BY id ASC")) {
            $st->bind_param("i", $bid);
            $st->execute();
            $r = $st->get_result();
            while ($row = $r->fetch_assoc()) {
                $cases_by_brs[$bid][] = $row;
            }
            $st->close();
        }
    }
}

// ---------- Message + Validation ----------
$message = '';

// Ensure attachments table
$conn->query("CREATE TABLE IF NOT EXISTS bug_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bug_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $severity = trim($_POST['severity'] ?? 'Medium');
        $reporter = $_SESSION['user_id'];
        $ref = isset($_POST['ref']) ? $_POST['ref'] : '';

        // Link selection (optional)
        $use_link = isset($_POST['link_enable']) ? 1 : 0;
        $sel_brs_id = isset($_POST['sel_brs_id']) ? (int)$_POST['sel_brs_id'] : 0;
        $sel_tc_ids = isset($_POST['sel_tc_ids']) ? $_POST['sel_tc_ids'] : []; // array

        // Legacy single link (from query)
        $link_brs_id = isset($_POST['brs_id']) ? (int)$_POST['brs_id'] : (isset($_GET['brs_id']) ? (int)$_GET['brs_id'] : 0);
        $link_tc_id = isset($_POST['tc_id']) ? (int)$_POST['tc_id'] : (isset($_GET['tc_id']) ? (int)$_GET['tc_id'] : 0);

        if ($title === '' || $description === '') {
            $message = "<div class='alert alert-warning'>Title and Description are required.</div>";
        } else {
            // Insert bug
            if ($ins = $conn->prepare("INSERT INTO bugs (title, description, severity, status, reported_by) VALUES (?, ?, ?, 'Open', ?)")) {
                $ins->bind_param("sssi", $title, $description, $severity, $reporter);
                if ($ins->execute()) {
                    $bug_id = $ins->insert_id;
                    $ins->close();

                    // Uploaded screenshot (optional)
                    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
                        $allowed = ['image/jpeg','image/png','image/webp'];
                        if (in_array($_FILES['screenshot']['type'], $allowed) && $_FILES['screenshot']['size'] <= 5*1024*1024) {
                            $dir = __DIR__ . '/uploads';
                            if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
                            $ext = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
                            $fname = 'bug_'.$bug_id.'_'.time().'.'.$ext;
                            $target = $dir . '/' . $fname;
                            if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $target)) {
                                $rel = 'modules/bug_tracker/uploads/'.$fname;
                                if ($a = $conn->prepare("INSERT INTO bug_attachments (bug_id, file_path) VALUES (?, ?)")) {
                                    $a->bind_param("is", $bug_id, $rel);
                                    $a->execute();
                                    $a->close();
                                }
                            }
                        }
                    }

                    // ----- Link logic -----
                    $linked_any = false;
                    // 1) Legacy single link (from direct TC prefill)
                    if ($link_brs_id && $link_tc_id) {
                        if ($u = $conn->prepare("UPDATE brs_test_cases SET bug_id=? WHERE id=? AND brs_id=?")) {
                            $u->bind_param("iii", $bug_id, $link_tc_id, $link_brs_id);
                            $u->execute();
                            $u->close();
                            $linked_any = true;
                        }
                    }
                    // 2) New multi-select link
                    if ($use_link && $sel_brs_id && !empty($sel_tc_ids)) {
                        foreach ($sel_tc_ids as $tid) {
                            $cid = (int)$tid;
                            if ($u = $conn->prepare("UPDATE brs_test_cases SET bug_id=? WHERE id=? AND brs_id=?")) {
                                $u->bind_param("iii", $bug_id, $cid, $sel_brs_id);
                                $u->execute();
                                $u->close();
                                $linked_any = true;
                            }
                        }
                    }

                    // Redirect: show "Return to previous case" when exactly one TC is associated
                    if ($ref === 'brs' && $link_brs_id && $link_tc_id) {
                        header("Location: bug_detail.php?id=".$bug_id."&ref=brs&brs_id=".$link_brs_id."&tc_id=".$link_tc_id);
                        exit();
                    } elseif ($use_link && $sel_brs_id && !empty($sel_tc_ids) && count($sel_tc_ids)===1) {
                        $first_tc = (int)$sel_tc_ids[0];
                        header("Location: bug_detail.php?id=".$bug_id."&ref=brs&brs_id=".$sel_brs_id."&tc_id=".$first_tc);
                        exit();
                    } else {
                        header("Location: bug_detail.php?id=".$bug_id);
                        exit();
                    }
                } else {
                    $message = "<div class='alert alert-danger'>Failed to create bug: ".htmlspecialchars($conn->error)."</div>";
                }
            } else {
                $message = "<div class='alert alert-danger'>Failed to prepare DB insert.</div>";
            }
        }
    }
}

// Prepare JSON for test case selector
$cases_json = [];
foreach ($cases_by_brs as $bid=>$rows) {
    $arr = [];
    foreach ($rows as $r) {
        $label = "#".$r['id']." ".($r['requirement_id'] ? $r['requirement_id'].' - ' : '').$r['title'];
        $arr[] = ['id'=>$r['id'], 'label'=>$label];
    }
    $cases_json[$bid] = $arr;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Report a Bug - Rofane</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Report a Bug</h3>
            <a href="../../dashboard/tester_dashboard.php" class="btn btn-outline-secondary">Back</a>
        </div>

        <?= $message ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <?php if ($link_brs_id && $link_tc_id): ?>
                        <input type="hidden" name="brs_id" value="<?= (int)$link_brs_id ?>">
                        <input type="hidden" name="tc_id" value="<?= (int)$link_tc_id ?>">
                        <input type="hidden" name="ref" value="<?= htmlspecialchars($ref) ?>">
                        <div class="alert alert-info mb-3">
                            Linking to Test Case <strong>#<?= (int)$link_tc_id ?></strong> (BRS <strong>#<?= (int)$link_brs_id ?></strong>).
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($prefill_title) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="8" class="form-control" required><?= htmlspecialchars($prefill_description) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Severity</label>
                        <select name="severity" class="form-select">
                            <?php foreach (['High','Medium','Low'] as $sev): ?>
                                <option value="<?= $sev ?>" <?= $prefill_severity===$sev?'selected':'' ?>><?= $sev ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Optional: Link Test Case(s) -->
                    <div class="border rounded p-3 mb-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="link_enable" name="link_enable" <?= ($link_brs_id && $link_tc_id) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="link_enable">Link one or more Test Case(s) to this bug</label>
                        </div>
                        <div id="linkSection" style="display: none;">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Select BRS</label>
                                    <select name="sel_brs_id" id="sel_brs_id" class="form-select">
                                        <option value="0">-- choose BRS --</option>
                                        <?php foreach ($brs_list as $b): ?>
                                            <option value="<?= (int)$b['id'] ?>" <?= ($link_brs_id===(int)$b['id'])?'selected':'' ?>>
                                                <?= htmlspecialchars($b['original_name']) ?><?= isset($b['owner'])?' ('.htmlspecialchars($b['owner']).')':'' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Select Test Case(s)</label>
                                    <select name="sel_tc_ids[]" id="sel_tc_ids" class="form-select" multiple size="8">
                                        <option value="">-- select BRS first --</option>
                                    </select>
                                    <div class="form-text">Hold Ctrl/Cmd to select multiple.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Screenshot (optional, JPG/PNG/WEBP ≤ 5MB)</label>
                        <input type="file" name="screenshot" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/*">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Submit Bug</button>
                        <a href="../../dashboard/tester_dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
const linkEnable = document.getElementById('link_enable');
const section = document.getElementById('linkSection');
const selBRS = document.getElementById('sel_brs_id');
const selTCs = document.getElementById('sel_tc_ids');
const casesByBRS = <?= json_encode($cases_json) ?>;

function refreshTCs() {
    const bid = parseInt(selBRS.value || '0', 10);
    while (selTCs.firstChild) selTCs.removeChild(selTCs.firstChild);
    if (!bid || !casesByBRS[bid] || casesByBRS[bid].length===0) {
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '-- no test cases --';
        selTCs.appendChild(opt);
        return;
    }
    casesByBRS[bid].forEach(row => {
        const opt = document.createElement('option');
        opt.value = row.id;
        opt.textContent = row.label;
        selTCs.appendChild(opt);
    });
}

// Initial state
function updateVisibility() { section.style.display = linkEnable.checked ? '' : 'none'; }
linkEnable.addEventListener('change', updateVisibility);
selBRS.addEventListener('change', refreshTCs);
updateVisibility();
// If preselected from context
if (selBRS.value !== '0') { refreshTCs(); }
</script>
</body>
</html>
