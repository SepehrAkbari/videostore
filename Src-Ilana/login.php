<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Please enter login information";
    } else {
        $stmt = $conn->prepare("SELECT UserID, Fname, Lname, Role FROM Member WHERE Email = ? AND Password = ?");
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $member = $result->fetch_assoc();
            $_SESSION['UserID'] = $member['UserID'];
            $_SESSION['Fname'] = $member['Fname'];
            $_SESSION['Lname'] = $member['Lname'];
            if ($member['Role'] === 'Admin') {
                $_SESSION['Role'] = 'Admin';
                header("Location: admin_main.php");
            } elseif ($member['Role'] === 'Customer') {
                $_SESSION["Role"] = "Customer";
                header("Location: member_main.php");
            } else {
                $error = "Unauthorized role.";
            }
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
    <title>Login</title>
    <link rel="stylesheet" href="style sheet\total_style.css">
</head>
<body>
    <h1>Welcome to VideoStore</h1>
    <nav> 
        <ul>
            <li><a href="index.php"> Home </a></li>
        </ul>
    </nav>
    
    <h1>Login</h1>
    <?php if (isset($error)): ?>
        <p class="error_message"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form class = 'form' method="POST" action="">
        <label for="email">Email:</label>
        <input placeholder = "Email" type="email" id="email" name="email" required>
        <br>
        <label for="password">Password:</label>
        <input placeholder = "Password" type="password" id="password" name="password" required>
        <br>
        <input type="submit" value="Login">
        <input type="reset" value="Clear Form">
    </form>
</body>
</html>