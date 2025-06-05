<?php 

session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php");
    exit();
}
require_once 'config.php';
$email = $_SESSION['email'];

// Fetch user info
$user_sql = "SELECT * FROM users WHERE email = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

$business_id = $user['business_id'];

// Fetch business info
$business_sql = "SELECT * FROM businesses WHERE business_id = ?";
$business_stmt = $conn->prepare($business_sql);
$business_stmt->bind_param("i", $business_id);
$business_stmt->execute();
$business = $business_stmt->get_result()->fetch_assoc();

// Fetch subscription info
$sub_sql = "SELECT * FROM subscriptions WHERE business_id = ? AND status = 'active' LIMIT 1";
$sub_stmt = $conn->prepare($sub_sql);
$sub_stmt->bind_param("i", $business_id);
$sub_stmt->execute();
$subscription = $sub_stmt->get_result()->fetch_assoc();

// Fetch users of this business
$users_sql = "SELECT name, email, phone, role FROM users WHERE business_id = ?";
$users_stmt = $conn->prepare($users_sql);
$users_stmt->bind_param("i", $business_id);
$users_stmt->execute();
$users = $users_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard</title>
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
        .sidebar .nav-link i { min-width: 30px; text-align: center; }
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
        #tableContainer { overflow-x: auto; }
        #scheduleTable { width: auto; table-layout: auto; }
        #scheduleTable th, #scheduleTable td {
            font-size: 0.85rem;
            padding: 4px;
            text-align: center;
            min-width: 40px;
        }
        #scheduleTable th:first-child, #scheduleTable td:first-child {
            min-width: 160px;
            text-align: left;
            position: sticky;
            left: 0;
            background-color: #fff;
            z-index: 1;
            box-shadow: 2px 0 2px rgba(0, 0, 0, 0.1);
        }
        .reserved {
            background-color: #0d6efd !important;
            color: #fff;
            cursor: pointer;
        }
        #addReservation { margin-right: 10px; }
        .tooltip-inner {
            max-width: 400px;
            text-align: left;
            white-space: pre-wrap;
            font-size: 1rem;
            padding: 10px;
        }
        .select2-container { width: 100% !important; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="owner_page.php">Car Rental</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="owner_page.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="calendar.php">Calendar</a>
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
                <a class="nav-link" href="owner_page.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="calendar.php">
                    <i class="fas fa-calendar"></i>
                    <span>Calendar</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#profile">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </li>
             <li class="nav-item">
                <a class="nav-link" href="#profile">
                    <i class="fas fa-user"></i>
                    <span>Cars</span>
                </a>
            </li>
        </ul>
    </div>

        <!-- Main content -->
    <div class="main-content" id="mainContent">
        <div class="container mt-4">
            <!-- Welcome -->
            <div class="mb-4">
                <h2>Welcome, <?= htmlspecialchars($user['name']) ?></h2>
            </div>

            <!-- Business & Subscription Info -->
            <div class="d-flex flex-wrap gap-4 mb-4">
                <div class="card flex-fill" style="min-width: 300px;">
                    <div class="card-header bg-primary text-white">Business Information</div>
                    <div class="card-body">
                        <p><strong>Name:</strong> <?= htmlspecialchars($business['business_name']) ?></p>
                        <p><strong>Address:</strong> <?= htmlspecialchars($business['address']) ?></p>
                        <p><strong>Contact:</strong> <?= htmlspecialchars($business['contact_info']) ?></p>
                    </div>
                </div>

                <div class="card flex-fill" style="min-width: 300px;">
                    <div class="card-header bg-success text-white">Subscription Details</div>
                    <div class="card-body">
                        <p><strong>Allowed Cars:</strong> <?= $subscription['allowed_cars'] ?></p>
                        <p><strong>Start Date:</strong> <?= $subscription['start_date'] ?></p>
                        <p><strong>End Date:</strong> <?= $subscription['end_date'] ?></p>
                        <p><strong>Amount:</strong> &euro;<?= number_format($subscription['amount'], 2) ?></p>
                        <p><strong>Status:</strong> <?= ucfirst($subscription['status']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Add New User Button -->
            <div class="mb-4 text-end">
                <a href="add_user.php" class="btn btn-primary">Add Owner/Manager</a>
            </div>

            <!-- Users Table -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">Users</div>
                <div class="card-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td><?= htmlspecialchars($row['phone']) ?></td>
                                    <td><?= htmlspecialchars($row['role']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Financial Reports -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">Financial Reports</div>
                <div class="card-body">
                    <p>Total Revenue (This Year): <strong>&euro;<?= number_format(12345.67, 2) ?></strong></p>
                    <p>Total Costs (Maintenance + Services): <strong>&euro;<?= number_format(2345.67, 2) ?></strong></p>
                    <p>Total Profit: <strong>&euro;<?= number_format(10000.00, 2) ?></strong></p>
                </div>
            </div>

            <!-- Most Reserved Cars -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">Top 5 Most Reserved Cars</div>
                <div class="card-body">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Car</th>
                                <th>Reservations</th>
                                <th>Revenue (€)</th>
                                <th>Costs (€)</th>
                                <th>Total (€)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Placeholder rows - dynamically populate with data -->
                            <tr>
                                <td>Car A</td>
                                <td>42</td>
                                <td>€4200</td>
                                <td>€800</td>
                                <td>€3400</td>
                            </tr>
                            <!-- Repeat rows as needed -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Most Profitable Cars -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">Top 5 Most Profitable Cars</div>
                <div class="card-body">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Car</th>
                                <th>Reservations</th>
                                <th>Revenue (€)</th>
                                <th>Costs (€)</th>
                                <th>Profit (€)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Placeholder rows - dynamically populate with data -->
                            <tr>
                                <td>Car B</td>
                                <td>37</td>
                                <td>€5000</td>
                                <td>€1000</td>
                                <td>€4000</td>
                            </tr>
                            <!-- Repeat rows as needed -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div> <!-- /.container -->

        </div>
    </div>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>