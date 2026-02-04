<?php
// modules/bug_tracker/bug_detail.php
session_start();
require_once '../../includes/config.php';

/* ---- Access control ---- */
$role   = $_SESSION['role']  ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
$allowView = in_array($role, ['tester','manager','test_manager','developer','admin','client'], true);
if (!$userId || !$allowView) {
    header("Location: ../../login.php");
    exit();
}

/* ---- CSRF ---- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function valid_csrf(): bool {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/* ---- Ensure audit table exists (safe if already exists) ---- */
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

/* ---- Resolve bug id ---- */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id < 1) { die("Invalid bug ID"); }

$flash_update = '';
$flash_msg    = '';

/* ---- Fetch current bug (with names) ---- */
$sqlBug = "
SELECT b.id, b.title, b.description, b.severity, b.status, b.reported_by, b.assigned_to, b.created_at,
       b.commit_ref, b.fix_notes,
       ua.name AS assignee_name, ur.name AS reporter_name
FROM bugs b
LEFT JOIN users ua ON ua.id=b.assigned_to
LEFT JOIN users ur ON ur.id=b.reported_by
WHERE b.id=?
";
$bug_stmt = $conn->prepare($sqlBug);
if (!$bug_stmt) { die('SQL error (bug SELECT): '.$conn->error); }
$bug_stmt->bind_param("i", $id);
$bug_stmt->execute();
$current_bug = $bug_stmt->get_result()->fetch_assoc();
$bug_stmt->close();
if (!$current_bug) { die("Bug not found."); }

/* ---- Helper: am I the assignee? ---- */
$isAssignee = ((int)$current_bug['assigned_to'] === (int)$userId);

/* ===========================================================
   POST actions
   =========================================================== */

/* ---- Delete bug (tester can delete only their own bug) ---- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_bug'])) {
    if (!valid_csrf()) {
        $flash_update = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        if ($role === 'tester' && (int)$current_bug['reported_by'] === $userId) {
            $del = $conn->prepare("DELETE FROM bugs WHERE id = ? AND reported_by = ?");
            if (!$del) { die('SQL error (bug DELETE): '.$conn->error); }
            $del->bind_param("ii", $id, $userId);
            if ($del->execute() && $del->affected_rows > 0) {
                $del->close();
                header("Location: view_bugs.php?deleted=1");
                exit();
            } else {
                $flash_update = "<div class='alert alert-danger'>Delete failed.</div>";
            }
            $del->close();
        } else {
            $flash_update = "<div class='alert alert-warning'>You cannot delete this bug.</div>";
        }
    }
}

/* ---- Update status/assignee (tester, manager, test_manager) + audit ---- */
if (in_array($role, ['tester','manager','test_manager'], true) && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_bug'])) {
    if (!valid_csrf()) {
        $flash_update = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        $new_status  = $_POST['status'] ?? '';
        $assigned_to = (isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '') ? (int)$_POST['assigned_to'] : null;

        $valid_status = ['Open','In Progress','Resolved','Closed'];
        if (!in_array($new_status, $valid_status, true)) {
            $flash_update = "<div class='alert alert-warning'>Invalid status value.</div>";
        } else {
            // Read old values before update
            $oldq = $conn->prepare("SELECT status, assigned_to FROM bugs WHERE id=?");
            if (!$oldq) { die('SQL error (bug READ-OLD): '.$conn->error); }
            $oldq->bind_param("i", $id);
            $oldq->execute();
            $old = $oldq->get_result()->fetch_assoc();
            $oldq->close();

            if ($assigned_to === null) {
                $stmt = $conn->prepare("UPDATE bugs SET status = ?, assigned_to = NULL, updated_at=NOW() WHERE id = ?");
                if (!$stmt) { die('SQL error (bug UPDATE null assignee): '.$conn->error); }
                $stmt->bind_param("si", $new_status, $id);
            } else {
                $stmt = $conn->prepare("UPDATE bugs SET status = ?, assigned_to = ?, updated_at=NOW() WHERE id = ?");
                if (!$stmt) { die('SQL error (bug UPDATE assignee): '.$conn->error); }
                $stmt->bind_param("sii", $new_status, $assigned_to, $id);
            }

            if ($stmt->execute()) {
                // Audit log
                $log = $conn->prepare("INSERT INTO bug_audit_log (bug_id, action, old_status, new_status, old_assigned_to, new_assigned_to, changed_by)
                                       VALUES (?, ?, ?, ?, ?, ?, ?)");
                if (!$log) { die('SQL error (audit INSERT): '.$conn->error); }
                $log_action     = 'update';
                $old_status_val = $old ? $old['status'] : null;
                $old_assignee   = $old ? $old['assigned_to'] : null;
                $new_assignee   = $assigned_to;
                $changer        = $userId;
                $log->bind_param("isssiii", $id, $log_action, $old_status_val, $new_status, $old_assignee, $new_assignee, $changer);
                $log->execute();
                $log->close();

                $flash_update = "<div class='alert alert-success'>Bug updated successfully.</div>";

                // Refresh current bug
                $bug_stmt = $conn->prepare($sqlBug);
                if (!$bug_stmt) { die('SQL error (bug REFRESH): '.$conn->error); }
                $bug_stmt->bind_param("i", $id);
                $bug_stmt->execute();
                $current_bug = $bug_stmt->get_result()->fetch_assoc();
                $bug_stmt->close();
                $isAssignee = ((int)$current_bug['assigned_to'] === (int)$userId);
            } else {
                $flash_update = "<div class='alert alert-danger'>Failed to update bug.</div>";
            }
            $stmt->close();
        }
    }
}

/* ---- Developer update (assignee only): status + commit ref + fix notes ---- */
if ($role === 'developer' && $isAssignee && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['dev_update'])) {
    if (!valid_csrf()) {
        $flash_update = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        $new_status = $_POST['status'] ?? '';
        // Allowed developer transitions
        $allowed = ['Open','In Progress','Resolved'];
        if (!in_array($new_status, $allowed, true)) {
            $flash_update = "<div class='alert alert-warning'>Invalid status for developer.</div>";
        } else {
            $commit_ref = trim($_POST['commit_ref'] ?? '');
            $fix_notes  = trim($_POST['fix_notes'] ?? '');

            $stmt = $conn->prepare("UPDATE bugs SET status=?, commit_ref=?, fix_notes=?, updated_at=NOW() WHERE id=?");
            if (!$stmt) { die('SQL error (dev UPDATE): '.$conn->error); }
            $stmt->bind_param("sssi", $new_status, $commit_ref, $fix_notes, $id);

            if ($stmt->execute()) {
                // Audit
                $log = $conn->prepare("INSERT INTO bug_audit_log (bug_id, action, old_status, new_status, old_assigned_to, new_assigned_to, changed_by)
                                       VALUES (?, 'dev_update', ?, ?, ?, ?, ?)");
                if ($log) {
                    $old_status = $current_bug['status'];
                    $assignee   = (int)$current_bug['assigned_to'];
                    $log->bind_param("issiii", $id, $old_status, $new_status, $assignee, $assignee, $userId);
                    $log->execute();
                    $log->close();
                }
                $flash_update = "<div class='alert alert-success'>Saved.</div>";

                // Refresh
                $bug_stmt = $conn->prepare($sqlBug);
                if (!$bug_stmt) { die('SQL error (bug REFRESH): '.$conn->error); }
                $bug_stmt->bind_param("i", $id);
                $bug_stmt->execute();
                $current_bug = $bug_stmt->get_result()->fetch_assoc();
                $bug_stmt->close();
                $isAssignee = ((int)$current_bug['assigned_to'] === (int)$userId);
            } else {
                $flash_update = "<div class='alert alert-danger'>Failed to save.</div>";
            }
            $stmt->close();
        }
    }
}

/* ---- Add comment (comment_text, commented_by, user_id, created_at) ---- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset(['$_POST']['comment_submit'])) {
    if (!valid_csrf()) {
        $flash_msg = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        $comment = trim($_POST['comment'] ?? '');
        if ($comment !== '') {
            $hasTable = $conn->query("SHOW TABLES LIKE 'bug_comments'");
            if ($hasTable && $hasTable->num_rows === 1) {
                $c = $conn->prepare("
                    INSERT INTO bug_comments (bug_id, comment_text, commented_by, user_id)
                    VALUES (?, ?, ?, ?)
                ");
                if (!$c) { die('SQL error (comments INSERT): '.$conn->error); }
                $c->bind_param("isii", $id, $comment, $userId, $userId);
                if ($c->execute()) {
                    $flash_msg = "<div class='alert alert-success'>Comment added.</div>";
                } else {
                    $flash_msg = "<div class='alert alert-danger'>Failed to add comment.</div>";
                }
                $c->close();
            } else {
                $flash_msg = "<div class='alert alert-warning'>Comments not enabled (run migrations).</div>";
            }
        } else {
            $flash_msg = "<div class='alert alert-warning'>Comment cannot be empty.</div>";
        }
    }
}

/* ===========================================================
   Read-only data: audit + comments
   =========================================================== */

/* ---- Audit log list ---- */
$audit_res = false;
$hasAudit = $conn->query("SHOW TABLES LIKE 'bug_audit_log'");
if ($hasAudit && $hasAudit->num_rows === 1) {
    $audit = $conn->prepare("
        SELECT l.id, l.action, l.old_status, l.new_status, l.old_assigned_to, l.new_assigned_to,
               l.changed_by, l.created_at, u.name AS changer
        FROM bug_audit_log l
        LEFT JOIN users u ON u.id = l.changed_by
        WHERE l.bug_id = ?
        ORDER BY l.created_at DESC
    ");
    if (!$audit) { die('SQL error (audit SELECT): '.$conn->error); }
    $audit->bind_param("i", $id);
    $audit->execute();
    $audit_res = $audit->get_result();
    $audit->close();
}

/* ---- Comments (select) ---- */
$comments = null;
$hasComments = $conn->query("SHOW TABLES LIKE 'bug_comments'");
if ($hasComments && $hasComments->num_rows === 1) {
    $q = $conn->prepare("
        SELECT bc.id,
               bc.comment_text AS comment,
               bc.created_at,
               u.name AS author
        FROM bug_comments bc
        LEFT JOIN users u
          ON u.id = COALESCE(bc.commented_by, bc.user_id)
        WHERE bc.bug_id = ?
        ORDER BY bc.created_at DESC
    ");
    if (!$q) { die('SQL error (comments SELECT): '.$conn->error); }
    $q->bind_param("i", $id);
    $q->execute();
    $comments = $q->get_result();
    $q->close();
}

/* ---- Assignable users (for manager/tester form) ---- */
$assignable = $conn->query("
    SELECT id, name, role
    FROM users
    WHERE role IN ('tester','developer','manager','test_manager')
    ORDER BY role, name
");

/* ---- UI helpers ---- */
function badgeClass($status) {
    switch ($status) {
        case 'Open': return 'bg-danger';
        case 'In Progress': return 'bg-warning text-dark';
        case 'Resolved': return 'bg-success';
        case 'Closed': return 'bg-secondary';
        default: return 'bg-light text-dark';
    }
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bug #<?= (int)$current_bug['id'] ?> - <?= h($current_bug['title']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">
    <a href="view_bugs.php" class="btn btn-outline-secondary mb-3">&larr; Back to Bugs</a>

    <?= $flash_update ?>
    <?= $flash_msg ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h4 class="mb-1">#<?= (int)$current_bug['id'] ?> — <?= h($current_bug['title']) ?></h4>
            <div class="text-muted small mb-3">
                Reported by <?= h($current_bug['reporter_name'] ?? ('User '.$current_bug['reported_by'])) ?>
                on <?= h($current_bug['created_at']) ?>
            </div>

            <p><?= nl2br(h($current_bug['description'])) ?></p>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge bg-info">Severity: <?= h($current_bug['severity']) ?></span>
                <span class="badge <?= badgeClass($current_bug['status']) ?>">Status: <?= h($current_bug['status']) ?></span>
                <span class="badge bg-secondary">Assigned: <?= h($current_bug['assignee_name'] ?? '-') ?></span>
            </div>

            <?php if (in_array($role, ['tester','manager','test_manager'], true)): ?>
            <form method="POST" class="row g-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <?php foreach (['Open','In Progress','Resolved','Closed'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($current_bug['status']===$s)?'selected':''; ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">Unassigned</option>
                        <?php if ($assignable && $assignable->num_rows>0): ?>
                            <?php while ($u = $assignable->fetch_assoc()): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= ((int)$current_bug['assigned_to'] === (int)$u['id']) ? 'selected':''; ?>>
                                    <?= h($u['name']) ?> (<?= h($u['role']) ?>)
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-primary" name="update_bug" value="1" type="submit">Update</button>
                </div>
            </form>
            <?php endif; ?>

            <?php if ($role==='tester' && (int)$current_bug['reported_by'] === $userId): ?>
                <form method="POST" class="mt-3" onsubmit="return confirm('Delete this bug? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                    <button class="btn btn-outline-danger" name="delete_bug" value="1" type="submit">Delete Bug</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Developer Actions (assignee only) -->
    <?php if ($role==='developer' && $isAssignee): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <h5 class="card-title">Developer Actions</h5>
        <form method="POST" class="row g-2">
          <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
          <input type="hidden" name="dev_update" value="1">

          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" required>
              <?php foreach (['Open','In Progress','Resolved'] as $s): ?>
                <option value="<?= $s ?>" <?= ($current_bug['status']===$s)?'selected':''; ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Commit / PR</label>
            <input type="text" name="commit_ref" class="form-control" placeholder="e.g. 9f2c1d3 / PR#145"
                   value="<?= h($current_bug['commit_ref'] ?? '') ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Fix Notes</label>
            <textarea name="fix_notes" rows="3" class="form-control"
              placeholder="Root cause, what changed, how to retest"><?= h($current_bug['fix_notes'] ?? '') ?></textarea>
          </div>

          <div class="col-12 d-grid">
            <button class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Audit Log -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h5 class="mb-3">Audit Log</h5>
            <?php if ($audit_res && $audit_res->num_rows>0): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>When</th>
                                <th>Who</th>
                                <th>Action</th>
                                <th>Old Status</th>
                                <th>New Status</th>
                                <th>Old Assignee</th>
                                <th>New Assignee</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($l = $audit_res->fetch_assoc()): ?>
                                <tr>
                                    <td><?= h($l['created_at']) ?></td>
                                    <td><?= h($l['changer'] ?? ('User '.$l['changed_by'])) ?></td>
                                    <td><?= h($l['action']) ?></td>
                                    <td><?= h($l['old_status'] ?: '-') ?></td>
                                    <td><?= h($l['new_status'] ?: '-') ?></td>
                                    <td><?= h($l['old_assigned_to'] ?: '-') ?></td>
                                    <td><?= h($l['new_assigned_to'] ?: '-') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No audit entries yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Comments -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h5 class="mb-3">Comments</h5>
            <?php if ($comments && $comments->num_rows>0): ?>
                <ul class="list-group mb-3">
                    <?php while ($c = $comments->fetch_assoc()): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <strong><?= h($c['author'] ?? 'User') ?></strong>
                                <small class="text-muted"><?= h($c['created_at']) ?></small>
                            </div>
                            <div><?= nl2br(h($c['comment'])) ?></div>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted">No comments yet.</p>
            <?php endif; ?>

            <form method="POST" class="d-flex gap-2">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                <input type="text" name="comment" class="form-control" placeholder="Add a comment..." required>
                <button class="btn btn-secondary" name="comment_submit" type="submit">Post</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
