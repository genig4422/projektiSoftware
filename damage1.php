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
$search_date = isset($_GET['reported_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['reported_date']) ? $_GET['reported_date'] : null;

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

    // Fetch damage records with customer name
    $query = "SELECT d.damage_id, d.car_id, d.maintenance_id, d.description, d.repair_cost, d.reported_at,
                     c.brand, c.model, c.license_plate,
                     cu.name AS customer_name
              FROM damage_reports d
              JOIN cars c ON d.car_id = c.car_id
              LEFT JOIN reservations r ON d.car_id = r.car_id 
                  AND d.reported_at >= r.start_date 
                  AND d.reported_at <= r.end_date
              LEFT JOIN customers cu ON r.customer_id = cu.customer_id
              WHERE c.business_id = ? AND d.reported_at IS NOT NULL";
    $params = [$business_id];
    $types = "i";

    if ($search_car_id) {
        $query .= " AND d.car_id = ?";
        $params[] = $search_car_id;
        $types .= "i";
    }
    if ($search_date) {
        $query .= " AND DATE(d.reported_at) = ?";
        $params[] = $search_date;
        $types .= "s";
    }
    $query .= " ORDER BY d.reported_at DESC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $damages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Group damages by date
    $grouped_damages = [];
    foreach ($damages as $damage) {
        $date = date('Y-m-d', strtotime($damage['reported_at']));
        $grouped_damages[$date][] = $damage;
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
    <title>Damage Reports</title>
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
        .table-container { max-width: 800px; margin: 0 auto; }
        .damage-card { margin-bottom: 20px; }
    </style>
</head>
<body>
   

    <div class="main-content" id="mainContent">
        <div class="container my-5">
            <h2 class="text-center mb-4">Damage Reports</h2>
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
                <form method="GET" action="damage1.php" class="row g-3">
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
                        <label for="reported_date" class="form-label">Search by Reported Date</label>
                        <input type="date" class="form-control" id="reported_date" name="reported_date" value="<?= htmlspecialchars($search_date ?? '') ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                </form>
            </div>

            <?php if (empty($grouped_damages)): ?>
                <div class="alert alert-warning">No damage reports found.</div>
            <?php else: ?>
                <?php foreach ($grouped_damages as $date => $damages): ?>
                    <div class="damage-card">
                        <div class="card">
                            <div class="card-header">
                                <h4>Damages Reported on <?= htmlspecialchars($date) ?> (Total: <?= count($damages) ?>)</h4>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Damage ID</th>
                                            <th>Car</th>
                                            <th>Customer</th>
                                            <th>Description</th>
                                            <th>Repair Cost (€)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($damages as $damage): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($damage['damage_id']) ?></td>
                                                <td><?= htmlspecialchars($damage['brand'] . ' ' . $damage['model'] . ' (' . $damage['license_plate'] . ')') ?></td>
                                                <td><?= htmlspecialchars($damage['customer_name'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($damage['description'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars(number_format($damage['repair_cost'] ?? 0, 2)) ?></td>
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