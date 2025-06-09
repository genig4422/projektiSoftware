<?php
session_start();

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/htdocs/debug.log'); // Adjust for your XAMPP setup

// Debug: Log session data
error_log("Session data: " . print_r($_SESSION, true));

// Check session for manager role
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'manager') {
    error_log("Session check failed: email=" . ($_SESSION['email'] ?? 'unset') . ", role=" . ($_SESSION['role'] ?? 'unset'));
    header("Location: login.php");
    exit();
}

// Include database configuration
require_once 'config.php';
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Database connection failed. Check error log for details.");
}

// Include header
require_once 'header1.php';

// Initialize variables
$errors = [];
$success = '';
$year = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) && is_numeric($_GET['month']) ? (int)$_GET['month'] : date('m');
try {
    $daysInMonth = (new DateTime("$year-$month-01"))->format('t');
} catch (Exception $e) {
    error_log("Invalid date: " . $e->getMessage());
    $year = date('Y');
    $month = date('m');
    $daysInMonth = date('t');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get business_id
        $stmt = $conn->prepare("SELECT business_id FROM users WHERE email = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $_SESSION['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if (!$user) {
            throw new Exception("No business found for this user");
        }
        $business_id = $user['business_id'];

        if (isset($_POST['add_reservation'])) {
            // Get POST data
            $car_id = $_POST['car_id'] ?? null;
            $customer_id = $_POST['customer_id'] ?? null;
            $customer_name = $_POST['customer_name'] ?? null;
            $customer_phone = $_POST['phone'] ?? null;
            $customer_license = $_POST['license_number'] ?? null;
            $start_date = $_POST['start_date'] ?? null;
            $start_time = $_POST['start_time'] ?? null;
            $end_date = $_POST['end_date'] ?? null;
            $end_time = $_POST['end_time'] ?? null;
            $total_cost = $_POST['total_cost'] ?? null;
            $comments = $_POST['comments'] ?? null;
            $customer_type = $_POST['customer_type'] ?? null;

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

                // Validate times
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

                // Validate phone
                if ($customer_phone && !preg_match("/^[\+]?[\d\s\-]{7,20}$/", $customer_phone)) {
                    $errors[] = 'Invalid phone number format';
                }

                // Validate license number
                if ($customer_license && !preg_match("/^[A-Za-z0-9\s\-]{5,50}$/", $customer_license)) {
                    $errors[] = 'Invalid license number format';
                }

                // Verify car
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
                        $errors[] = 'Invalid customer selected';
                    }
                } elseif ($customer_type === 'new') {
                    $stmt = $conn->prepare("INSERT INTO customers (name, phone, license_number, business_id) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $customer_name, $customer_phone, $customer_license, $business_id);
                    $stmt->execute();
                    $customer_id = $conn->insert_id;
                }

                // Check date conflicts
                $stmt = $conn->prepare("
                    SELECT COUNT(*) FROM reservations
                    WHERE car_id = ? AND (
                        (start_date <= ? AND end_date >= ?) OR
                        (start_date <= ? AND end_date >= ?) OR
                        (start_date >= ? AND end_date <= ?)
                    )
                ");
                $stmt->bind_param("issssss", $car_id, $end_date, $start_date, $start_date, $start_date, $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->fetch_array()[0] > 0) {
                    $errors[] = 'Date conflict: One or more dates are already reserved';
                }

                // Insert reservation and payment
                if (empty($errors)) {
                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare("
                            INSERT INTO reservations (car_id, customer_id, start_date, start_time, end_date, end_time, total_cost, comments)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $start_time = $start_time ?: null;
                        $end_time = $end_time ?: null;
                        $stmt->bind_param("iissssss", $car_id, $customer_id, $start_date, $start_time, $end_date, $end_time, $total_cost, $comments);
                        $stmt->execute();
                        $reservation_id = $conn->insert_id;

                        $status = 'pending';
                        $stmt = $conn->prepare("INSERT INTO payments (reservation_id, amount, status) VALUES (?, ?, ?)");
                        $stmt->bind_param("ids", $reservation_id, $total_cost, $status);
                        $stmt->execute();

                        $conn->commit();
                        $success = 'Reservation and payment added successfully';
                        header("Location: add_reservation.php?year=$year&month=$month");
                        exit();
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw new Exception("Transaction failed: " . $e->getMessage());
                    }
                }
            }
        } elseif (isset($_POST['delete_reservation'])) {
            $reservation_id = $_POST['reservation_id'] ?? null;
            if ($reservation_id) {
                $stmt = $conn->prepare("
                    SELECT r.reservation_id
                    FROM reservations r
                    JOIN cars c ON r.car_id = c.car_id
                    WHERE r.reservation_id = ? AND c.business_id = ?
                ");
                $stmt->bind_param("ii", $reservation_id, $business_id);
                $stmt->execute();
                if ($stmt->get_result()->fetch_assoc()) {
                    $stmt = $conn->prepare("DELETE FROM reservations WHERE reservation_id = ?");
                    $stmt->bind_param("i", $reservation_id);
                    $stmt->execute();
                    $success = 'Reservation deleted successfully';
                    header("Location: add_reservation.php?year=$year&month=$month");
                    exit();
                } else {
                    $errors[] = 'Invalid reservation or unauthorized access';
                }
            } else {
                $errors[] = 'No reservation selected for deletion';
            }
        } elseif (isset($_POST['pay_reservation'])) {
            $reservation_id = $_POST['reservation_id'] ?? null;
            $amount = $_POST['amount'] ?? null;
            // Debug: Log input values
            error_log("Pay Reservation: reservation_id=$reservation_id, amount=$amount, business_id=$business_id");
            if (!$reservation_id || !$amount) {
                $errors[] = 'Reservation ID and amount are required';
                error_log("Error: Missing reservation_id or amount");
            } elseif (!is_numeric($amount) || $amount <= 0) {
                $errors[] = 'Amount must be a positive number';
                error_log("Error: Invalid amount ($amount)");
            } else {
                // Verify reservation
                $stmt = $conn->prepare("
                    SELECT r.reservation_id
                    FROM reservations r
                    JOIN cars c ON r.car_id = c.car_id
                    WHERE r.reservation_id = ? AND c.business_id = ?
                ");
                $stmt->bind_param("ii", $reservation_id, $business_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->fetch_assoc()) {
                    // Check if payment exists
                    $stmt = $conn->prepare("SELECT payment_id FROM payments WHERE reservation_id = ?");
                    $stmt->bind_param("i", $reservation_id);
                    $stmt->execute();
                    $payment_exists = $stmt->get_result()->fetch_assoc();
                    if ($payment_exists) {
                        // Update payment
                        $stmt = $conn->prepare("UPDATE payments SET status = 'paid', amount = ?, payment_date = NOW() WHERE reservation_id = ?");
                        $stmt->bind_param("di", $amount, $reservation_id);
                        if ($stmt->execute()) {
                            if ($stmt->affected_rows > 0) {
                                $success = 'Payment recorded successfully';
                                error_log("Payment updated: reservation_id=$reservation_id, amount=$amount");
                                header("Location: add_reservation.php?year=$year&month=$month&payment_success=1");
                                exit();
                            } else {
                                $errors[] = 'Payment update failed: No rows affected';
                                error_log("Error: No rows affected for reservation_id=$reservation_id");
                            }
                        } else {
                            $errors[] = 'Database error: ' . $stmt->error;
                            error_log("SQL Error: " . $stmt->error);
                        }
                    } else {
                        $errors[] = 'No payment record found for this reservation';
                        error_log("Error: No payment record for reservation_id=$reservation_id");
                    }
                } else {
                    $errors[] = 'Invalid reservation or unauthorized access';
                    error_log("Error: Invalid reservation_id=$reservation_id or business_id=$business_id");
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
        error_log("Exception: " . $e->getMessage());
    }
}

// Fetch data
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

    $stmt = $conn->prepare("SELECT car_id, brand, model, license_plate FROM cars WHERE business_id = ?");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $cars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare("SELECT customer_id, name, phone, license_number FROM customers WHERE business_id = ? ORDER BY name");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare("
        SELECT r.reservation_id, r.car_id, r.customer_id, r.start_date, TIME_FORMAT(r.start_time, '%H:%i') AS start_time, 
               r.end_date, TIME_FORMAT(r.end_time, '%H:%i') AS end_time, r.total_cost, r.comments,
               c.brand, c.model, c.license_plate, cu.name AS customer_name
        FROM reservations r
        JOIN cars c ON r.car_id = c.car_id
        JOIN customers cu ON r.customer_id = cu.customer_id
        JOIN payments p ON r.reservation_id = p.reservation_id
        WHERE c.business_id = ? AND p.status = 'pending'
        ORDER BY r.start_date DESC
    ");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
    error_log("Fetch error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Management</title>
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
        .table-container { overflow-x: auto; }
        .success-message {
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 1050;
            max-width: 300px;
        }
        .select2-container { width: 100% !important; }
    </style>
</head>
<body>
    <div class="main-content" id="mainContent">
        <div class="container my-5">
            <h2 class="text-center mb-4">Reservation Management</h2>
            <?php if ($success && isset($_GET['payment_success'])): ?>
                <div class="alert alert-success success-message">Payment Recorded Successfully!</div>
            <?php elseif ($success): ?>
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
            <div class="text-center mb-4">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReservationModal">
                    <i class="fas fa-plus"></i> Add New Reservation
                </button>
            </div>

            <!-- Add Reservation Modal -->
            <?php if (!empty($cars)): ?>
                <div class="modal fade" id="addReservationModal" tabindex="-1" aria-labelledby="addReservationModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addReservationModalLabel">Add New Reservation</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST" action="add_reservation.php?year=<?= htmlspecialchars($year) ?>&month=<?= htmlspecialchars($month) ?>">
                                <div class="modal-body">
                                    <input type="hidden" name="add_reservation" value="1">
                                    <div class="mb-3">
                                        <label for="car_id" class="form-label">Car</label>
                                        <select class="form-select" id="car_id" name="car_id" required>
                                            <option value="">Select a car</option>
                                            <?php foreach ($cars as $car): ?>
                                                <option value="<?= htmlspecialchars($car['car_id']) ?>"><?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['license_plate'] . ')') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Customer Type</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="customer_type" id="existing_customer" value="existing" checked>
                                            <label class="form-check-label" for="existing_customer">Existing Customer</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="customer_type" id="new_customer" value="new">
                                            <label class="form-check-label" for="new_customer">New Customer</label>
                                        </div>
                                    </div>
                                    <div class="mb-3" id="existing-customer-fields">
                                        <label for="customer_id" class="form-label">Select Customer</label>
                                        <select class="form-select" id="customer_id" name="customer_id">
                                            <option value=""><?php echo empty($customers) ? 'No customers found' : 'Select or type a customer name'; ?></option>
                                            <?php foreach ($customers as $customer): ?>
                                                <option value="<?= htmlspecialchars($customer['customer_id']) ?>" 
                                                        data-name="<?= htmlspecialchars($customer['name']) ?>" 
                                                        data-phone="<?= htmlspecialchars($customer['phone'] ?? '') ?>" 
                                                        data-license="<?= htmlspecialchars($customer['license_number'] ?? '') ?>">
                                                    <?= htmlspecialchars($customer['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3 d-none" id="new-customer-fields">
                                        <label for="customer_name" class="form-label">New Customer Name</label>
                                        <input type="text" class="form-control" id="customer_name" name="customer_name">
                                        <label for="phone" class="form-label mt-2">Phone Number</label>
                                        <input type="text" class="form-control" id="phone" name="phone">
                                        <label for="license_number" class="form-label mt-2">License Number</label>
                                        <input type="text" class="form-control" id="license_number" name="license_number">
                                    </div>
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="start_time" class="form-label">Pick-up Time</label>
                                        <input type="time" class="form-control" id="start_time" name="start_time">
                                    </div>
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="end_time" class="form-label">Return Time</label>
                                        <input type="time" class="form-control" id="end_time" name="end_time">
                                    </div>
                                    <div class="mb-3">
                                        <label for="total_cost" class="form-label">Total Cost (€)</label>
                                        <input type="number" step="0.01" class="form-control" id="total_cost" name="total_cost" value="0" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="comments" class="form-label">Comments</label>
                                        <textarea class="form-control" id="comments" name="comments" rows="4"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add Reservation</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">No cars found for your business. Please add cars to create a reservation.</div>
            <?php endif; ?>

            <div class="table-container mt-5">
                <h2 class="text-center mb-4">Pending Reservations</h2>
                <?php if (empty($reservations)): ?>
                    <div class="alert alert-warning">No pending reservations found.</div>
                <?php else: ?>
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Reservation ID</th>
                                <th>Car</th>
                                <th>Customer</th>
                                <th>Start Date</th>
                                <th>Start Time</th>
                                <th>End Date</th>
                                <th>End Time</th>
                                <th>Total Cost (€)</th>
                                <th>Comments</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td><?= htmlspecialchars($reservation['reservation_id']) ?></td>
                                    <td><?= htmlspecialchars($reservation['brand'] . ' ' . $reservation['model'] . ' (' . $reservation['license_plate'] . ')') ?></td>
                                    <td><?= htmlspecialchars($reservation['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($reservation['start_date']) ?></td>
                                    <td><?= htmlspecialchars($reservation['start_time'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($reservation['end_date']) ?></td>
                                    <td><?= htmlspecialchars($reservation['end_time'] ?? 'N/A') ?></td>
                                    <td><?= number_format($reservation['total_cost'], 2) ?></td>
                                    <td><?= htmlspecialchars($reservation['comments'] ?? 'None') ?></td>
                                    <td>
                                        <a href="edit_reservation.php?reservation_id=<?= htmlspecialchars($reservation['reservation_id']) ?>&year=<?= htmlspecialchars($year) ?>&month=<?= htmlspecialchars($month) ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <form method="POST" action="add_reservation.php?year=<?= htmlspecialchars($year) ?>&month=<?= htmlspecialchars($month) ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this reservation?');">
                                            <input type="hidden" name="delete_reservation" value="1">
                                            <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($reservation['reservation_id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal<?= htmlspecialchars($reservation['reservation_id']) ?>">
                                            <i class="fas fa-money-bill"></i> Pay
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Payment Modals -->
    <?php if (!empty($reservations)): ?>
        <?php foreach ($reservations as $reservation): ?>
            <div class="modal fade" id="paymentModal<?= htmlspecialchars($reservation['reservation_id']) ?>" tabindex="-1" aria-labelledby="paymentModalLabel<?= htmlspecialchars($reservation['reservation_id']) ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="paymentModalLabel<?= htmlspecialchars($reservation['reservation_id']) ?>">Record Payment for Reservation #<?= htmlspecialchars($reservation['reservation_id']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="add_reservation.php?year=<?= htmlspecialchars($year) ?>&month=<?= htmlspecialchars($month) ?>">
                            <div class="modal-body">
                                <input type="hidden" name="pay_reservation" value="1">
                                <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($reservation['reservation_id']) ?>">
                                <div class="mb-3">
                                    <label for="amount_<?= htmlspecialchars($reservation['reservation_id']) ?>" class="form-label">Amount (€)</label>
                                    <input type="number" step="0.01" class="form-control" id="amount_<?= htmlspecialchars($reservation['reservation_id']) ?>" name="amount" value="<?= htmlspecialchars($reservation['total_cost']) ?>" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Record Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        console.log('add_reservation.php script loaded');

        // Sidebar toggle
        const toggleSidebar = document.getElementById('toggleSidebar');
        if (toggleSidebar) {
            toggleSidebar.addEventListener('click', () => {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.getElementById('mainContent');
                if (sidebar && mainContent) {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('collapsed');
                    console.log('Sidebar toggled');
                } else {
                    console.error('Sidebar or mainContent not found');
                }
            });
        } else {
            console.warn('toggleSidebar not found');
        }

        // Initialize Select2
        try {
            $('#customer_id').select2({
                placeholder: '<?php echo empty($customers) ? "No customers found" : "Select or type a customer name"; ?>',
                allowClear: true,
                dropdownParent: $('#addReservationModal')
            });
            console.log('Select2 initialized');
        } catch (e) {
            console.error('Select2 initialization failed:', e);
        }

        // Handle customer type radio buttons
        function toggleCustomerFields() {
            const isExisting = document.getElementById('existing_customer').checked;
            $('#existing-customer-fields').toggleClass('d-none', !isExisting);
            $('#new-customer-fields').toggleClass('d-none', isExisting);
            $('#customer_id').prop('disabled', !isExisting);
            $('#customer_name').prop('disabled', isExisting);
            $('#phone').prop('disabled', isExisting);
            $('#license_number').prop('disabled', isExisting);
            console.log('Customer fields toggled:', isExisting ? 'existing' : 'new');
        }

        try {
            toggleCustomerFields();
            $('input[name="customer_type"]').on('change', toggleCustomerFields);
        } catch (e) {
            console.error('Customer type toggle failed:', e);
        }

        // Handle customer selection
        try {
            $('#customer_id').on('change', function() {
                const selected = $(this).find('option:selected');
                const name = selected.data('name') || '';
                const phone = selected.data('phone') || '';
                const license = selected.data('license') || '';
                $('#customer_name').val(name);
                $('#phone').val(phone);
                $('#license_number').val(license);
                console.log('Customer selected:', name);
            });
        } catch (e) {
            console.error('Customer selection handler failed:', e);
        }

        // Debug modal triggers
        document.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
            button.addEventListener('click', () => {
                const target = button.getAttribute('data-bs-target');
                console.log('Modal trigger clicked, target:', target);
                const modal = document.querySelector(target);
                if (!modal) {
                    console.error('Modal not found:', target);
                }
            });
        });
    </script>
</body>
</html>