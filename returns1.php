<?php
session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

require_once 'header1.php';

// Initialize variables
$errors = [];
$search_car_id = isset($_GET['car_id']) && is_numeric($_GET['car_id']) ? (int)$_GET['car_id'] : null;
$search_date = isset($_GET['return_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['return_date']) ? $_GET['return_date'] : null;

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

    // Fetch return records
    $query = "SELECT r.reservation_id, r.car_id, r.end_date, TIME_FORMAT(r.end_time, '%H:%i') AS end_time,
                     c.brand, c.model, c.license_plate,
                     cu.name AS customer_name
              FROM reservations r
              JOIN cars c ON r.car_id = c.car_id
              JOIN customers cu ON r.customer_id = cu.customer_id
              WHERE c.business_id = ? AND r.end_date IS NOT NULL";
    $params = [$business_id];
    $types = "i";

    if ($search_car_id) {
        $query .= " AND r.car_id = ?";
        $params[] = $search_car_id;
        $types .= "i";
    }
    if ($search_date) {
        $query .= " AND DATE(r.end_date) = ?";
        $params[] = $search_date;
        $types .= "s";
    }
    $query .= " ORDER BY r.end_date DESC, r.end_time DESC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $returns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Group returns by date
    $grouped_returns = [];
    foreach ($returns as $return) {
        $date = date('Y-m-d', strtotime($return['end_date']));
        $grouped_returns[$date][] = $return;
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
    <title>Car Returns</title>
   
</head>
<body>
  
    <div class="main-content" id="mainContent">
        <div class="container my-5">
            <h2 class="text-center mb-4">Car Returns</h2>
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
                <form method="GET" action="returns1.php" class="row g-3">
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
                        <label for="return_date" class="form-label">Search by Return Date</label>
                        <input type="date" class="form-control" id="return_date" name="return_date" value="<?= htmlspecialchars($search_date ?? '') ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                </form>
            </div>

            <?php if (empty($grouped_returns)): ?>
                <div class="alert alert-warning">No returns found.</div>
            <?php else: ?>
                <?php foreach ($grouped_returns as $date => $returns): ?>
                    <div class="return-card">
                        <div class="card">
                            <div class="card-header">
                                <h4>Returns for <?= htmlspecialchars($date) ?> (Total: <?= count($returns) ?>)</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Car</th>
                                            <th>Return Time</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($returns as $return): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($return['customer_name']) ?></td>
                                                <td><?= htmlspecialchars($return['brand'] . ' ' . $return['model'] . ' (' . $return['license_plate'] . ')') ?></td>
                                                <td><?= htmlspecialchars($return['end_time'] ?? '-') ?></td>
                                                <td>
                                                    <a href="add_damage.php?reservation_id=<?= $return['reservation_id'] ?>&car_id=<?= $return['car_id'] ?>&customer_name=<?= urlencode($return['customer_name']) ?>" 
                                                       class="btn btn-warning btn-sm action-btn">
                                                       <i class="fas fa-exclamation-triangle"></i> Report Damage
                                                    </a>
                                                </td>
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