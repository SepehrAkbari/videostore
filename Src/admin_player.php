<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role']) || $_SESSION['Role'] !== 'Admin') {
    header("Location: admin_login.php");
    exit();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$stores = [];
$query = "SELECT Store_ID, Address FROM Store ORDER BY Store_ID";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $stores[] = $row;
}

$results = [];
$no_results = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $player_id = isset($_POST['player_id']) && trim($_POST['player_id']) !== '' ? trim($_POST['player_id']) : null;
    $generation = isset($_POST['generation']) && trim($_POST['generation']) !== '' ? trim($_POST['generation']) : null;

    $sql = "SELECT p.Player_ID, p.Generation 
            FROM Player p 
            WHERE 1=1";
    $params = [];
    $types = '';

    if ($player_id && is_numeric($player_id)) {
        $sql .= " AND p.Player_ID = ?";
        $params[] = $player_id;
        $types .= 'i';
    }
    if ($generation && is_numeric($generation)) {
        $sql .= " AND p.Generation = ?";
        $params[] = $generation;
        $types .= 'i';
    }

    $sql .= " ORDER BY p.Player_ID";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }

    if (empty($results)) {
        $no_results = true;
    }

    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_player'])) {
    $generation = trim($_POST['generation']);
    $store_id = trim($_POST['store_id']);
    $charge_per_day = trim($_POST['charge_per_day']) ?: 10.00;
    $rental_period = trim($_POST['rental_period']) ?: 14;

    if (!is_numeric($generation) || $generation < 1 || $generation > 3) {
        $message = "Generation must be between 1 and 3.";
    } elseif (empty($store_id) || !is_numeric($store_id)) {
        $message = "Please select a valid store.";
    } elseif ($charge_per_day <= 0) {
        $message = "Charge per day must be greater than 0.";
    } elseif ($rental_period <= 0) {
        $message = "Rental period must be greater than 0.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO Player (Generation) VALUES (?)");
            $stmt->bind_param("i", $generation);
            $stmt->execute();
            $player_id = $conn->insert_id;
            $stmt->close();

            $type = "DVD";

            $stmt = $conn->prepare("
                INSERT INTO Store_Object (Store_ID, Movie_ID, Player_ID, Type, Charge_per_day, Rental_period)
                VALUES (?, NULL, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iisdi", $store_id, $player_id, $type, $charge_per_day, $rental_period);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $message = "Player and its store object added successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error adding player: " . htmlspecialchars($e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_player'])) {
    $player_id = $_POST['player_id'];
    $generation = trim($_POST['generation']);

    if (!is_numeric($generation) || $generation < 1 || $generation > 3) {
        $message = "Generation must be between 1 and 3.";
    } else {
        $stmt = $conn->prepare("UPDATE Player SET Generation = ? WHERE Player_ID = ?");
        $stmt->bind_param("ii", $generation, $player_id);
        if ($stmt->execute()) {
            $message = "Player updated successfully.";
        } else {
            $message = "Error updating player.";
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_player'])) {
    $player_id = $_POST['player_id'];

    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM Transaction t
        JOIN Store_Object so ON t.Object_ID = so.Object_ID
        WHERE so.Player_ID = ?
    ");
    $stmt->bind_param("i", $player_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $transaction_count = $result->fetch_assoc()['count'];
    $stmt->close();

    if ($transaction_count > 0) {
        $message = "Cannot delete player because it has $transaction_count transaction(s) in history. Please remove these transactions first.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("DELETE FROM Store_Object WHERE Player_ID = ?");
            $stmt->bind_param("i", $player_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM Player WHERE Player_ID = ?");
            $stmt->bind_param("i", $player_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $message = "Player and its associated entries deleted successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error deleting player: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Players - VideoStore</title>
</head>
<body>
    <h2>Manage Players</h2>
    <nav>
        <ul>
            <li><a href="admin_manage.php">Back to Manage</a></li>
            <li><a href="admin_main.php">Dashboard</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <?php if (!empty($message)): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <h3>Add New Player</h3>
    <form method="POST" action="">
        <label for="generation">Generation (1-3):</label>
        <select id="generation" name="generation" required>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
        </select>
        <br>
        <label for="store_id">Store:</label>
        <select id="store_id" name="store_id" required>
            <option value="">-- Select Store --</option>
            <?php foreach ($stores as $store): ?>
                <option value="<?php echo htmlspecialchars($store['Store_ID']); ?>">
                    Store <?php echo htmlspecialchars($store['Store_ID']); ?> - <?php echo htmlspecialchars($store['Address']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br>
        <label for="charge_per_day">Charge per Day ($):</label>
        <input type="number" id="charge_per_day" name="charge_per_day" step="0.01" min="0.01" value="10.00" required>
        <br>
        <label for="rental_period">Rental Period (days):</label>
        <input type="number" id="rental_period" name="rental_period" min="1" value="14" required>
        <br>
        <input type="submit" name="add_player" value="Add Player">
    </form>

    <h3>Search Players</h3>
    <form method="POST" action="">
        <label for="player_id">Player ID:</label>
        <input type="text" id="player_id" name="player_id">
        <br>
        <label for="generation">Generation:</label>
        <select id="generation" name="generation">
            <option value="">-- Select Generation --</option>
            <option value="1" <?php echo (isset($_POST['generation']) && $_POST['generation'] === '1') ? 'selected' : ''; ?>>1</option>
            <option value="2" <?php echo (isset($_POST['generation']) && $_POST['generation'] === '2') ? 'selected' : ''; ?>>2</option>
            <option value="3" <?php echo (isset($_POST['generation']) && $_POST['generation'] === '3') ? 'selected' : ''; ?>>3</option>
        </select>
        <br>
        <input type="submit" name="search" value="Search">
    </form>

    <h3>Player List</h3>
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])): ?>
        <?php if ($no_results): ?>
            <p>No players found matching your criteria.</p>
        <?php else: ?>
            <?php foreach ($results as $player): ?>
                <div>
                    <h4>Player ID: <?php echo htmlspecialchars($player['Player_ID']); ?></h4>
                    <p><strong>Generation:</strong> <?php echo htmlspecialchars($player['Generation']); ?></p>

                    <h4>Edit Player</h4>
                    <form method="POST" action="">
                        <input type="hidden" name="player_id" value="<?php echo htmlspecialchars($player['Player_ID']); ?>">
                        <label for="generation_<?php echo $player['Player_ID']; ?>">Generation (1-3):</label>
                        <select id="generation_<?php echo $player['Player_ID']; ?>" name="generation" required>
                            <option value="1" <?php echo $player['Generation'] == 1 ? 'selected' : ''; ?>>1</option>
                            <option value="2" <?php echo $player['Generation'] == 2 ? 'selected' : ''; ?>>2</option>
                            <option value="3" <?php echo $player['Generation'] == 3 ? 'selected' : ''; ?>>3</option>
                        </select>
                        <br>
                        <input type="submit" name="edit_player" value="Update Player">
                    </form>

                    <form method="POST" action="">
                        <input type="hidden" name="player_id" value="<?php echo htmlspecialchars($player['Player_ID']); ?>">
                        <input type="submit" name="delete_player" value="Delete Player">
                    </form>
                </div>
                <hr>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php else: ?>
        <p>Please use the search form to find players.</p>
    <?php endif; ?>
</body>
</html>