<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function user_role(): string { return (string)($_SESSION['role'] ?? ''); }
function require_roles(array $roles): void {
  if (!in_array(user_role(), $roles, true)) { http_response_code(403); die('Forbidden'); }
}
function is_manager(): bool { return in_array(user_role(), ['manager','test_manager','admin'], true); }
function is_test_manager(): bool { return in_array(user_role(), ['test_manager','admin'], true); }
function is_developer(): bool { return user_role() === 'developer'; }
function can_dev_transition(string $from, string $to): bool {
  $allowed = [
    'Open'        => ['In Progress','Resolved'],
    'In Progress' => ['Resolved'],
    'Blocked'     => [],
    'Resolved'    => [],
    'Closed'      => [],
  ];
  return in_array($to, $allowed[$from] ?? [], true);
}
?>