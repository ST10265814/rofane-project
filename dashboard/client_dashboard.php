<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: ../login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Client Dashboard - Rofane</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; }
        .card-hover:hover {
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            transform: scale(1.02);
            transition: 0.3s;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="#">Rofane Client</a>
        <div class="d-flex">
            <a href="../logout.php" class="btn btn-outline-light"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="container py-5">
    <h2 class="text-center mb-4">Welcome, Client!</h2>
    <div class="row justify-content-center g-4">

        <div class='col-md-4'>
            <div class='card card-hover border-0 shadow-sm'>
                <div class='card-body text-center'>
                    <i class='bi bi-clipboard-data display-4 text-info'></i>
                    <h5 class='card-title mt-3'>View Bug Reports</h5>
                    <p class='card-text'>Check on progress of bugs you've submitted.</p>
                    <a href='../modules/bug_tracker/view_bugs.php' class='btn btn-info'>View Bug Reports</a>
                </div>
            </div>
        </div>

        <div class='col-md-4'>
            <div class='card card-hover border-0 shadow-sm'>
                <div class='card-body text-center'>
                    <i class='bi bi-chat-dots display-4 text-secondary'></i>
                    <h5 class='card-title mt-3'>Submit Feedback</h5>
                    <p class='card-text'>Share your thoughts with the testing team.</p>
                    <a href='#' class='btn btn-secondary'>Submit Feedback</a>
                </div>
            </div>
        </div>

    </div>
</div>
</body>
</html>
