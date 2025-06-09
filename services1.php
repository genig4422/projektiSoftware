<?php
session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

require_once 'header1.php'; // This should define $conn (MySQLi connection)

$errors = [];
$success = '';
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$car_id = isset($_GET['car_id']) ? (int)$_GET['car_id'] : null;

try {
    // Get business_id and user_id
    $stmt = $conn->prepare("SELECT `business_id`, `user_id` FROM `users` WHERE `email` = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if (!$user) {
        throw new Exception("User not found");
    }
    $business_id = $user['business_id'];
    $user_id = $user['user_id'];

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_service'])) {
            $car_id = $_POST['car_id'] ?? null;
            $service_type = trim($_POST['service_type'] ?? '');
            $due_date = $_POST['due_date'] ?? null;
            $cost = $_POST['cost'] ?? null;

            // Validate
            if (!$car_id || !$service_type || !$due_date || $cost === null) {
                $errors[] = 'Car, Service Type, Due Date, and Cost are required';
            } elseif (!is_numeric($cost) || $cost < 0) {
                $errors[] = 'Cost must be a non-negative number';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
                $errors[] = 'Invalid due date format';
            } else {
                // Check car ownership
                $stmt = $conn->prepare("SELECT `car_id` FROM `cars` WHERE `car_id` = ? AND `business_id` = ?");
                $stmt->bind_param("ii", $car_id, $business_id);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_assoc()) {
                    $errors[] = 'Invalid car selected';
                } else {
                    // Insert with created_by
                    $stmt = $conn->prepare("INSERT INTO `services` (`car_id`, `service_type`, `cost`, `due_date`, `created_by`) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("isdsi", $car_id, $service_type, $cost, $due_date, $user_id);
                    $stmt->execute();
                    if ($stmt->affected_rows > 0) {
                        $success = 'Service record added successfully';
                    } else {
                        $errors[] = 'Failed to add service';
                    }
                }
            }
        }

        // Edit service
        if (isset($_POST['edit_service'])) {
            $service_id = $_POST['service_id'] ?? null;
            $car_id = $_POST['car_id'] ?? null;
            $service_type = trim($_POST['service_type'] ?? '');
            $due_date = $_POST['due_date'] ?? null;
            $cost = $_POST['cost'] ?? null;

            if (!$service_id || !$car_id || !$service_type || !$due_date || $cost === null) {
                $errors[] = 'All fields are required';
            } elseif (!is_numeric($cost) || $cost < 0) {
                $errors[] = 'Cost must be non-negative';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
                $errors[] = 'Invalid date format';
            } else {
                $stmt = $conn->prepare("SELECT `s`.`service_id` FROM `services` `s` JOIN `cars` `c` ON `s`.`car_id` = `c`.`car_id` WHERE `s`.`service_id` = ? AND `c`.`business_id` = ?");
                $stmt->bind_param("ii", $service_id, $business_id);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_assoc()) {
                    $errors[] = 'Invalid service or car selected';
                } else {
                    $stmt = $conn->prepare("UPDATE `services` SET `car_id` = ?, `service_type` = ?, `cost` = ?, `due_date` = ? WHERE `service_id` = ?");
                    $stmt->bind_param("isdsi", $car_id, $service_type, $cost, $due_date, $service_id);
                    $stmt->execute();
                    if ($stmt->affected_rows >= 0) {
                        $success = 'Service record updated successfully';
                    } else {
                        $errors[] = 'Failed to update service';
                    }
                }
            }
        }

        // Delete service
        if (isset($_POST['delete_service'])) {
            $service_id = $_POST['service_id'] ?? null;

            if (!$service_id) {
                $errors[] = 'Service ID is required';
            } else {
                // Check if service exists and belongs to the business
                $stmt = $conn->prepare("SELECT `s`.`service_id` FROM `services` `s` JOIN `cars` `c` ON `s`.`car_id` = `c`.`car_id` WHERE `s`.`service_id` = ? AND `c`.`business_id` = ?");
                $stmt->bind_param("ii", $service_id, $business_id);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_assoc()) {
                    $errors[] = 'Invalid service selected';
                } else {
                    // Start transaction to ensure data integrity
                    $conn->begin_transaction();
                    try {
                        // Delete related notifications
                        $stmt = $conn->prepare("DELETE FROM `notifications` WHERE `service_id` = ?");
                        $stmt->bind_param("i", $service_id);
                        $stmt->execute();

                        // Delete the service
                        $stmt = $conn->prepare("DELETE FROM `services` WHERE `service_id` = ?");
                        $stmt->bind_param("i", $service_id);
                        $stmt->execute();

                        if ($stmt->affected_rows > 0) {
                            $conn->commit();
                            $success = 'Service record deleted successfully';
                        } else {
                            $conn->rollback();
                            $errors[] = 'Failed to delete service';
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        $errors[] = 'Failed to delete service: ' . $e->getMessage();
                    }
                }
            }
        }
    }

    // Fetch cars
    $stmt = $conn->prepare("SELECT `car_id`, `brand`, `model`, `license_plate` FROM `cars` WHERE `business_id` = ? ORDER BY `brand`, `model`");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $cars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch service records
    $sql = "SELECT `s`.*, `c`.`brand`, `c`.`model`
            FROM `services` `s`
            JOIN `cars` `c` ON `s`.`car_id` = `c`.`car_id`
            WHERE `c`.`business_id` = ?";
    $params = [$business_id];
    $types = "i";
    if ($car_id) {
        $sql .= " AND `s`.`car_id` = ?";
        $params[] = $car_id;
        $types .= "i";
    }
    $sql .= " ORDER BY `s`.`due_date` DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
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
    <title>Service Management</title>
</head>
<body>

    <div class="main-content" id="mainContent">
        <div class="container my-5">
            <h2 class="text-center mb-4">Service Management</h2>
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
                    <h3>Service Records</h3>
                    <?php if (!empty($cars)): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                            <i class="fas fa-plus"></i> Add Service
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Search Bar -->
                <div class="search-bar">
                    <form method="GET" action="services1.php" class="row g-3">
                        <div class="col-md-6">
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
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </form>
                </div>

                <?php if (empty($cars)): ?>
                    <div class="alert alert-warning">No cars found. Please add a car first.</div>
                <?php elseif (empty($service_records)): ?>
                    <div class="alert alert-warning">No service records found.</div>
                <?php else: ?>
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Car</th>
                                <th>Service Type</th>
                                <th>Due Date</th>
                                <th>Cost (€)</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $today = new DateTime();
                            $warning_threshold = (new DateTime())->modify('+7 days');
                            foreach ($service_records as $record):
                                $due_date = new DateTime($record['due_date']);
                                $row_class = '';
                                if ($due_date < $today) {
                                    $row_class = 'table-danger';
                                } elseif ($due_date <= $warning_threshold) {
                                    $row_class = 'table-warning';
                                }
                            ?>
                                <tr class="<?= $row_class ?>">
                                    <td><?= htmlspecialchars($record['brand'] . ' ' . $record['model']) ?></td>
                                    <td><?= htmlspecialchars($record['service_type']) ?></td>
                                    <td><?= htmlspecialchars($record['due_date']) ?></td>
                                    <td><?= number_format($record['cost'], 2) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editServiceModal"
                                            data-service-id="<?= $record['service_id'] ?>"
                                            data-car-id="<?= $record['car_id'] ?>"
                                            data-service-type="<?= htmlspecialchars($record['service_type']) ?>"
                                            data-due-date="<?= $record['due_date'] ?>"
                                            data-cost="<?= $record['cost'] ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteServiceModal"
                                            data-service-id="<?= $record['service_id'] ?>"
                                            data-service-type="<?= htmlspecialchars($record['service_type']) ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Add Service Modal -->
            <?php if (!empty($cars)): ?>
                <div class="modal fade" id="addServiceModal" tabindex="-1" aria-labelledby="addServiceModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addServiceModalLabel">Add Service</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="services1.php<?php if ($car_id) echo '?car_id=' . htmlspecialchars($car_id); ?>">
                                <div class="modal-body">
                                    <input type="hidden" name="add_service" value="1">
                                    <div class="mb-3">
                                        <label for="car_id_add" class="form-label">Car <span class="text-danger">*</span></label>
                                        <select class="form-select" id="car_id_add" name="car_id" required>
                                            <option value="">Select a car</option>
                                            <?php foreach ($cars as $car): ?>
                                                <option value="<?= $car['car_id'] ?>" <?= $car_id == $car['car_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['license_plate'] . ')') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="service_type_add" class="form-label">Service Type <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="service_type_add" name="service_type" required maxlength="100">
                                    </div>
                                    <div class="mb-3">
                                        <label for="due_date_add" class="form-label">Due Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="due_date_add" name="due_date" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="cost_add" class="form-label">Cost (€) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" class="form-control" id="cost_add" name="cost" value="0" required min="0">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Add Service</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Service Modal -->
                <div class="modal fade" id="editServiceModal" tabindex="-1" aria-labelledby="editServiceModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editServiceModalLabel">Edit Service</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="services1.php<?php if ($car_id) echo '?car_id=' . htmlspecialchars($car_id); ?>">
                                <div class="modal-body">
                                    <input type="hidden" name="edit_service" value="1">
                                    <input type="hidden" name="service_id" id="edit_service_id">
                                    <div class="mb-3">
                                        <label for="car_id_edit" class="form-label">Car <span class="text-danger">*</span></label>
                                        <select class="form-select" id="car_id_edit" name="car_id" required>
                                            <option value="">Select a car</option>
                                            <?php foreach ($cars as $car): ?>
                                                <option value="<?= $car['car_id'] ?>">
                                                    <?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['license_plate'] . ')') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="service_type_edit" class="form-label">Service Type <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="service_type_edit" name="service_type" required maxlength="100">
                                    </div>
                                    <div class="mb-3">
                                        <label for="due_date_edit" class="form-label">Due Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="due_date_edit" name="due_date" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="cost_edit" class="form-label">Cost (€) <span class="text-danger">*</span></label>
                                        <input type="number" step="0.01" class="form-control" id="cost_edit" name="cost" required min="0">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Update Service</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Delete Service Modal -->
                <div class="modal fade" id="deleteServiceModal" tabindex="-1" aria-labelledby="deleteServiceModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteServiceModalLabel">Delete Service</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="services1.php<?php if ($car_id) echo '?car_id=' . htmlspecialchars($car_id); ?>">
                                <div class="modal-body">
                                    <input type="hidden" name="delete_service" value="1">
                                    <input type="hidden" name="service_id" id="delete_service_id">
                                    <p>Are you sure you want to delete the service "<span id="delete_service_type"></span>"?</p>
                                    <p class="text-danger">This action will also remove related notifications and cannot be undone.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Delete</button>
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

        // Initialize Select2 for add modal dropdown
        $('#addServiceModal').on('shown.bs.modal', function () {
            $('#car_id_add').select2({
                placeholder: 'Select a car',
                allowClear: true,
                dropdownParent: $('#addServiceModal')
            });
        });
        $('#addServiceModal').on('hidden.bs.modal', function () {
            $('#car_id_add').select2('destroy');
        });

        // Initialize Select2 for edit modal dropdown and populate fields
        $('#editServiceModal').on('shown.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const serviceId = button.data('service-id');
            const carId = button.data('car-id');
            const serviceType = button.data('service-type');
            const dueDate = button.data('due-date');
            const cost = button.data('cost');

            const modal = $(this);
            modal.find('#edit_service_id').val(serviceId);
            modal.find('#service_type_edit').val(serviceType);
            modal.find('#due_date_edit').val(dueDate);
            modal.find('#cost_edit').val(cost);

            $('#car_id_edit').select2({
                placeholder: 'Select a car',
                allowClear: true,
                dropdownParent: $('#editServiceModal')
            }).val(carId).trigger('change');
        });
        $('#editServiceModal').on('hidden.bs.modal', function () {
            $('#car_id_edit').select2('destroy');
        });

        // Populate delete modal fields
        $('#deleteServiceModal').on('shown.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const serviceId = button.data('service-id');
            const serviceType = button.data('service-type');

            const modal = $(this);
            modal.find('#delete_service_id').val(serviceId);
            modal.find('#delete_service_type').text(serviceType);
        });
    </script>
</body>
</html>