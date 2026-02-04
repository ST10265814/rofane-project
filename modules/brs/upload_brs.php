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

$msg = '';

// Ensure tables
$conn->query("CREATE TABLE IF NOT EXISTS brs_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uploaded_by INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
)");
$conn->query("CREATE TABLE IF NOT EXISTS brs_test_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brs_id INT NOT NULL,
    requirement_id VARCHAR(64) NULL,
    title VARCHAR(255) NOT NULL,
    steps TEXT NULL,
    expected TEXT NULL,
    priority VARCHAR(20) DEFAULT 'Medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (brs_id) REFERENCES brs_files(id) ON DELETE CASCADE
)");

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $msg = "<div class='alert alert-danger'>Invalid request.</div>";
    } else {
        if (!isset($_FILES['brs']) || $_FILES['brs']['error'] !== UPLOAD_ERR_OK) {
            $msg = "<div class='alert alert-warning'>Please choose a file.</div>";
        } else {
            $allowed = ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'application/pdf'];
            $type = $_FILES['brs']['type'];
            $size = $_FILES['brs']['size'];
            if (!in_array($type, $allowed)) {
                $msg = "<div class='alert alert-warning'>Only DOCX, TXT, or PDF is allowed.</div>";
            } elseif ($size > 10*1024*1024) {
                $msg = "<div class='alert alert-warning'>Max file size is 10 MB.</div>";
            } else {
                $ext = pathinfo($_FILES['brs']['name'], PATHINFO_EXTENSION);
                $fname = 'brs_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $target = __DIR__ . '/uploads/' . $fname;
                if (!is_dir(__DIR__ . '/uploads')) { @mkdir(__DIR__ . '/uploads', 0777, true); }
                if (move_uploaded_file($_FILES['brs']['tmp_name'], $target)) {
                    $rel = 'modules/brs/uploads/' . $fname;
                    $ins = $conn->prepare("INSERT INTO brs_files (uploaded_by, original_name, file_path) VALUES (?, ?, ?)");
                    if ($ins) {
                        $uid = $_SESSION['user_id'];
                        $orig = $_FILES['brs']['name'];
                        $ins->bind_param("iss", $uid, $orig, $rel);
                        if ($ins->execute()) {
                            $brs_id = $ins->insert_id;
                            $ins->close();
                            header("Location: generate_cases.php?brs_id=" . (int)$brs_id . "&preview=1");
                            exit();
                        } else {
                            $msg = "<div class='alert alert-danger'>Failed to save file record.</div>";
                            $ins->close();
                        }
                    } else {
                        $msg = "<div class='alert alert-danger'>Failed to prepare DB statement.</div>";
                    }
                } else {
                    $msg = "<div class='alert alert-danger'>Failed to upload file.</div>";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Upload BRS - Rofane</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Upload BRS</h3>
    <a class="btn btn-outline-secondary" href="../bug_tracker/view_bugs.php">Back</a>
  </div>

  <?= $msg ?>

  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="col-12">
          <label class="form-label">BRS File (DOCX / TXT / PDF)</label>
          <input type="file" name="brs" class="form-control" accept=".docx,.txt,.pdf,application/pdf" required>
          <div class="form-text">Max 10 MB.</div>
        </div>
        <div class="col-12 d-grid">
          <button class="btn btn-primary" type="submit">Upload & Generate Test Cases</button>
        </div>
      </form>
    </div>
  </div>
</div>
</body>
</html>
