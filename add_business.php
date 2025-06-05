<?php
session_start();
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_name = $_POST['business_name'] ?? '';
    $address = $_POST['address'] ?? '';
    $contact_info = $_POST['contact_info'] ?? '';
    $owner_name = $_POST['owner_name'] ?? '';
    $owner_email = strtolower($_POST['owner_email'] ?? '');
    $owner_password = $_POST['owner_password'] ?? '';
    $allowed_cars = $_POST['allowed_cars'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $status = $_POST['status'] ?? '';

    // Validate input
    if (empty($business_name) || empty($address) || empty($contact_info) || 
        empty($owner_name) || empty($owner_email) || empty($owner_password) ||
        empty($allowed_cars) || empty($start_date) || empty($end_date) || 
        empty($amount) || empty($status)) {
        $message = "<p class='error'>All fields are required.</p>";
    } else {
        // Start transaction
        $conn->begin_transaction();
        try {
            // Check if owner email already exists
            $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
            $stmt->bind_param("s", $owner_email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $message = "<p class='error'>Owner email already exists.</p>";
                $stmt->close();
                $conn->rollback();
            } else {
                $stmt->close();

                // Insert new owner (user) with owner role
                $hashed_password = password_hash($owner_password, PASSWORD_DEFAULT);
                $owner_role = 'owner';
                $stmt = $conn->prepare("INSERT INTO users (email, name, password, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $owner_email, $owner_name, $hashed_password, $owner_role);
                if (!$stmt->execute()) {
                    throw new Exception("Error adding owner: " . $stmt->error);
                }
                $stmt->close();

                // Insert business
                $stmt = $conn->prepare("INSERT INTO businesses (business_name, address, contact_info) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $business_name, $address, $contact_info);
                if (!$stmt->execute()) {
                    throw new Exception("Error adding business: " . $stmt->error);
                }
                $business_id = $conn->insert_id;
                $stmt->close();

                // Update owner with business_id
                $stmt = $conn->prepare("UPDATE users SET business_id = ? WHERE email = ?");
                $stmt->bind_param("is", $business_id, $owner_email);
                if (!$stmt->execute()) {
                    throw new Exception("Error linking owner to business: " . $stmt->error);
                }
                $stmt->close();

                // Insert subscription
                $stmt = $conn->prepare("INSERT INTO subscriptions (business_id, allowed_cars, start_date, end_date, amount, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iissss", $business_id, $allowed_cars, $start_date, $end_date, $amount, $status);
                if (!$stmt->execute()) {
                    throw new Exception("Error adding subscription: " . $stmt->error);
                }
                $stmt->close();

                // Commit transaction
                $conn->commit();
                $message = "<p class='success'>Business, owner, and subscription added successfully.</p>";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<p class='error'>" . $e->getMessage() . "</p>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Business</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <div class="container my-5">
        <h2>Add New Business</h2>
        <?php echo $message; ?>
        <form action="add_business.php" method="post">
            <h4>Business Details</h4>
            <div class="mb-3">
                <label for="business_name" class="form-label">Business Name:</label>
                <input type="text" name="business_name" id="business_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">Address:</label>
                <input type="text" name="address" id="address" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="contact_info" class="form-label">Contact Info:</label>
                <input type="text" name="contact_info" id="contact_info" class="form-control" required>
            </div>
            <h4>Owner Details</h4>
            <div class="mb-3">
                <label for="owner_name" class="form-label">Owner Name:</label>
                <input type="text" name="owner_name" id="owner_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="owner_email" class="form-label">Owner Email:</label>
                <input type="email" name="owner_email" id="owner_email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="owner_password" class="form-label">Owner Password:</label>
                <input type="password" name="owner_password" id="owner_password" class="form-control" required>
            </div>
            <h4>Subscription Details</h4>
            <div class="mb-3">
                <label for="allowed_cars" class="form-label">Allowed Cars:</label>
                <input type="number" name="allowed_cars" id="allowed_cars" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="start_date" class="form-label">Start Date:</label>
                <input type="date" name="start_date" id="start_date" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="end_date" class="form-label">End Date:</label>
                <input type="date" name="end_date" id="end_date" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="amount" class="form-label">Amount:</label>
                <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="status" class="form-label">Status:</label>
                <select name="status" id="status" class="form-select" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Add Business</button>
        </form>
        <p><a href="admin_page.php" class="btn btn-secondary">Back to Dashboard</a></p>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>