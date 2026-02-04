<nav class="navbar navbar-dark bg-dark px-3">
  <span class="navbar-brand mb-0 h1">Rofane</span>
  <span class="text-white">Logged in as: <?= $_SESSION['email'] ?></span>
  <a href="../logout.php" class="btn btn-sm btn-outline-light ms-3">Logout</a>
  <a class="nav-link" href="/modules/brs/index.php">BRS Library</a>
  <a class="nav-link" href="/modules/test_runs/list.php">Test Runs</a>
  <a class="nav-link" href="/modules/brs/traceability.php">RTM</a>
</nav>