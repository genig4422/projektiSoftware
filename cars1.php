<?php
session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

require_once 'header1.php';

// Initialize variables
$errors = [];
$success = '';
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_car'])) {
    try {
        // Get business_id
        $stmt = $conn->prepare("SELECT `business_id` FROM `users` WHERE `email` = ?");
        $stmt->bind_param("s", $_SESSION['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if (!$user) {
            throw new Exception("No business found for this user");
        }
        $business_id = $user['business_id'];

        // Proceed with car addition
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $license_plate = trim($_POST['license_plate'] ?? '');

        // Validate car inputs
        if (!$brand || !$model || !$license_plate) {
            $errors[] = 'Brand, Model, and License Plate are required';
        } elseif (!preg_match("/^[A-Za-z0-9\s\-]{1,50}$/", $license_plate)) {
            $errors[] = 'Invalid license plate format';
        } else {
            // Check for duplicate license plate
            $stmt = $conn->prepare("SELECT `car_id` FROM `cars` WHERE `license_plate` = ? AND `business_id` = ?");
            $stmt->bind_param("si", $license_plate, $business_id);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                $errors[] = 'License plate already exists';
            } else {
                // Insert car
                $stmt = $conn->prepare("INSERT INTO `cars` (`brand`, `model`, `license_plate`, `business_id`) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $brand, $model, $license_plate, $business_id);
                $stmt->execute();
                $success = 'Car added successfully';
            }
        }
    } catch (Exception $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Fetch cars
try {
    // Fetch business_id
    $stmt = $conn->prepare("SELECT `business_id` FROM `users` WHERE `email` = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if (!$user) {
        throw new Exception("No business found for user: " . $_SESSION['email']);
    }
    $business_id = $user['business_id'];

    // Fetch cars
    $stmt = $conn->prepare("SELECT `car_id`, `brand`, `model`, `license_plate` FROM `cars` WHERE `business_id` = ? ORDER BY `brand`, `model`");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $cars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Debug: Log fetched data
    error_log("Cars fetched: " . print_r($cars, true));

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
</head>
<body>
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

            <div class="table-container">
                <div class="d-flex justify-content-between mb-3">
                    <h3>Car List</h3>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCarModal">
                        <i class="fas fa-plus"></i> Add New Car
                    </button>
                </div>
                <?php if (empty($cars)): ?>
                    <div class="alert alert-warning">No cars found for your business.</div>
                <?php else: ?>
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>License Plate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cars as $car): ?>
                                <tr>
                                    <td><?= htmlspecialchars($car['car_id']) ?></td>
                                    <td><?= htmlspecialchars($car['brand']) ?></td>
                                    <td><?= htmlspecialchars($car['model']) ?></td>
                                    <td><?= htmlspecialchars($car['license_plate']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Add Car Modal -->
            <div class="modal fade" id="addCarModal" tabindex="-1" aria-labelledby="addCarModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addCarModalLabel">Add New Car</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="cars1.php">
                            <div class="modal-body">
                                <input type="hidden" name="add_car" value="1">
                                <div class="mb-3">
                                    <label for="brand" class="form-label">Brand <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="brand" name="brand" required maxlength="50">
                                </div>
                                <div class="mb-3">
                                    <label for="model" class="form-label">Model <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="model" name="model" required maxlength="50">
                                </div>
                                <div class="mb-3">
                                    <label for="license_plate" class="form-label">License Plate <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="license_plate" name="license_plate" required maxlength="50">
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Add Car</button>
                            </div>
                        </form>
                    </div>
                </div>
                </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('toggleSidebar').addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        });
    </script>
</body>
</html>