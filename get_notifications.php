<?php
session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'owner') {
    echo json_encode(['count' => 0]);
    exit();
}

require_once 'config.php';

try {
    $stmt = $conn->prepare("SELECT business_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $_SESSION['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if (!$user) {
        throw new Exception("No business found for user");
    }
    $business_id = $user['business_id'];

    $query = "SELECT COUNT(*) as count
              FROM services s
              JOIN cars c ON s.car_id = c.car_id
              WHERE c.business_id = ?
                AND s.due_date >= CURDATE()
                AND s.due_date <= DATE_ADD(CURDATE(), INTERVAL 10 DAY)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    echo json_encode(['count' => $result['count']]);

} catch (Exception $e) {
    error_log("Error in get_notifications: " . $e->getMessage());
    echo json_encode(['count' => 0]);
}
?>