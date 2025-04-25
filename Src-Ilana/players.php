<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role'])) {
    header("Location: member_login.php");
    exit();
}

$results = [];
$no_results = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player_id = isset($_POST['player_id']) && trim($_POST['player_id']) !== '' ? trim($_POST['player_id']) : null;
    $generation = isset($_POST['generation']) && trim($_POST['generation']) !== '' ? trim($_POST['generation']) : null;

    $sql = "SELECT p.Player_ID, p.Generation, so.Object_ID, so.Type 
            FROM Player p 
            JOIN Store_Object so ON p.Player_ID = so.Player_ID 
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Players - VideoStore</title>
    <link rel="stylesheet" href="style sheet/total_style.css">
</head>
<body>
    <h2>Browse Players</h2>
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

    <h3>Search Players</h3>
    <form class="form" method="POST" action="">
        <label for="player_id">Player ID:</label>
        <input placeholder = "Player ID" type="text" id="player_id" name="player_id">
        <br>
        <label for="generation">Generation:</label>
        <select id="generation" name="generation">
            <option value="">-- Select Generation --</option>
            <option value="1" <?php echo (isset($_POST['generation']) && $_POST['generation'] === '1') ? 'selected' : ''; ?>>1</option>
            <option value="2" <?php echo (isset($_POST['generation']) && $_POST['generation'] === '2') ? 'selected' : ''; ?>>2</option>
            <option value="3" <?php echo (isset($_POST['generation']) && $_POST['generation'] === '3') ? 'selected' : ''; ?>>3</option>
        </select>
        <br>
        <input type="submit" value="Search">
        <input type="reset" value="Reset">
    </form>

    <h3>Player Catalog</h3>
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <?php if ($no_results): ?>
            <p>No players found matching your criteria.</p>
        <?php else: ?>
            <table class="table">
                <tr>
                    <th>Player ID</th>
                    <th>Generation</th>
                    <th>Type</th>
                    <th>Action</th>
                </tr>
            <?php foreach ($results as $player): ?>
                <tr> 
                    <td><?php echo htmlspecialchars($player['Player_ID']); ?></td>
                    <td><?php echo htmlspecialchars($player['Generation']); ?></td>
                    <td><?php echo htmlspecialchars($player['Type']); ?></td>
                    <td class="link"><a href="checkout.php?object_id=<?php echo htmlspecialchars($player['Object_ID']); ?>">Rent</a></td> 
                </tr>
            <?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php else: ?>
        <p>Please use the search form to find players.</p>
    <?php endif; ?>
</body>
</html>