<?php
session_start();
require_once 'auth_functions.php';
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle book return
if (isset($_GET['return'])) {
    $borrowing_id = (int)$_GET['return'];
    
    try {
        // Verify the book belongs to this user
        $stmt = $conn->prepare("SELECT book_id FROM borrowings WHERE borrowing_id = :borrowing_id AND user_id = :user_id");
        $stmt->bindParam(':borrowing_id', $borrowing_id);
        $stmt->bindParam(':user_id', $user_id);
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
            }
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle book renewal
if (isset($_GET['renew'])) {
    $borrowing_id = (int)$_GET['renew'];
    
    try {
        // Get max renewals from settings
        $stmt = $conn->query("SELECT setting_value FROM library_settings WHERE setting_name = 'max_renewals'");
        $max_renewals = (int)$stmt->fetchColumn();
        
        // Get current renewal count
        $stmt = $conn->prepare("SELECT renewed_count FROM borrowings WHERE borrowing_id = :borrowing_id AND user_id = :user_id");
        $stmt->bindParam(':borrowing_id', $borrowing_id);
        $stmt->bindParam(':user_id', $user_id);
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
                                  WHERE borrowing_id = :borrowing_id AND user_id = :user_id");
            $stmt->bindParam(':days', $max_borrow_days);
            $stmt->bindParam(':borrowing_id', $borrowing_id);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                $success = "Book renewed successfully! New due date is " . date('Y-m-d', strtotime("+$max_borrow_days days"));
            }
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Fetch user's current borrowings
try {
    $stmt = $conn->prepare("SELECT b.borrowing_id, b.book_id, bk.title, 
                           b.borrowed_date, b.due_date, b.status, b.renewed_count,
                           DATEDIFF(b.due_date, CURDATE()) as days_remaining
                           FROM borrowings b
                           JOIN books bk ON b.book_id = bk.book_id
                           WHERE b.user_id = :user_id AND b.status = 'borrowed'
                           ORDER BY b.due_date ASC");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $current_borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch user's borrowing history
    $stmt = $conn->prepare("SELECT b.borrowing_id, b.book_id, bk.title, 
                           b.borrowed_date, b.return_date, b.status
                           FROM borrowings b
                           JOIN books bk ON b.book_id = bk.book_id
                           WHERE b.user_id = :user_id AND b.status = 'returned'
                           ORDER BY b.return_date DESC
                           LIMIT 10");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $borrowing_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get max borrow limit
    $stmt = $conn->query("SELECT setting_value FROM library_settings WHERE setting_name = 'max_borrow_limit'");
    $max_borrow_limit = (int)$stmt->fetchColumn();
    
    // Get daily fine amount
    $stmt = $conn->query("SELECT setting_value FROM library_settings WHERE setting_name = 'daily_fine'");
    $daily_fine = (float)$stmt->fetchColumn();
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Borrowings - BookSphere</title>
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
        
        * {
            box-sizing: border-box;
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
            margin: 0;
        }
        
        .sidebar-menu li a {
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
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
            display: flex;
            align-items: center;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert i {
            margin-right: 10px;
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
            display: inline-flex;
            align-items: center;
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
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #e05555;
        }
        
        .btn i {
            margin-right: 8px;
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
        
        .due-soon {
            background-color: #fff3cd !important;
        }
        
        .overdue {
            background-color: #f8d7da !important;
        }
        
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 12px;
            cursor: pointer;
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .header {
                padding: 15px;
                margin-top: 50px;
            }
            
            .table-responsive {
                overflow-x: auto;
            }
            
            .action-buttons a {
                display: block;
                margin-bottom: 5px;
                margin-right: 0;
            }
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
            <li><a href="user_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="borrow_books.php"><i class="fas fa-book"></i> Borrow Books</a></li>
            <li><a href="my_borrowings.php" class="active"><i class="fas fa-exchange-alt"></i> My Borrowings</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h2>My Borrowings</h2>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name']); ?>&background=4a90e2&color=fff" alt="User">
                <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            </div>
        </div>
        
        <?php if ($success !=''): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error!=''): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Currently Borrowed Books</h3>
                <div>
                    <span class="badge badge-primary">Limit: <?php echo $max_borrow_limit; ?> books</span>
                    <span class="badge badge-danger">Daily fine: $<?php echo number_format($daily_fine, 2); ?></span>
                </div>
            </div>
            
            <?php if (empty($current_borrowings)): ?>
                <div class="text-center" style="padding: 30px;">
                    <i class="fas fa-book-open" style="font-size: 50px; color: #ddd; margin-bottom: 15px;"></i>
                    <h3>No Books Borrowed</h3>
                    <p>You haven't borrowed any books yet.</p>
                    <a href="borrow_books.php" class="btn btn-primary">
                        <i class="fas fa-book"></i> Browse Books
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Borrowed Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($current_borrowings as $borrowing): ?>
                                <tr class="<?php echo $borrowing['days_remaining'] <= 3 && $borrowing['days_remaining'] > 0 ? 'due-soon' : ''; ?>
                                           <?php echo $borrowing['days_remaining'] < 0 ? 'overdue' : ''; ?>">
                                    <td><?php echo htmlspecialchars($borrowing['title']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($borrowing['borrowed_date'])); ?></td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($borrowing['due_date'])); ?>
                                        <?php if ($borrowing['days_remaining'] < 0): ?>
                                            <br><small class="badge badge-danger">Overdue by <?php echo abs($borrowing['days_remaining']); ?> days</small>
                                        <?php elseif ($borrowing['days_remaining'] <= 3): ?>
                                            <br><small class="badge badge-warning">Due in <?php echo $borrowing['days_remaining']; ?> days</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary">Borrowed</span>
                                        <?php if ($borrowing['renewed_count'] > 0): ?>
                                            <br><small>Renewed <?php echo $borrowing['renewed_count']; ?> time(s)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="my_borrowings.php?return=<?php echo $borrowing['borrowing_id']; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-undo"></i> Return
                                        </a>
                                        <a href="my_borrowings.php?renew=<?php echo $borrowing['borrowing_id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-sync"></i> Renew
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Borrowing History</h3>
            </div>
            
            <?php if (empty($borrowing_history)): ?>
                <div class="text-center" style="padding: 30px;">
                    <i class="fas fa-history" style="font-size: 50px; color: #ddd; margin-bottom: 15px;"></i>
                    <h3>No Borrowing History</h3>
                    <p>Your borrowing history will appear here.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Borrowed Date</th>
                                <th>Returned Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($borrowing_history as $history): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($history['title']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($history['borrowed_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($history['return_date'])); ?></td>
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
            
            // Confirm before returning or renewing books
            document.querySelectorAll('a[href*="return"], a[href*="renew"]').forEach(link => {
                link.addEventListener('click', function(e) {
                    const action = this.href.includes('return') ? 'return' : 'renew';
                    const confirmMsg = action === 'return' 
                        ? 'Are you sure you want to return this book?'
                        : 'Are you sure you want to renew this book?';
                    
                    if (!confirm(confirmMsg)) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>