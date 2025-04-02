<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['UserID']) || !isset($_SESSION['Role']) || $_SESSION['Role'] !== 'Admin') {
    header("Location: admin_login.php");
    exit();
}

$results = [];
$no_results = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $fname = isset($_POST['fname']) && trim($_POST['fname']) !== '' ? trim($_POST['fname']) : null;
    $lname = isset($_POST['lname']) && trim($_POST['lname']) !== '' ? trim($_POST['lname']) : null;
    $address = isset($_POST['address']) && trim($_POST['address']) !== '' ? trim($_POST['address']) : null;
    $phone = isset($_POST['phone']) && trim($_POST['phone']) !== '' ? trim($_POST['phone']) : null;
    $email = isset($_POST['email']) && trim($_POST['email']) !== '' ? trim($_POST['email']) : null;

    $sql = "SELECT UserID, Fname, Lname, Address, Phone, Email 
            FROM Member 
            WHERE Role = 'Admin'";
    $params = [];
    $types = '';

    if ($fname) {
        $sql .= " AND Fname LIKE ?";
        $params[] = "%$fname%";
        $types .= 's';
    }
    if ($lname) {
        $sql .= " AND Lname LIKE ?";
        $params[] = "%$lname%";
        $types .= 's';
    }
    if ($address) {
        $sql .= " AND Address LIKE ?";
        $params[] = "%$address%";
        $types .= 's';
    }
    if ($phone) {
        $sql .= " AND Phone LIKE ?";
        $params[] = "%$phone%";
        $types .= 's';
    }
    if ($email) {
        $sql .= " AND Email LIKE ?";
        $params[] = "%$email%";
        $types .= 's';
    }

    $sql .= " ORDER BY Fname, Lname";

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $address = trim($_POST['address']);
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']) ?: null;

    if (empty($fname) || empty($lname) || empty($address) || empty($password) || empty($email)) {
        $message = "First name, last name, address, password, and email are required.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO Member (Fname, Lname, Address, Password, Email, Phone, Role, Num_disks_rented, Player_rented)
            VALUES (?, ?, ?, ?, ?, ?, 'Admin', 0, 0)
        ");
        $stmt->bind_param("ssssss", $fname, $lname, $address, $password, $email, $phone);
        if ($stmt->execute()) {
            $message = "Admin added successfully.";
        } else {
            $message = "Error adding admin. Email or phone may already be in use.";
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
    $user_id = $_POST['user_id'];
    $stmt = $conn->prepare("DELETE FROM Member WHERE UserID = ? AND Role = 'Admin'");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $message = "Admin deleted successfully.";
    } else {
        $message = "Error deleting admin.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - VideoStore</title>
</head>
<body>
    <h2>Manage Admins</h2>
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

    <h3>Add New Admin</h3>
    <form method="POST" action="">
        <label for="fname">First Name:</label>
        <input type="text" id="fname" name="fname" required>
        <br>
        <label for="lname">Last Name:</label>
        <input type="text" id="lname" name="lname" required>
        <br>
        <label for="address">Address:</label>
        <input type="text" id="address" name="address" required>
        <br>
        <label for="password">Password:</label>
        <input type="text" id="password" name="password" required>
        <br>
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <br>
        <label for="phone">Phone:</label>
        <input type="text" id="phone" name="phone">
        <br>
        <input type="submit" name="add_admin" value="Add Admin">
    </form>

    <h3>Search Admins</h3>
    <form method="POST" action="">
        <label for="fname">First Name:</label>
        <input type="text" id="fname" name="fname" value="<?php echo isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : ''; ?>">
        <br>
        <label for="lname">Last Name:</label>
        <input type="text" id="lname" name="lname" value="<?php echo isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : ''; ?>">
        <br>
        <label for="address">Address:</label>
        <input type="text" id="address" name="address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
        <br>
        <label for="phone">Phone:</label>
        <input type="text" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
        <br>
        <label for="email">Email:</label>
        <input type="text" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        <br>
        <input type="submit" name="search" value="Search">
    </form>

    <h3>Admin List</h3>
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])): ?>
        <?php if ($no_results): ?>
            <p>No admins found matching your criteria.</p>
        <?php else: ?>
            <table border="1">
                <tr>
                    <th>User ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Address</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($results as $admin): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($admin['UserID']); ?></td>
                        <td><?php echo htmlspecialchars($admin['Fname']); ?></td>
                        <td><?php echo htmlspecialchars($admin['Lname']); ?></td>
                        <td><?php echo htmlspecialchars($admin['Address']); ?></td>
                        <td><?php echo htmlspecialchars($admin['Phone'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($admin['Email']); ?></td>
                        <td>
                            <form method="POST" action="">
                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($admin['UserID']); ?>">
                                <input type="submit" name="delete_admin" value="Delete">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php else: ?>
        <p>Please use the search form to find admins.</p>
    <?php endif; ?>
</body>
</html>