<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Expired</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .error-container {
            max-width: 500px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-heading">Subscription Expired</h4>
            <p>Your subscription has expired. Please contact the admin to make a payment.</p>
            <hr>
            <p class="mb-0">Contact the administrator for assistance.</p>
            <!-- Placeholder for admin contact info; update as needed -->
            <p class="mt-2">Admin Contact: Please reach out via whatsapp or call +355 68 59 400 38.</p>
            
            <a href="login.php" class="btn btn-primary mt-3">Back to Login</a>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>