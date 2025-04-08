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
$object = null;

if (!isset($_GET['object_id']) || !is_numeric($_GET['object_id'])) {
    $message = "Invalid object ID.";
} else {
    $object_id = $_GET['object_id'];
    $trans_id = isset($_GET['trans_id']) && is_numeric($_GET['trans_id']) ? $_GET['trans_id'] : null;

    $stmt = $conn->prepare("
        SELECT so.Object_ID, so.Store_ID, so.Type, so.Charge_per_day, so.Rental_period, 
               m.Title, m.Rating, p.Generation, so.Player_ID
        FROM Store_Object so
        LEFT JOIN Movie m ON so.Movie_ID = m.Movie_ID
        LEFT JOIN Player p ON so.Player_ID = p.Player_ID
        LEFT JOIN Transaction t ON so.Object_ID = t.Object_ID AND t.Status IN ('Active', 'Reserved')
        WHERE so.Object_ID = ? AND (t.Trans_ID IS NULL OR t.Trans_ID = ?)
    ");
    $stmt->bind_param("ii", $object_id, $trans_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $object = $result->fetch_assoc();
    } else {
        $message = "Object not available for checkout.";
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_checkout'])) {
    $start_date = trim($_POST['start_date']);
    $object_id = $_POST['object_id'];
    $store_id = $_POST['store_id'];
    $rental_period = $_POST['rental_period'];
    $trans_id = isset($_POST['trans_id']) && is_numeric($_POST['trans_id']) ? $_POST['trans_id'] : null;

    $is_player = isset($object['Player_ID']) && $object['Player_ID'] !== null;

    if (empty($start_date) || !strtotime($start_date)) {
        $message = "Please enter a valid start date.";
    } else {
        $start_date = date('Y-m-d', strtotime($start_date));
        $current_date = date('Y-m-d', strtotime('-1 day'));
        if ($start_date < $current_date) {
            $message = "Checkout date cannot be in the past.";
        } else {
            $due_date = date('Y-m-d', strtotime($start_date . " + $rental_period days"));

            $stmt = $conn->prepare("SELECT Num_disks_rented, Player_rented FROM Member WHERE UserID = ?");
            $stmt->bind_param("i", $userID);
            $stmt->execute();
            $result = $stmt->get_result();
            $member = $result->fetch_assoc();
            $stmt->close();

            if (!$is_player && $member['Num_disks_rented'] >= 10) {
                $message = "You have reached the maximum limit of 10 rented disks.";
            } elseif ($is_player && $member['Player_rented'] >= 1 && !$trans_id) {
                $message = "You can only rent one player at a time. Please return your current player before renting another.";
            } else {
                if ($trans_id) {
                    $stmt = $conn->prepare("
                        UPDATE Transaction 
                        SET Start_date = ?, Type = 'Rental', Status = 'Active'
                        WHERE Trans_ID = ? AND UserID = ? AND Status = 'Reserved'
                    ");
                    $stmt->bind_param("sii", $start_date, $trans_id, $userID);
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO Transaction (UserID, Object_ID, Store_ID, Start_date, Type, Status)
                        VALUES (?, ?, ?, ?, 'Rental', 'Active')
                    ");
                    $stmt->bind_param("iiss", $userID, $object_id, $store_id, $start_date);
                }

                if ($stmt->execute()) {
                    if ($is_player) {
                        $stmt = $conn->prepare("UPDATE Member SET Player_rented = Player_rented + 1 WHERE UserID = ?");
                    } else {
                        $stmt = $conn->prepare("UPDATE Member SET Num_disks_rented = Num_disks_rented + 1 WHERE UserID = ?");
                    }
                    $stmt->bind_param("i", $userID);
                    $stmt->execute();
                    $stmt->close();

                    $success_message = "Checkout successful! The item is due on $due_date.";
                    $object = null;
                } else {
                    $message = "Error checking out the item.";
                }
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
    <title>Checkout - VideoStore</title>
    <link rel="stylesheet" href="style sheet/total_style.css">
</head>
<body>
    <h2>Checkout</h2>
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
        
    <?php elseif ($object): ?>
        <?php if (!empty($message)): ?>
            <p class="error_message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <h3>Checkout Item</h3>
        <p>
            <strong>Item:</strong> 
            <?php 
            if ($object['Player_ID']) {
                echo "Player (Generation: " . htmlspecialchars($object['Generation']) . ")";
            } else {
                echo htmlspecialchars($object['Title']);
            }
            ?>
        </p>
        <p><strong>Type:</strong> <?php echo htmlspecialchars($object['Type']); ?></p>
        <?php if (!$object['Player_ID']): ?>
            <p><strong>Rating:</strong> <?php echo htmlspecialchars($object['Rating']); ?>/10</p>
        <?php endif; ?>
        <p><strong>Store ID:</strong> <?php echo htmlspecialchars($object['Store_ID']); ?></p>
        <p><strong>Charge per Day:</strong> $<?php echo htmlspecialchars($object['Charge_per_day']); ?></p>
        <p><strong>Rental Period:</strong> <?php echo htmlspecialchars($object['Rental_period']); ?> days</p>

        <form class="form" method="POST" action="">
            <label for="start_date">Checkout Date (YYYY-MM-DD):</label>
            <input placeholder = 'YYYY-MM-DD' type="text" id="start_date" name="start_date" required>
            <br>
            <?php
            if (isset($_POST['start_date']) && strtotime($_POST['start_date'])) {
                $start_date = date('Y-m-d', strtotime($_POST['start_date']));
                $day_of_week = date('N', strtotime($start_date));
                if ($day_of_week >= 1 && $day_of_week <= 5) {
                    echo "<p><strong>Note:</strong> A 10% discount will be applied as this is a weekday rental.</p>";
                }
            }
            ?>
            <input type="hidden" name="object_id" value="<?php echo htmlspecialchars($object['Object_ID']); ?>">
            <input type="hidden" name="store_id" value="<?php echo htmlspecialchars($object['Store_ID']); ?>">
            <input type="hidden" name="rental_period" value="<?php echo htmlspecialchars($object['Rental_period']); ?>">
            <?php if (isset($_GET['trans_id'])): ?>
                <input type="hidden" name="trans_id" value="<?php echo htmlspecialchars($_GET['trans_id']); ?>">
            <?php endif; ?>
            <input type="submit" name="confirm_checkout" value="Confirm Checkout">
        </form>
        <br>
    <?php else: ?>
        <p>No item selected for checkout.</p>
    <?php endif; ?>
</body>
</html>