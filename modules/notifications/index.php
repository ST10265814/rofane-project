<?php
session_start();
require_once '../../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// Ensure table exists (defensive)
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bug_id INT NULL,
    type VARCHAR(50) NOT NULL, -- bug_created, status_change, assigned, comment
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Mark all read
if (isset($_GET['mark']) && $_GET['mark'] === 'all') {
    if ($m = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")) {
        $m->bind_param("i", $user_id);
        $m->execute();
        $m->close();
    }
    header("Location: index.php");
    exit();
}

// Mark a single notification read
if (isset($_GET['read'])) {
    $nid = (int)$_GET['read'];
    if ($m = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")) {
        $m->bind_param("ii", $nid, $user_id);
        $m->execute();
        $m->close();
    }
    header("Location: index.php");
    exit();
}

// Open a notification (mark read then redirect to bug if linked)
if (isset($_GET['open'])) {
    $nid = (int)$_GET['open'];
    $bug_id = null;
    if ($s = $conn->prepare("SELECT bug_id FROM notifications WHERE id = ? AND user_id = ?")) {
        $s->bind_param("ii", $nid, $user_id);
        $s->execute();
        $s->bind_result($bug_id);
        $s->fetch();
        $s->close();
    }
    if ($u = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")) {
        $u->bind_param("ii", $nid, $user_id);
        $u->execute();
        $u->close();
    }
    if ($bug_id) {
        header("Location: ../bug_tracker/bug_detail.php?id=".(int)$bug_id);
    } else {
        header("Location: index.php");
    }
    exit();
}

// Pagination
$per_page = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Count total
$total = 0;
if ($c = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?")) {
    $c->bind_param("i", $user_id);
    $c->execute();
    $c->bind_result($total);
    $c->fetch();
    $c->close();
}
$total_pages = max(1, (int)ceil($total / $per_page));

// Fetch page
$res = false;
if ($stmt = $conn->prepare("SELECT id, bug_id, type, message, is_read, created_at
                            FROM notifications
                            WHERE user_id = ?
                            ORDER BY created_at DESC
                            LIMIT ? OFFSET ?")) {
    $stmt->bind_param("iii", $user_id, $per_page, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Notifications - Rofane</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-bell me-2"></i>Notifications</h3>
    <div class="d-flex gap-2">
        <a href="../bug_tracker/view_bugs.php" class="btn btn-outline-secondary">Back</a>
        <a href="?mark=all" class="btn btn-primary">Mark all read</a>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="list-group list-group-flush">
      <?php if ($res && $res->num_rows > 0): ?>
        <?php while ($n = $res->fetch_assoc()): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center <?=
               $n['is_read'] ? '' : 'list-group-item-warning' ?>">
            <div class="me-3">
              <div class="fw-semibold"><?= htmlspecialchars($n['message']) ?></div>
              <small class="text-muted"><?= $n['created_at'] ?></small>
            </div>

            <div class="d-flex gap-2">
              <?php if ($n['bug_id']): ?>
                <a class="btn btn-sm btn-outline-primary"
                   href="?open=<?= (int)$n['id'] ?>">Open</a>
              <?php endif; ?>
              <?php if (!$n['is_read']): ?>
                <a class="btn btn-sm btn-outline-secondary"
                   href="?read=<?= (int)$n['id'] ?>">Mark read</a>
              <?php endif; ?>
              <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($n['type']) ?></span>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="list-group-item text-muted">No notifications.</div>
      <?php endif; ?>
    </div>

    <!-- Pagination -->
    <div class="card-body">
      <nav>
        <ul class="pagination justify-content-end mb-0">
          <?php for ($p = 1; $p <= $total_pages; $p++): ?>
            <li class="page-item <?= $p == $page ? 'active' : '' ?>">
              <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    </div>
  </div>
</div>
</body>
</html>
