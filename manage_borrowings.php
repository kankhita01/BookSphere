<?php
session_start();
require_once 'auth_functions.php';
require_once 'db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: login.php');
    exit();
}

// Handle return book action
if (isset($_GET['return'])) {
    $borrowing_id = (int)$_GET['return'];
    
    try {
        // Get borrowing details first
        $stmt = $conn->prepare("SELECT book_id FROM borrowings WHERE borrowing_id = :borrowing_id");
        $stmt->bindParam(':borrowing_id', $borrowing_id);
        $stmt->execute();
        $borrowing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($borrowing) {
            // Update borrowing status
            $stmt = $conn->prepare("UPDATE borrowings SET 
                                  status = 'returned', 
                                  return_date = CURDATE() 
                                  WHERE borrowing_id = :borrowing_id");
            $stmt->bindParam(':borrowing_id', $borrowing_id);
            
            if ($stmt->execute()) {
                // Update book available quantity
                $stmt = $conn->prepare("UPDATE books SET 
                                      available_quantity = available_quantity + 1 
                                      WHERE book_id = :book_id");
                $stmt->bindParam(':book_id', $borrowing['book_id']);
                $stmt->execute();
                
                $success = "Book returned successfully!";
            } else {
                $error = "Failed to return book. Please try again.";
            }
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle renew book action
if (isset($_GET['renew'])) {
    $borrowing_id = (int)$_GET['renew'];
    
    try {
        // Get max renewals from settings
        $stmt = $conn->query("SELECT setting_value FROM library_settings WHERE setting_name = 'max_renewals'");
        $max_renewals = (int)$stmt->fetchColumn();
        
        // Get current renewal count
        $stmt = $conn->prepare("SELECT renewed_count FROM borrowings WHERE borrowing_id = :borrowing_id");
        $stmt->bindParam(':borrowing_id', $borrowing_id);
        $stmt->execute();
        $current_renewals = $stmt->fetchColumn();
        
        if ($current_renewals >= $max_renewals) {
            $error = "Maximum renewals reached for this book.";
        } else {
            // Get max borrow days from settings
            $stmt = $conn->query("SELECT setting_value FROM library_settings WHERE setting_name = 'max_borrow_days'");
            $max_borrow_days = (int)$stmt->fetchColumn();
            
            // Renew the book
            $stmt = $conn->prepare("UPDATE borrowings SET 
                                  due_date = DATE_ADD(due_date, INTERVAL :days DAY),
                                  renewed_count = renewed_count + 1 
                                  WHERE borrowing_id = :borrowing_id");
            $stmt->bindParam(':days', $max_borrow_days);
            $stmt->bindParam(':borrowing_id', $borrowing_id);
            
            if ($stmt->execute()) {
                $success = "Book renewed successfully! New due date is " . date('Y-m-d', strtotime("+$max_borrow_days days"));
            } else {
                $error = "Failed to renew book. Please try again.";
            }
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle new borrowing action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_borrowing'])) {
    $user_id = trim($_POST['user_id']);
    $book_id = (int)$_POST['book_id'];
    
    try {
        // Check if book is available
        $stmt = $conn->prepare("SELECT available_quantity FROM books WHERE book_id = :book_id");
        $stmt->bindParam(':book_id', $book_id);
        $stmt->execute();
        $available = $stmt->fetchColumn();
        
        if ($available < 1) {
            $error = "This book is not currently available.";
        } else {
            // Get max borrow days from settings
            $stmt = $conn->query("SELECT setting_value FROM library_settings WHERE setting_name = 'max_borrow_days'");
            $max_borrow_days = (int)$stmt->fetchColumn();
            $due_date = date('Y-m-d', strtotime("+$max_borrow_days days"));
            
            // Create new borrowing record
            $stmt = $conn->prepare("INSERT INTO borrowings 
                                   (user_id, book_id, borrowed_date, due_date, status) 
                                   VALUES 
                                   (:user_id, :book_id, CURDATE(), :due_date, 'borrowed')");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':book_id', $book_id);
            $stmt->bindParam(':due_date', $due_date);
            
            if ($stmt->execute()) {
                // Update book available quantity
                $stmt = $conn->prepare("UPDATE books SET 
                                      available_quantity = available_quantity - 1 
                                      WHERE book_id = :book_id");
                $stmt->bindParam(':book_id', $book_id);
                $stmt->execute();
                
                $success = "Book borrowed successfully! Due date: $due_date";
            } else {
                $error = "Failed to borrow book. Please try again.";
            }
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Fetch all borrowings
try {
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    
    $query = "SELECT b.borrowing_id, b.user_id, u.full_name as user_name, 
              bk.book_id, bk.title as book_title, 
              b.borrowed_date, b.due_date, b.return_date, 
              b.status, b.renewed_count
              FROM borrowings b
              JOIN users u ON b.user_id = u.user_id
              JOIN books bk ON b.book_id = bk.book_id";
    
    if ($status_filter != 'all') {
        $query .= " WHERE b.status = :status";
    }
    
    $query .= " ORDER BY b.borrowed_date DESC";
    
    $stmt = $conn->prepare($query);
    
    if ($status_filter != 'all') {
        $stmt->bindParam(':status', $status_filter);
    }
    
    $stmt->execute();
    $borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch available books for new borrowing
    $stmt = $conn->query("SELECT book_id, title FROM books WHERE available_quantity > 0 ORDER BY title");
    $available_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all users
    $stmt = $conn->query("SELECT user_id, full_name FROM users ORDER BY full_name");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowings Management - BookSphere</title>
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
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
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
        
        .badge-primary {
            background-color: #cfe2ff;
            color: #084298;
        }
        
        .badge-success {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        .badge-warning {
            background-color: #fff3cd;
            color: #664d03;
        }
        
        .badge-danger {
            background-color: #f8d7da;
            color: #842029;
        }
        
        .text-center {
            text-align: center;
        }
        
        .action-buttons a {
            margin-right: 5px;
        }
        
        .status-filter {
            margin-bottom: 20px;
        }
        
        .status-filter a {
            margin-right: 10px;
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            color: var(--dark-color);
            background-color: #f8f9fa;
        }
        
        .status-filter a.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .add-borrowing-form {
            margin-bottom: 20px;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 10px;
        }
        
        @media (max-width: 768px) {
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
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
        
        .due-date {
            font-weight: 500;
        }
        
        .overdue {
            color: var(--danger-color);
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
            <li><a href="manage_borrowings.php" class="active"><i class="fas fa-exchange-alt"></i> Borrowings</a></li>
            <h3>USERS</h3>
            <li><a href="manage_users.php"><i class="fas fa-users"></i> User Management</a></li>
            <h3>SYSTEM</h3>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h2>Borrowings Management</h2>
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
        
        <div class="card add-borrowing-form">
            <div class="card-header">
                <h3 class="card-title">Add New Borrowing</h3>
            </div>
            <form method="POST" action="manage_borrowings.php">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="user_id">User</label>
                            <select class="form-control" id="user_id" name="user_id" required>
                                <option value="">-- Select User --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo $user['user_id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="book_id">Book</label>
                            <select class="form-control" id="book_id" name="book_id" required>
                                <option value="">-- Select Book --</option>
                                <?php foreach ($available_books as $book): ?>
                                    <option value="<?php echo $book['book_id']; ?>">
                                        <?php echo htmlspecialchars($book['title']); ?> (ID: <?php echo $book['book_id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="text-center">
                    <button type="submit" name="add_borrowing" class="btn btn-primary">Borrow Book</button>
                </div>
            </form>
        </div>
        
        <div class="status-filter">
            <a href="manage_borrowings.php?status=all" class="<?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
            <a href="manage_borrowings.php?status=borrowed" class="<?php echo $status_filter == 'borrowed' ? 'active' : ''; ?>">Active</a>
            <a href="manage_borrowings.php?status=returned" class="<?php echo $status_filter == 'returned' ? 'active' : ''; ?>">Returned</a>
            <a href="manage_borrowings.php?status=overdue" class="<?php echo $status_filter == 'overdue' ? 'active' : ''; ?>">Overdue</a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Borrowing Records</h3>
                <span class="badge badge-primary"><?php echo count($borrowings); ?> records</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Book</th>
                            <th>Borrowed Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($borrowings)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No borrowing records found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($borrowings as $borrowing): ?>
                                <?php
                                $due_date = new DateTime($borrowing['due_date']);
                                $today = new DateTime();
                                $is_overdue = ($borrowing['status'] == 'borrowed' && $due_date < $today);
                                ?>
                                <tr>
                                    <td><?php echo $borrowing['borrowing_id']; ?></td>
                                    <td><?php echo htmlspecialchars($borrowing['user_name']); ?> (<?php echo $borrowing['user_id']; ?>)</td>
                                    <td><?php echo htmlspecialchars($borrowing['book_title']); ?> (ID: <?php echo $borrowing['book_id']; ?>)</td>
                                    <td><?php echo date('M d, Y', strtotime($borrowing['borrowed_date'])); ?></td>
                                    <td class="due-date <?php echo $is_overdue ? 'overdue' : ''; ?>">
                                        <?php echo date('M d, Y', strtotime($borrowing['due_date'])); ?>
                                        <?php if ($is_overdue): ?>
                                            <br><small class="overdue">Overdue</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($borrowing['status'] == 'borrowed'): ?>
                                            <span class="badge badge-primary">Borrowed</span>
                                        <?php elseif ($borrowing['status'] == 'returned'): ?>
                                            <span class="badge badge-success">Returned</span>
                                        <?php elseif ($borrowing['status'] == 'overdue'): ?>
                                            <span class="badge badge-danger">Overdue</span>
                                        <?php endif; ?>
                                        <?php if ($borrowing['renewed_count'] > 0): ?>
                                            <br><small>Renewed <?php echo $borrowing['renewed_count']; ?> time(s)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <?php if ($borrowing['status'] == 'borrowed'): ?>
                                            <a href="manage_borrowings.php?return=<?php echo $borrowing['borrowing_id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-undo"></i> Return
                                            </a>
                                            <a href="manage_borrowings.php?renew=<?php echo $borrowing['borrowing_id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-sync"></i> Renew
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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