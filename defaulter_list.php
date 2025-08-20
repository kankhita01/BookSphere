<?php
session_start();
require_once 'auth_functions.php';
require_once 'db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: login.php');
    exit();
}

// Handle sending reminders
if (isset($_GET['send_reminder'])) {
    $user_id = $_GET['send_reminder'];
    
    try {
        // In a real application, you would implement actual email sending here
        // This is just a simulation for the demo
        $success = "Reminder sent successfully to user ID: $user_id";
    } catch(Exception $e) {
        $error = "Failed to send reminder: " . $e->getMessage();
    }
}

// Calculate fines for overdue books
if (isset($_GET['calculate_fines'])) {
    try {
        // Get daily fine amount from settings
        $stmt = $conn->query("SELECT setting_value FROM library_settings WHERE setting_name = 'daily_fine'");
        $daily_fine = (float)$stmt->fetchColumn();
        
        // Find all overdue borrowings that haven't been marked as overdue yet
        $stmt = $conn->prepare("UPDATE borrowings SET 
                              status = 'overdue',
                              fine_amount = DATEDIFF(CURDATE(), due_date) * :daily_fine
                              WHERE status = 'borrowed' AND due_date < CURDATE()");
        $stmt->bindParam(':daily_fine', $daily_fine);
        $stmt->execute();
        
        $success = "Fines calculated for all overdue books";
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Fetch defaulters (users with overdue books)
try {
    $query = "SELECT u.user_id, u.full_name, u.email, 
              COUNT(b.borrowing_id) as overdue_count,
              SUM(b.fine_amount) as total_fine,
              MAX(b.due_date) as latest_due_date
              FROM borrowings b
              JOIN users u ON b.user_id = u.user_id
              WHERE b.status = 'overdue'
              GROUP BY u.user_id, u.full_name, u.email
              ORDER BY total_fine DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $defaulters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get daily fine amount for display
    $stmt = $conn->query("SELECT setting_value FROM library_settings WHERE setting_name = 'daily_fine'");
    $daily_fine = (float)$stmt->fetchColumn();
    
    // Get max borrow days for display
    $stmt = $conn->query("SELECT setting_value FROM library_settings WHERE setting_name = 'max_borrow_days'");
    $max_borrow_days = (int)$stmt->fetchColumn();
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Defaulter List - BookSphere</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #50e3c2;
            --danger-color: #ff6b6b;
            --success-color: #51cf66;
            --warning-color: #ffc107;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background: #2c3e50;
            color: #fff;
            height: 100vh;
            position: fixed;
            padding: 20px 0;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        
        .sidebar-header h2 {
            color: var(--primary-color);
            margin: 0;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
        }
        
        .sidebar-menu h3 {
            padding: 10px 20px;
            margin: 0;
            font-size: 12px;
            text-transform: uppercase;
            color: #999;
            letter-spacing: 1px;
        }
        
        .sidebar-menu li a {
            color: #fff;
            text-decoration: none;
            display: block;
            padding: 12px 20px;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu li a:hover, .sidebar-menu li a.active {
            background: rgba(74, 144, 226, 0.1);
            border-left: 3px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .alert-info {
            background-color: #cff4fc;
            color: #055160;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .card-title {
            margin: 0;
            font-size: 18px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #3a7bd5;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #e05555;
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #40b95c;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            color: var(--dark-color);
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .table tr:hover {
            background-color: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-danger {
            background-color: #f8d7da;
            color: #842029;
        }
        
        .badge-warning {
            background-color: #fff3cd;
            color: #664d03;
        }
        
        .text-center {
            text-align: center;
        }
        
        .action-buttons a {
            margin-right: 5px;
        }
        
        .fine-amount {
            font-weight: bold;
            color: var(--danger-color);
        }
        
        .library-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .library-info h3 {
            margin-top: 0;
            color: var(--primary-color);
        }
        
        .library-info p {
            margin-bottom: 5px;
        }
        
        .info-label {
            font-weight: 500;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .sidebar {
                width: 0;
                overflow: hidden;
                transition: width 0.3s;
                position: fixed;
                z-index: 1000;
            }
            
            .sidebar.show {
                width: var(--sidebar-width);
            }
            
            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 15px;
                left: 15px;
                z-index: 1001;
                background: var(--primary-color);
                color: white;
                border: none;
                border-radius: 5px;
                padding: 8px 12px;
                cursor: pointer;
            }
        }
        
        .mobile-menu-btn {
            display: none;
        }
        
        .no-defaulters {
            text-align: center;
            padding: 30px;
            color: #666;
        }
    </style>
</head>
<body>
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>BookSphere</h2>
        </div>
        <ul class="sidebar-menu">
            <h3>MAIN</h3>
            <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <h3>LIBRARY</h3>
            <li><a href="manage_books.php"><i class="fas fa-book"></i> Book Management</a></li>
            <li><a href="manage_borrowings.php"><i class="fas fa-exchange-alt"></i> Borrowings</a></li>
            <li><a href="defaulter_list.php" class="active"><i class="fas fa-exclamation-triangle"></i> Defaulter list</a></li>
            <h3>USERS</h3>
            <li><a href="manage_users.php"><i class="fas fa-users"></i> User Management</a></li>
            <h3>SYSTEM</h3>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h2>Defaulter List</h2>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name']); ?>&background=4a90e2&color=fff" alt="User">
                <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="library-info">
            <h3>Library Borrowing Policy</h3>
            <p><span class="info-label">Maximum Borrow Days:</span> <?php echo $max_borrow_days; ?> days</p>
            <p><span class="info-label">Daily Fine Amount:</span> $<?php echo number_format($daily_fine, 2); ?></p>
            <a href="manage_borrowings.php?calculate_fines=1" class="btn btn-warning">
                <i class="fas fa-calculator"></i> Calculate Fines for All Overdue Books
            </a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Users with Overdue Books</h3>
                <span class="badge badge-danger"><?php echo count($defaulters); ?> defaulters</span>
            </div>
            
            <?php if (empty($defaulters)): ?>
                <div class="no-defaulters">
                    <i class="fas fa-check-circle fa-3x" style="color: var(--success-color); margin-bottom: 15px;"></i>
                    <h3>No Defaulters Found</h3>
                    <p>All books have been returned on time.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Overdue Books</th>
                                <th>Total Fine</th>
                                <th>Latest Due Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($defaulters as $defaulter): ?>
                                <tr>
                                    <td><?php echo $defaulter['user_id']; ?></td>
                                    <td><?php echo htmlspecialchars($defaulter['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($defaulter['email']); ?></td>
                                    <td><?php echo $defaulter['overdue_count']; ?></td>
                                    <td class="fine-amount">$<?php echo number_format($defaulter['total_fine'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($defaulter['latest_due_date'])); ?></td>
                                    <td class="action-buttons">
                                        <a href="manage_borrowings.php?user_id=<?php echo $defaulter['user_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-book"></i> View Books
                                        </a>
                                        <a href="defaulter_list.php?send_reminder=<?php echo $defaulter['user_id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-envelope"></i> Send Reminder
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            
            // Mobile menu toggle
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!sidebar.contains(event.target) && event.target !== mobileMenuBtn) {
                    sidebar.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>