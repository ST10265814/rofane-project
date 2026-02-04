<?php $role = $_SESSION['role']; ?>
<div class="list-group rounded-0">
  <a href="dashboard.php" class="list-group-item list-group-item-action">Dashboard</a>
  <?php if ($role == 'tester' || $role == 'manager'): ?>
    <a href="test_cases.php" class="list-group-item list-group-item-action">Test Cases</a>
  <?php endif; ?>
  <?php if ($role == 'manager'): ?>
    <a href="reports.php" class="list-group-item list-group-item-action">Reports</a>
    <a href="users.php" class="list-group-item list-group-item-action">User Management</a>
  <?php endif; ?>
  <?php if ($role == 'client'): ?>
    <a href="reports.php" class="list-group-item list-group-item-action">Reports</a>
  <?php endif; ?>
  <a href="settings.php" class="list-group-item list-group-item-action">Settings</a>
</div>