<?php
session_start();

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role']) || $_SESSION['Role'] !== 'Admin') {
    header("Location: admin_login.php");
    exit();
}

$fname = $_SESSION['Fname'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - VideoStore</title>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($fname); ?>!</h1>
    <h2>Admin Dashboard</h2>
    <nav>
        <ul>
            <li><a href="admin_main.php">Dashboard</a></li>
            <li><a href="admin_reports.php">Reports</a></li>
            <li><a href="admin_manage.php">Manage</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
</body>
</html>