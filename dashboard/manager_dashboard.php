<?php
session_start();
require_once '../includes/config.php'; // must set up $conn (mysqli)

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['manager','test_manager','admin'], true)) {
    header("Location: ../login.php");
    exit();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ---------- Ensure core tables exist (defensive) ---------- */
$conn->query("CREATE TABLE IF NOT EXISTS bugs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  severity VARCHAR(20) DEFAULT 'Medium',
  priority VARCHAR(20) DEFAULT 'Medium',
  status VARCHAR(20) DEFAULT 'Open',
  resolution VARCHAR(40) NULL,
  component VARCHAR(80) NULL,
  target_release VARCHAR(40) NULL,
  reported_by INT NOT NULL,
  assigned_to INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  closed_by INT NULL,
  closed_at DATETIME NULL
)");

/* ---------- Status counts (prepared) ---------- */
$statuses = ['Open','In Progress','Blocked','Resolved','Closed'];
$counts = [];
if ($stmt = $conn->prepare("SELECT COUNT(*) AS c FROM bugs WHERE status=?")) {
    foreach ($statuses as $st) {
        $stmt->bind_param("s", $st);
        $stmt->execute();
        $res = $stmt->get_result();
        $counts[$st] = (int)($res->fetch_assoc()['c'] ?? 0);
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Manager Dashboard - Rofane</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
      <h3 class="mb-0"><i class="bi bi-diagram-3"></i> Manager Dashboard</h3>
    </div>

    <!-- Status tiles -->
    <div class="row g-3 mb-3">
      <?php foreach ($statuses as $st): ?>
      <div class="col-6 col-md-2">
        <div class="card border-0 shadow-sm text-center">
          <div class="card-body">
            <div class="fw-bold"><?= htmlspecialchars($st) ?></div>
            <div class="display-6"><?= (int)$counts[$st] ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-3">
      <!-- Quick Actions -->
      <div class="col-12">
        <div class="card border-0 shadow-sm">
          <div class="card-body d-flex flex-wrap gap-2 align-items-center">
            <h6 class="mb-0 me-2">Quick Actions</h6>
            <a href="../manager/triage_queue.php" class="btn btn-primary btn-sm"><i class="bi bi-clipboard-check"></i> Triage Queue</a>
            <a href="../manager/workload.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-people"></i> Team Workload</a>
            <a href="../bug_tracker/list.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-list-task"></i> All Bugs</a>
          </div>
        </div>
      </div>

      <!-- Recently updated bugs -->
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title">Recently Updated Bugs</h5>
            <ul class="list-group list-group-flush">
              <?php
              $recent = $conn->query("SELECT id, title, status FROM bugs ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 8");
              if ($recent && $recent->num_rows > 0):
                while($b = $recent->fetch_assoc()): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    <a href="../bug_tracker/bug_detail.php?id=<?= (int)$b['id'] ?>">#<?= (int)$b['id'] ?> — <?= htmlspecialchars($b['title']) ?></a>
                    <span class="badge text-bg-secondary"><?= htmlspecialchars($b['status']) ?></span>
                  </li>
                <?php endwhile;
              else: ?>
                <li class="list-group-item text-muted">No recent activity.</li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
      </div>

      <!-- Unassigned / needs triage -->
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title">Needs Triage</h5>
            <p class="text-muted mb-2">Open/Blocked/In Progress with missing assignee/priority/severity.</p>
            <ul class="list-group list-group-flush">
              <?php
              $tri = $conn->query("
                SELECT id,title,status FROM bugs
                WHERE status IN ('Open','Blocked','In Progress')
                  AND (assigned_to IS NULL OR priority IS NULL OR severity IS NULL)
                ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 8
              ");
              if ($tri && $tri->num_rows > 0):
                while ($t = $tri->fetch_assoc()): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    <a href="../bug_tracker/bug_detail.php?id=<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?> — <?= htmlspecialchars($t['title']) ?></a>
                    <span class="badge text-bg-warning"><?= htmlspecialchars($t['status']) ?></span>
                  </li>
                <?php endwhile;
              else: ?>
                <li class="list-group-item text-muted">All good.</li>
              <?php endif; ?>
            </ul>
            <div class="mt-3">
              <a href="../manager/triage_queue.php" class="btn btn-sm btn-primary">Open Triage</a>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /row -->
  </div><!-- /container -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
