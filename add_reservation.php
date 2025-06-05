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
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$daysInMonth = (new DateTime("$year-$month-01"))->format('t');

// Handle form submission
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

            // Validate times (if provided)
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
                // Verify existing customer belongs to this business
                $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE customer_id = ? AND business_id = ?");
                $stmt->bind_param("ii", $customer_id, $business_id);
                $stmt->execute();
                if (!$stmt->get_result()->fetch_assoc()) {
                    $errors[] = 'Invalid customer selected or customer not associated with your business';
                }
            } elseif ($customer_type === 'new') {
                // Create new customer with business_id
                $stmt = $conn->prepare("INSERT INTO customers (name, phone, license_number, business_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sssi", $customer_name, $customer_phone, $customer_license, $business_id);
                $stmt->execute();
                $customer_id = $conn->insert_id;
            }

            // Check for date conflicts
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

            // Insert reservation if no errors
            if (empty($errors)) {
                $stmt = $conn->prepare("
                    INSERT INTO reservations (car_id, customer_id, start_date, start_time, end_date, end_time, total_cost, comments)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iissssss", $car_id, $customer_id, $start_date, $start_time, $end_date, $end_time, $total_cost, $comments);
                $stmt->execute();
                $success = 'Reservation added successfully';
                $conn->close();
                header("Location: calendar.php?year=$year&month=$month");
                exit();
            }
        }
    } catch (Exception $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Fetch data for form
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
    $stmt = $conn->prepare("SELECT car_id, brand, model, license_plate FROM cars WHERE business_id = ?");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $cars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch customers
    $stmt = $conn->prepare("SELECT customer_id, name, phone, license_number FROM customers WHERE business_id = ? ORDER BY name");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $conn->close();
} catch (Exception $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Reservation</title>
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
                <a class="nav-link active" href="add_reservation.php">
                    <i class="fas fa-plus"></i>
                    <span>Add Reservation</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#profile">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content" id="mainContent">
        <div class="container my-5">
            <h2 class="text-center mb-4">Add New Reservation</h2>
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
            <?php if (empty($cars)): ?>
                <div class="alert alert-warning">No cars found for your business. Please add cars to create a reservation.</div>
            <?php else: ?>
                <div class="form-container">
                    <form method="POST" action="add_reservation.php?year=<?= $year ?>&month=<?= $month ?>">
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
                                    <option value="<?= $customer['customer_id'] ?>" 
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
                            <input type="date" class="form-control" id="start_date" name="start_date" required
                                   min="<?= sprintf("%d-%02d-01", $year, $month) ?>"
                                   max="<?= sprintf("%d-%02d-%d", $year, $month, $daysInMonth) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="start_time" class="form-label">Pick-up Time</label>
                            <input type="time" class="form-control" id="start_time" name="start_time">
                        </div>
                        <div class="mb-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required
                                   min="<?= sprintf("%d-%02d-01", $year, $month) ?>"
                                   max="<?= sprintf("%d-%02d-%d", $year, $month, $daysInMonth) ?>">
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
                        <div class="d-flex justify-content-between">
                            <a href="calendar.php?year=<?= $year ?>&month=<?= $month ?>" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Add Reservation</button>
                        </div>
                    </form>
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
            allowClear: true
        });

        // Handle customer type radio buttons
        function toggleCustomerFields() {
            const isExisting = document.getElementById('existing_customer').checked;
            $('#existing-customer-fields').toggleClass('d-none', !isExisting);
            $('#new-customer-fields').toggleClass('d-none', isExisting);
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