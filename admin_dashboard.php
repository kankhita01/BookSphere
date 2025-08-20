<?php
session_start();
require_once 'auth_functions.php';
require_once 'db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: login.php');
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Fetch statistics from database
try {
    // Total Books
    $stmt = $conn->query("SELECT COUNT(*) FROM books");
    $total_books = $stmt->fetchColumn();
    
    // Total Users
    $stmt = $conn->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt->fetchColumn();
    
    // Active Borrowings
    $stmt = $conn->query("SELECT COUNT(*) FROM borrowings WHERE status = 'borrowed'");
    $active_borrowings = $stmt->fetchColumn();
    
    // Defaulters (users with overdue books)
    $stmt = $conn->query("SELECT COUNT(DISTINCT user_id) FROM borrowings WHERE status = 'overdue'");
    $defaulters = $stmt->fetchColumn();
    
    // Monthly borrowings data for chart
    $stmt = $conn->query("SELECT 
                            DATE_FORMAT(borrowed_date, '%Y-%m') AS month, 
                            COUNT(*) AS count 
                          FROM borrowings 
                          WHERE borrowed_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                          GROUP BY month 
                          ORDER BY month");
    $monthly_borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Book categories data for chart
    $stmt = $conn->query("SELECT category, COUNT(*) as count FROM books GROUP BY category");
    $book_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent borrowings
    $stmt = $conn->query("SELECT b.title, u.full_name, br.borrowed_date, br.due_date 
                          FROM borrowings br
                          JOIN books b ON br.book_id = b.book_id
                          JOIN users u ON br.user_id = u.user_id
                          ORDER BY br.borrowed_date DESC
                          LIMIT 5");
    $recent_borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // Handle error
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BookSphere</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #50e3c2;
            --danger-color: #ff6b6b;
            --success-color: #51cf66;
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
            color: white;
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
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        
        .stat-card.books::after {
            background: var(--primary-color);
        }
        
        .stat-card.users::after {
            background: var(--secondary-color);
        }
        
        .stat-card.borrowings::after {
            background: var(--success-color);
        }
        
        .stat-card.defaulters::after {
            background: var(--danger-color);
        }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stat-card-title {
            margin: 0;
            font-size: 16px;
            color: #666;
        }
        
        .stat-card-icon {
            font-size: 20px;
            padding: 10px;
            border-radius: 50%;
            background: rgba(74, 144, 226, 0.1);
            color: var(--primary-color);
        }
        
        .stat-card.books .stat-card-icon {
            background: rgba(74, 144, 226, 0.1);
            color: var(--primary-color);
        }
        
        .stat-card.users .stat-card-icon {
            background: rgba(80, 227, 194, 0.1);
            color: var(--secondary-color);
        }
        
        .stat-card.borrowings .stat-card-icon {
            background: rgba(81, 207, 102, 0.1);
            color: var(--success-color);
        }
        
        .stat-card.defaulters .stat-card-icon {
            background: rgba(255, 107, 107, 0.1);
            color: var(--danger-color);
        }
        
        .stat-card-value {
            font-size: 28px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-card-footer {
            display: flex;
            align-items: center;
            color: #666;
            font-size: 14px;
        }
        
        .stat-card-trend {
            display: flex;
            align-items: center;
            margin-right: 10px;
            color: var(--success-color);
        }
        
        .stat-card-trend.down {
            color: var(--danger-color);
        }
        
        .chart-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .chart-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            position: relative;
            height: 300px; /* Fixed height for charts */
        }
        
        .chart-card h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
        }
        
        .chart-wrapper {
            height: 220px; /* Fixed height for chart canvas */
        }
        
        .recent-activity {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .recent-activity h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 18px;
        }
        
        .activity-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-info {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .activity-user {
            color: #666;
            font-size: 14px;
        }
        
        .activity-date {
            color: #999;
            font-size: 12px;
            text-align: right;
        }
        
        .no-data {
            text-align: center;
            color: #999;
            padding: 40px 0;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>BookSphere</h2>
        </div>
        <ul class="sidebar-menu">
            <h3>MAIN</h3>
            <li><a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <h3>LIBRARY</h3>
            <li><a href="manage_books.php"><i class="fas fa-book"></i> Book Management</a></li>
            <li><a href="manage_borrowings.php"><i class="fas fa-exchange-alt"></i> Borrowings</a></li>
            <h3>USERS</h3>
            <li><a href="manage_users.php"><i class="fas fa-users"></i> User Management</a></li>
            <h3>SYSTEM</h3>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h2>Dashboard Overview</h2>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=4a90e2&color=fff" alt="User">
                <span><?php echo htmlspecialchars($full_name); ?></span>
            </div>
        </div>
        
        <div class="stats-container">
            <div class="stat-card books">
                <div class="stat-card-header">
                    <h3 class="stat-card-title">Total Books</h3>
                    <div class="stat-card-icon">
                        <i class="fas fa-book"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo number_format($total_books); ?></div>
                <div class="stat-card-footer">
                    <span class="stat-card-trend">
                        <i class="fas fa-arrow-up"></i> 12%
                    </span>
                    <span>From last month</span>
                </div>
            </div>
            
            <div class="stat-card users">
                <div class="stat-card-header">
                    <h3 class="stat-card-title">Total Users</h3>
                    <div class="stat-card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo number_format($total_users); ?></div>
                <div class="stat-card-footer">
                    <span class="stat-card-trend">
                        <i class="fas fa-arrow-up"></i> 8%
                    </span>
                    <span>From last month</span>
                </div>
            </div>
            
            <div class="stat-card borrowings">
                <div class="stat-card-header">
                    <h3 class="stat-card-title">Active Borrowings</h3>
                    <div class="stat-card-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo number_format($active_borrowings); ?></div>
                <div class="stat-card-footer">
                    <span class="stat-card-trend">
                        <i class="fas fa-arrow-up"></i> 5%
                    </span>
                    <span>From last month</span>
                </div>
            </div>
            
            <div class="stat-card defaulters">
                <div class="stat-card-header">
                    <h3 class="stat-card-title">Defaulters</h3>
                    <div class="stat-card-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="stat-card-value"><?php echo number_format($defaulters); ?></div>
                <div class="stat-card-footer">
                    <span class="stat-card-trend down">
                        <i class="fas fa-arrow-down"></i> 3%
                    </span>
                    <span>From last month</span>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-card">
                <h3>Monthly Borrowings</h3>
                <div class="chart-wrapper">
                    <?php if (!empty($monthly_borrowings)): ?>
                        <canvas id="borrowingsChart"></canvas>
                    <?php else: ?>
                        <div class="no-data">No borrowing data available</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="chart-card">
                <h3>Books by Category</h3>
                <div class="chart-wrapper">
                    <?php if (!empty($book_categories)): ?>
                        <canvas id="categoryChart"></canvas>
                    <?php else: ?>
                        <div class="no-data">No category data available</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="recent-activity">
            <h3>Recent Borrowings</h3>
            <?php if (!empty($recent_borrowings)): ?>
                <?php foreach ($recent_borrowings as $borrowing): ?>
                    <div class="activity-item">
                        <div class="activity-info">
                            <div class="activity-title"><?php echo htmlspecialchars($borrowing['title']); ?></div>
                            <div class="activity-user">Borrowed by <?php echo htmlspecialchars($borrowing['full_name']); ?></div>
                        </div>
                        <div class="activity-date">
                            <?php echo date('M d, Y', strtotime($borrowing['borrowed_date'])); ?><br>
                            Due: <?php echo date('M d, Y', strtotime($borrowing['due_date'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">No recent borrowings</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        <?php if (!empty($monthly_borrowings)): ?>
        // Monthly Borrowings Chart
        const borrowingsCtx = document.getElementById('borrowingsChart').getContext('2d');
        const borrowingsChart = new Chart(borrowingsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthly_borrowings, 'month')); ?>,
                datasets: [{
                    label: 'Borrowings',
                    data: <?php echo json_encode(array_column($monthly_borrowings, 'count')); ?>,
                    backgroundColor: 'rgba(74, 144, 226, 0.1)',
                    borderColor: 'rgba(74, 144, 226, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if (!empty($book_categories)): ?>
        // Category Chart (using real data from database) 
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($book_categories, 'category')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($book_categories, 'count')); ?>,
                    backgroundColor: [
                        '#0d6efd',
                        '#6f42c1',
                        '#d63384',
                        '#ff6b6b',
                        '#fd7e14',
                        '#fcc419',
                        '#20c997',
                        '#4a90e2',
                        '#50e3c2',
                        '#51cf66'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>