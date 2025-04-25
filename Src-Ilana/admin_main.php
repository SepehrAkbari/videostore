<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role']) || $_SESSION['Role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

// 1
$revenue_by_store = [];
$stmt = $conn->prepare("
    SELECT s.Store_ID, s.Address, SUM(t.Price) as Total_Revenue
    FROM Store s
    LEFT JOIN Transaction t ON s.Store_ID = t.Store_ID AND t.Type = 'Rental' AND t.Status = 'Completed'
    GROUP BY s.Store_ID, s.Address
    ORDER BY Total_Revenue DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $revenue_by_store[] = $row;
}
$stmt->close();

// 2
$revenue_by_movie = [];
$stmt = $conn->prepare("
    SELECT m.Movie_ID, m.Title, SUM(t.Price) as Total_Revenue
    FROM Movie m
    LEFT JOIN Store_Object so ON m.Movie_ID = so.Movie_ID
    LEFT JOIN Transaction t ON so.Object_ID = t.Object_ID AND t.Type = 'Rental' AND t.Status = 'Completed'
    GROUP BY m.Movie_ID, m.Title
    ORDER BY Total_Revenue DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $revenue_by_movie[] = $row;
}
$stmt->close();

// 3
$revenue_by_player_gen = [];
$stmt = $conn->prepare("
    SELECT p.Generation, SUM(t.Price) as Total_Revenue
    FROM Player p
    LEFT JOIN Store_Object so ON p.Player_ID = so.Player_ID
    LEFT JOIN Transaction t ON so.Object_ID = t.Object_ID AND t.Type = 'Rental' AND t.Status = 'Completed'
    GROUP BY p.Generation
    ORDER BY Total_Revenue DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $revenue_by_player_gen[] = $row;
}
$stmt->close();

// 4
$most_rented_movies = [];
$stmt = $conn->prepare("
    SELECT m.Movie_ID, m.Title, COUNT(t.Trans_ID) as Rental_Count
    FROM Movie m
    LEFT JOIN Store_Object so ON m.Movie_ID = so.Movie_ID
    LEFT JOIN Transaction t ON so.Object_ID = t.Object_ID AND t.Type = 'Rental'
    GROUP BY m.Movie_ID, m.Title
    ORDER BY Rental_Count DESC
    LIMIT 10
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $most_rented_movies[] = $row;
}
$stmt->close();

// 5
$most_active_members = [];
$stmt = $conn->prepare("
    SELECT m.UserID, m.Fname, m.Lname, COUNT(t.Trans_ID) as Rental_Count
    FROM Member m
    LEFT JOIN Transaction t ON m.UserID = t.UserID AND t.Type = 'Rental'
    WHERE m.Role = 'Customer'
    GROUP BY m.UserID, m.Fname, m.Lname
    ORDER BY Rental_Count DESC
    LIMIT 10
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $most_active_members[] = $row;
}
$stmt->close();

$fname = $_SESSION['Fname'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - VideoStore</title>
    <link rel="stylesheet" href="style sheet\total_style.css">
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($fname); ?>!</h1>
    <nav>
        <ul>
            <li ><a href = "member_main.php">User View</a></li>
            <li><a href="admin_main.php">Home</a></li>
            <li class="dropdown"><button class = 'dropdown_button'>Manage</button>
            <div class="dropdown-content">
                <a href="admin_members.php">Members</a>
                <a href="admin_movie.php">Movies</a>
                <a href="admin_player.php">Players</a>
                <a href = "admin_admins.php">Admins</a>
            </div>
            </li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <h1>Admin Dashboard</h1>
    <h2>Reports</h2>

    <h3>Total Revenue by Store</h3>
    <?php if (empty($revenue_by_store)): ?>
        <p>No revenue data available.</p>
    <?php else: ?>
        <table class = "table" >
            <tr>
                <th>Store ID</th>
                <th>Address</th>
                <th>Total Revenue</th>
            </tr>
            <?php foreach ($revenue_by_store as $store): ?>
                <tr>
                    <td><?php echo htmlspecialchars($store['Store_ID']); ?></td>
                    <td><?php echo htmlspecialchars($store['Address']); ?></td>
                    <td>$<?php echo htmlspecialchars(number_format($store['Total_Revenue'] ?? 0, 2)); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h3>Total Revenue by Movie</h3>
    <?php if (empty($revenue_by_movie)): ?>
        <p>No revenue data available.</p>
    <?php else: ?>
        <table class = "table" >
            <tr>
                <th>Movie ID</th>
                <th>Title</th>
                <th>Total Revenue</th>
            </tr>
            <?php foreach ($revenue_by_movie as $movie): ?>
                <tr>
                    <td><?php echo htmlspecialchars($movie['Movie_ID']); ?></td>
                    <td><?php echo htmlspecialchars($movie['Title']); ?></td>
                    <td>$<?php echo htmlspecialchars(number_format($movie['Total_Revenue'] ?? 0, 2)); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h3>Total Revenue by Player Generation</h3>
    <?php if (empty($revenue_by_player_gen)): ?>
        <p>No revenue data available.</p>
    <?php else: ?>
        <table class = "table" >
            <tr>
                <th>Generation</th>
                <th>Total Revenue</th>
            </tr>
            <?php foreach ($revenue_by_player_gen as $gen): ?>
                <tr>
                    <td><?php echo htmlspecialchars($gen['Generation']); ?></td>
                    <td>$<?php echo htmlspecialchars(number_format($gen['Total_Revenue'] ?? 0, 2)); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h3>Top 10 Rented Movies</h3>
    <?php if (empty($most_rented_movies)): ?>
        <p>No rental data available.</p>
    <?php else: ?>
        <table class = "table" >
            <tr>
                <th>Movie ID</th>
                <th>Title</th>
                <th>Rental Count</th>
            </tr>
            <?php foreach ($most_rented_movies as $movie): ?>
                <tr>
                    <td><?php echo htmlspecialchars($movie['Movie_ID']); ?></td>
                    <td><?php echo htmlspecialchars($movie['Title']); ?></td>
                    <td><?php echo htmlspecialchars($movie['Rental_Count']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h3>Top 10 Active Members</h3>
    <?php if (empty($most_active_members)): ?>
        <p>No member rental data available.</p>
    <?php else: ?>
        <table class = "table" >
            <tr>
                <th>User ID</th>
                <th>Name</th>
                <th>Rental Count</th>
            </tr>
            <?php foreach ($most_active_members as $member): ?>
                <tr>
                    <td><?php echo htmlspecialchars($member['UserID']); ?></td>
                    <td><?php echo htmlspecialchars($member['Fname'] . ' ' . $member['Lname']); ?></td>
                    <td><?php echo htmlspecialchars($member['Rental_Count']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</body>
</html>