<?php
session_start();

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role'])) {
    header("Location: member_login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalog - VideoStore</title>
    <link rel="stylesheet" href="total_style.css">
</head>
<body>
    <h2>Catalog</h2>
    <nav>
        <ul>
            <li><a href="movies.php">Browse Movies</a></li>
            <li><a href="players.php">Browse Players</a></li>
            <li><a href="member_main.php">Back to Dashboard</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
</body>
</html>