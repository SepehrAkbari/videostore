
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
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $address = trim($_POST['address']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']) ?: null;

    if (empty($fname) || empty($lname) || empty($address) || empty($password) || empty($email)) {
        $message = "All fields except phone are required.";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } elseif($email) {
        // Check if the email already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Member WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($email_exists);
        $stmt->fetch();
        $stmt->close();

        if ($email_exists > 0) {
            $message = "Error signing up. Email is already in use.";
        }
     } elseif ($phone) {
            // Check if the phone already exists
            if ($phone) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM Member WHERE Phone = ?");
                $stmt->bind_param("s", $phone);
                $stmt->execute();
                $stmt->bind_result($phone_exists);
                $stmt->fetch();
                $stmt->close();

                if ($phone_exists > 0) {
                    $message = "Error signing up. Phone number is already in use.";
                }}
    }  

    if (empty($message)) {
        $stmt = $conn->prepare("
        INSERT INTO Member (Fname, Lname, Address, Password, Email, Phone, Role, Num_disks_rented, Player_rented)
        VALUES (?, ?, ?, ?, ?, ?, 'Customer', 0, 0)");
        $stmt->bind_param("ssssss", $fname, $lname, $address, $password, $email, $phone); // Bind variables to parameters
        $stmt->execute();
        $stmt->close();
        $success_message = "Sign-up successful! You can now log in.";
    }

}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to VideoStore</title>
    <link rel="stylesheet" href="style sheet\total_style.css">
</head>
<body>
    <h1>Welcome to VideoStore</h1>
    <nav>
        <ul>
            <li><a href ='index.php'>Home</a></li>
            <li><a href="login.php">Login</a></li>
        </ul>
    </nav>

    <h1>Sign-Up</h1>

        <?php if (!empty($message)): ?>
        <p class = 'error_message' ><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
        <p class = "success_message" ><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>

    <form class = 'form' method="POST" action="">
        <label for="fname">First Name:</label>
        <input placeholder = 'First Name' type="text" id="fname" name="fname" required>
        <br>
        <label for="lname">Last Name:</label>
        <input placeholder= 'Last Name' type="text" id="lname" name="lname" required>
        <br>
        <label for="address">Address:</label>
        <input placeholder="Address" type="text" id="address" name="address" required>
        <br>
        <label for="password">Password:</label>
        <input placeholder = 'Password' type="password" id="password" name="password" required>
        <br>
        <label for="confirm_password">Confirm Password:</label>
        <input placeholder='Confirm Password' type="password" id="confirm_password" name="confirm_password" required>
        <br>
        <label for="email">Email:</label>
        <input placeholder = 'Email' type="email" id="email" name="email" required>
        <br>
        <label for="phone">Phone (optional):</label>
        <input placeholder = 'Phone' type="text" id="phone" name="phone">
        <br>
        <input type="submit" value="Sign Up">
        <input type="reset" value = 'Clear Form'>
    </form>

</body>
</html>
