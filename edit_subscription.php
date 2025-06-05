<?php
session_start();
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
$subscription_id = $_GET['subscription_id'] ?? '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed_cars = $_POST['allowed_cars'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $status = $_POST['status'] ?? '';

    if (empty($allowed_cars) || empty($start_date) || empty($end_date) || empty($amount) || empty($status)) {
        $message = "<p class='error'>All fields are required.</p>";
    } else {
        $stmt = $conn->prepare("UPDATE subscriptions SET allowed_cars = ?, start_date = ?, end_date = ?, amount = ?, status = ? WHERE subscription_id = ?");
        $stmt->bind_param("issssi", $allowed_cars, $start_date, $end_date, $amount, $status, $subscription_id);
        if ($stmt->execute()) {
            $message = "<p class='success'>Subscription updated successfully.</p>";
        } else {
            $message = "<p class='error'>Error updating subscription: " . $conn->error . "</p>";
        }
        $stmt->close();
    }
}

$stmt = $conn->prepare("SELECT allowed_cars, start_date, end_date, amount, status FROM subscriptions WHERE subscription_id = ?");
$stmt->bind_param("i", $subscription_id);
$stmt->execute();
$subscription = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Subscription</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <div class="container my-5">
        <h2>Edit Subscription</h2>
        <?php echo $message; ?>
        <form action="edit_subscription.php?subscription_id=<?php echo urlencode($subscription_id); ?>" method="post">
            <div class="mb-3">
                <label for="allowed_cars" class="form-label">Allowed Cars:</label>
                <input type="number" name="allowed_cars" id="allowed_cars" class="form-control" value="<?php echo htmlspecialchars($subscription['allowed_cars'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="start_date" class="form-label">Start Date:</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($subscription['start_date'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="end_date" class="form-label">End Date:</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($subscription['end_date'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="amount" class="form-label">Amount:</label>
                <input type="number" step="0.01" name="amount" id="amount" class="form-control" value="<?php echo htmlspecialchars($subscription['amount'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="status" class="form-label">Status:</label>
                <select name="status" id="status" class="form-select" required>
                    <option value="active" <?php echo ($subscription['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($subscription['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
        <p><a href="admin_page.php" class="btn btn-secondary">Back to Dashboard</a></p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>