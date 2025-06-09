<?php
session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php");
    exit();
}

require_once 'header.php';

// Initialize variables
$errors = [];
$success_message = '';
$car_id = isset($_GET['car_id']) && is_numeric($_GET['car_id']) ? (int)$_GET['car_id'] : null;
$reservation_id = isset($_GET['reservation_id']) && is_numeric($_GET['reservation_id']) ? (int)$_GET['reservation_id'] : null;
$customer_name = isset($_GET['customer_name']) ? urldecode($_GET['customer_name']) : '';
$description = '';
$repair_cost = '';
$maintenance_id = '';
$reported_at = date('Y-m-d'); // Default to today (2025-06-08)

// Fetch business_id and car details
try {
    $stmt = $conn->prepare("SELECT business_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if (!$user) {
        throw new Exception("No business found for user: " . $_SESSION['email']);
    }
    $business_id = $user['business_id'];

    // Fetch car details if car_id is provided
    $car = null;
    if ($car_id) {
        $stmt = $conn->prepare("SELECT car_id, brand, model, license_plate FROM cars WHERE car_id = ? AND business_id = ?");
        $stmt->bind_param("ii", $car_id, $business_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $car = $result->fetch_assoc();
        if (!$car) {
            $errors[] = "Invalid car ID or car does not belong to your business.";
        }
    }

    // Fetch maintenance IDs for dropdown
    $maintenances = [];
    if ($car_id) {
        $stmt = $conn->prepare("SELECT m.maintenance_id, m.maintenance_type, m.maintenance_date 
                                FROM maintenance m
                                JOIN cars c ON m.car_id = c.car_id
                                WHERE m.car_id = ? AND c.business_id = ? 
                                ORDER BY m.maintenance_date DESC");
        $stmt->bind_param("ii", $car_id, $business_id);
        $stmt->execute();
        $maintenances = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $description = trim($_POST['description'] ?? '');
        $repair_cost = trim($_POST['repair_cost'] ?? '');
        $maintenance_id = !empty($_POST['maintenance_id']) && is_numeric($_POST['maintenance_id']) ? (int)$_POST['maintenance_id'] : null;
        $reported_at = trim($_POST['reported_at'] ?? '');

        // Validate inputs
        if (empty($description)) {
            $errors[] = "Description is required.";
        }
        if (empty($repair_cost) || !is_numeric($repair_cost) || $repair_cost < 0) {
            $errors[] = "Valid repair cost is required.";
        }
        if (empty($reported_at) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reported_at)) {
            $errors[] = "Valid reported date is required.";
        }
        if ($maintenance_id && !in_array($maintenance_id, array_column($maintenances, 'maintenance_id'))) {
            $errors[] = "Invalid maintenance ID.";
        }
        if (!$car_id || !$car) {
            $errors[] = "Valid car ID is required.";
        }

        // Insert damage report if no errors
        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO damage_reports (car_id, maintenance_id, description, repair_cost, reported_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisds", $car_id, $maintenance_id, $description, $repair_cost, $reported_at);
            if ($stmt->execute()) {
                $success_message = "Damage report added successfully.";
                // Reset form fields
                $description = '';
                $repair_cost = '';
                $maintenance_id = '';
                $reported_at = date('Y-m-d');
            } else {
                $errors[] = "Failed to add damage report: " . $conn->error;
            }
        }
    }

} catch (Exception $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Damage Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

</head>
<body>

    <div class="main-content" id="mainContent">
        <div class="container my-5">
            <h2 class="text-center mb-4">Add Damage Report</h2>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" action="add_damage.php?reservation_id=<?= htmlspecialchars($reservation_id ?? '') ?>&car_id=<?= htmlspecialchars($car_id ?? '') ?>&customer_name=<?= urlencode($customer_name) ?>" class="row g-3">
                    <div class="col-md-12">
                        <label for="car_info" class="form-label">Car</label>
                        <input type="text" class="form-control" id="car_info" value="<?= $car ? htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['license_plate'] . ')') : 'N/A' ?>" readonly>
                        <input type="hidden" name="car_id" value="<?= htmlspecialchars($car_id ?? '') ?>">
                    </div>
                    <div class="col-md-12">
                        <label for="customer_name" class="form-label">Customer</label>
                        <input type="text" class="form-control" id="customer_name" value="<?= htmlspecialchars($customer_name) ?>" readonly>
                    </div>
                    <div class="col-md-12">
                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="4" required><?= htmlspecialchars($description) ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="repair_cost" class="form-label">Repair Cost (€) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="repair_cost" name="repair_cost" step="0.01" min="0" value="<?= htmlspecialchars($repair_cost) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="reported_at" class="form-label">Reported Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="reported_at" name="reported_at" value="<?= htmlspecialchars($reported_at) ?>" required>
                    </div>
                  
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary w-100">Submit Damage Report</button>
                    </div>
                </form>
                <div class="text-center mt-3">
                    <a href="returns.php" class="btn btn-secondary">Back to Returns</a>
                </div>
            </div>
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

        // Initialize Select2 for maintenance dropdown
        $('#maintenance_id').select2({
            placeholder: 'Select maintenance',
            allowClear: true
        });
    </script>
</body>
</html>