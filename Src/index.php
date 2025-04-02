<?php
session_start();

if (isset($_SESSION['UserID']) && isset($_SESSION['Role'])) {
    if ($_SESSION['Role'] === 'Customer') {
        header("Location: member_main.php");
    } elseif ($_SESSION['Role'] === 'Admin') {
        header("Location: admin_main.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to VideoStore</title>
</head>
<body>
    <h1>Welcome to VideoStore</h1>
    <nav>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="member_login.php">Member Login</a></li>
            <li><a href="admin_login.php">Admin Login</a></li>
        </ul>
    </nav>
    <p>Please log in or sign up to access the VideoStore services.</p>
    <a href="member_signup.php">Sign Up as a Member</a>
</body>
</html>