<?php 


session_start();
require_once 'header1.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    
    <div class="main-content" id="mainContent">
        <div class="container my-5">
    <p>Welcome, <span><?= $_SESSION['name'] ?></span></p>
    <p>This is an <span>User</span> Page</p>
   <button onclick="window.location.href='logout.php'">Logout</button>

   </div></div>
</body>
</html>