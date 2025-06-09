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
$reservation_id = isset($_GET['reservation_id']) ? (int)$_GET['reservation_id'] : null;
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$daysInMonth = (new DateTime("$year-$month-01"))->format('t');
$reservation = null;
$cars = [];
$customers = [];
$form_data = $_POST; // Preserve form data on error

// Fetch reservation data
if ($reservation_id) {
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

        // Fetch reservation details
        $stmt = $conn->prepare("
            SELECT r.reservation_id, r.car_id, r.customer_id, r.start_date, 
                   TIME_FORMAT(r.start_time, '%H:%i') AS start_time, 
                   TIME_FORMAT(r.end_time, '%H:%i') AS end_time, 
                   r.total_cost, r.comments,
                   cu.name AS customer_name, cu.phone, cu.license_number, p.status
            FROM reservations r
            JOIN customers cu ON r.customer_id = cu.customer_id
            JOIN payments p ON r.reservation_id = p.reservation_id
            JOIN cars c ON r.car_id = c.car_id
            WHERE r.reservation_id = ? AND c.business_id = ? AND p.status = 'pending'
        ");
        $stmt->bind_param("ii", $reservation_id, $business_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $reservation = $result->fetch_assoc();
        if (!$reservation) {
            $errors[] = 'Reservation not found, not pending, or unauthorized access';
        }
    } catch (Exception $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
} else {
    $errors[] = 'No reservation ID provided';
}

// Fetch cars and customers
try {
    $stmt = $conn->prepare("SELECT business_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $business_id = $user['business_id'];

    // Fetch cars
    $stmt = $conn->prepare("SELECT car_id, brand, model, license_plate FROM cars WHERE business_id = ?");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $cars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch customers
    $stmt = $conn->prepare("SELECT customer_id, name, phone, license_number FROM customers WHERE business_id = ? ORDER BY name");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_reservation']) && $reservation_id) {
    try {
        $car_id = $_POST['car_id'] ?? null;
        $customer_id = $_POST['customer_id'] ?? null;
        $customer_name = $_POST['customer_name'] ?? '';
        $customer_phone = $_POST['phone'] ?? '';
        $customer_license = $_POST['license_number'] ?? '';
        $start_date = $_POST['start_date'] ?? null;
        $start_time = $_POST['start_time'] ?? '';
        $end_date = $_POST['end_date'] ?? null;
        $end_time = $_POST['end_time'] ?? '';
        $total_cost = $_POST['total_cost'] ?? null;
        $comments = $_POST['comments'] ?? '';
        $customer_type = $_POST['customer_type'] ?? '';

        // Validate required fields
        if (!$car_id || !$start_date || !$end_date || $total_cost === null) {
            $errors[] = 'Car, Start Date, End Date, and Total Cost are required';
        } elseif ($customer_type === 'existing' && !$customer_id) {
            $errors[] = 'Please select an existing customer';
        } elseif ($customer_type === 'new' && (!$customer_name || !$customer_phone || !$customer_license)) {
            $errors[] = 'Name, Phone, and License Number are required for a new customer';
        } else {
            // Validate dates
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            if ($start > $end) {
                $errors[] = 'Start date must be before or equal to end date';
            }

            // Validate times (if provided, allow empty)
            if ($start_time && !preg_match("/^([01]\d|2[0-3]):[0-5]\d$/", $start_time)) {
                $errors[] = 'Invalid start time format (use HH:MM)';
            }
            if ($end_time && !preg_match("/^([01]\d|2[0-3]):[0-5]\d$/", $end_time)) {
                $errors[] = 'Invalid end time format (use HH:MM)';
            }

            // Validate total_cost
            if (!is_numeric($total_cost) || $total_cost < 0) {
                $errors[] = 'Total cost must be a non-negative number';
            }

            // Validate phone (basic format)
            if ($customer_phone && !preg_match("/^[\+]?[\d\s\-]{7,20}$/", $customer_phone)) {
                $errors[] = 'Invalid phone number format';
            }

            // Validate license number (alphanumeric)
            if ($customer_license && !preg_match("/^[A-Za-z0-9\s\-]{5,50}$/", $customer_license)) {
                $errors[] = 'Invalid license number format';
            }

            // Verify car belongs to this business
            $stmt = $conn->prepare("SELECT car_id FROM cars WHERE car_id = ? AND business_id = ?");
            $stmt->bind_param("ii", $car_id, $business_id);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                $errors[] = 'Invalid car or unauthorized access';
            }

            // Handle customer
            if ($customer_type === 'existing' && $customer_id) {
                $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE customer_id = ? AND business_id = ?");
                $stmt->bind_param("ii", $customer_id, $business_id);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_assoc()) {
                    $errors[] = 'Invalid customer selected or customer not associated with your business';
                }
            } elseif ($customer_type === 'new') {
                $stmt = $conn->prepare("INSERT INTO customers (name, phone, license_number, business_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $customer_name, $customer_phone, $customer_license, $business_id);
                $stmt->execute();
                $customer_id = $conn->insert_id;
            }

            // Check for date conflicts (exclude current reservation)
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM reservations
                WHERE car_id = ? AND reservation_id != ? AND (
                    (start_date <= ? AND end_date >= ?) OR
                    (start_date <= ? AND end_date >= ?) OR
                    (start_date >= ? AND end_date <= ?)
                )
            ");
            $stmt->bind_param("iissssss", $car_id, $reservation_id, $end_date, $start_date, $start_date, $start_date, $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->fetch_array()[0] > 0) {
                $errors[] = 'Date conflict: One or more dates are already reserved';
            }

            // Update reservation and payment if no errors
            if (empty($errors)) {
                $conn->begin_transaction();
                try {
                    // Prepare start_time and end_time for database (NULL if empty)
                    $start_time = $start_time ?: null;
                    $end_time = $end_time ?: null;

                    // Update reservation
                    $stmt = $conn->prepare("
                        UPDATE reservations
                        SET car_id = ?, customer_id = ?, start_date = ?, start_time = ?, end_date = ?, end_time = ?, total_cost = ?, comments = ?
                        WHERE reservation_id = ?
                    ");
                    $stmt->bind_param("iisssssdi", $car_id, $customer_id, $start_date, $start_time, $end_date, $end_time, $total_cost, $comments, $reservation_id);
                    $stmt->execute();

                    // Update payment amount
                    $stmt = $conn->prepare("UPDATE payments SET amount = ? WHERE reservation_id = ?");
                    $stmt->bind_param("di", $total_cost, $reservation_id);
                    $stmt->execute();

                    $conn->commit();
                    $success = 'Reservation updated successfully';
                    header("Location: add_reservation.php?year=$year&month=$month");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    throw new Exception("Transaction failed: " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Reservation</title>
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
        .select2-container { width: 100% !important; }
        .success-message { font-size: 1.5rem; font-weight: bold; text-align: center; padding: 20px; }
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
                <a class="nav-link active" href="add_reservation.php?year=<?= $year ?>&month=<?= $month ?>">
                    <i class="fas fa-plus"></i>
                    <span>Add Reservation</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="cars.php?tab=list">
                    <i class="fas fa-car"></i>
                    <span>Car List</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="cars.php?tab=maintenance">
                    <i class="fas fa-wrench"></i>
                    <span>Add Maintenance</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="cars.php?tab=services">
                    <i class="fas fa-cogs"></i>
                    <span>Add Services</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="payments.php">
                    <i class="fas fa-money-bill"></i>
                    <span>Payments</span>
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
            <h2 class="text-center mb-4">Edit Reservation</h2>
            <?php if ($reservation && !empty($cars)): ?>
                <div class="modal fade show d-block" id="editReservationModal" tabindex="-1" aria-labelledby="editReservationModalLabel" aria-modal="true" role="dialog">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editReservationModalLabel">Edit Reservation #<?= $reservation['reservation_id'] ?></h5>
                                <a href="add_reservation.php?year=<?= $year ?>&month=<?= $month ?>" class="btn-close"></a>
                            </div>
                            <form method="POST" action="edit_reservation.php?reservation_id=<?= $reservation_id ?>&year=<?= $year ?>&month=<?= $month ?>">
                                <div class="modal-body">
                                    <input type="hidden" name="edit_reservation" value="1">
                                    <?php if (!empty($errors)): ?>
                                        <div class="alert alert-danger">
                                            <ul>
                                                <?php foreach ($errors as $error): ?>
                                                    <li><?= htmlspecialchars($error) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <label for="car_id" class="form-label">Car</label>
                                        <select class="form-select" id="car_id" name="car_id" required>
                                            <option value="">Select a car</option>
                                            <?php foreach ($cars as $car): ?>
                                                <option value="<?= $car['car_id'] ?>" <?= ($form_data['car_id'] ?? $reservation['car_id']) == $car['car_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['license_plate'] . ')') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Customer Type</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="customer_type" id="existing_customer" value="existing" <?= ($form_data['customer_type'] ?? 'existing') === 'existing' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="existing_customer">Existing Customer</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="customer_type" id="new_customer" value="new" <?= ($form_data['customer_type'] ?? '') === 'new' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="new_customer">New Customer</label>
                                        </div>
                                    </div>
                                    <div class="mb-3" id="existing-customer-fields" class="<?= ($form_data['customer_type'] ?? 'existing') === 'new' ? 'd-none' : '' ?>">
                                        <label for="customer_id" class="form-label">Select Customer</label>
                                        <select class="form-select" id="customer_id" name="customer_id">
                                            <option value=""><?php echo empty($customers) ? 'No customers found' : 'Select or type a customer name'; ?></option>
                                            <?php foreach ($customers as $customer): ?>
                                                <option value="<?= $customer['customer_id'] ?>" 
                                                        data-name="<?= htmlspecialchars($customer['name']) ?>" 
                                                        data-phone="<?= htmlspecialchars($customer['phone'] ?? '') ?>" 
                                                        data-license="<?= htmlspecialchars($customer['license_number'] ?? '') ?>"
                                                        <?= ($form_data['customer_id'] ?? $reservation['customer_id']) == $customer['customer_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($customer['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3 <?= ($form_data['customer_type'] ?? 'existing') === 'existing' ? 'd-none' : '' ?>" id="new-customer-fields">
                                        <label for="customer_name" class="form-label">New Customer Name</label>
                                        <input type="text" class="form-control" id="customer_name" name="customer_name" value="<?= htmlspecialchars($form_data['customer_name'] ?? $reservation['customer_name']) ?>">
                                        <label for="phone" class="form-label mt-2">Phone Number</label>
                                        <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($form_data['phone'] ?? $reservation['phone']) ?>">
                                        <label for="license_number" class="form-label mt-2">License Number</label>
                                        <input type="text" class="form-control" id="license_number" name="license_number" value="<?= htmlspecialchars($form_data['license_number'] ?? $reservation['license_number']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($form_data['start_date'] ?? $reservation['start_date']) ?>" required
                                               min="<?= sprintf("%d-%02d-01", $year, $month) ?>"
                                               max="<?= sprintf("%d-%02d-%d", $year, $month, $daysInMonth) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="start_time" class="form-label">Pick-up Time</label>
                                        <input type="time" class="form-control" id="start_time" name="start_time" value="<?= htmlspecialchars($form_data['start_time'] ?? $reservation['start_time']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($form_data['end_date'] ?? $reservation['end_date']) ?>" required
                                               min="<?= sprintf("%d-%02d-01", $year, $month) ?>"
                                               max="<?= sprintf("%d-%02d-%d", $year, $month, $daysInMonth) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="end_time" class="form-label">Return Time</label>
                                        <input type="time" class="form-control" id="end_time" name="end_time" value="<?= htmlspecialchars($form_data['end_time'] ?? $reservation['end_time']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="total_cost" class="form-label">Total Cost (€)</label>
                                        <input type="number" step="0.01" class="form-control" id="total_cost" name="total_cost" value="<?= htmlspecialchars($form_data['total_cost'] ?? $reservation['total_cost']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="comments" class="form-label">Comments</label>
                                        <textarea class="form-control" id="comments" name="comments" rows="4"><?= htmlspecialchars($form_data['comments'] ?? ($reservation['comments'] ?? '')) ?></textarea>
                                   Dataprintf>
                                </div>
                                <div>
                                <div class="modal-footer">
                                    <a href="add_reservation.php?year=<?= $year ?>&month=<?= $month ?>" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Update Reservation</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-backdrop fade show"></div>
            <?php elseif (empty($cars)): ?>
                <div class="alert alert-warning">No cars found for your business. Please add cars to edit a reservation.</div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as &$error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
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

        // Initialize Select2 for customer dropdown
        $('#customer_id').select2({
            placeholder: '<?php echo empty($customers) ? "No customers found" : "Select or type a customer name"; ?>',
            allowClear: true,
            dropdownParent: $('#editReservationModal')
        });

        // Handle customer type radio buttons
        function toggleCustomerFields() {
            const isExisting = document.getElementById('existing_customer').checked;
            $('#existing-customer-fields').toggleClass('d-none', !isExisting);
            $('#new-customer-fields').toggleClass('d-block', isExisting);
            $('#customer_id').prop('disabled', !isExisting);
            $('#customer_name').prop('disabled', isExisting);
            $('#phone').prop('disabled', isExisting);
            $('#license_number').prop('disabled', isExisting);
        }

        // Initialize fields based on default radio button
        toggleCustomerFields();

        // Handle radio button change
        $('input[name="customer_type"]').on('change', toggleCustomerFields);

        // Handle customer selection
        $('#customer_id').on('change', function() {
            const selected = $(this).find('option:selected');
            const name = selected.data('name') || '';
            const phone = selected.data('phone') || '';
            const license = selected.data('license') || '';
            $('#customer_name').val(name);
            $('#phone').val(phone);
            $('#license_number').val(license);
        });
    </script>
</body>
</html>