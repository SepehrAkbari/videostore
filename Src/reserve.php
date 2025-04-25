<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role'])) {
    header("Location: member_login.php");
    exit();
}

$userID = $_SESSION['UserID'];
$message = '';
$success_message = '';
$object = null;

if (!isset($_GET['object_id']) || !is_numeric($_GET['object_id'])) {
    $message = "Invalid object ID.";
} else {
    $object_id = $_GET['object_id'];

    $stmt = $conn->prepare("
        SELECT so.Object_ID, so.Store_ID, s.Address, so.Type, so.Charge_per_day, so.Rental_period, 
               m.Title, m.Rating, p.Generation
        FROM Store_Object so
        Join Store s ON so.Store_ID = s.Store_ID
        LEFT JOIN Movie m ON so.Movie_ID = m.Movie_ID
        LEFT JOIN Player p ON so.Player_ID = p.Player_ID
        LEFT JOIN Transaction t ON so.Object_ID = t.Object_ID AND t.Status = 'Reserved'
        WHERE so.Object_ID = ? AND t.Trans_ID IS NULL
    ");
    $stmt->bind_param("i", $object_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $object = $result->fetch_assoc();
    } elseif(!empty($object["Player_ID"])){
        $message = "Players can not be rented";
    } else {
        $message = "Object not available for reservation.";
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reserve'])) {
    $reserve_date = trim($_POST['reserve_date']);
    $object_id = $_POST['object_id'];
    $store_id = $_POST['store_id'];

    if (empty($reserve_date) || !strtotime($reserve_date)) {
        $message = "Please enter a valid reservation date.";
    } else {
        $reserve_date = date('Y-m-d', strtotime($reserve_date));
        $valid_until = date('Y-m-d', strtotime($reserve_date . " + 3 days"));

        $stmt = $conn->prepare("
            INSERT INTO Transaction (UserID, Object_ID, Store_ID, Type, Status)
            VALUES (?, ?, ?, 'Reservation', 'Reserved')
        ");
        $stmt->bind_param("iii", $userID, $object_id, $store_id);
        if ($stmt->execute()) {
            $success_message = "Reservation successful! Your reservation is valid until $valid_until.";
            $object = null; // Clear object to show confirmation
        } else {
            $message = "Error reserving the item.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserve - VideoStore</title>
    <link rel="stylesheet" href="style sheet/total_style.css">
</head>
<body>
    <h2>Reserve</h2>
    <nav>
        <ul>
            <li><?php if ($_SESSION['Role'] == 'Admin'): ?>
                <li><a href="admin_main.php">Home</a></li>
            <?php endif; ?></li>
            <li><a href="member_main.php">Dashboard</a></li>
            <li class="dropdown"><button class="dropdown_button">Catalog</button>
                <div class="dropdown-content">
                    <a href="movies.php">Movies</a>
                    <a href="players.php">Players</a>
                </div>
            </li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <?php if (!empty($message)): ?>
        <p class="error_message"><?php echo htmlspecialchars($message); ?></p>
    <?php elseif (!empty($success_message)): ?>
            <p class="success_message"><?php echo htmlspecialchars($success_message); ?></p>
    <?php elseif ($object): ?>
        <h3>Reserve Item</h3>
        <p>
            <strong>Item:</strong> 
            <?php echo htmlspecialchars($object['Title']); ?>
        </p>
        <p><strong>Type:</strong> <?php echo htmlspecialchars($object['Type']); ?></p>
        <p><strong>Rating:</strong> <?php echo htmlspecialchars($object['Rating']); ?>/10</p>
        <p><strong>Store:</strong> <?php echo htmlspecialchars($object['Address']); ?></p>
        <p><strong>Charge per Day:</strong> $<?php echo htmlspecialchars($object['Charge_per_day']); ?></p>
        <p><strong>Rental Period:</strong> <?php echo htmlspecialchars($object['Rental_period']); ?> days</p>

        <form class = 'form' method="POST" action="">
            <label for="reserve_date">Reservation Date (YYYY-MM-DD):</label>
            <input placeholder = 'YYYY-MM-DD' type="text" id="reserve_date" name="reserve_date" required>
            <br>
            <p><strong>Note:</strong> Reservations are valid for 3 days from the reservation date.</p>
            <input type="hidden" name="object_id" value="<?php echo htmlspecialchars($object['Object_ID']); ?>">
            <input type="hidden" name="store_id" value="<?php echo htmlspecialchars($object['Store_ID']); ?>">
            <input type="submit" name="confirm_reserve" value="Confirm Reservation">
        </form>
        <br>
    <?php else: ?>
        <p>No item selected for reservation.</p>
    <?php endif; ?>
</body>
</html>