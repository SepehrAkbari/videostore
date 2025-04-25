<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role'])) {
    header("Location: member_login.php");
    exit();
}

$userID = $_SESSION['UserID'];
$message = '';
$success_message ='';
$rental = null;

$stores = [];
$query = "SELECT Store_ID, Address FROM Store ORDER BY Store_ID";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $stores[] = $row;
}

if (!isset($_GET['trans_id']) || !is_numeric($_GET['trans_id'])) {
    $message = "Invalid transaction ID.";
} else {
    $trans_id = $_GET['trans_id'];

    $stmt = $conn->prepare("
        SELECT t.Trans_ID, t.Object_ID, t.Store_ID, t.Start_date, 
               m.Title, so.Type, so.Charge_per_day, so.Player_ID, 
               p.Generation, so.Store_ID, s.Address
        FROM Transaction t
        JOIN Store_Object so ON t.Object_ID = so.Object_ID
        JOIN Store s ON so.Store_ID = s.Store_ID 
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
    $return_store = trim($_POST['store_id']);
    $charge_per_day = $_POST['charge_per_day'];
    $start_date = $_POST['start_date'];
    $is_player = isset($rental['Player_ID']) && $rental['Player_ID'] !== null;

    if (empty($end_date) || !strtotime($end_date)) {
        $message = "Please enter a valid end date.";
    } else {
        if ($is_player){
            if ($return_store != $rental['Store_ID']){
                $message = 'Player must be returned to origional store';} 
        }
        
        $end_date = date('Y-m-d', strtotime($end_date));
        $current_date = date('Y-m-d', strtotime('-1 day'));
        if ($end_date < $start_date) {
            $message = "Return date cannot be before the start date.";
        } elseif ($end_date < $current_date) {
            $message = "Return date cannot be in the past.";
        } else {
            $days_rented = (new DateTime($end_date))->diff(new DateTime($start_date))->days;
            if ($days_rented ==0) {
                $days_rented += 1;
            }
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

                if ($days_rented ==1) {$success_message = "Thank you for returning the item! Total charge: $$price for $days_rented day.";} 
                else{$success_message = "Thank you for returning the item! Total charge: $$price for $days_rented days.";
                    $rental = null;}
                
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
    <link rel="stylesheet" href="style sheet/total_style.css">
</head>
<body>
    <h2>Return</h2>
    <nav>
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

    <?php if (!empty($success_message)): ?>
        <p class="success_message"><?php echo htmlspecialchars($success_message); ?></p>
    <?php elseif ($rental): ?>
        <?php if(!empty($message)) : ?>
            <p class='error_message'><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

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
        <p><strong>Store Address: </strong><?php echo htmlspecialchars($rental['Address']); ?></p>

        <form class='form' method="POST" action="">
            <label for="end_date">Return Date (YYYY-MM-DD):</label>
            <input placeholder = "YYYY-MM-DD" type="text" id="end_date" name="end_date" required>
            <br>
            <label>Return Address</label>
            <select id="store_id" name="store_id" required>
            <option value="">-- Select Store --</option>
            <?php foreach ($stores as $store): ?>
                <option value="<?php echo htmlspecialchars($store['Store_ID']); ?>">
                    Store <?php echo htmlspecialchars($store['Store_ID']); ?> - <?php echo htmlspecialchars($store['Address']); ?>
                </option>
            <?php endforeach; ?>
            </select>
            
            <?php
            // Calculate and display potential charges if end date is provided
            if (isset($_POST['end_date']) && strtotime($_POST['end_date']) && strtotime($_POST['end_date']) >= strtotime($rental['Start_date'])) {
                $end_date = date('Y-m-d', strtotime($_POST['end_date']));
                $days_rented = (new DateTime($end_date))->diff(new DateTime($rental['Start_date']))->days;
                $price = $rental['Charge_per_day'] * $days_rented;
            }
            ?>
            <input type="hidden" name="trans_id" value="<?php echo htmlspecialchars($rental['Trans_ID']); ?>">
            <input type="hidden" name="charge_per_day" value="<?php echo htmlspecialchars($rental['Charge_per_day']); ?>">
            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($rental['Start_date']); ?>">
            <input type="submit" name="confirm_return" value="Confirm Return">
        </form>
    <?php else: ?>
        <p>No item selected for return.</p>
    <?php endif; ?>
</body>
</html>