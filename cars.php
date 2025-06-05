<?php
session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Initialize variables
$errors = [];
$success = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'list'; // Default to car list
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
       

        // Get business_id
        $stmt = $conn->prepare("SELECT business_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $_SESSION['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if (!$user) {
            throw new Exception("No business found for this user");
        }
        $business_id = $user['business_id'];

        if (isset($_POST['add_car'])) {
            $brand = $_POST['brand'] ?? null;
            $model = $_POST['model'] ?? null;
            $license_plate = $_POST['license_plate'] ?? null;

            // Validate car inputs
            if (!$brand || !$model || !$license_plate) {
                $errors[] = 'Brand, Model, and License Plate are required';
            } elseif (!preg_match("/^[A-Za-z0-9\s\-]{1,50}$/", $license_plate)) {
                $errors[] = 'Invalid license plate format';
            } else {
                // Check for duplicate license plate
                $stmt = $conn->prepare("SELECT car_id FROM cars WHERE license_plate = ? AND business_id = ?");
                $stmt->bind_param("si", $license_plate, $business_id);
                $stmt->execute();
                if ($stmt->get_result()->fetch_assoc()) {
                    $errors[] = 'License plate already exists';
                } else {
                    // Insert car
                    $stmt = $conn->prepare("INSERT INTO cars (brand, model, license_plate, business_id) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $brand, $model, $license_plate, $business_id);
                    $stmt->execute();
                    $success = 'Car added successfully';
                }
            }
        } elseif (isset($_POST['add_maintenance'])) {
            $car_id = $_POST['car_id'] ?? null;
            $maintenance_type = $_POST['maintenance_type'] ?? null;
            $maintenance_date = $_POST['maintenance_date'] ?? null;
            $cost = $_POST['cost'] ?? null;
            $comments = $_POST['comments'] ?? null;

            // Validate maintenance inputs
            if (!$car_id || !$maintenance_type || !$maintenance_date || $cost === null) {
                $errors[] = 'Car, Maintenance Type, Date, and Cost are required';
            } elseif (!is_numeric($cost) || $cost < 0) {
                $errors[] = 'Cost must be a non-negative number';
            } else {
                // Verify car belongs to this business
                $stmt = $conn->prepare("SELECT car_id FROM cars WHERE car_id = ? AND business_id = ?");
                $stmt->bind_param("ii", $car_id, $business_id);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_assoc()) {
                    $errors[] = 'Invalid car selected';
                } else {
                    // Insert maintenance
                    $stmt = $conn->prepare("INSERT INTO maintenance (car_id, maintenance_type, maintenance_date, cost, comments) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issds", $car_id, $maintenance_type, $maintenance_date, $cost, $comments);
                    $stmt->execute();
                    $success = 'Maintenance record added successfully';
                }
            }
        } elseif (isset($_POST['add_service'])) {
            $car_id = $_POST['car_id'] ?? null;
            $service_type = $_POST['service_type'] ?? null;
            $due_date = $_POST['due_date'] ?? null;
            $cost = $_POST['cost'] ?? null;
            $status = $_POST['status'] ?? null;
            $created_by = $_SESSION['email'];

            // Validate service inputs
            if (!$car_id || !$service_type || !$due_date || $cost === null || !$status) {
                $errors[] = 'Car, Service Type, Due Date, Cost, and Status are required';
            } elseif (!is_numeric($cost) || $cost < 0) {
                $errors[] = 'Cost must be a non-negative number';
            } elseif (!in_array($status, ['Pending', 'Completed', 'Cancelled'])) {
                $errors[] = 'Invalid status selected';
            } else {
                // Verify car belongs to this business
                $stmt = $conn->prepare("SELECT car_id FROM cars WHERE car_id = ? AND business_id = ?");
                $stmt->bind_param("ii", $car_id, $business_id);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_assoc()) {
                    $errors[] = 'Invalid car selected';
                } else {
                    // Insert service
                    $stmt = $conn->prepare("INSERT INTO services (car_id, service_type, cost, due_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isdsss", $car_id, $service_type, $cost, $due_date, $status, $created_by);
                    $stmt->execute();
                    $success = 'Service record added successfully';
                }
            }
        }

    } catch (Exception $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Fetch data for forms
try {

    // Fetch business_id
    $stmt = $conn->prepare("SELECT business_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if (!$user) {
        throw new Exception("No business found for user: " . $_SESSION['email']);
    }
    $business_id = $user['business_id'];

    // Fetch cars
    $stmt = $conn->prepare("SELECT car_id, brand, model, license_plate FROM cars WHERE business_id = ? ORDER BY brand, model");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $cars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch maintenance records
    $stmt = $conn->prepare("SELECT m.maintenance_id, m.car_id, m.maintenance_type, m.maintenance_date, m.cost, m.comments, c.brand, c.model 
                            FROM maintenance m 
                            JOIN cars c ON m.car_id = c.car_id 
                            ORDER BY m.maintenance_date DESC");
    $stmt->execute();
    $maintenance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch service records
    $stmt = $conn->prepare("SELECT s.service_id, s.car_id, s.service_type, s.cost, s.due_date, s.status, s.created_by, c.brand, c.model 
                            FROM services s 
                            JOIN cars c ON s.car_id = c.car_id 
                            ORDER BY s.due_date DESC");
    $stmt->execute();
    $service_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

   
} catch (Exception $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Management</title>
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
        .form-container { max-width: 600px; margin: 0 auto; }
        .select2-container { width: 100% !important; }
        .table-container { max-width: 800px; margin: 0 auto; }
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
                        <a class="nav-link" href="calendar.php">Calendar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="cars.php">Cars</a>
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
                <a class="nav-link" href="calendar.php">
                    <i class="fas fa-calendar"></i>
                    <span>Calendar</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="add_reservation.php?year=<?= $year ?>&month=<?= $month ?>">
                    <i class="fas fa-plus"></i>
                    <span>Add Reservation</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'list' ? 'active' : '' ?>" href="cars.php?tab=list">
                    <i class="fas fa-car"></i>
                    <span>Car List</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'maintenance' ? 'active' : '' ?>" href="cars.php?tab=maintenance">
                    <i class="fas fa-wrench"></i>
                    <span>Add Maintenance</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $active_tab === 'services' ? 'active' : '' ?>" href="cars.php?tab=services">
                    <i class="fas fa-cogs"></i>
                    <span>Add Services</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="payment.php">
                    <i class="fas fa-money-bill"></i>
                    <span>Payment</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="customers.php">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="damage.php">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Damage</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="notification.php">
                    <i class="fas fa-bell"></i>
                    <span>Notification</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="returns.php">
                    <i class="fas fa-undo"></i>
                    <span>Returns</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content" id="mainContent">
        <div class="container my-5">
            <h2 class="text-center mb-4">Car Management</h2>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($active_tab === 'list'): ?>
                <div class="table-container">
                    <h3>Car List</h3>
                    <?php if (empty($cars)): ?>
                        <div class="alert alert-warning">No cars found for your business.</div>
                    <?php else: ?>
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Brand</th>
                                    <th>Model</th>
                                    <th>License Plate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cars as $car): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($car['brand']) ?></td>
                                        <td><?= htmlspecialchars($car['model']) ?></td>
                                        <td><?= htmlspecialchars($car['license_plate']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <h4 class="mt-4">Add New Car</h4>
                    <form method="POST" action="cars.php?tab=list" class="form-container">
                        <input type="hidden" name="add_car" value="1">
                        <div class="mb-3">
                            <label for="brand" class="form-label">Brand</label>
                            <input type="text" class="form-control" id="brand" name="brand" required>
                        </div>
                        <div class="mb-3">
                            <label for="model" class="form-label">Model</label>
                            <input type="text" class="form-control" id="model" name="model" required>
                        </div>
                        <div class="mb-3">
                            <label for="license_plate" class="form-label">License Plate</label>
                            <input type="text" class="form-control" id="license_plate" name="license_plate" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Car</button>
                    </form>
                </div>
            <?php elseif ($active_tab === 'maintenance'): ?>
                <div class="form-container">
                    <h3>Add Maintenance</h3>
                    <?php if (empty($cars)): ?>
                        <div class="alert alert-warning">No cars found. Please add a car first.</div>
                    <?php else: ?>
                        <form method="POST" action="cars.php?tab=maintenance">
                            <input type="hidden" name="add_maintenance" value="1">
                            <div class="mb-3">
                                <label for="car_id" class="form-label">Car</label>
                                <select class="form-select" id="car_id" name="car_id" required>
                                    <option value="">Select a car</option>
                                    <?php foreach ($cars as $car): ?>
                                        <option value="<?= $car['car_id'] ?>"><?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['license_plate'] . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="maintenance_type" class="form-label">Maintenance Type</label>
                                <input type="text" class="form-control" id="maintenance_type" name="maintenance_type" required>
                            </div>
                            <div class="mb-3">
                                <label for="maintenance_date" class="form-label">Maintenance Date</label>
                                <input type="date" class="form-control" id="maintenance_date" name="maintenance_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="cost" class="form-label">Cost (€)</label>
                                <input type="number" step="0.01" class="form-control" id="cost" name="cost" value="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="comments" class="form-label">Comments</label>
                                <textarea class="form-control" id="comments" name="comments" rows="4"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Maintenance</button>
                        </form>
                        <?php if (!empty($maintenance_records)): ?>
                            <h4 class="mt-4">Maintenance Records</h4>
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Car</th>
                                        <th>Maintenance Type</th>
                                        <th>Date</th>
                                        <th>Cost (€)</th>
                                        <th>Comments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maintenance_records as $record): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($record['brand'] . ' ' . $record['model']) ?></td>
                                            <td><?= htmlspecialchars($record['maintenance_type']) ?></td>
                                            <td><?= htmlspecialchars($record['maintenance_date']) ?></td>
                                            <td><?= number_format($record['cost'], 2) ?></td>
                                            <td><?= htmlspecialchars($record['comments'] ?? 'None') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php elseif ($active_tab === 'services'): ?>
                <div class="form-container">
                    <h3>Add Services</h3>
                    <?php if (empty($cars)): ?>
                        <div class="alert alert-warning">No cars found. Please add a car first.</div>
                    <?php else: ?>
                        <form method="POST" action="cars.php?tab=services">
                            <input type="hidden" name="add_service" value="1">
                            <div class="mb-3">
                                <label for="car_id" class="form-label">Car</label>
                                <select class="form-select" id="car_id" name="car_id" required>
                                    <option value="">Select a car</option>
                                    <?php foreach ($cars as $car): ?>
                                        <option value="<?= $car['car_id'] ?>"><?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['license_plate'] . ')') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="service_type" class="form-label">Service Type</label>
                                <input type="text" class="form-control" id="service_type" name="service_type" required>
                            </div>
                            <div class="mb-3">
                                <label for="due_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="cost" class="form-label">Cost (€)</label>
                                <input type="number" step="0.01" class="form-control" id="cost" name="cost" value="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">Select status</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Service</button>
                        </form>
                        <?php if (!empty($service_records)): ?>
                            <h4 class="mt-4">Service Records</h4>
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Car</th>
                                        <th>Service Type</th>
                                        <th>Due Date</th>
                                        <th>Cost (€)</th>
                                        <th>Status</th>
                                        <th>Created By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($service_records as $record): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($record['brand'] . ' ' . $record['model']) ?></td>
                                            <td><?= htmlspecialchars($record['service_type']) ?></td>
                                            <td><?= htmlspecialchars($record['due_date']) ?></td>
                                            <td><?= number_format($record['cost'], 2) ?></td>
                                            <td><?= htmlspecialchars($record['status']) ?></td>
                                            <td><?= htmlspecialchars($record['created_by']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('toggleSidebar').addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        });

        // Initialize Select2 for car dropdowns
        $('#car_id, #status').select2({
            placeholder: 'Select an option',
            allowClear: true
        });
    </script>
</body>
</html>