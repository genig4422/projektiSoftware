<?php
session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Initialize current date
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$currentDate = new DateTime("$year-$month-01");
$daysInMonth = (int)$currentDate->format('t');
$monthName = $currentDate->format('F Y');

// Handle Add Reservation form submission
$errors = [];
$success = '';


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

    // Fetch customers for dropdown, filtered by business_id
    $stmt = $conn->prepare("SELECT customer_id, name, phone, license_number FROM customers WHERE business_id = ? ORDER BY name");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch reservations with new columns
    $stmt = $conn->prepare("
        SELECT r.reservation_id, r.car_id, r.start_date, r.end_date, r.start_time, r.end_time, r.comments, r.total_cost, c.name AS customer
        FROM reservations r
        JOIN customers c ON r.customer_id = c.customer_id
        JOIN cars car ON r.car_id = car.car_id
        WHERE car.business_id = ?
    ");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Organize reservations by car
    $carData = [];
    foreach ($cars as $car) {
        $carReservations = [];
        foreach ($reservations as $res) {
            if ($res['car_id'] === $car['car_id']) {
                $dates = [];
                $start = new DateTime($res['start_date']);
                $end = new DateTime($res['end_date']);
                $interval = new DateInterval('P1D');
                $datePeriod = new DatePeriod($start, $interval, $end->modify('+1 day'));
                foreach ($datePeriod as $date) {
                    $dates[] = $date->format('Y-m-d');
                }
                $carReservations[] = [
                    'id' => $res['reservation_id'],
                    'dates' => $dates,
                    'customer' => $res['customer'],
                    'start_time' => $res['start_time'],
                    'end_time' => $res['end_time'],
                    'comments' => $res['comments'],
                    'total_cost' => $res['total_cost']
                ];
            }
        }
        $carData[] = [
            'car_id' => $car['car_id'],
            'name' => $car['brand'] . ' ' . $car['model'],
            'license' => $car['license_plate'],
            'reservations' => $carReservations
        ];
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle month navigation
$prevMonth = (new DateTime("$year-$month-01"))->modify('-1 month');
$nextMonth = (new DateTime("$year-$month-01"))->modify('+1 month');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Car Schedule Calendar</title>
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
        #tableContainer { overflow-x: auto; }
        #scheduleTable { width: auto; table-layout: auto; }
        #scheduleTable th, #scheduleTable td {
            font-size: 0.85rem;
            padding: 4px;
            text-align: center;
            min-width: 40px;
        }
        #scheduleTable th:first-child, #scheduleTable td:first-child {
            min-width: 160px;
            text-align: left;
            position: sticky;
            left: 0;
            background-color: #fff;
            z-index: 1;
            box-shadow: 2px 0 2px rgba(0, 0, 0, 0.1);
        }
        .reserved {
            background-color: #0d6efd !important;
            color: #fff;
            cursor: pointer;
        }
        #addReservation { margin-right: 10px; }
        .tooltip-inner {
            max-width: 400px;
            text-align: left;
            white-space: pre-wrap;
            font-size: 1rem;
            padding: 10px;
        }
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
                        <a class="nav-link active" href="calendar.php">Calendar</a>
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
                <a class="nav-link active" href="calendar.php">
                    <i class="fas fa-calendar"></i>
                    <span>Calendar</span>
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
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php elseif (empty($carData)): ?>
                <div class="alert alert-warning">No cars found for your business. Please add cars to display the schedule.</div>
            <?php endif; ?>
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
            <div class="d-flex justify-content-between mb-3">
                <div>
                    <a href="add_reservation.php"><button type="button" class="btn btn-success" >Add Reservation</button></a>
                    <form action="calendar.php" method="GET" style="display: inline;">
                        <input type="hidden" name="year" value="<?= $prevMonth->format('Y') ?>">
                        <input type="hidden" name="month" value="<?= $prevMonth->format('m') ?>">
                        <button type="submit" class="btn btn-primary">Previous</button>
                    </form>
                </div>
                <h2 class="text-center"><?= htmlspecialchars($monthName) ?></h2>
                <form action="calendar.php" method="GET" style="display: inline;">
                    <input type="hidden" name="year" value="<?= $nextMonth->format('Y') ?>">
                    <input type="hidden" name="month" value="<?= $nextMonth->format('m') ?>">
                    <button type="submit" class="btn btn-primary">Next</button>
                </form>
            </div>
            <div id="tableContainer">
                <table id="scheduleTable" class="table table-bordered table-hover">
                    <thead>
                        <tr id="dateRow">
                            <th scope="col">Car</th>
                            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                                <th scope="col"><?= $day ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($carData as $car): ?>
                            <tr>
                                <td><?= htmlspecialchars($car['name'] . ' (' . $car['license'] . ')') ?></td>
                                <?php
                                $reservedDates = [];
                                foreach ($car['reservations'] as $res) {
                                    foreach ($res['dates'] as $date) {
                                        $reservedDates[$date] = [
                                            'id' => $res['id'],
                                            'customer' => $res['customer'],
                                            'start_time' => $res['start_time'],
                                            'end_time' => $res['end_time'],
                                            'comments' => $res['comments'],
                                            'total_cost' => $res['total_cost'],
                                            'start_date' => $res['dates'][0],
                                            'end_date' => $res['dates'][count($res['dates']) - 1]
                                        ];
                                    }
                                }
                                $day = 1;
                                while ($day <= $daysInMonth) {
                                    $dateString = sprintf("%d-%02d-%02d", $year, $month, $day);
                                    if (isset($reservedDates[$dateString])) {
                                        $resId = $reservedDates[$dateString]['id'];
                                        $count = 1;
                                        $nextDay = $day + 1;
                                        while ($nextDay <= $daysInMonth) {
                                            $nextDateString = sprintf("%d-%02d-%02d", $year, $month, $nextDay);
                                            if (isset($reservedDates[$nextDateString]) && $reservedDates[$nextDateString]['id'] === $resId) {
                                                $count++;
                                                $nextDay++;
                                            } else {
                                                break;
                                            }
                                        }
                                        $endDate = sprintf("%d-%02d-%02d", $year, $month, $day + $count - 1);
                                        $tooltip = "Reservation: " . htmlspecialchars($resId) . "\n" .
                                                   "Customer: " . htmlspecialchars($reservedDates[$dateString]['customer']) . "\n" .
                                                   "Dates: " . htmlspecialchars($dateString) . " to " . htmlspecialchars($endDate) . "\n" .
                                                   "Pick-up Time: " . ($reservedDates[$dateString]['start_time'] ? htmlspecialchars($reservedDates[$dateString]['start_time']) : 'N/A') . "\n" .
                                                   "Return Time: " . ($reservedDates[$dateString]['end_time'] ? htmlspecialchars($reservedDates[$dateString]['end_time']) : 'N/A') . "\n" .
                                                   "Total Cost: €" . number_format($reservedDates[$dateString]['total_cost'], 2) . "\n" .
                                                   "Comments: " . ($reservedDates[$dateString]['comments'] ? htmlspecialchars($reservedDates[$dateString]['comments']) : 'None');
                                        ?>
                                        <td class="reserved" colspan="<?= $count ?>" data-res-id="<?= htmlspecialchars($resId) ?>"
                                            data-bs-toggle="tooltip" data-bs-placement="top" data-bs-delay='{"show":100,"hide":100}'
                                            title="<?= $tooltip ?>"
                                            data-res-details='<?= json_encode([
                                                "reservation_id" => $resId,
                                                "customer" => $reservedDates[$dateString]['customer'],
                                                "start_date" => $reservedDates[$dateString]['start_date'],
                                                "end_date" => $reservedDates[$dateString]['end_date'],
                                                "start_time" => $reservedDates[$dateString]['start_time'] ?: 'N/A',
                                                "end_time" => $reservedDates[$dateString]['end_time'] ?: 'N/A',
                                                "total_cost" => number_format($reservedDates[$dateString]['total_cost'], 2),
                                                "comments" => $reservedDates[$dateString]['comments'] ?: 'None'
                                            ]) ?>'>
                                            <?= ($day === 1 || !isset($prevResId) || $prevResId !== $resId) ? htmlspecialchars($resId) : '' ?>
                                        </td>
                                        <?php
                                        $prevResId = $resId;
                                        $day += $count;
                                    } else {
                                        echo '<td></td>';
                                        $day++;
                                    }
                                }
                                ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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

       

        
        // Initialize Bootstrap tooltips with custom delay
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(tooltipTriggerEl => {
            new bootstrap.Tooltip(tooltipTriggerEl, {
                delay: { show: 100, hide: 100 }
            });
        });

        // Handle click on reserved cells
        document.querySelectorAll('.reserved').forEach(cell => {
            cell.addEventListener('click', () => {
                const details = JSON.parse(cell.getAttribute('data-res-details'));
                document.getElementById('res-id').textContent = details.reservation_id;
                document.getElementById('res-customer').textContent = details.customer;
                document.getElementById('res-start-date').textContent = details.start_date;
                document.getElementById('res-end-date').textContent = details.end_date;
                document.getElementById('res-start-time').textContent = details.start_time;
                document.getElementById('res-end-time').textContent = details.end_time;
                document.getElementById('res-total-cost').textContent = details.total_cost;
                document.getElementById('res-comments').textContent = details.comments;

                const modal = new bootstrap.Modal(document.getElementById('reservationDetailsModal'));
                modal.show();
            });
        });
    </script>
</body>
</html>