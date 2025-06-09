<?php 

session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'owner') {
    header("Location: login.php");
    exit();
}
require_once 'header.php';
$email = $_SESSION['email'];

// Fetch user info
$user_sql = "SELECT * FROM users WHERE email = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("s", $email);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

$business_id = $user['business_id'];

// Fetch business info
$business_sql = "SELECT * FROM businesses WHERE business_id = ?";
$business_stmt = $conn->prepare($business_sql);
$business_stmt->bind_param("i", $business_id);
$business_stmt->execute();
$business = $business_stmt->get_result()->fetch_assoc();

// Fetch subscription info
$sub_sql = "SELECT * FROM subscriptions WHERE business_id = ? AND status = 'active' LIMIT 1";
$sub_stmt = $conn->prepare($sub_sql);
$sub_stmt->bind_param("i", $business_id);
$sub_stmt->execute();
$subscription = $sub_stmt->get_result()->fetch_assoc();

// Fetch users of this business
$users_sql = "SELECT name, email, phone, role FROM users WHERE business_id = ?";
$users_stmt = $conn->prepare($users_sql);
$users_stmt->bind_param("i", $business_id);
$users_stmt->execute();
$users = $users_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard</title>
   
</head>
<body>
  
        <!-- Main content -->
    <div class="main-content" id="mainContent">
        <div class="container mt-4">
            <!-- Welcome -->
            <div class="mb-4">
                <h2>Welcome, <?= htmlspecialchars($user['name']) ?></h2>
            </div>

            <!-- Business & Subscription Info -->
            <div class="d-flex flex-wrap gap-4 mb-4">
                <div class="card flex-fill" style="min-width: 300px;">
                    <div class="card-header bg-primary text-white">Business Information</div>
                    <div class="card-body">
                        <p><strong>Name:</strong> <?= htmlspecialchars($business['business_name']) ?></p>
                        <p><strong>Address:</strong> <?= htmlspecialchars($business['address']) ?></p>
                        <p><strong>Contact:</strong> <?= htmlspecialchars($business['contact_info']) ?></p>
                    </div>
                </div>

                <div class="card flex-fill" style="min-width: 300px;">
                    <div class="card-header bg-success text-white">Subscription Details</div>
                    <div class="card-body">
                        <p><strong>Allowed Cars:</strong> <?= $subscription['allowed_cars'] ?></p>
                        <p><strong>Start Date:</strong> <?= $subscription['start_date'] ?></p>
                        <p><strong>End Date:</strong> <?= $subscription['end_date'] ?></p>
                        <p><strong>Amount:</strong> &euro;<?= number_format($subscription['amount'], 2) ?></p>
                        <p><strong>Status:</strong> <?= ucfirst($subscription['status']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Add New User Button -->
            <div class="mb-4 text-end">
                <a href="add_user.php" class="btn btn-primary">Add Owner/Manager</a>
            </div>

            <!-- Users Table -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">Users</div>
                <div class="card-body">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td><?= htmlspecialchars($row['phone']) ?></td>
                                    <td><?= htmlspecialchars($row['role']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Financial Reports -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">Financial Reports</div>
                <div class="card-body">
                    <p>Total Revenue (This Year): <strong>&euro;<?= number_format(12345.67, 2) ?></strong></p>
                    <p>Total Costs (Maintenance + Services): <strong>&euro;<?= number_format(2345.67, 2) ?></strong></p>
                    <p>Total Profit: <strong>&euro;<?= number_format(10000.00, 2) ?></strong></p>
                </div>
            </div>

            <!-- Most Reserved Cars -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">Top 5 Most Reserved Cars</div>
                <div class="card-body">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Car</th>
                                <th>Reservations</th>
                                <th>Revenue (€)</th>
                                <th>Costs (€)</th>
                                <th>Total (€)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Placeholder rows - dynamically populate with data -->
                            <tr>
                                <td>Car A</td>
                                <td>42</td>
                                <td>€4200</td>
                                <td>€800</td>
                                <td>€3400</td>
                            </tr>
                            <!-- Repeat rows as needed -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Most Profitable Cars -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">Top 5 Most Profitable Cars</div>
                <div class="card-body">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Car</th>
                                <th>Reservations</th>
                                <th>Revenue (€)</th>
                                <th>Costs (€)</th>
                                <th>Profit (€)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Placeholder rows - dynamically populate with data -->
                            <tr>
                                <td>Car B</td>
                                <td>37</td>
                                <td>€5000</td>
                                <td>€1000</td>
                                <td>€4000</td>
                            </tr>
                            <!-- Repeat rows as needed -->
                        </tbody>
                    </table>
                </div>
            </div>

        </div> <!-- /.container -->

        </div>
    </div>

    </div>
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