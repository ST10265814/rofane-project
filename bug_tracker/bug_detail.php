<?php
session_start();
require_once '../../includes/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['tester','manager','client'])) {
    header("Location: ../../login.php");
    exit();
}

// CSRF token
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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { die("Invalid bug ID"); }

$update_msg = '';
$msg = '';

// Get current bug
$bug_stmt = $conn->prepare("SELECT id, title, description, severity, status, reported_by, assigned_to, created_at FROM bugs WHERE id = ?");
$bug_stmt->bind_param("i", $id);
$bug_stmt->execute();
$current_bug = $bug_stmt->get_result()->fetch_assoc();
$bug_stmt->close();
if (!$current_bug) { die("Bug not found."); }

// Delete bug (tester only for own bugs)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_bug'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $update_msg = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        if ($_SESSION['role']==='tester' && $current_bug['reported_by']===$_SESSION['user_id']) {
            $del = $conn->prepare("DELETE FROM bugs WHERE id = ? AND reported_by = ?");
            $del->bind_param("ii", $id, $_SESSION['user_id']);
            if ($del->execute() && $del->affected_rows>0) {
                $del->close();
                header("Location: view_bugs.php?deleted=1");
                exit();
            } else {
                $update_msg = "<div class='alert alert-danger'>Delete failed.</div>";
            }
            $del->close();
        } else {
            $update_msg = "<div class='alert alert-warning'>You cannot delete this bug.</div>";
        }
    }
}

// Update status / assignment (tester & manager) with audit
if (in_array($_SESSION['role'], ['tester','manager']) && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_bug'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $update_msg = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        $new_status = $_POST['status'] ?? '';
        $assigned_to = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;
        $valid_status = ['Open','In Progress','Resolved','Closed'];
        if (!in_array($new_status, $valid_status)) {
            $update_msg = "<div class='alert alert-warning'>Invalid status value.</div>";
        } else {
            // Fetch OLD values BEFORE update
            $oldq = $conn->prepare("SELECT status, assigned_to FROM bugs WHERE id=?");
            $oldq->bind_param("i", $id);
            $oldq->execute();
            $old = $oldq->get_result()->fetch_assoc();
            $oldq->close();

            if ($assigned_to === null) {
                $stmt = $conn->prepare("UPDATE bugs SET status = ?, assigned_to = NULL WHERE id = ?");
                $stmt->bind_param("si", $new_status, $id);
            } else {
                $stmt = $conn->prepare("UPDATE bugs SET status = ?, assigned_to = ? WHERE id = ?");
                $stmt->bind_param("sii", $new_status, $assigned_to, $id);
            }
            if ($stmt->execute()) {
                // Insert audit log
                $log = $conn->prepare("INSERT INTO bug_audit_log (bug_id, action, old_status, new_status, old_assigned_to, new_assigned_to, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $log_action = 'update';
                $old_status = $old ? $old['status'] : null;
                $new_status_val = $new_status;
                $old_assignee = $old ? $old['assigned_to'] : null;
                $new_assignee = $assigned_to;
                $changer = $_SESSION['user_id'];
                $log->bind_param("isssiii", $id, $log_action, $old_status, $new_status_val, $old_assignee, $new_assignee, $changer);
                $log->execute();
                $log->close();

                $update_msg = "<div class='alert alert-success'>Bug updated successfully.</div>";
                // Refresh current bug after update
                $bug_stmt = $conn->prepare("SELECT id, title, description, severity, status, reported_by, assigned_to, created_at FROM bugs WHERE id = ?");
                $bug_stmt->bind_param("i", $id);
                $bug_stmt->execute();
                $current_bug = $bug_stmt->get_result()->fetch_assoc();
                $bug_stmt->close();
            } else {
                $update_msg = "<div class='alert alert-danger'>Failed to update bug.</div>";
            }
            $stmt->close();
        }
    }
}

// Comment (with CSRF)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['comment_submit'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $msg = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        $comment = trim($_POST['comment']);
        $by = $_SESSION['user_id'];
        if ($comment !== '') {
            if ($conn->query("SHOW TABLES LIKE 'bug_comments'")->num_rows === 1) {
                $c = $conn->prepare("INSERT INTO bug_comments (bug_id, comment, commented_by) VALUES (?, ?, ?)");
                $c->bind_param("isi", $id, $comment, $by);
                if ($c->execute()) {
                    $msg = "<div class='alert alert-success'>Comment added.</div>";
                } else {
                    $msg = "<div class='alert alert-danger'>Failed to add comment.</div>";
                }
                $c->close();
            } else {
                $msg = "<div class='alert alert-warning'>Comments not enabled (run migrations).</div>";
            }
        } else {
            $msg = "<div class='alert alert-warning'>Comment cannot be empty.</div>";
        }
    }
}

// Reporter name
$reporter_name = null;
$rep_stmt = $conn->prepare("SELECT u.name FROM users u JOIN bugs b ON u.id=b.reported_by WHERE b.id=?");
if ($rep_stmt) {
    $rep_stmt->bind_param("i", $id);
    $rep_stmt->execute();
    $rep_stmt->bind_result($reporter_name);
    $rep_stmt->fetch();
    $rep_stmt->close();
}

// Audit logs
$audit_res = false;
if ($conn->query("SHOW TABLES LIKE 'bug_audit_log'")->num_rows === 1) {
    $audit = null;
if ($conn->query("SHOW TABLES LIKE 'bug_audit_log'")->num_rows === 1) {
    $audit = $conn->prepare("SELECT l.id, l.action, l.old_status, l.new_status, l.old_assigned_to, l.new_assigned_to, l.changed_by, l.created_at, u.name AS changer
                            FROM bug_audit_log l
                            LEFT JOIN users u ON u.id = l.changed_by
                            WHERE l.bug_id = ?
                            ORDER BY l.created_at DESC");
    $audit->bind_param("i", $id);
    $audit->execute();
    $audit_res = $audit->get_result();
    $audit->close();
}}

// Assignable users
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
    <title>Bug #<?= $current_bug['id'] ?> - <?= htmlspecialchars($current_bug['title']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

    <?php if (isset($_GET['ref']) && $_GET['ref']==='brs' && isset($_GET['brs_id']) && isset($_GET['tc_id'])): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center">
        <div>
            Linked from Test Case <strong>#<?= (int)$_GET['tc_id'] ?></strong> (BRS <strong>#<?= (int)$_GET['brs_id'] ?></strong>).
        </div>
        <div>
            <a class="btn btn-sm btn-outline-primary" href="../brs/view_cases.php?brs_id=<?= (int)$_GET['brs_id'] ?>&created_bug_id=<?= (int)$_GET['id'] ?>&tc_id=<?= (int)$_GET['tc_id'] ?>#tc-<?= (int)$_GET['tc_id'] ?>">Return to previous case</a>
        </div>
    </div>
    <?php endif; ?>
    
<div class="container py-4">
    <a href="view_bugs.php" class="btn btn-outline-secondary mb-3">&larr; Back to Bugs</a>
    <?= $update_msg ?>
    <?= $msg ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h4 class="mb-1">#<?= $current_bug['id'] ?> — <?= htmlspecialchars($current_bug['title']) ?></h4>
            <div class="text-muted small mb-3">Reported by <?= htmlspecialchars($reporter_name ?? ('User '.$current_bug['reported_by'])) ?> on <?= $current_bug['created_at'] ?></div>
            <p><?= nl2br(htmlspecialchars($current_bug['description'])) ?></p>
            <div class="d-flex gap-2 mb-3">
                <span class="badge bg-info">Severity: <?= $current_bug['severity'] ?></span>
                <span class="badge <?= badgeClass($current_bug['status']) ?>">Status: <?= $current_bug['status'] ?></span>
                <span class="badge bg-secondary">Assigned: <?= $current_bug['assigned_to'] ?: '-' ?></span>
            </div>

            <?php if (in_array($_SESSION['role'], ['tester','manager'])): ?>
            <form method="POST" class="row g-2 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <?php foreach (['Open','In Progress','Resolved','Closed'] as $s): ?>
                            <option value="<?= $s ?>" <?= $current_bug['status']===$s?'selected':'' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">Unassigned</option>
                        <?php if ($assignable && $assignable->num_rows>0): ?>
                            <?php while ($u = $assignable->fetch_assoc()): ?>
                                <option value="<?= $u['id'] ?>" <?= ($current_bug['assigned_to'] == $u['id']) ? 'selected':'' ?>>
                                    <?= htmlspecialchars($u['name']) ?> (<?= $u['role'] ?>)
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-primary" name="update_bug" value="1" type="submit">Update</button>
                </div>
            </form>

            <?php if ($_SESSION['role']==='tester' && $current_bug['reported_by']===$_SESSION['user_id']): ?>
                <form method="POST" class="mt-3" onsubmit="return confirm('Delete this bug? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button class="btn btn-outline-danger" name="delete_bug" value="1" type="submit">Delete Bug</button>
                </form>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

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
                                    <td><?= $l['created_at'] ?></td>
                                    <td><?= htmlspecialchars($l['changer'] ?? ('User '.$l['changed_by'])) ?></td>
                                    <td><?= $l['action'] ?></td>
                                    <td><?= $l['old_status'] ?: '-' ?></td>
                                    <td><?= $l['new_status'] ?: '-' ?></td>
                                    <td><?= $l['old_assigned_to'] ?: '-' ?></td>
                                    <td><?= $l['new_assigned_to'] ?: '-' ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No audit entries yet (or audit table not created).</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h5 class="mb-3">Comments</h5>
            <?php
            $comments = [];
            if ($conn->query("SHOW TABLES LIKE 'bug_comments'")->num_rows === 1) {
                $q = $conn->prepare("SELECT bc.id, bc.comment, bc.created_at, u.name as author
                                     FROM bug_comments bc
                                     LEFT JOIN users u ON u.id = bc.commented_by
                                     WHERE bc.bug_id = ? ORDER BY bc.created_at DESC");
                $q->bind_param("i", $id);
                $q->execute();
                $comments = $q->get_result();
                $q->close();
            }
            if ($comments && $comments->num_rows>0): ?>
                <ul class="list-group mb-3">
                    <?php while ($c = $comments->fetch_assoc()): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <strong><?= htmlspecialchars($c['author'] ?? 'User') ?></strong>
                                <small class="text-muted"><?= $c['created_at'] ?></small>
                            </div>
                            <div><?= nl2br(htmlspecialchars($c['comment'])) ?></div>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p class="text-muted">No comments yet.</p>
            <?php endif; ?>

            <form method="POST" class="d-flex gap-2">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="text" name="comment" class="form-control" placeholder="Add a comment...">
                <button class="btn btn-secondary" name="comment_submit" type="submit">Post</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
