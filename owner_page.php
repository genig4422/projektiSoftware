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