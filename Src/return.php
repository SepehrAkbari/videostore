<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role']) || $_SESSION['Role'] !== 'Customer') {
    header("Location: member_login.php");
    exit();
}

$userID = $_SESSION['UserID'];
$message = '';
$rental = null;

if (!isset($_GET['trans_id']) || !is_numeric($_GET['trans_id'])) {
    $message = "Invalid transaction ID.";
} else {
    $trans_id = $_GET['trans_id'];

    $stmt = $conn->prepare("
        SELECT t.Trans_ID, t.Object_ID, t.Store_ID, t.Start_date, 
               m.Title, so.Type, so.Charge_per_day, so.Player_ID, p.Generation
        FROM Transaction t
        JOIN Store_Object so ON t.Object_ID = so.Object_ID
        LEFT JOIN Movie m ON so.Movie_ID = m.Movie_ID
        LEFT JOIN Player p ON so.Player_ID = p.Player_ID
        WHERE t.Trans_ID = ? AND t.UserID = ? AND t.Type = 'Rental' AND t.Status = 'Active'
    ");
    $stmt->bind_param("ii", $trans_id, $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $rental = $result->fetch_assoc();
    } else {
        $message = "Rental not found or not available for return.";
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_return'])) {
    $trans_id = $_POST['trans_id'];
    $end_date = trim($_POST['end_date']);
    $charge_per_day = $_POST['charge_per_day'];
    $start_date = $_POST['start_date'];
    $is_player = isset($rental['Player_ID']) && $rental['Player_ID'] !== null;

    if (empty($end_date) || !strtotime($end_date)) {
        $message = "Please enter a valid end date.";
    } else {
        $end_date = date('Y-m-d', strtotime($end_date));
        $current_date = date('Y-m-d');
        if ($end_date < $start_date) {
            $message = "Return date cannot be before the start date.";
        } elseif ($end_date < $current_date) {
            $message = "Return date cannot be in the past.";
        } else {
            $days_rented = (new DateTime($end_date))->diff(new DateTime($start_date))->days;
            $price = $charge_per_day * $days_rented;

            $stmt = $conn->prepare("
                UPDATE Transaction 
                SET Status = 'Completed', End_date = ?, Price = ?
                WHERE Trans_ID = ? AND UserID = ? AND Status = 'Active'
            ");
            $stmt->bind_param("sdii", $end_date, $price, $trans_id, $userID);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                if ($is_player) {
                    $stmt = $conn->prepare("UPDATE Member SET Player_rented = Player_rented - 1 WHERE UserID = ?");
                } else {
                    $stmt = $conn->prepare("UPDATE Member SET Num_disks_rented = Num_disks_rented - 1 WHERE UserID = ?");
                }
                $stmt->bind_param("i", $userID);
                $stmt->execute();
                $stmt->close();

                $message = "Thank you for returning the item! Total charge: $$price for $days_rented days.";
                $rental = null;
            } else {
                $message = "Error returning the item.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return - VideoStore</title>
</head>
<body>
    <h2>Return</h2>
    <nav>
        <ul>
            <li><a href="member_main.php">Dashboard</a></li>
            <li><a href="member_catalog.php">Catalog</a></li>
            <li><a href="member_reserved.php">My Reserved</a></li>
            <li><a href="member_stuff.php">My Stuff</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <?php if (!empty($message)): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
        <a href="member_stuff.php">Back to My Stuff</a>
    <?php elseif ($rental): ?>
        <h3>Return Item</h3>
        <p>
            <strong>Item:</strong> 
            <?php 
            if ($rental['Player_ID']) {
                echo "Player (Generation: " . htmlspecialchars($rental['Generation']) . ")";
            } else {
                echo htmlspecialchars($rental['Title']);
            }
            ?>
        </p>
        <p><strong>Type:</strong> <?php echo htmlspecialchars($rental['Type']); ?></p>
        <p><strong>Store ID:</strong> <?php echo htmlspecialchars($rental['Store_ID']); ?></p>
        <p><strong>Start Date:</strong> <?php echo htmlspecialchars($rental['Start_date']); ?></p>
        <p><strong>Charge per Day:</strong> $<?php echo htmlspecialchars($rental['Charge_per_day']); ?></p>

        <form method="POST" action="">
            <label for="end_date">Return Date (YYYY-MM-DD):</label>
            <input type="text" id="end_date" name="end_date" required>
            <br>
            <?php
            // Calculate and display potential charges if end date is provided
            if (isset($_POST['end_date']) && strtotime($_POST['end_date']) && strtotime($_POST['end_date']) >= strtotime($rental['Start_date'])) {
                $end_date = date('Y-m-d', strtotime($_POST['end_date']));
                $days_rented = (new DateTime($end_date))->diff(new DateTime($rental['Start_date']))->days;
                $price = $rental['Charge_per_day'] * $days_rented;
                echo "<p><strong>Invoice:</strong> Total charge for $days_rented days: $$price</p>";
            }
            ?>
            <input type="hidden" name="trans_id" value="<?php echo htmlspecialchars($rental['Trans_ID']); ?>">
            <input type="hidden" name="charge_per_day" value="<?php echo htmlspecialchars($rental['Charge_per_day']); ?>">
            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($rental['Start_date']); ?>">
            <input type="submit" name="confirm_return" value="Confirm Return">
        </form>
        <br>
        <a href="member_stuff.php">Back to My Stuff</a>
    <?php else: ?>
        <p>No item selected for return.</p>
        <a href="member_stuff.php">Back to My Stuff</a>
    <?php endif; ?>
</body>
</html>