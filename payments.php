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
$search_car_id = isset($_GET['car_id']) ? (int)$_GET['car_id'] : null;
$search_date = isset($_GET['payment_date']) ? $_GET['payment_date'] : null;

// Fetch business_id
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

    // Fetch cars for search dropdown
    $stmt = $conn->prepare("SELECT car_id, brand, model, license_plate FROM cars WHERE business_id = ? ORDER BY brand, model");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $cars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch payment records
    $query = "SELECT p.payment_id, p.reservation_id, p.amount, p.payment_date, p.status, 
                     c.brand, c.model, c.license_plate, 
                     cu.name AS customer_name, 
                     r.start_date, r.end_date
              FROM payments p
              JOIN reservations r ON p.reservation_id = r.reservation_id
              JOIN cars c ON r.car_id = c.car_id
              JOIN customers cu ON r.customer_id = cu.customer_id
              WHERE c.business_id = ?";
    $params = [$business_id];
    $types = "i";

    if ($search_car_id) {
        $query .= " AND r.car_id = ?";
        $params[] = $search_car_id;
        $types .= "i";
    }
    if ($search_date) {
        $query .= " AND DATE(p.payment_date) = ?";
        $params[] = $search_date;
        $types .= "s";
    }
    $query .= " ORDER BY p.payment_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Group payments by date
    $grouped_payments = [];
    foreach ($payments as $payment) {
        $date = date('Y-m-d', strtotime($payment['payment_date']));
        $grouped_payments[$date][] = $payment;
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
    <title>Payment Management</title>
</head>
<body>


    <div class="main-content" id="mainContent">
        <div class="container my-5">
            <h2 class="text-center mb-4">Payment Management</h2>
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

            <div class="form-container mb-4">
                <form method="GET" action="payments.php" class="row g-3">
                    <div class="col-md-6">
                        <label for="car_id" class="form-label">Search by Car</label>
                        <select class="form-select" id="car_id" name="car_id">
                            <option value="">All Cars</option>
                            <?php foreach ($cars as $car): ?>
                                <option value="<?= $car['car_id'] ?>" <?= $search_car_id == $car['car_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['license_plate'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="payment_date" class="form-label">Search by Date</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?= htmlspecialchars($search_date ?? '') ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                </form>
            </

div>
            <?php if (empty($grouped_payments)): ?>
                <div class="alert alert-warning">No payments found.</div>
            <?php else: ?>
                <?php foreach ($grouped_payments as $date => $payments): ?>
                    <?php
                    $daily_total = array_sum(array_column($payments, 'amount'));
                    ?>
                    <div class="payment-card">
                        <div class="card">
                            <div class="card-header">
                                <h4>Payments for <?= htmlspecialchars($date) ?> (Total: €<?= number_format($daily_total, 2) ?>)</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Car</th>
                                            <th>Customer</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Amount (€)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payments as $payment): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($payment['brand'] . ' ' . $payment['model'] . ' (' . $payment['license_plate'] . ')') ?></td>
                                                <td><?= htmlspecialchars($payment['customer_name']) ?></td>
                                                <td><?= htmlspecialchars($payment['start_date']) ?></td>
                                                <td><?= htmlspecialchars($payment['end_date']) ?></td>
                                                <td><?= number_format($payment['amount'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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

        // Initialize Select2 for car dropdown
        $('#car_id').select2({
            placeholder: 'Select a car',
            allowClear: true
        });
    </script>
</body>
</html>