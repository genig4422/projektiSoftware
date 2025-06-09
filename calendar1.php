<?php
session_start();

// Debug: Log session data
error_log("Session data: " . print_r($_SESSION, true));

// Check session for manager role
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'manager') {
    error_log("Session check failed: email=" . ($_SESSION['email'] ?? 'unset') . ", role=" . ($_SESSION['role'] ?? 'unset'));
    header("Location: login.php");
    exit();
}

// Include header
require_once 'header1.php';

// Initialize current date with input validation
$year = isset($_GET['year']) && is_numeric($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) && is_numeric($_GET['month']) ? (int)$_GET['month'] : date('m');
try {
    $currentDate = new DateTime(sprintf("%d-%02d-01", $year, $month));
} catch (Exception $e) {
    error_log("Invalid date: " . $e->getMessage());
    $currentDate = new DateTime(date('Y-m-01'));
    $year = (int)$currentDate->format('Y');
    $month = (int)$currentDate->format('m');
}
$daysInMonth = (int)$currentDate->format('t');
$monthName = $currentDate->format('F Y');

// Prevent navigation to past months
$currentMonth = new DateTime(date('Y-m-01'));
$prevMonth = (new DateTime(sprintf("%d-%02d-01", $year, $month)))->modify('-1 month');
$allowPrev = $prevMonth >= $currentMonth;

// Prepare next month navigation
$nextMonth = (new DateTime(sprintf("%d-%02d-01", $year, $month)))->modify('+1 month');

// Initialize variables
$errors = [];
$success = '';
$carData = [];

try {
    // Include database configuration
    require_once 'config.php';
    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }

    // Fetch business_id
    $stmt = $conn->prepare("SELECT business_id FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
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
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $cars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch customers (for potential future use)
    $stmt = $conn->prepare("SELECT customer_id, name, phone, license_number FROM customers WHERE business_id = ? ORDER BY name");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch reservations
    $stmt = $conn->prepare("
        SELECT r.reservation_id, r.car_id, r.start_date, r.end_date, r.start_time, r.end_time, r.comments, r.total_cost, c.name AS customer
        FROM reservations r
        JOIN customers c ON r.customer_id = c.customer_id
        JOIN cars car ON r.car_id = car.car_id
        WHERE car.business_id = ?
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Organize reservations by car
    foreach ($cars as $car) {
        $carReservations = [];
        foreach ($reservations as $res) {
            if ($res['car_id'] === $car['car_id']) {
                $dates = [];
                try {
                    $start = new DateTime($res['start_date']);
                    $end = new DateTime($res['end_date']);
                    $interval = new DateInterval('P1D');
                    $datePeriod = new DatePeriod($start, $interval, $end->modify('+1 day'));
                    foreach ($datePeriod as $date) {
                        $dates[] = $date->format('Y-m-d');
                    }
                } catch (Exception $e) {
                    error_log("Invalid reservation dates for reservation_id {$res['reservation_id']}: " . $e->getMessage());
                    continue;
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
    $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
    error_log("Database error: " . $e->getMessage());
}
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
    <div class="main-content" id="mainContent">
        <div class="container my-5">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php elseif (empty($carData)): ?>
                <div class="alert alert-warning">No cars found for your business. Please add cars to display the schedule.</div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <div class="d-flex justify-content-between mb-3">
                <div>
                    <a href="add_reservation1.php"><button type="button" class="btn btn-success" id="addReservation">Add Reservation</button></a>
                    <?php if ($allowPrev): ?>
                        <form action="calendar1.php" method="GET" style="display: inline;">
                            <input type="hidden" name="year" value="<?= htmlspecialchars($prevMonth->format('Y')) ?>">
                            <input type="hidden" name="month" value="<?= htmlspecialchars($prevMonth->format('m')) ?>">
                            <button type="submit" class="btn btn-primary">Previous</button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary" disabled>Previous</button>
                    <?php endif; ?>
                </div>
                <h2 class="text-center"><?= htmlspecialchars($monthName) ?></h2>
                <form action="calendar1.php" method="GET" style="display: inline;">
                    <input type="hidden" name="year" value="<?= htmlspecialchars($nextMonth->format('Y')) ?>">
                    <input type="hidden" name="month" value="<?= htmlspecialchars($nextMonth->format('m')) ?>">
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
                                            title="<?= htmlspecialchars($tooltip) ?>"
                                            data-res-details='<?= json_encode([
                                                "reservation_id" => $resId,
                                                "customer" => $reservedDates[$dateString]['customer'],
                                                "start_date" => $reservedDates[$dateString]['start_date'],
                                                "end_date" => $reservedDates[$dateString]['end_date'],
                                                "start_time" => $reservedDates[$dateString]['start_time'] ?: 'N/A',
                                                "end_time" => $reservedDates[$dateString]['end_time'] ?: 'N/A',
                                                "total_cost" => number_format($reservedDates[$dateString]['total_cost'], 2),
                                                "comments" => $reservedDates[$dateString]['comments'] ?: 'None'
                                            ], JSON_HEX_QUOT | JSON_HEX_TAG) ?>'>
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

    <!-- Reservation Details Modal -->
    <div class="modal fade" id="reservationDetailsModal" tabindex="-1" aria-labelledby="reservationDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reservationDetailsModalLabel">Reservation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Reservation ID:</strong> <span id="res-id"></span></p>
                    <p><strong>Customer:</strong> <span id="res-customer"></span></p>
                    <p><strong>Start Date:</strong> <span id="res-start-date"></span></p>
                    <p><strong>End Date:</strong> <span id="res-end-date"></span></p>
                    <p><strong>Pick-up Time:</strong> <span id="res-start-time"></span></p>
                    <p><strong>Return Time:</strong> <span id="res-end-time"></span></p>
                    <p><strong>Total Cost:</strong> <span id="res-total-cost"></span></p>
                    <p><strong>Comments:</strong> <span id="res-comments"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Debug: Log when script runs
        console.log('Calendar1 script loaded');

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
            console.warn('toggleSidebar button not found');
        }

        // Initialize Bootstrap tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        if (tooltipTriggerList.length) {
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                new bootstrap.Tooltip(tooltipTriggerEl, {
                    delay: { show: 100, hide: 100 }
                });
            });
            console.log('Tooltips initialized');
        } else {
            console.warn('No tooltips found');
        }

        // Handle click on reserved cells
        const reservedCells = document.querySelectorAll('.reserved');
        if (reservedCells.length) {
            reservedCells.forEach(cell => {
                cell.addEventListener('click', () => {
                    try {
                        const details = JSON.parse(cell.getAttribute('data-res-details'));
                        const modal = document.getElementById('reservationDetailsModal');
                        if (modal) {
                            document.getElementById('res-id').textContent = details.reservation_id || 'N/A';
                            document.getElementById('res-customer').textContent = details.customer || 'N/A';
                            document.getElementById('res-start-date').textContent = details.start_date || 'N/A';
                            document.getElementById('res-end-date').textContent = details.end_date || 'N/A';
                            document.getElementById('res-start-time').textContent = details.start_time || 'N/A';
                            document.getElementById('res-end-time').textContent = details.end_time || 'N/A';
                            document.getElementById('res-total-cost').textContent = details.total_cost || 'N/A';
                            document.getElementById('res-comments').textContent = details.comments || 'N/A';
                            const bsModal = new bootstrap.Modal(modal);
                            bsModal.show();
                            console.log('Modal opened for reservation:', details.reservation_id);
                        } else {
                            console.error('Modal #reservationDetailsModal not found');
                        }
                    } catch (e) {
                        console.error('Error parsing reservation details:', e);
                    }
                });
            });
        } else {
            console.warn('No reserved cells found');
        }
    </script>
</body>
</html>