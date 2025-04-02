<?php
session_start();

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role']) || $_SESSION['Role'] !== 'Admin') {
    header("Location: admin_login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Manage - VideoStore</title>
</head>
<body>
    <h2>Admin Manage</h2>
    <nav>
        <ul>
            <li><a href="admin_movie.php">Manage Movies</a></li>
            <li><a href="admin_player.php">Manage Players</a></li>
            <li><a href="admin_members.php">Manage Members</a></li>
            <li><a href="admin_admins.php">Manage Admins</a></li>
            <li><a href="admin_main.php">Back to Dashboard</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
</body>
</html>