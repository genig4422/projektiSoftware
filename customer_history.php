<?php
session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php");
    exit();
}

require_once 'header.php';

// Initialize variables
$errors = [];
$customer = null;
$reservation_history = [];

if (!isset($_GET['customer_id']) || !is_numeric($_GET['customer_id'])) {
    $errors[] = 'Invalid customer ID';
} else {
    try {
        // Fetch business ID
        $stmt = $conn->prepare("SELECT business_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $_SESSION['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if (!$user) {
            throw new Exception("No business found for this user");
        }
        $business_id = $user['business_id'];

        // Fetch customer details
        $customer_id = (int)$_GET['customer_id'];
        $stmt = $conn->prepare("SELECT name FROM customers WHERE customer_id = ? AND business_id = ?");
        $stmt->bind_param("ii", $customer_id, $business_id);
        $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();
        if (!$customer) {
            throw new Exception("Customer not found");
        }

        // Fetch reservation history
        $stmt = $conn->prepare("
            SELECT r.reservation_id, r.start_date, TIME_FORMAT(r.start_time, '%H:%i') AS start_time,
                   r.end_date, TIME_FORMAT(r.end_time, '%H:%i') AS end_time, r.total_cost, r.comments,
                   c.brand, c.model, c.license_plate, p.status, p.payment_date
            FROM reservations r
            JOIN cars c ON r.car_id = c.car_id
            JOIN payments p ON r.reservation_id = p.reservation_id
            WHERE r.customer_id = ? AND c.business_id = ?
            ORDER BY r.start_date DESC
        ");
        $stmt->bind_param("ii", $customer_id, $business_id);
        $stmt->execute();
        $reservation_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <title>Customer Reservation History</title>
  
</head>
<body>
  

    <div class="main-content" id="mainContent">
        <div class="container my-5">
            <h2 class="text-center mb-4">
                Reservation History for <?= $customer ? htmlspecialchars($customer['name']) : 'Customer' ?>
            </h2>

            <div class="mb-4">
                <a href="customers.php" class="btn btn-secondary">Back to Customers</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif (empty($reservation_history)): ?>
                <div class="alert alert-info">No reservations found for this customer.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Car</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Total Cost (€)</th>
                                <th>Payment Status</th>
                                <th>Payment Date</th>
                                <th>Comments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservation_history as $reservation): ?>
                                <tr>
                                    <td><?= htmlspecialchars($reservation['reservation_id']) ?></td>
                                    <td><?= htmlspecialchars($reservation['brand'] . ' ' . $reservation['model'] . ' (' . $reservation['license_plate'] . ')') ?></td>
                                    <td>
                                        <?= htmlspecialchars($reservation['start_date']) ?>
                                        <?= $reservation['start_time'] ? ' - ' . htmlspecialchars($reservation['start_time']) : '' ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($reservation['end_date']) ?>
                                        <?= $reservation['end_time'] ? ' - ' . htmlspecialchars($reservation['end_time']) : '' ?>
                                    </td>
                                    <td><?= htmlspecialchars(number_format($reservation['total_cost'], 2)) ?></td>
                                    <td><?= htmlspecialchars($reservation['status']) ?></td>
                                    <td><?= htmlspecialchars($reservation['payment_date'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($reservation['comments'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
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