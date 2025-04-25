<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role'])) {
    header("Location: member_login.php");
    exit();
}

$userID = $_SESSION['UserID'];
$message = '';
$reservations = [];

$stmt = $conn->prepare("
    SELECT t.Trans_ID, t.Object_ID, t.Store_ID, t.Status, 
           m.Title, so.Type, so.Player_ID, p.Generation
    FROM Transaction t
    JOIN Store_Object so ON t.Object_ID = so.Object_ID
    LEFT JOIN Movie m ON so.Movie_ID = m.Movie_ID
    LEFT JOIN Player p ON so.Player_ID = p.Player_ID
    WHERE t.UserID = ? AND t.Type = 'Reservation' AND t.Status = 'Reserved'
");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reservations[] = $row;
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $trans_id = $_POST['trans_id'];
    $stmt = $conn->prepare("DELETE FROM Transaction WHERE Trans_ID = ? AND UserID = ? AND Type = 'Reservation' AND Status = 'Reserved'");
    $stmt->bind_param("ii", $trans_id, $userID);
    if ($stmt->execute()) {
        $message = "Reservation deleted successfully.";
        header("Location: member_reserved.php");
        exit();
    } else {
        $message = "Error deleting reservation.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reserved Items - VideoStore</title>
    <link rel="stylesheet" href="style sheet/total_style.css">
</head>
<body>
    <h2>My Reserved Items</h2>
    <nav>
        <ul>
            <li><a href="member_main.php">Dashboard</a></li>
            <li><a href="member_catalog.php">Catalog</a></li>
            <li><a href="member_stuff.php">My Stuff</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <?php if (!empty($message)): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if (empty($reservations)): ?>
        <p>You have no reserved items.</p>
    <?php else: ?>
        <table border="1">
            <tr>
                <th>Transaction ID</th>
                <th>Item</th>
                <th>Type</th>
                <th>Store ID</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($reservations as $reservation): ?>
                <tr>
                    <td><?php echo htmlspecialchars($reservation['Trans_ID']); ?></td>
                    <td>
                        <?php 
                        if ($reservation['Player_ID']) {
                            echo "Player (Generation: " . htmlspecialchars($reservation['Generation']) . ")";
                        } else {
                            echo htmlspecialchars($reservation['Title']);
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($reservation['Type']); ?></td>
                    <td><?php echo htmlspecialchars($reservation['Store_ID']); ?></td>
                    <td><?php echo htmlspecialchars($reservation['Status']); ?></td>
                    <td>
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="trans_id" value="<?php echo htmlspecialchars($reservation['Trans_ID']); ?>">
                            <input type="submit" name="delete" value="Delete">
                        </form>
                        <a href="checkout.php?object_id=<?php echo htmlspecialchars($reservation['Object_ID']); ?>&trans_id=<?php echo htmlspecialchars($reservation['Trans_ID']); ?>">Check Out</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</body>
</html>