<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        $stmt = $conn->prepare("SELECT UserID, Fname, Lname FROM Member WHERE Email = ? AND Password = ? AND Role = 'Customer'");
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $member = $result->fetch_assoc();
            $_SESSION['UserID'] = $member['UserID'];
            $_SESSION['Role'] = 'Customer';
            $_SESSION['Fname'] = $member['Fname'];
            $_SESSION['Lname'] = $member['Lname'];
            header("Location: member_main.php");
            exit();
        } else {
            $error = "Invalid email or password.";
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
    <title>Member Login - VideoStore</title>
</head>
<body>
    <h2>Member Login</h2>
    <?php if (isset($error)): ?>
        <p><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form method="POST" action="">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <br>
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
        <br>
        <input type="submit" value="Login">
    </form>
    <br>
    <a href="index.php">Back to Home</a>
</body>
</html>