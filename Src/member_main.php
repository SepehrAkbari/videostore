<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role']) || $_SESSION['Role'] !== 'Customer') {
    header("Location: member_login.php");
    exit();
}

$fname = $_SESSION['Fname'];
$userID = $_SESSION['UserID'];

$rentals = [];
$stmt = $conn->prepare("
    SELECT t.Trans_ID, t.Object_ID, t.Start_date, m.Title, p.Generation, so.Type
    FROM Transaction t
    LEFT JOIN Store_Object so ON t.Object_ID = so.Object_ID
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

$reservations = [];
$stmt = $conn->prepare("
    SELECT t.Trans_ID, t.Object_ID, m.Title, p.Generation, so.Type
    FROM Transaction t
    LEFT JOIN Store_Object so ON t.Object_ID = so.Object_ID
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - VideoStore</title>
</head>
<body>
    <header>
        <h1>Welcome, <?php echo htmlspecialchars($fname); ?>!</h1>
    </header>
    <nav class="main-nav">
        <ul>
            <li><a href="member_main.php">Dashboard</a></li>
            <li><a href="member_catalog.php">Catalog</a></li>
            <li><a href="member_reserved.php">My Reserved</a></li>
            <li><a href="member_stuff.php">My Stuff</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
    <main>
        <section>
            <h2>Your Current Rentals</h2>
            <?php if (empty($rentals)): ?>
                <p class="info-message">You have no active rentals.</p>
            <?php else: ?>
                <ul class="rental-list">
                    <?php foreach ($rentals as $rental): ?>
                        <li>
                            <?php 
                            if ($rental['Generation']) {
                                echo "Player (Generation: " . htmlspecialchars($rental['Generation']) . ")";
                            } else {
                                echo htmlspecialchars($rental['Title']);
                            }
                            ?>
                            - Type: <?php echo htmlspecialchars($rental['Type']); ?>, 
                            Rented on: <?php echo htmlspecialchars($rental['Start_date']); ?>
                            (<a href="return.php?trans_id=<?php echo htmlspecialchars($rental['Trans_ID']); ?>">Return</a>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <section>
            <h2>Your Reservations</h2>
            <?php if (empty($reservations)): ?>
                <p class="info-message">You have no active reservations.</p>
            <?php else: ?>
                <ul class="reservation-list">
                    <?php foreach ($reservations as $reservation): ?>
                        <li>
                            <?php 
                            if ($reservation['Generation']) {
                                echo "Player (Generation: " . htmlspecialchars($reservation['Generation']) . ")";
                            } else {
                                echo htmlspecialchars($reservation['Title']);
                            }
                            ?>
                            - Type: <?php echo htmlspecialchars($reservation['Type']); ?>
                            (<a href="checkout.php?trans_id=<?php echo htmlspecialchars($reservation['Trans_ID']); ?>&object_id=<?php echo htmlspecialchars($reservation['Object_ID']); ?>">Checkout</a>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>