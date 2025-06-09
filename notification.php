<?php
$page_title = "Service Notifications";
require_once 'header.php';

// Initialize variables
$errors = [];
$notifications = [];

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

    $query = "SELECT s.service_id, s.car_id, s.service_type, s.due_date,
                     c.brand, c.model, c.license_plate
              FROM services s
              JOIN cars c ON s.car_id = c.car_id
              WHERE c.business_id = ?
                AND s.due_date >= CURDATE()
                AND s.due_date <= DATE_ADD(CURDATE(), INTERVAL 10 DAY)
              ORDER BY s.due_date ASC";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    error_log("Notifications fetched: " . print_r($notifications, true));

} catch (Exception $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}
?>

<div class="main-content" id="mainContent">
    <div class="container my-5">
        <h2 class="text-center mb-4">Service Notifications</h2>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($notifications)): ?>
            <div class="alert alert-info">No services due within the next 10 days.</div>
        <?php else: ?>
            <div class="notification-card">
                <div class="card">
                    <div class="card-header">
                        <h4>Upcoming Services (Total: <?= count($notifications) ?>)</h4>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Service ID</th>
                                    <th>Car</th>
                                    <th>Service Type</th>
                                    <th>Due Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $notification): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($notification['service_id']) ?></td>
                                        <td><?= htmlspecialchars($notification['brand'] . ' ' . $notification['model'] . ' (' . $notification['license_plate'] . ')') ?></td>
                                        <td><?= htmlspecialchars($notification['service_type']) ?></td>
                                        <td><?= htmlspecialchars(date('Y-m-d', strtotime($notification['due_date']))) ?></td>
                                        <td>
                                            <a href="services.php?car_id=<?= $notification['car_id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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

    // Update notification badge dynamically
    function updateNotificationBadge() {
        $.ajax({
            url: 'get_notifications.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                const count = response.count;
                const badge = document.querySelector('.notification-badge');
                const notificationLink = document.querySelector('a[href="notification.php"]');
                if (count > 0) {
                    if (!badge) {
                        const badgeSpan = document.createElement('span');
                        badgeSpan.className = 'notification-badge';
                        badgeSpan.textContent = count;
                        notificationLink.appendChild(badgeSpan);
                    } else {
                        badge.textContent = count;
                    }
                } else if (badge) {
                    badge.remove();
                }
            },
            error: function() {
                console.error('Failed to fetch notifications');
            }
        });
    }

    // Initial check and periodic update (every 60 seconds)
    updateNotificationBadge();
    setInterval(updateNotificationBadge, 60000);
</script>
</body>
</html>