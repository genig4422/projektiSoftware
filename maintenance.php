<?php
session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php");
    exit();
}

require_once 'header.php';

// Initialize variables
$errors = [];
$success = '';
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$car_id = isset($_GET['car_id']) ? (int)$_GET['car_id'] : null;
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_maintenance'])) {
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

        $car_id = $_POST['car_id'] ?? null;
        $maintenance_type = trim($_POST['maintenance_type'] ?? '');
        $maintenance_date = $_POST['maintenance_date'] ?? null;
        $cost = $_POST['cost'] ?? null;
        $comments = trim($_POST['comments'] ?? '');

        // Validate maintenance inputs
        if (!$car_id || !$maintenance_type || !$maintenance_date || $cost === null) {
            $errors[] = 'Car, Maintenance Type, Date, and Cost are required';
        } elseif (!is_numeric($cost) || $cost < 0) {
            $errors[] = 'Cost must be a non-negative number';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $maintenance_date)) {
            $errors[] = 'Invalid date format';
        } else {
            // Verify car belongs to this business
            $stmt = $conn->prepare("SELECT `car_id` FROM `cars` WHERE `car_id` = ? AND `business_id` = ?");
            $stmt->bind_param("ii", $car_id, $business_id);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                $errors[] = 'Invalid car selected';
            } else {
                // Insert maintenance
                $stmt = $conn->prepare("INSERT INTO `maintenance` (`car_id`, `maintenance_type`, `maintenance_date`, `cost`, `comments`) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issds", $car_id, $maintenance_type, $maintenance_date, $cost, $comments);
                $stmt->execute();
                $success = 'Maintenance record added successfully';
            }
        }
    } catch (Exception $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Fetch data
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

    // Fetch maintenance records with filters
    $sql = "SELECT `m`.*, `c`.`brand`, `c`.`model`
            FROM `maintenance` `m`
            JOIN `cars` `c` ON `m`.`car_id` = `c`.`car_id`
            WHERE `c`.`business_id` = ?";
    $params = [$business_id];
    $types = "i";

    if ($car_id) {
        $sql .= " AND `m`.`car_id` = ?";
        $params[] = $car_id;
        $types .= "i";
    }
    if ($start_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        $sql .= " AND `m`.`maintenance_date` >= ?";
        $params[] = $start_date;
        $types .= "s";
    }
    if ($end_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $sql .= " AND `m`.`maintenance_date` <= ?";
        $params[] = $end_date;
        $types .= "s";
    }
    $sql .= " ORDER BY `m`.`maintenance_date` DESC";

    error_log("Executing maintenance query: " . $sql . " with params: " . print_r($params, true));
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $maintenance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Debug: Log fetched data
    error_log("Cars fetched: " . print_r($cars, true));
    error_log("Maintenance records fetched: " . print_r($maintenance_records, true));

} catch (Exception $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Management</title>
 
</head>
<body>


    <div class="main-content" id="mainContent">
        <div class="container my-5">
            <h2 class="text-center mb-4">Maintenance Management</h2>
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
                    <h3>Maintenance Records</h3>
                    <?php if (!empty($cars)): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                            <i class="fas fa-plus"></i> Add Maintenance
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Search Bar -->
                <div class="search-bar">
                    <form method="GET" action="maintenance.php" class="row g-3">
                        <div class="col-md-4">
                            <label for="search_car_id" class="form-label">Car</label>
                            <select class="form-select" id="search_car_id" name="car_id">
                                <option value="">All Cars</option>
                                <?php foreach ($cars as $car): ?>
                                    <option value="<?= $car['car_id'] ?>" <?= $car_id == $car['car_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['license_plate'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($cars)): ?>
                    <div class="alert alert-warning">No cars found. Please add a car first.</div>
                <?php elseif (empty($maintenance_records)): ?>
                    <div class="alert alert-warning">No maintenance records found.</div>
                <?php else: ?>
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
            </div>

            <!-- Add Maintenance Modal -->
            <?php if (!empty($cars)): ?>
                <div class="modal fade" id="addMaintenanceModal" tabindex="-1" aria-labelledby="addMaintenanceModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addMaintenanceModalLabel">Add Maintenance</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="maintenance.php<?php if ($car_id) echo '?car_id=' . htmlspecialchars($car_id); ?>">
                                <div class="modal-body">
                                    <input type="hidden" name="add_maintenance" value="1">
                                    <div class="mb-3">
                                        <label for="car_id" class="form-label">Car <span class="text-danger">*</span></label>
                                        <select class="form-select" id="car_id_modal" name="car_id" required>
                                            <option value="">Select a car</option>
                                            <?php foreach ($cars as $car): ?>
                                                <option value="<?= $car['car_id'] ?>" <?= $car_id == $car['car_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['license_plate'] . ')') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="maintenance_type" class="form-label">Maintenance Type <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="maintenance_type" name="maintenance_type" required maxlength="100">
                                    </div>
                                    <div class="mb-3">
                                        <label for="maintenance_date" class="form-label">Maintenance Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="maintenance_date" name="maintenance_date" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="cost" class="form-label">Cost (€) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" class="form-control" id="cost" name="cost" value="0" required min="0">
                                    </div>
                                    <div class="mb-3">
                                        <label for="comments" class="form-label">Comments</label>
                                        <textarea class="form-control" id="comments" name="comments" rows="4" maxlength="500"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Add Maintenance</button>
                                </div>
                            </form>
                        </div>
                    </div>
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

        // Initialize Select2 for search bar dropdown
        $('#search_car_id').select2({
            placeholder: 'Select a car',
            allowClear: true
        });

        // Initialize Select2 for modal dropdown when modal is shown
        $('#addMaintenanceModal').on('shown.bs.modal', function () {
            $('#car_id_modal').select2({
                placeholder: 'Select a car',
                allowClear: true,
                dropdownParent: $('#addMaintenanceModal')
            });
        });

        // Destroy Select2 when modal is hidden to prevent memory leaks
        $('#addMaintenanceModal').on('hidden.bs.modal', function () {
            $('#car_id_modal').select2('destroy');
        });
    </script>
</body>
</html>