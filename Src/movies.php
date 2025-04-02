<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role']) || $_SESSION['Role'] !== 'Customer') {
    header("Location: member_login.php");
    exit();
}

$genres = [];
$query = "SELECT DISTINCT Genre FROM Movie WHERE Genre IS NOT NULL ORDER BY Genre";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $genres[] = $row['Genre'];
}

$results = [];
$no_results = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $genre = isset($_POST['genre']) && $_POST['genre'] !== '' ? $_POST['genre'] : null;
    $title = isset($_POST['title']) && trim($_POST['title']) !== '' ? trim($_POST['title']) : null;
    $director = isset($_POST['director']) && trim($_POST['director']) !== '' ? trim($_POST['director']) : null;
    $producer = isset($_POST['producer']) && trim($_POST['producer']) !== '' ? trim($_POST['producer']) : null;
    $actor1 = isset($_POST['actor1']) && trim($_POST['actor1']) !== '' ? trim($_POST['actor1']) : null;
    $actor2 = isset($_POST['actor2']) && trim($_POST['actor2']) !== '' ? trim($_POST['actor2']) : null;
    $movie_id = isset($_POST['movie_id']) && trim($_POST['movie_id']) !== '' ? trim($_POST['movie_id']) : null;

    $sql = "SELECT Movie_ID, Title, Genre, Producer, Director, Actor1, Actor2, Description, Release_date, Rating, Num_DVD, Num_Blu 
            FROM Movie WHERE 1=1";
    $params = [];
    $types = '';

    if ($movie_id && is_numeric($movie_id)) {
        $sql .= " AND Movie_ID = ?";
        $params[] = $movie_id;
        $types .= 'i';
    }
    if ($genre) {
        $sql .= " AND Genre = ?";
        $params[] = $genre;
        $types .= 's';
    }
    if ($title) {
        $sql .= " AND Title LIKE ?";
        $params[] = "%$title%";
        $types .= 's';
    }
    if ($director) {
        $sql .= " AND Director LIKE ?";
        $params[] = "%$director%";
        $types .= 's';
    }
    if ($producer) {
        $sql .= " AND Producer LIKE ?";
        $params[] = "%$producer%";
        $types .= 's';
    }
    if ($actor1) {
        $sql .= " AND Actor1 LIKE ?";
        $params[] = "%$actor1%";
        $types .= 's';
    }
    if ($actor2) {
        $sql .= " AND Actor2 LIKE ?";
        $params[] = "%$actor2%";
        $types .= 's';
    }

    $sql .= " ORDER BY Title";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Fetch available copies for each movie
        $movie_id = $row['Movie_ID'];
        $stmt2 = $conn->prepare("
            SELECT Object_ID, Type 
            FROM Store_Object 
            WHERE Movie_ID = ? AND Player_ID IS NULL
        ");
        $stmt2->bind_param("i", $movie_id);
        $stmt2->execute();
        $copies_result = $stmt2->get_result();
        $copies = [];
        while ($copy = $copies_result->fetch_assoc()) {
            $copies[] = $copy;
        }
        $stmt2->close();
        $row['Copies'] = $copies;
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
    <title>Browse Movies - VideoStore</title>
</head>
<body>
    <h2>Browse Movies</h2>
    <nav>
        <ul>
            <li><a href="member_catalog.php">Back to Catalog</a></li>
            <li><a href="member_main.php">Dashboard</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <h3>Search Movies</h3>
    <form method="POST" action="">
        <label for="movie_id">Movie ID:</label>
        <input type="text" id="movie_id" name="movie_id">
        <br>
        <label for="genre">Genre:</label>
        <select id="genre" name="genre">
            <option value="">-- Select Genre --</option>
            <?php foreach ($genres as $g): ?>
                <option value="<?php echo htmlspecialchars($g); ?>" <?php echo (isset($_POST['genre']) && $_POST['genre'] === $g) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($g); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <br>
        <label for="title">Title:</label>
        <input type="text" id="title" name="title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
        <br>
        <label for="producer">Producer:</label>
        <input type="text" id="producer" name="producer" value="<?php echo isset($_POST['producer']) ? htmlspecialchars($_POST['producer']) : ''; ?>">
        <br>
        <label for="director">Director:</label>
        <input type="text" id="director" name="director" value="<?php echo isset($_POST['director']) ? htmlspecialchars($_POST['director']) : ''; ?>">
        <br>
        <label for="actor1">Actor 1:</label>
        <input type="text" id="actor1" name="actor1" value="<?php echo isset($_POST['actor1']) ? htmlspecialchars($_POST['actor1']) : ''; ?>">
        <br>
        <label for="actor2">Actor 2:</label>
        <input type="text" id="actor2" name="actor2" value="<?php echo isset($_POST['actor2']) ? htmlspecialchars($_POST['actor2']) : ''; ?>">
        <br>
        <input type="submit" value="Search">
    </form>

    <h3>Movie Catalog</h3>
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <?php if ($no_results): ?>
            <p>No movies found matching your criteria.</p>
        <?php else: ?>
            <?php foreach ($results as $movie): ?>
                <div>
                    <h4><?php echo htmlspecialchars($movie['Title']); ?> (ID: <?php echo htmlspecialchars($movie['Movie_ID']); ?>)</h4>
                    <p><strong>Genre:</strong> <?php echo htmlspecialchars($movie['Genre'] ?? 'N/A'); ?></p>
                    <p><strong>Producer:</strong> <?php echo htmlspecialchars($movie['Producer'] ?? 'N/A'); ?></p>
                    <p><strong>Director:</strong> <?php echo htmlspecialchars($movie['Director'] ?? 'N/A'); ?></p>
                    <p><strong>Actors:</strong> <?php echo htmlspecialchars($movie['Actor1'] ?? 'N/A'); ?>, <?php echo htmlspecialchars($movie['Actor2'] ?? 'N/A'); ?></p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($movie['Description'] ?? 'N/A'); ?></p>
                    <p><strong>Release Date:</strong> <?php echo htmlspecialchars($movie['Release_date'] ?? 'N/A'); ?></p>
                    <p><strong>Rating:</strong> <?php echo htmlspecialchars($movie['Rating'] ?? 'N/A'); ?>/10</p>
                    <p><strong>Available Copies:</strong> DVD: <?php echo htmlspecialchars($movie['Num_DVD']); ?>, Blu-Ray: <?php echo htmlspecialchars($movie['Num_Blu']); ?></p>
                    <?php if (!empty($movie['Copies'])): ?>
                        <p><strong>Select Copy to Rent/Reserve:</strong></p>
                        <?php foreach ($movie['Copies'] as $copy): ?>
                            <p>
                                Copy ID: <?php echo htmlspecialchars($copy['Object_ID']); ?> (<?php echo htmlspecialchars($copy['Type']); ?>)
                                <a href="checkout.php?object_id=<?php echo htmlspecialchars($copy['Object_ID']); ?>">Rent</a>
                                <a href="reserve.php?object_id=<?php echo htmlspecialchars($copy['Object_ID']); ?>">Reserve</a>
                            </p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No copies available.</p>
                    <?php endif; ?>
                </div>
                <hr>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php else: ?>
        <p>Please use the search form to find movies.</p>
    <?php endif; ?>
</body>
</html>