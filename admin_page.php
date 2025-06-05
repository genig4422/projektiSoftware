<?php
session_start();

// Redirect to login.php if user is not logged in or not an admin
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .error { color: red; }
        .success { color: green; }
        .section { margin-bottom: 30px; }
        .main-content{margin-top: 20px;}

    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#dashboard">Rental Management System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                   
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>


    <!-- Main content -->
    <div class="main-content" id="mainContent">
        <div class="container my-5" id="contentArea">
            <h2>Admin Dashboard</h2>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</p>

            <!-- Tool 1: Business and Subscription Management -->
            <div class="section">
              
                    <h3>Manage Businesses and Subscriptions</h3>
                <p><a href="add_business.php" class="btn btn-primary">Add New Business</a></p>
               
                <?php
                require_once 'config.php';
                $stmt = $conn->prepare("
                    SELECT b.business_id, b.business_name, b.address, b.contact_info, 
                           s.subscription_id, s.allowed_cars, s.start_date, s.end_date, s.amount, s.status
                    FROM businesses b
                    LEFT JOIN subscriptions s ON b.business_id = s.business_id
                ");
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    echo "<table class='table table-bordered'>";
                    echo "<thead><tr>
                            <th>Business Name</th>
                            <th>Address</th>
                            <th>Contact Info</th>
                            <th>Allowed Cars</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                          </tr></thead><tbody>";
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['business_name'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($row['address'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($row['contact_info'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($row['allowed_cars'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($row['start_date'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($row['end_date'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($row['amount'] ?? 'N/A') . "</td>";
                        echo "<td>" . htmlspecialchars($row['status'] ?? 'N/A') . "</td>";
                        echo "<td>";
                        if ($row['subscription_id']) {
                            echo "<a href='edit_subscription.php?subscription_id=" . urlencode($row['subscription_id']) . "' class='btn btn-sm btn-warning'>Edit Subscription</a> ";
                        }
                        echo "</td>";
                        echo "</tr>";
                    }
                    echo "</tbody></table>";
                } else {
                    echo "<p class='error'>No businesses found.</p>";
                }
                $stmt->close();
                ?>
            </div>

            <!-- Tool 2: System Reports -->
            <div class="section">
                <h3>System Reports</h3>
                <?php
                $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM subscriptions GROUP BY status");
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    echo "<ul>";
                    while ($row = $result->fetch_assoc()) {
                        echo "<li>" . htmlspecialchars($row['status']) . ": " . $row['count'] . " subscriptions</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p class='error'>No subscription data available.</p>";
                }
                $stmt->close();
                ?>
            </div>

           
        </div>
    </div>

    <!-- Bootstrap 5 JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>