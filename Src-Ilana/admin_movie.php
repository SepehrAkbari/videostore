<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role']) || $_SESSION['Role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$genres = [];
$query = "SELECT DISTINCT Genre FROM Movie WHERE Genre IS NOT NULL ORDER BY Genre";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $genres[] = $row['Genre'];
}

$stores = [];
$query = "SELECT Store_ID, Address FROM Store ORDER BY Store_ID";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $stores[] = $row;
}

$results = [];
$no_results = false;
$message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
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
        $results[] = $row;
    }

    if (empty($results)) {
        $no_results = true;
    }

    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_movie'])) {
    $title = trim($_POST['title']);
    $genre = trim($_POST['genre']) ?: null;
    $producer = trim($_POST['producer']) ?: null;
    $director = trim($_POST['director']) ?: null;
    $actor1 = trim($_POST['actor1']) ?: null;
    $actor2 = trim($_POST['actor2']) ?: null;
    $description = trim($_POST['description']) ?: null;
    $release_date = trim($_POST['release_date']) ?: null;
    $rating = trim($_POST['rating']) ?: null;
    $num_dvd = trim($_POST['num_dvd']) ?: 0;
    $num_blu = trim($_POST['num_blu']) ?: 0;
    $store_id = trim($_POST['store_id']);
    $charge_per_day = trim($_POST['charge_per_day']) ?: 5.00;
    $rental_period = trim($_POST['rental_period']) ?: 7;

    if (empty($title)) {
        $message = "Title is required.";
    } elseif ($rating && ($rating < 0 || $rating > 10)) {
        $message = "Rating must be between 0 and 10.";
    } elseif ($num_dvd < 0 || $num_blu < 0) {
        $message = "Number of DVDs and Blu-Rays must be non-negative.";
    } elseif (empty($store_id) || !is_numeric($store_id)) {
        $message = "Please select a valid store.";
    } elseif ($charge_per_day <= 0) {
        $message = "Charge per day must be greater than 0.";
    } elseif ($rental_period <= 0) {
        $message = "Rental period must be greater than 0.";
    } elseif ($description) {
            // Check for existing movie with same Title and Description
            $check_stmt = $conn->prepare("SELECT Movie_ID FROM Movie WHERE Title = ? AND Description = ?");
            $check_stmt->bind_param("ss", $title, $description);
            $check_stmt->execute();
            $check_stmt->store_result();
    
            if ($check_stmt->num_rows > 0) {
                $message = "This movie has already been added.";
                $check_stmt->close();
            } else {
                $check_stmt->close(); }
    } else {
        
        $conn->begin_transaction();
        try {
            
            $stmt = $conn->prepare("
                INSERT INTO Movie (Title, Genre, Producer, Director, Actor1, Actor2, Description, Release_date, Rating, Num_DVD, Num_Blu)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssssssssdii", $title, $genre, $producer, $director, $actor1, $actor2, $description, $release_date, $rating, $num_dvd, $num_blu);
            $stmt->execute();
            $movie_id = $conn->insert_id; 
            $stmt->close();

            
            for ($i = 0; $i < $num_dvd; $i++) {
                $stmt = $conn->prepare("
                    INSERT INTO Store_Object (Store_ID, Movie_ID, Player_ID, Type, Charge_per_day, Rental_period)
                    VALUES (?, ?, NULL, 'DVD', ?, ?)
                ");
                $stmt->bind_param("iidi", $store_id, $movie_id, $charge_per_day, $rental_period);
                $stmt->execute();
                $stmt->close();
            }

            for ($i = 0; $i < $num_blu; $i++) {
                $stmt = $conn->prepare("
                    INSERT INTO Store_Object (Store_ID, Movie_ID, Player_ID, Type, Charge_per_day, Rental_period)
                    VALUES (?, ?, NULL, 'Blu-Ray', ?, ?)
                ");
                $stmt->bind_param("iidi", $store_id, $movie_id, $charge_per_day, $rental_period);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            $success_message = "Movie and its copies added successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error adding movie: " . htmlspecialchars($e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_movie'])) {
    $movie_id = $_POST['movie_id'];
    $title = trim($_POST['title']);
    $genre = trim($_POST['genre']) ?: null;
    $producer = trim($_POST['producer']) ?: null;
    $director = trim($_POST['director']) ?: null;
    $actor1 = trim($_POST['actor1']) ?: null;
    $actor2 = trim($_POST['actor2']) ?: null;
    $description = trim($_POST['description']) ?: null;
    $release_date = trim($_POST['release_date']) ?: null;
    $rating = trim($_POST['rating']) ?: null;
    $num_dvd = trim($_POST['num_dvd']) ?: 0;
    $num_blu = trim($_POST['num_blu']) ?: 0;

    if (empty($title)) {
        $message = "Title is required.";
    } elseif ($rating && ($rating < 0 || $rating > 10)) {
        $message = "Rating must be between 0 and 10.";
    } elseif ($num_dvd < 0 || $num_blu < 0) {
        $message = "Number of DVDs and Blu-Rays must be non-negative.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT Num_DVD, Num_Blu FROM Movie WHERE Movie_ID = ?");
            $stmt->bind_param("i", $movie_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current = $result->fetch_assoc();
            $current_num_dvd = $current['Num_DVD'];
            $current_num_blu = $current['Num_Blu'];
            $stmt->close();

            $stmt = $conn->prepare("
                UPDATE Movie 
                SET Title = ?, Genre = ?, Producer = ?, Director = ?, Actor1 = ?, Actor2 = ?, Description = ?, Release_date = ?, Rating = ?, Num_DVD = ?, Num_Blu = ?
                WHERE Movie_ID = ?
            ");
            $stmt->bind_param("ssssssssdiii", $title, $genre, $producer, $director, $actor1, $actor2, $description, $release_date, $rating, $num_dvd, $num_blu, $movie_id);
            $stmt->execute();
            $stmt->close();

            $dvd_diff = $num_dvd - $current_num_dvd;
            if ($dvd_diff > 0) {
                $stmt = $conn->prepare("SELECT Store_ID, Charge_per_day, Rental_period FROM Store_Object WHERE Movie_ID = ? AND Type = 'DVD' LIMIT 1");
                $stmt->bind_param("i", $movie_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $template = $result->fetch_assoc();
                $stmt->close();

                $store_id = $template ? $template['Store_ID'] : 1;
                $charge_per_day = $template ? $template['Charge_per_day'] : 5.00;
                $rental_period = $template ? $template['Rental_period'] : 7;

                for ($i = 0; $i < $dvd_diff; $i++) {
                    $stmt = $conn->prepare("
                        INSERT INTO Store_Object (Store_ID, Movie_ID, Player_ID, Type, Charge_per_day, Rental_period)
                        VALUES (?, ?, NULL, 'DVD', ?, ?)
                    ");
                    $stmt->bind_param("iidi", $store_id, $movie_id, $charge_per_day, $rental_period);
                    $stmt->execute();
                    $stmt->close();
                }
            } elseif ($dvd_diff < 0) {
                $stmt = $conn->prepare("
                    SELECT so.Object_ID 
                    FROM Store_Object so
                    LEFT JOIN Transaction t ON so.Object_ID = t.Object_ID
                    WHERE so.Movie_ID = ? AND so.Type = 'DVD' AND t.Object_ID IS NULL
                    LIMIT ?
                ");
                $limit = abs($dvd_diff);
                $stmt->bind_param("ii", $movie_id, $limit);
                $stmt->execute();
                $result = $stmt->get_result();
                $objects_to_delete = [];
                while ($row = $result->fetch_assoc()) {
                    $objects_to_delete[] = $row['Object_ID'];
                }
                $stmt->close();

                if (count($objects_to_delete) < abs($dvd_diff)) {
                    throw new Exception("Cannot remove DVD copies because some are associated with transactions.");
                }

                foreach ($objects_to_delete as $object_id) {
                    $stmt = $conn->prepare("DELETE FROM Store_Object WHERE Object_ID = ?");
                    $stmt->bind_param("i", $object_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $blu_diff = $num_blu - $current_num_blu;
            if ($blu_diff > 0) {
                $stmt = $conn->prepare("SELECT Store_ID, Charge_per_day, Rental_period FROM Store_Object WHERE Movie_ID = ? AND Type = 'Blu-Ray' LIMIT 1");
                $stmt->bind_param("i", $movie_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $template = $result->fetch_assoc();
                $stmt->close();

                $store_id = $template ? $template['Store_ID'] : 1;
                $charge_per_day = $template ? $template['Charge_per_day'] : 5.00;
                $rental_period = $template ? $template['Rental_period'] : 7;

                for ($i = 0; $i < $blu_diff; $i++) {
                    $stmt = $conn->prepare("
                        INSERT INTO Store_Object (Store_ID, Movie_ID, Player_ID, Type, Charge_per_day, Rental_period)
                        VALUES (?, ?, NULL, 'Blu-Ray', ?, ?)
                    ");
                    $stmt->bind_param("iidi", $store_id, $movie_id, $charge_per_day, $rental_period);
                    $stmt->execute();
                    $stmt->close();
                }
            } elseif ($blu_diff < 0) {
                $stmt = $conn->prepare("
                    SELECT so.Object_ID 
                    FROM Store_Object so
                    LEFT JOIN Transaction t ON so.Object_ID = t.Object_ID
                    WHERE so.Movie_ID = ? AND so.Type = 'Blu-Ray' AND t.Object_ID IS NULL
                    LIMIT ?
                ");
                $limit = abs($blu_diff);
                $stmt->bind_param("ii", $movie_id, $limit);
                $stmt->execute();
                $result = $stmt->get_result();
                $objects_to_delete = [];
                while ($row = $result->fetch_assoc()) {
                    $objects_to_delete[] = $row['Object_ID'];
                }
                $stmt->close();

                if (count($objects_to_delete) < abs($blu_diff)) {
                    throw new Exception("Cannot remove Blu-Ray copies because some are associated with transactions.");
                }

                foreach ($objects_to_delete as $object_id) {
                    $stmt = $conn->prepare("DELETE FROM Store_Object WHERE Object_ID = ?");
                    $stmt->bind_param("i", $object_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            $conn->commit();
            $success_message = "Movie updated successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error updating movie: " . htmlspecialchars($e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_movie'])) {
    $movie_id = $_POST['movie_id'];

    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM Transaction t
        JOIN Store_Object so ON t.Object_ID = so.Object_ID
        WHERE so.Movie_ID = ?
    ");
    $stmt->bind_param("i", $movie_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $transaction_count = $result->fetch_assoc()['count'];
    $stmt->close();

    if ($transaction_count > 0) {
        $message = "Cannot delete movie because it has $transaction_count transaction(s) in history. Please remove these transactions first.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("DELETE FROM Store_Object WHERE Movie_ID = ?");
            $stmt->bind_param("i", $movie_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM Disk WHERE Movie_ID = ?");
            $stmt->bind_param("i", $movie_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM Movie WHERE Movie_ID = ?");
            $stmt->bind_param("i", $movie_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $success_message = "Movie and its associated entries deleted successfully.";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error deleting movie: " . htmlspecialchars($e->getMessage());
        }
    }
}
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
    <title>Manage Movies - VideoStore</title>
    <link rel="stylesheet" href="style sheet\total_style.css">
</head>
<body>
    <h1>Manage Movies</h1>
    <nav>
        <ul>
            <li ><a href = "member_main.php">User View</a></li>
            <li><a href="admin_main.php">Home</a></li>
            <li class="dropdown"><button class = 'dropdown_button'>Manage</button>
            <div class="dropdown-content">
                <a href="admin_members.php">Members</a>
                <a href="admin_movie.php">Movies</a>
                <a href="admin_player.php">Players</a>
                <a href = "admin_admins.php">Admins</a>
            </div>
            </li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <?php if (!empty($message)): ?>
        <p class ="error_message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <?php if (!empty($success_message)): ?>
        <p class ="success_message"><?php echo htmlspecialchars($success_message); ?></p>
    <?php endif; ?>

    <h2>Add New Movie</h2>
    <form class='form'  method="POST" action="">
        <label for="title">Title:</label>
        <input placeholder ='Title' type="text" id="title" name="title" required>
        <br>
        <label for="genre">Genre:</label>
        <input placeholder = "Genre" type="text" id="genre" name="genre">
        <br>
        <label for="producer">Producer:</label>
        <input placeholder = "Producer" type="text" id="producer" name="producer">
        <br>
        <label for="director">Director:</label>
        <input placeholder = "Director" type="text" id="director" name="director">
        <br>
        <label for="actor1">Actor 1:</label>
        <input placeholder = "Main Actor" type="text" id="actor1" name="actor1">
        <br>
        <label for="actor2">Actor 2:</label>
        <input placeholder = "Main Actor" type="text" id="actor2" name="actor2">
        <br>
        <label for="description">Description:</label>
        <textarea placeholder = "Write Description Here" id="description" name="description"></textarea>
        <br>
        <label for="release_date">Release Date (YYYY-MM-DD):</label>
        <input placeholder = "Release Date: YYYY-MM-DD" type="text" id="release_date" name="release_date">
        <br>
        <label for="rating">Rating (0-10):</label>
        <input placeholder = "Rating Out Of 10" type="number" id="rating" name="rating" step="0.1" min="0" max="10">
        <br>
        <label for="num_dvd">Number of DVDs:</label>
        <input placeholder = "Number of DVDs" type="number" id="num_dvd" name="num_dvd" min="0" value="1">
        <br>
        <label for="num_blu">Number of Blu-Rays:</label>
        <input placeholder = "Number of Blu-Ray" type="number" id="num_blu" name="num_blu" min="0" value="0">
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
        <input type="number" id="charge_per_day" name="charge_per_day" step="0.01" min="0.01" value="5.00" required>
        <br>
        <label for="rental_period">Rental Period (days):</label>
        <input type="number" id="rental_period" name="rental_period" min="1" value="7" required>
        <br>
        <input type="submit" name="add_movie" value="Add Movie">
        <input type="reset" value="Clear Form">
    </form>

    <h2>Search Movies</h2>
    <form class ='form' method="POST" action="">
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
        <input placeholder = 'Title' type="text" id="title" name="title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
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

        <input type="submit" name="search" value="Search">
        <input type="reset" value="Clear Form">
    </form>

    <h2>Movie List</h2>
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])): ?>
        <?php if ($no_results): ?>
            <p class="error_message">No movies found matching your criteria.</p>
        <?php else: ?>
            <table class='table'>
            <tr>
                    <th>Movie ID</th>
                    <th>Title</th>
                    <th>Genre</th>
                    <th>Producer</th>
                    <th>Director</th>
                    <th>Actor</th>
                    <th>Description</th>
                    <th>Release Date</th>
                    <th>Rating</th>
                    <th>Number of DVDs</th>
                    <th>Number of Blu-Rays</th>
                    <th>Actions</th>
                </tr>
            <?php foreach ($results as $movie): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($movie['Movie_ID']); ?></td>
                        <td><?php echo htmlspecialchars($movie['Title']); ?> </td>
                        <td><?php echo htmlspecialchars($movie['Genre'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($movie['Producer'] ?? 'N/A'); ?></t>
                        <td><?php echo htmlspecialchars($movie['Director'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($movie['Actor1'] ?? 'N/A'); ?> <br> <br><?php echo htmlspecialchars($movie['Actor2'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($movie['Description'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($movie['Release_date'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($movie['Rating'] ?? 'N/A'); ?>/10</td>
                        <td><?php echo htmlspecialchars($movie['Num_DVD']); ?></t>
                        <td><?php echo htmlspecialchars($movie['Num_Blu']); ?></td>
                        <td class = 'delete'>
                        <form class="delete_button" method="POST" action="">
                            <input type="hidden" name="movie_id" value="<?php echo htmlspecialchars($movie['Movie_ID']); ?>">
                            <input type="submit" name="delete_movie" value="Delete">
                        </form>
                        <form class = 'delete_button' method="post" action="">
                            <input type="hidden" name="show_edit" value="1">
                            <button type="button" name="show_edit" onclick="openForm('<?php echo $movie['Movie_ID']; ?>')">Edit</button>
                        </form>

                        </td>
                    </tr>

                    <div class = 'edit_form' id ="edit_form_<?php echo $movie['Movie_ID']; ?>">
                    <h3>Edit Movie - <?php echo htmlspecialchars($movie['Title']); ?> </h3>
                    <form class ='form' method="POST" action="">
                        <input type="hidden" name="movie_id" value="<?php echo htmlspecialchars($movie['Movie_ID']); ?>">
                        <label for="title_<?php echo $movie['Movie_ID']; ?>">Title:</label>
                        <input type="text" id="title_<?php echo $movie['Movie_ID']; ?>" name="title" value="<?php echo htmlspecialchars($movie['Title']); ?>" required>
                        <br>
                        <label for="genre_<?php echo $movie['Movie_ID']; ?>">Genre:</label>
                        <input type="text" id="genre_<?php echo $movie['Movie_ID']; ?>" name="genre" value="<?php echo htmlspecialchars($movie['Genre'] ?? ''); ?>">
                        <br>
                        <label for="producer_<?php echo $movie['Movie_ID']; ?>">Producer:</label>
                        <input type="text" id="producer_<?php echo $movie['Movie_ID']; ?>" name="producer" value="<?php echo htmlspecialchars($movie['Producer'] ?? ''); ?>">
                        <br>
                        <label for="director_<?php echo $movie['Movie_ID']; ?>">Director:</label>
                        <input type="text" id="director_<?php echo $movie['Movie_ID']; ?>" name="director" value="<?php echo htmlspecialchars($movie['Director'] ?? ''); ?>">
                        <br>
                        <label for="actor1_<?php echo $movie['Movie_ID']; ?>">Actor 1:</label>
                        <input type="text" id="actor1_<?php echo $movie['Movie_ID']; ?>" name="actor1" value="<?php echo htmlspecialchars($movie['Actor1'] ?? ''); ?>">
                        <br>
                        <label for="actor2_<?php echo $movie['Movie_ID']; ?>">Actor 2:</label>
                        <input type="text" id="actor2_<?php echo $movie['Movie_ID']; ?>" name="actor2" value="<?php echo htmlspecialchars($movie['Actor2'] ?? ''); ?>">
                        <br>
                        <label for="description_<?php echo $movie['Movie_ID']; ?>">Description:</label>
                        <textarea id="description_<?php echo $movie['Movie_ID']; ?>" name="description"><?php echo htmlspecialchars($movie['Description'] ?? ''); ?></textarea>
                        <br>
                        <label for="release_date_<?php echo $movie['Movie_ID']; ?>">Release Date (YYYY-MM-DD):</label>
                        <input type="text" id="release_date_<?php echo $movie['Movie_ID']; ?>" name="release_date" value="<?php echo htmlspecialchars($movie['Release_date'] ?? ''); ?>">
                        <br>
                        <label for="rating_<?php echo $movie['Movie_ID']; ?>">Rating (0-10):</label>
                        <input type="number" id="rating_<?php echo $movie['Movie_ID']; ?>" name="rating" step="0.1" min="0" max="10" value="<?php echo htmlspecialchars($movie['Rating'] ?? ''); ?>">
                        <br>
                        <label for="num_dvd_<?php echo $movie['Movie_ID']; ?>">Number of DVDs:</label>
                        <input type="number" id="num_dvd_<?php echo $movie['Movie_ID']; ?>" name="num_dvd" min="0" value="<?php echo htmlspecialchars($movie['Num_DVD']); ?>">
                        <br>
                        <label for="num_blu_<?php echo $movie['Movie_ID']; ?>">Number of Blu-Rays:</label>
                        <input type="number" id="num_blu_<?php echo $movie['Movie_ID']; ?>" name="num_blu" min="0" value="<?php echo htmlspecialchars($movie['Num_Blu']); ?>">
                        <br>
                        <input type="submit" name="edit_movie" value="Update Movie">
                        <button class ='close_edit' type="button" onclick = "closeForm('<?php echo $movie['Movie_ID']; ?>')">Close</button>
                    </form>
                    </div>
            <?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php else: ?>
        <p>Please use the search form to find movies.</p>
    <?php endif; ?>
</body>
</html>