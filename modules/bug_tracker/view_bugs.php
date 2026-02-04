<?php
session_start();
require_once '../../includes/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['tester', 'manager'])) {
    header("Location: ../../login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ensure audit table exists
$conn->query("CREATE TABLE IF NOT EXISTS bug_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bug_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    old_status VARCHAR(50) NULL,
    new_status VARCHAR(50) NULL,
    old_assigned_to INT NULL,
    new_assigned_to INT NULL,
    changed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bug_id) REFERENCES bugs(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE
)");

$flash = '';

// Inline update (status + assign)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['inline_update'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $flash = "<div class='alert alert-danger'>Invalid request. Please try again.</div>";
    } else {
        $bug_id = (int)$_POST['bug_id'];
        $new_status = $_POST['status'] ?? '';
        $assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;
        $valid_status = ['Open','In Progress','Resolved','Closed'];
        if (!in_array($new_status, $valid_status)) {
            $flash = "<div class='alert alert-warning'>Invalid status.</div>";
        } else {
            // Fetch OLD BEFORE update
            $oldq = $conn->prepare("SELECT status, assigned_to FROM bugs WHERE id=?");
            $oldq->bind_param("i", $bug_id);
            $oldq->execute();
            $oldres = $oldq->get_result()->fetch_assoc();
            $oldq->close();

            if ($assigned_to === null) {
                $stmt = $conn->prepare("UPDATE bugs SET status = ?, assigned_to = NULL WHERE id = ?");
                $stmt->bind_param("si", $new_status, $bug_id);
            } else {
                $stmt = $conn->prepare("UPDATE bugs SET status = ?, assigned_to = ? WHERE id = ?");
                $stmt->bind_param("sii", $new_status, $assigned_to, $bug_id);
            }
            if ($stmt->execute()) {
                $flash = "<div class='alert alert-success'>Bug #$bug_id updated.</div>";
                // Audit
                $log = $conn->prepare("INSERT INTO bug_audit_log (bug_id, action, old_status, new_status, old_assigned_to, new_assigned_to, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $log_action = 'inline_update';
                $old_status = $oldres ? $oldres['status'] : null;
                $new_status_val = $new_status;
                $old_assignee = $oldres ? $oldres['assigned_to'] : null;
                $new_assignee = $assigned_to;
                $changer = $_SESSION['user_id'];
                $log->bind_param("isssiii", $bug_id, $log_action, $old_status, $new_status_val, $old_assignee, $new_assignee, $changer);
                $log->execute();
                $log->close();
            } else {
                $flash = "<div class='alert alert-danger'>Update failed.</div>";
            }
            $stmt->close();
        }
    }
}

// Bulk update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_update'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $flash = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        $new_status = $_POST['bulk_status'] ?? '';
        $assigned_to = isset($_POST['bulk_assigned_to']) && $_POST['bulk_assigned_to'] !== '' ? (int)$_POST['bulk_assigned_to'] : null;
        $valid_status = ['Open','In Progress','Resolved','Closed'];
        if (!$ids || !in_array($new_status, $valid_status)) {
            $flash = "<div class='alert alert-warning'>Select bugs and valid status.</div>";
        } else {
            $updated = 0;
            foreach ($ids as $bug_id) {
                if ($role==='tester') {
                    $chk = $conn->prepare("SELECT id FROM bugs WHERE id = ? AND reported_by = ?");
                    $chk->bind_param("ii", $bug_id, $user_id);
                    $chk->execute();
                    $chk->store_result();
                    if ($chk->num_rows === 0) { $chk->close(); continue; }
                    $chk->close();
                }

                // Fetch OLD BEFORE update
                $oldq = $conn->prepare("SELECT status, assigned_to FROM bugs WHERE id=?");
                $oldq->bind_param("i", $bug_id);
                $oldq->execute();
                $oldres = $oldq->get_result()->fetch_assoc();
                $oldq->close();

                if ($assigned_to === null) {
                    $stmt = $conn->prepare("UPDATE bugs SET status = ?, assigned_to = NULL WHERE id = ?");
                    $stmt->bind_param("si", $new_status, $bug_id);
                } else {
                    $stmt = $conn->prepare("UPDATE bugs SET status = ?, assigned_to = ? WHERE id = ?");
                    $stmt->bind_param("sii", $new_status, $assigned_to, $bug_id);
                }
                if ($stmt->execute()) {
                    $updated++;
                    // Audit
                    $log = $conn->prepare("INSERT INTO bug_audit_log (bug_id, action, old_status, new_status, old_assigned_to, new_assigned_to, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $log_action = 'bulk_update';
                    $old_status = $oldres ? $oldres['status'] : null;
                    $new_status_val = $new_status;
                    $old_assignee = $oldres ? $oldres['assigned_to'] : null;
                    $new_assignee = $assigned_to;
                    $changer = $_SESSION['user_id'];
                    $log->bind_param("isssiii", $bug_id, $log_action, $old_status, $new_status_val, $old_assignee, $new_assignee, $changer);
                    $log->execute();
                    $log->close();
                }
                $stmt->close();
            }
            $flash = "<div class='alert alert-success'>Bulk updated $updated bug(s).</div>";
        }
    }
}

// Bulk delete (tester only, own bugs)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['bulk_delete'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $flash = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        if ($role!=='tester') {
            $flash = "<div class='alert alert-warning'>Only testers can bulk delete their own bugs.</div>";
        } else {
            $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
            if (!$ids) {
                $flash = "<div class='alert alert-warning'>Select bug(s) to delete.</div>";
            } else {
                $deleted = 0;
                foreach ($ids as $bug_id) {
                    $del = $conn->prepare("DELETE FROM bugs WHERE id = ? AND reported_by = ?");
                    $del->bind_param("ii", $bug_id, $user_id);
                    $del->execute();
                    $deleted += $del->affected_rows;
                    $del->close();
                }
                $flash = "<div class='alert alert-success'>Deleted $deleted bug(s).</div>";
            }
        }
    }
}

// Filters
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$severity = isset($_GET['severity']) ? $_GET['severity'] : '';
$only_mine = isset($_GET['only_mine']) ? (int)$_GET['only_mine'] : ($role==='tester'?1:0);
$only_assigned_me = isset($_GET['assigned_me']) ? (int)$_GET['assigned_me'] : 0;

// Export CSV
if (isset($_GET['export']) && $_GET['export']==='csv') {
    $where = [];
    $params = [];
    $types = '';
    if ($role === 'tester') { $where[] = "reported_by = ?"; $params[]=$user_id; $types.='i'; }
    if ($q!=='') { $where[]="title LIKE ?"; $params[]="%$q%"; $types.='s'; }
    if ($status!=='') { $where[]="status = ?"; $params[]=$status; $types.='s'; }
    if ($severity!=='') { $where[]="severity = ?"; $params[]=$severity; $types.='s'; }
    if ($only_mine===1) { $where[]="reported_by = ?"; $params[]=$user_id; $types.='i'; }
    if ($only_assigned_me===1) { $where[]="assigned_to = ?"; $params[]=$user_id; $types.='i'; }
    $where_sql = count($where) ? ('WHERE '.implode(' AND ', $where)) : '';
    $sql = "SELECT id, title, severity, status, reported_by, assigned_to, created_at FROM bugs $where_sql ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    if ($types) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=bugs_export.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Title','Severity','Status','ReportedBy','AssignedTo','CreatedAt']);
    while ($r = $res->fetch_assoc()) {
        fputcsv($out, [$r['id'],$r['title'],$r['severity'],$r['status'],$r['reported_by'],$r['assigned_to'],$r['created_at']]);
    }
    fclose($out);
    exit;
}

// Pagination
$per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Build base query
$where = [];
$params = [];
$types = '';

if ($role === 'tester') { $where[] = "reported_by = ?"; $params[] = $user_id; $types .= 'i'; }
if ($q !== '') { $where[] = "title LIKE ?"; $params[] = '%' . $q . '%'; $types .= 's'; }
if ($status !== '') { $where[] = "status = ?"; $params[] = $status; $types .= 's'; }
if ($severity !== '') { $where[] = "severity = ?"; $params[] = $severity; $types .= 's'; }
if ($only_mine === 1) { $where[] = "reported_by = ?"; $params[] = $user_id; $types .= 'i'; }
if ($only_assigned_me === 1) { $where[] = "assigned_to = ?"; $params[] = $user_id; $types .= 'i'; }

$where_sql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total
$count_sql = "SELECT COUNT(*) FROM bugs $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($types) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$count_stmt->bind_result($total);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = max(1, ceil($total / $per_page));

// Fetch page
$list_sql = "SELECT id, title, severity, status, reported_by, assigned_to, created_at
             FROM bugs
             $where_sql
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?";
$list_stmt = $conn->prepare($list_sql);
if ($types) {
    $bind_types = $types . 'ii';
    $bind_params = array_merge($params, [$per_page, $offset]);
    $list_stmt->bind_param($bind_types, ...$bind_params);
} else {
    $list_stmt->bind_param('ii', $per_page, $offset);
}
$list_stmt->execute();
$result = $list_stmt->get_result();
$list_stmt->close();

$assignable = $conn->query("SELECT id, name, role FROM users WHERE role IN ('tester','manager') ORDER BY role, name");

function badgeClass($status) {
    switch ($status) {
        case 'Open': return 'bg-danger';
        case 'In Progress': return 'bg-warning text-dark';
        case 'Resolved': return 'bg-success';
        case 'Closed': return 'bg-secondary';
        default: return 'bg-light text-dark';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Bugs - Rofane</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0"><i class="bi bi-bug me-2"></i><?= ucfirst($role) ?> Bug List</h3>
            <div class="d-flex gap-2">
                <a href="../../dashboard/<?= $role ?>_dashboard.php" class="btn btn-outline-secondary">Back</a>
                <?php $qs = $_GET; $qs['export']='csv'; $export_link = '?' . http_build_query($qs); ?>
                <a href="<?= $export_link ?>" class="btn btn-outline-primary"><i class="bi bi-download me-1"></i>Export CSV</a>
                <a href="report_bug.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>New Bug</a>
            </div>
        </div>

        <?= $flash ?>

        <!-- Filters -->
        <form class="card card-body shadow-sm border-0 mb-3">
            <div class="row g-2">
                <div class="col-md-3">
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Search title...">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <?php foreach (['Open','In Progress','Resolved','Closed'] as $s): ?>
                            <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="severity" class="form-select">
                        <option value="">All Severities</option>
                        <?php foreach (['Low','Medium','High','Critical'] as $sev): ?>
                            <option value="<?= $sev ?>" <?= $severity===$sev?'selected':'' ?>><?= $sev ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 form-check d-flex align-items-center ms-2">
                    <input class="form-check-input me-2" type="checkbox" id="onlyMine" name="only_mine" value="1" <?= $only_mine? 'checked':'' ?>>
                    <label for="onlyMine" class="form-check-label">Only my bugs</label>
                </div>
                <div class="col-md-3 form-check d-flex align-items-center">
                    <input class="form-check-input me-2" type="checkbox" id="assignedMe" name="assigned_me" value="1" <?= $only_assigned_me? 'checked':'' ?>>
                    <label for="assignedMe" class="form-check-label">Assigned to me</label>
                </div>
                <div class="col-12 col-md-12 d-grid mt-2">
                    <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Apply</button>
                </div>
            </div>
        </form>

        <!-- Results with inline update and bulk actions -->
        <form method="POST" onsubmit="return confirm('Are you sure?');">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:40px;"><input type="checkbox" onclick="document.querySelectorAll('input[name=\'ids[]\']').forEach(cb=>cb.checked=this.checked);"></th>
                                <th style="width:70px;">ID</th>
                                <th>Title</th>
                                <th>Severity</th>
                                <th>Status</th>
                                <th>Assigned</th>
                                <th>Created</th>
                                <th style="width:280px;">Quick Update (row)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows === 0): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No bugs found.</td></tr>
                            <?php else: ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><input type="checkbox" name="ids[]" value="<?= $row['id'] ?>"></td>
                                        <td>#<?= $row['id'] ?></td>
                                        <td><a href="bug_detail.php?id=<?= $row['id'] ?>"><?= htmlspecialchars($row['title']) ?></a></td>
                                        <td><?= $row['severity'] ?></td>
                                        <td><span class="badge <?= badgeClass($row['status']) ?>"><?= $row['status'] ?></span></td>
                                        <td><?= $row['assigned_to'] ?: '-' ?></td>
                                        <td><?= $row['created_at'] ?></td>
                                        <td>
                                            <form method="POST" class="d-flex gap-2">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="inline_update" value="1">
                                                <input type="hidden" name="bug_id" value="<?= $row['id'] ?>">
                                                <select name="status" class="form-select form-select-sm" required>
                                                    <?php foreach (['Open','In Progress','Resolved','Closed'] as $s): ?>
                                                        <option value="<?= $s ?>" <?= $row['status']===$s?'selected':'' ?>><?= $s ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select name="assigned_to" class="form-select form-select-sm" style="min-width:130px;">
                                                    <option value="">Unassigned</option>
                                                    <?php
                                                    mysqli_data_seek($assignable, 0);
                                                    if ($assignable && $assignable->num_rows>0): while ($u = $assignable->fetch_assoc()): ?>
                                                        <option value="<?= $u['id'] ?>" <?= ($row['assigned_to'] == $u['id']) ? 'selected':'' ?>>
                                                            <?= htmlspecialchars($u['name']) ?> (<?= $u['role'] ?>)
                                                        </option>
                                                    <?php endwhile; endif; ?>
                                                </select>
                                                <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Bulk controls -->
                <div class="card-body d-flex flex-wrap gap-2 align-items-end">
                    <div>
                        <label class="form-label mb-1">Bulk status</label>
                        <select name="bulk_status" class="form-select form-select-sm" style="min-width:160px;">
                            <?php foreach (['Open','In Progress','Resolved','Closed'] as $s): ?>
                                <option value="<?= $s ?>"><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label mb-1">Bulk assign</label>
                        <select name="bulk_assigned_to" class="form-select form-select-sm" style="min-width:200px;">
                            <option value="">Unassigned</option>
                            <?php
                            $assignable2 = $conn->query("SELECT id, name, role FROM users WHERE role IN ('tester','manager') ORDER BY role, name");
                            if ($assignable2 && $assignable2->num_rows>0): while ($u = $assignable2->fetch_assoc()): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= $u['role'] ?>)</option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <button class="btn btn-success" name="bulk_update" value="1" type="submit"><i class="bi bi-check2 me-1"></i>Bulk Update</button>
                    <?php if ($role==='tester'): ?>
                        <button class="btn btn-outline-danger" name="bulk_delete" value="1" type="submit"><i class="bi bi-trash me-1"></i>Delete Selected (Own)</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- Pagination -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <nav>
                    <ul class="pagination justify-content-end mb-0">
                        <?php
                        $qs = $_GET;
                        for ($p = 1; $p <= $total_pages; $p++):
                            $qs['page'] = $p;
                            $link = '?' . http_build_query($qs);
                        ?>
                            <li class="page-item <?= $p==$page?'active':'' ?>">
                                <a class="page-link" href="<?= $link ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</body>
</html>
