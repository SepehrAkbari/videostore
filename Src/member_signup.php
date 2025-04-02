<?php
session_start();
require_once 'db_connect.php';

if (isset($_SESSION['UserID'])) {
    if ($_SESSION['Role'] === 'Customer') {
        header("Location: member_main.php");
    } elseif ($_SESSION['Role'] === 'Admin') {
        header("Location: admin_main.php");
    }
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $address = trim($_POST['address']);
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']) ?: null;

    if (empty($fname) || empty($lname) || empty($address) || empty($password) || empty($email)) {
        $message = "All fields except phone are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO Member (Fname, Lname, Address, Password, Email, Phone, Role, Num_disks_rented, Player_rented)
            VALUES (?, ?, ?, ?, ?, ?, 'Customer', 0, 0)
        ");
        $stmt->bind_param("ssssss", $fname, $lname, $address, $password, $email, $phone);
        if ($stmt->execute()) {
            $message = "Sign-up successful! Please log in.";
        } else {
            $message = "Error signing up. Email or phone may already be in use.";
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
    <title>Member Sign-Up - VideoStore</title>
</head>
<body>
    <h2>Member Sign-Up</h2>
    <nav>
        <ul>
            <li><a href="index.php">Home</a></li>
        </ul>
    </nav>

    <?php if (!empty($message)): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

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
        <input type="password" id="password" name="password" required>
        <br>
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <br>
        <label for="phone">Phone (optional):</label>
        <input type="text" id="phone" name="phone">
        <br>
        <input type="submit" value="Sign Up">
    </form>
    <br>
    <a href="member_login.php">Already have an account? Log in here.</a>
</body>
</html>