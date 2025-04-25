<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role'])) {
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
            SELECT so.Object_ID, so.Type, s.Address
            FROM Store_Object so 
            JOIN Store s ON s.Store_ID = so.Store_ID
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


$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<script>function openForm(id) {
  let form = document.getElementById("edit_form_" + id);
  if (form) {
    form.style.display = "block"; // Show the form
  } 
}

function closeForm(id) {
  let form = document.getElementById("edit_form_" + id);
  if (form) {
    form.style.display = "none"; // Hide the form
  }
}
</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Movies - VideoStore</title>
    <link rel="stylesheet" href="style sheet/total_style.css">
</head>
<body>
    <h2>Browse Movies</h2>
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

    <h3>Search Movies</h3>
    <form class ="form" method="POST" action="">
        <label for="movie_id">Movie ID:</label>
        <input placeholder = "Movie ID" type="text" id="movie_id" name="movie_id">
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
        <input placeholder = "Title" type="text" id="title" name="title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
        <br>
        <label for="producer">Producer:</label>
        <input placeholder = "Producer" type="text" id="producer" name="producer" value="<?php echo isset($_POST['producer']) ? htmlspecialchars($_POST['producer']) : ''; ?>">
        <br>
        <label for="director">Director:</label>
        <input placeholder = "Director" type="text" id="director" name="director" value="<?php echo isset($_POST['director']) ? htmlspecialchars($_POST['director']) : ''; ?>">
        <br>
        <label for="actor1">Actor 1:</label>
        <input placeholder = "Main Actor" type="text" id="actor1" name="actor1" value="<?php echo isset($_POST['actor1']) ? htmlspecialchars($_POST['actor1']) : ''; ?>">
        <br>
        <label for="actor2">Actor 2:</label>
        <input placeholder = "Main Actor" type="text" id="actor2" name="actor2" value="<?php echo isset($_POST['actor2']) ? htmlspecialchars($_POST['actor2']) : ''; ?>">
        <br>
        <input type="submit" value="Search">
        <input type="reset" value="Reset">
    </form>

    <h3>Movie Catalog</h3>
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <?php if ($no_results): ?>
            <p>No movies found matching your criteria.</p>
        <?php else: ?>
            <?php foreach ($results as $movie): ?>
                <table class="table">
                <tr>
                    <th>Title</th>
                    <th>Genre</th>
                    <th>Producer</th>
                    <th>Director</th>
                    <th>Actors</th>
                    <th>Description</th>
                    <th>Released </th>
                    <th>Rating</th>
                    <th>Available Copies</th>
                </tr>
                <tr>
                    <td><strong><?php echo htmlspecialchars($movie['Title']); ?></strong><br> (ID: <?php echo htmlspecialchars($movie['Movie_ID']); ?>)</td>
                    <td><?php echo htmlspecialchars($movie['Genre'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($movie['Producer'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($movie['Director'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($movie['Actor1'] ?? 'N/A'); ?> <br><br> <?php echo htmlspecialchars($movie['Actor2'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($movie['Description'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($movie['Release_date'] ? substr($movie['Release_date'], 0, 4) : 'N/A'); ?></td>
                    <td><p><?php echo htmlspecialchars($movie['Rating'] ?? 'N/A'); ?>/10</p> </td>
                    <td>
                    <?php if (!empty($movie['Copies'])): ?>
                        
                        <form class="rent_button" method="POST" action="">
                            <input type="hidden" name="show_edit" value="1">
                            <button type="button" name="show_edit" onclick="openForm('<?php echo $movie['Movie_ID']; ?>')">Select</button>
                        </form>
                        </td>
                        </tr>
                        </table>

                        <div class="edit_form" id="edit_form_<?php echo $movie['Movie_ID']; ?>">
                            <h3>Rent Movie - <?php echo htmlspecialchars($movie['Title']); ?> </h3>
                            <table class="table">
                            <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Location</th>
                                        <th>Rent/Reserve</th>
                                </tr>
                            <?php foreach ($movie['Copies'] as $copy): ?>
                                <tr >
                                    <td> <?php echo htmlspecialchars($copy['Object_ID']); ?> </td>
                                    <td> <?php echo htmlspecialchars($copy['Type']); ?> </td>
                                    <td><?php echo htmlspecialchars($copy['Address']); ?></td>
                                    <td class="link"><a href="checkout.php?object_id=<?php echo htmlspecialchars($copy['Object_ID']); ?>">Rent</a> <br>
                                    <a href="reserve.php?object_id=<?php echo htmlspecialchars($copy['Object_ID']); ?>">Reserve</a></t>
                                </tr>
                                <?php endforeach; ?>
                                <button class="close_edit" type="button" onclick="closeForm('<?php echo $movie['Movie_ID']; ?>')">Close</button>
                                </table>
                            </div>
                    <?php else: ?>
                        <p>No copies available.</p>
                    <?php endif; ?>
            <?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php else: ?>
        <p>Please use the search form to find movies.</p>
    <?php endif; ?>
</body>
</html>