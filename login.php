<?php
session_start();

$error = ['login' => $_SESSION['login_error'] ?? ''];

// Clear only the login_error session variable after use
unset($_SESSION['login_error']);

function showError($error){
    return !empty($error) ? "<p class='error-message'>$error</p>" : "";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RMS Software</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="form-box" id="login-form">
            <form action="loginservice.php" method="post">
                <h2>Login</h2>
                <?= showError($error['login']); ?>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login">Login</button>
                <p>Don't have an account? <a href="#">Talk to Owner</a> </p>
            </form>
        </div>
    </div>
</body>
</html>