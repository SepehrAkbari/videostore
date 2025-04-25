<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role'])) {
    header("Location: member_login.php");
    exit();
}

$fname = $_SESSION['Fname'];
$userID = $_SESSION['UserID'];

$rentals = [];
$stmt = $conn->prepare("
    SELECT t.Trans_ID, t.Object_ID, t.Store_ID, t.Start_date, 
           m.Title, so.Type, so.Player_ID, p.Generation, s.Address
    FROM Transaction t
    JOIN Store s ON s.Store_ID = t.Store_ID
    JOIN Store_Object so ON t.Object_ID = so.Object_ID
    LEFT JOIN Movie m ON so.Movie_ID = m.Movie_ID
    LEFT JOIN Player p ON so.Player_ID = p.Player_ID
    WHERE t.UserID = ? AND t.Type = 'Rental' AND t.Status = 'Active' AND s.Store_ID = t.Store_ID
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
    SELECT t.Trans_ID, t.Object_ID, m.Title, p.Generation, so.Type, s.Address
    FROM Transaction t
    JOIN Store s ON s.Store_ID = t.Store_ID
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
    <link rel="stylesheet" href="style sheet/total_style.css">
</head>
<body>
    <header>
        <h1>Welcome, <?php echo htmlspecialchars($fname); ?>!</h1>
    </header>
    <nav class="main-nav">
        <ul>
            <?php if ($_SESSION['Role'] == 'Admin'): ?>
                <li><a href="admin_main.php">Admin Dashboard</a></li>
            <?php endif; ?>
            <li><a href="member_main.php">Home</a></li>
            <li class="dropdown"><button class="dropdown_button">Catalog</button>
                <div class="dropdown-content">
                    <a href="movies.php">Movies</a>
                    <a href="players.php">Players</a>
                </div>
            </li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
    <main>
        <section>
            <h2>Your Current Rentals</h2>
            <?php if (empty($rentals)): ?>
                <p class="info-message">You have no active rentals.</p>
            <?php else: ?>
                <table class="table">
                    <tr>
                        <th>Transaction ID</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Store</th>
                        <th>Rented On</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($rentals as $rental): ?>
                        <tr >
                            <td> <?php echo htmlspecialchars($rental['Trans_ID']); ?></td>
                        <td >
                            <?php 
                            if ($rental['Generation']) {
                                echo "Player (Generation: " . htmlspecialchars($rental['Generation']) . ")";
                            } else {
                                echo htmlspecialchars($rental['Title']);
                            }
                            ?>
                        </td>
                        <td ><?php echo htmlspecialchars($rental['Type']); ?></td>
                        <td> <?php echo htmlspecialchars($rental['Address']); ?> </td>
                        <td> <?php echo htmlspecialchars($rental['Start_date']); ?></td>
                        <td class="link"><a href="return.php?trans_id=<?php echo htmlspecialchars($rental['Trans_ID']); ?>">Return</a></td>
                        </tr>
                    <?php endforeach; ?>
                        </table >
            <?php endif; ?>
        </section>
        <section>
            <h2>Your Reservations</h2>
            <?php if (empty($reservations)): ?>
                <p class="info-message">You have no active reservations.</p>
            <?php else: ?>
                
                <table class="table">
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Store</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($reservations as $reservation): ?>
                        <tr >
                           <td><?php 
                            if ($reservation['Generation']) {
                                echo "Player (Generation: " . htmlspecialchars($reservation['Generation']) . ")";
                            } else {
                                echo htmlspecialchars($reservation['Title']);
                            }
                            ?></td> 
                            <td> <?php echo htmlspecialchars($reservation['Type']); ?> </td>
                            <td> <?php echo htmlspecialchars($reservation['Address']); ?></td>
                            <td class="link"><a href="checkout.php?trans_id=<?php echo htmlspecialchars($reservation['Trans_ID']); ?>&object_id=<?php echo htmlspecialchars($reservation['Object_ID']); ?>">Checkout</a></td>
                        </tr>
                    <?php endforeach; ?>
                        </table >
            <?php endif; ?>
        </section>
    </main>
</body>
</html>