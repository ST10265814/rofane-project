<?php
session_start();
require_once '../includes/config.php'; // must set up $conn (mysqli)

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['developer','test_manager','manager','admin'], true)) {
    header("Location: ../login.php");
    exit();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ---------- Ensure tables exist (defensive) ---------- */
$conn->query("CREATE TABLE IF NOT EXISTS bugs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  severity VARCHAR(20) DEFAULT 'Medium',
  priority VARCHAR(20) DEFAULT 'Medium',
  status VARCHAR(20) DEFAULT 'Open',
  fix_notes TEXT NULL,
  commit_ref VARCHAR(80) NULL,
  reported_by INT NOT NULL,
  assigned_to INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL
)");

$uid = (int)($_SESSION['user_id'] ?? 0);

/* ---------- My assigned (active) ---------- */
$active = null;
if ($stmt = $conn->prepare("
    SELECT id,title,status,severity,priority,COALESCE(updated_at,created_at) as ts
    FROM bugs
    WHERE assigned_to=? AND status IN ('Open','In Progress','Blocked')
    ORDER BY ts DESC LIMIT 10
")) {
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $active = $stmt->get_result();
    $stmt->close();
}

/* ---------- Recently resolved by me (via fix_notes/commit_ref or recent activity) ---------- */
$resolved = null;
if ($stmt = $conn->prepare("
    SELECT id,title,status,COALESCE(updated_at,created_at) as ts
    FROM bugs
    WHERE assigned_to=? AND status='Resolved'
    ORDER BY ts DESC LIMIT 10
")) {
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $resolved = $stmt->get_result();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Developer Dashboard - Rofane</title>
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
      <h3 class="mb-0"><i class="bi bi-code-slash"></i> Developer Dashboard</h3>
    </div>

    <div class="row g-3">
      <!-- Quick Actions -->
      <div class="col-12">
        <div class="card border-0 shadow-sm">
          <div class="card-body d-flex flex-wrap gap-2 align-items-center">
            <h6 class="mb-0 me-2">Quick Actions</h6>
     <a href="../modules/bug_tracker/assigned_bugs.php" class="btn btn-primary btn-sm">My Assigned Bugs</a>
            
          
          </div>
        </div>
      </div>

      <!-- My assigned (active) -->
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title">Active Assigned to Me</h5>
            <ul class="list-group list-group-flush">
              <?php if ($active && $active->num_rows > 0): ?>
                <?php while ($r = $active->fetch_assoc()): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    <a href="../modules/bug_tracker/bug_detail.php?id=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?> — <?= htmlspecialchars($r['title']) ?></a>
                 

                    <span class="badge text-bg-info"><?= htmlspecialchars($r['status']) ?></span>
                  </li>
                <?php endwhile; ?>
              <?php else: ?>
                <li class="list-group-item text-muted">No active bugs assigned.</li>
              <?php endif; ?>
            </ul>
            <div class="mt-3">
              
               <a href="../modules/bug_tracker/assigned_bugs.php" class="btn btn-primary btn-sm">Open My List</a>
            </div>
          </div>
        </div>
      </div>

      <!-- Recently resolved -->
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <h5 class="card-title">Recently Resolved by Me</h5>
            <ul class="list-group list-group-flush">
              <?php if ($resolved && $resolved->num_rows > 0): ?>
                <?php while ($r = $resolved->fetch_assoc()): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                                       <a href="../modules/bug_tracker/bug_detail.php?id=<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?> — <?= htmlspecialchars($r['title']) ?></a>
                    <span class="badge text-bg-success"><?= htmlspecialchars($r['status']) ?></span>
                  </li>
                <?php endwhile; ?>
              <?php else: ?>
                <li class="list-group-item text-muted">No recent resolves.</li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
      </div>

    </div><!-- /row -->
  </div><!-- /container -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
