<?php
session_start();
require_once 'includes/config.php';

$success = '';
$error   = '';

/* --- CSRF token --- */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* --- Helpers --- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* --- Handle registration POST --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid request. Please try again.";
    } else {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $role     = (string)($_POST['role'] ?? '');

        $allowed_roles = ['tester','manager','client','developer'];

        if ($name === '' || $email === '' || $password === '' || $role === '') {
            $error = "All fields are required.";
        } elseif (!in_array($role, $allowed_roles, true)) {
            $error = "Invalid role selected.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if ($check) {
                $check->bind_param("s", $email);
                $check->execute();
                $check->store_result();

                if ($check->num_rows > 0) {
                    $error = "Email already exists.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
                        if ($stmt->execute()) {
                            $success = "Account registered successfully! You can now log in.";
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        } else {
                            $error = "Registration failed. Try again.";
                        }
                        $stmt->close();
                    } else {
                        $error = "Server error. Please try again later.";
                    }
                }
                $check->close();
            } else {
                $error = "Server error. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - Rofane</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- Navbar with logo -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="#">
      <img src="assets/logo.png" alt="Logo" width="32" height="32" class="me-2">
      <span>Rofane</span>
    </a>
  </div>
</nav>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body">
                    <h3 class="text-center">Register</h3>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= h($success) ?></div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger"><?= h($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" autocomplete="off" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?= h($_POST['name'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?= h($_POST['email'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                            <div class="form-text">At least 6 characters.</div>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select id="role" name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <option value="tester"    <?= (($_POST['role'] ?? '')==='tester')?'selected':''; ?>>Tester</option>
                                <option value="manager"   <?= (($_POST['role'] ?? '')==='manager')?'selected':''; ?>>Test Manager</option>
                                <option value="client"    <?= (($_POST['role'] ?? '')==='client')?'selected':''; ?>>Client</option>
                                <option value="developer" <?= (($_POST['role'] ?? '')==='developer')?'selected':''; ?>>Developer</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-success w-100">Register</button>
                    </form>

                    <p class="mt-3 text-center">Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
