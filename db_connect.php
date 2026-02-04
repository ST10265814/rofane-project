<?php
$conn = mysqli_connect("localhost", "root", "", "rofane");
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>