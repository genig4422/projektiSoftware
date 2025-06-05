<?php 

session_start();

$error = ['register' => $_SESSION['register_error'] ?? ''];

session_unset();

function showError($error){
    return !empty($error) ? "<p class='error-message'>$error</p>": "";
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

            <form action="registerservice.php" method="post">
                <h2>Register</h2>
                <? showError($error['register']) ?>
                <input type="name" name="name" placeholder="Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <input type="phone" name="phone" placeholder="Phone" required>
                <select name="role" required>
                    <option value="">--Select Role--</option>
                    <option value="admin">Admin</option>
                    <option value="user">User</option>

                </select>
                <button type="submit" name="register">Register</button>
                 <p>Already have an account? <a href="login.php">Login</a> </p>
            </form>
            
        </div>
    </div>
    
</body>
</html>