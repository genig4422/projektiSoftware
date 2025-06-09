<?php
session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Fetch notification count for sidebar badge
try {
    $stmt = $conn->prepare("SELECT business_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) {
        throw new Exception("No business found for user");
    }
    $business_id = $user['business_id'];

    $query = "SELECT COUNT(*) as count
              FROM services s
              JOIN cars c ON s.car_id = c.car_id
              WHERE c.business_id = ?
                AND s.due_date >= CURDATE()
                AND s.due_date <= DATE_ADD(CURDATE(), INTERVAL 10 DAY)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $notification_count = $result['count'];
} catch (Exception $e) {
    error_log("Header notification error: " . $e->getMessage());
    $notification_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'Car Rental Management') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <style>
        .navbar { z-index: 1030; }
        .sidebar {
            position: fixed;
            top: 56px;
            left: 0;
            height: calc(100vh - 56px);
            background-color: #343a40;
            color: #fff;
            transition: width 0.3s;
            overflow-x: hidden;
            z-index: 1020;
        }
        .sidebar.collapsed { width: 50px; }
        .sidebar:not(.collapsed) { width: 200px; }
        .sidebar .nav-link {
            color: #fff;
            padding: 10px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }
        .sidebar .nav-link i { min-width: 30px; text-align: center; position: relative; }
        .sidebar .nav-link span { display: inline; margin-left: 10px; }
        .sidebar.collapsed .nav-link span { display: none; }
        .sidebar .toggle-btn {
            background: none;
            border: none;
            color: #fff;
            padding: 10px;
            width: 100%;
            text-align: left;
            cursor: pointer;
        }
        .main-content {
            transition: margin-left 0.3s;
            padding-top: 70px;
        }
        .main-content.collapsed { margin-left: 50px; }
        .main-content:not(.collapsed) { margin-left: 200px; }
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            display: inline-block;
            width: 20px;
            height: 20px;
            background-color: red;
            color: #fff;
            border-radius: 50%;
            text-align: center;
            line-height: 20px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="owner_page.php">Car Rental Management</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="owner_page.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="calendar.php">Calendar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cars.php">Cars</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payments.php">Payments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="sidebar" id="sidebar">
        <button class="toggle-btn" id="toggleSidebar">
            <i class="fas fa-bars"></i>
        </button>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'owner_page.php' ? 'active' : '' ?>" href="owner_page.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'calendar.php' ? 'active' : '' ?>" href="calendar.php">
                    <i class="fas fa-calendar"></i>
                    <span>Calendar</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'add_reservation.php' ? 'active' : '' ?>" href="add_reservation.php?year=<?= date('Y') ?>&month=<?= date('m') ?>">
                    <i class="fas fa-plus"></i>
                    <span>Add Reservation</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'cars.php' ? 'active' : '' ?>" href="cars.php">
                    <i class="fas fa-car"></i>
                    <span>Car List</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'maintenance.php' ? 'active' : '' ?>" href="maintenance.php">
                    <i class="fas fa-wrench"></i>
                    <span>Maintenance</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'services.php' ? 'active' : '' ?>" href="services.php">
                    <i class="fas fa-cogs"></i>
                    <span>Services</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : '' ?>" href="payments.php">
                    <i class="fas fa-money-bill"></i>
                    <span>Payments</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : '' ?>" href="customers.php">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'damage.php' ? 'active' : '' ?>" href="damage.php">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Damage</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'notification.php' ? 'active' : '' ?>" href="notification.php">
                    <i class="fas fa-bell">
                        <?php if ($notification_count > 0): ?>
                            <span class="notification-badge"><?= $notification_count ?></span>
                        <?php endif; ?>
                    </i>
                    <span>Notifications</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'returns.php' ? 'active' : '' ?>" href="returns.php">
                    <i class="fas fa-undo"></i>
                    <span>Returns</span>
                </a>
            </li>
        </ul>
    </div>