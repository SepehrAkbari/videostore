<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role']) || $_SESSION['Role'] !== 'Customer') {
    header("Location: member_login.php");
    exit();
}

$userID = $_SESSION['UserID'];
$rentals = [];

$stmt = $conn->prepare("
    SELECT t.Trans_ID, t.Object_ID, t.Store_ID, t.Start_date, 
           m.Title, so.Type, so.Player_ID, p.Generation
    FROM Transaction t
    JOIN Store_Object so ON t.Object_ID = so.Object_ID
    LEFT JOIN Movie m ON so.Movie_ID = m.Movie_ID
    LEFT JOIN Player p ON so.Player_ID = p.Player_ID
    WHERE t.UserID = ? AND t.Type = 'Rental' AND t.Status = 'Active'
");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $rentals[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Active Rentals - VideoStore</title>
</head>
<body>
    <h2>My Active Rentals</h2>
    <nav>
        <ul>
            <li><a href="member_main.php">Dashboard</a></li>
            <li><a href="member_catalog.php">Catalog</a></li>
            <li><a href="member_reserved.php">My Reserved</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <?php if (empty($rentals)): ?>
        <p>You have no active rentals.</p>
    <?php else: ?>
        <table border="1">
            <tr>
                <th>Transaction ID</th>
                <th>Item</th>
                <th>Type</th>
                <th>Store ID</th>
                <th>Start Date</th>
                <th>Action</th>
            </tr>
            <?php foreach ($rentals as $rental): ?>
                <tr>
                    <td><?php echo htmlspecialchars($rental['Trans_ID']); ?></td>
                    <td>
                        <?php 
                        if ($rental['Player_ID']) {
                            echo "Player (Generation: " . htmlspecialchars($rental['Generation']) . ")";
                        } else {
                            echo htmlspecialchars($rental['Title']);
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($rental['Type']); ?></td>
                    <td><?php echo htmlspecialchars($rental['Store_ID']); ?></td>
                    <td><?php echo htmlspecialchars($rental['Start_date']); ?></td>
                    <td>
                        <a href="return.php?trans_id=<?php echo htmlspecialchars($rental['Trans_ID']); ?>">Return</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</body>
</html>