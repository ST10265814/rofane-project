<?php
session_start();
require_once '../../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'tester';

// Ensure tables (defensive)
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
    status VARCHAR(20) DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Delete BRS action (POST)
$flash = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_brs'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $flash = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        $del_id = (int)$_POST['delete_brs'];
        // Check permission: testers can delete their own, managers can delete any
        $owner_id = 0; $file_path = null;
        if ($s = $conn->prepare("SELECT uploaded_by, file_path FROM brs_files WHERE id=?")) {
            $s->bind_param("i", $del_id);
            $s->execute();
            $s->bind_result($owner_id, $file_path);
            $s->fetch();
            $s->close();
        }
        if ($owner_id && ($role==='manager' || $owner_id===$user_id)) {
            // Delete cases
            if ($d = $conn->prepare("DELETE FROM brs_test_cases WHERE brs_id=?")) {
                $d->bind_param("i", $del_id);
                $d->execute();
                $d->close();
            }
            // Delete file record
            if ($d2 = $conn->prepare("DELETE FROM brs_files WHERE id=?")) {
                $d2->bind_param("i", $del_id);
                $d2->execute();
                $d2->close();
            }
            // Delete file from disk
            if ($file_path) {
                $abs = realpath(__DIR__ . '/../../..' . '/' . $file_path);
                if ($abs && file_exists($abs)) { @unlink($abs); }
            }
            $flash = "<div class='alert alert-success'>BRS #{$del_id} deleted.</div>";
        } else {
            $flash = "<div class='alert alert-warning'>You do not have permission to delete this BRS.</div>";
        }
    }
}

// Filters
$start = isset($_GET['start']) ? trim($_GET['start']) : '';
$end = isset($_GET['end']) ? trim($_GET['end']) : '';
$owner = isset($_GET['owner']) ? (int)$_GET['owner'] : 0;

$clauses = [];
$types = '';
$binds = [];

if ($role !== 'manager') {
    $clauses[] = "f.uploaded_by = ?";
    $types .= 'i';
    $binds[] = $user_id;
} else {
    if ($owner > 0) {
        $clauses[] = "f.uploaded_by = ?";
        $types .= 'i';
        $binds[] = $owner;
    }
}

if ($start !== '') {
    $clauses[] = "DATE(f.created_at) >= ?";
    $types .= 's';
    $binds[] = $start;
}
if ($end !== '') {
    $clauses[] = "DATE(f.created_at) <= ?";
    $types .= 's';
    $binds[] = $end;
}

$where = '';
if (!empty($clauses)) {
    $where = 'WHERE ' . implode(' AND ', $clauses);
}

$sql = "SELECT f.id, f.original_name, f.created_at, f.uploaded_by, u.name as owner_name, COUNT(c.id) AS case_count
        FROM brs_files f
        LEFT JOIN users u ON u.id = f.uploaded_by
        LEFT JOIN brs_test_cases c ON c.brs_id = f.id
        $where
        GROUP BY f.id
        ORDER BY f.created_at DESC";

$res = false;
if ($stmt = $conn->prepare($sql)) {
    if ($types !== '') {
        // build references for bind_param
        $params = [];
        $params[] = & $types;
        for ($i=0; $i<count($binds); $i++) {
            $params[] = & $binds[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();
}

// Owner list for filter (managers only)
$owners = [];
if ($role==='manager') {
    $ou = $conn->query("SELECT id, name FROM users ORDER BY name");
    if ($ou && $ou->num_rows>0) {
        while ($row = $ou->fetch_assoc()) { $owners[] = $row; }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>BRS Library - Rofane</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><i class="bi bi-journal-text me-2"></i>BRS Library</h3>
    <div class="d-flex gap-2">
        <a href="../../dashboard/tester_dashboard.php" class="btn btn-outline-secondary">Back</a>
        <a href="upload_brs.php" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Upload BRS</a>
    </div>
  </div>

  <?= $flash ?>

  <form class="card border-0 shadow-sm mb-3 p-3" method="GET">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label">From</label>
        <input type="date" name="start" value="<?= htmlspecialchars($start) ?>" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">To</label>
        <input type="date" name="end" value="<?= htmlspecialchars($end) ?>" class="form-control">
      </div>
      <?php if ($role==='manager'): ?>
      <div class="col-md-3">
        <label class="form-label">Owner</label>
        <select name="owner" class="form-select">
            <option value="0">All</option>
            <?php foreach ($owners as $o): ?>
              <option value="<?= (int)$o['id'] ?>" <?= $owner===(int)$o['id']?'selected':'' ?>><?= htmlspecialchars($o['name']) ?></option>
            <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-3 d-grid">
        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-filter me-1"></i>Apply Filters</button>
      </div>
    </div>
  </form>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead class="table-dark">
            <tr>
                <th style="width:90px;">BRS ID</th>
                <th>Name</th>
                <th style="width:180px;">Uploaded</th>
                <?php if ($role==='manager'): ?><th style="width:200px;">Owner</th><?php endif; ?>
                <th style="width:120px;"># Test Cases</th>
                <th style="width:320px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$res || $res->num_rows===0): ?>
                <tr><td colspan="<?= $role==='manager' ? 6 : 5 ?>" class="text-center text-muted py-4">No BRS found for the selected filters.</td></tr>
            <?php else: ?>
                <?php while ($r = $res->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= (int)$r['id'] ?></td>
                        <td><?= htmlspecialchars($r['original_name']) ?></td>
                        <td><?= $r['created_at'] ?></td>
                        <?php if ($role==='manager'): ?><td><?= htmlspecialchars($r['owner_name'] ?? '—') ?></td><?php endif; ?>
                        <td><span class="badge bg-secondary"><?= (int)$r['case_count'] ?></span></td>
                        <td class="d-flex gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="view_cases.php?brs_id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i> View Cases</a>
                            <a class="btn btn-sm btn-outline-success" href="generate_cases.php?brs_id=<?= (int)$r['id'] ?>"><i class="bi bi-magic"></i> Generate / Edit</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= (int)$r['case_count']>0 ? 'view_cases.php?brs_id='.(int)$r['id'].'&export=csv' : '#' ?>" <?= (int)$r['case_count']>0 ? '' : 'aria-disabled="true" tabindex="-1"' ?>><i class="bi bi-download"></i> Export CSV</a>
                            <form method="POST" onsubmit="return confirm('Delete BRS #<?= (int)$r['id'] ?> and all its test cases?');">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" name="delete_brs" value="<?= (int)$r['id'] ?>"><i class="bi bi-trash"></i> Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
