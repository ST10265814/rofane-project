<?php
// Quick demo seed (run once): creates a BRS record and a few test cases
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user_id'])) { die("Login required"); }
$user_id = $_SESSION['user_id'];

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
    bug_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$name = "Demo BRS "+date('Y-m-d H:i');
$stmt = $conn->prepare("INSERT INTO brs_files (uploaded_by, original_name, file_path) VALUES (?, ?, ?)");
$fp = "uploads/demo.txt";
$stmt->bind_param("iss", $user_id, $name, $fp);
$stmt->execute();
$brs_id = $stmt->insert_id;
$stmt->close();

$cases = [
    ['REQ-101','Login works','Given user on login\nWhen entering valid creds\nThen sees dashboard','Dashboard appears','High'],
    ['REQ-102','Login rejects bad password','Given user on login\nWhen wrong password\nThen error shown','Error message','Medium'],
    ['REQ-103','Logout clears session','Given logged in\nWhen clicking logout\nThen session cleared','Redirect to login','Low'],
];
$ins = $conn->prepare("INSERT INTO brs_test_cases (brs_id, requirement_id, title, steps, expected, priority, status) VALUES (?, ?, ?, ?, ?, ?, 'Ready')");
foreach ($cases as $c) {
    $ins->bind_param("isssss", $brs_id, $c[0], $c[1], $c[2], $c[3], $c[4]);
    $ins->execute();
}
$ins->close();
echo "Seeded BRS #$brs_id with ".count($cases)." test cases.";
