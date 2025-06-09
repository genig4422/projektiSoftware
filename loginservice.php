<?php
session_start();
require_once 'config.php';

if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, business_id, name, email, role, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Check hashed password
        if (password_verify($password, $user['password'])) {
            // Store user data in session
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            // Check subscription for owner and manager roles
            if ($user['role'] === 'owner' || $user['role'] === 'manager') {
                $stmt = $conn->prepare("SELECT subscription_id FROM subscriptions WHERE business_id = ? AND status = 'active' AND end_date > NOW()");
                $stmt->bind_param("i", $user['business_id']);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 0) {
                    // No active subscription found
                    $_SESSION['login_error'] = 'Your subscription has expired. Please contact the admin to make a payment.';
                    header("Location: subscription_expired.php");
                    exit();
                }
            }

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header("Location: admin_page.php");
            } elseif ($user['role'] === 'owner') {
                header("Location: owner_page.php");
            } elseif ($user['role'] === 'manager') {
                header("Location: calendar1.php");
            } else {
                header("Location: login.php");
            }
            exit();
        }
    }

    // Failed login
    $_SESSION['login_error'] = 'Incorrect email or password';
    header("Location: login.php");
    exit();
}
?>