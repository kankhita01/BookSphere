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

// Handle book borrowing
if (isset($_POST['borrow'])) {
    $book_id = (int)$_POST['book_id'];
    
    try {
        // Check if user has reached max borrow limit
        $stmt = $conn->query("SELECT setting_value FROM library_settings WHERE setting_name = 'max_borrow_limit'");
        $max_borrow_limit = (int)$stmt->fetchColumn();
        
        $stmt = $conn->prepare("SELECT COUNT(*) FROM borrowings WHERE user_id = :user_id AND status = 'borrowed'");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $current_borrowings = $stmt->fetchColumn();
        
        if ($current_borrowings >= $max_borrow_limit) {
            $error = "You have reached your maximum borrowing limit of $max_borrow_limit books.";
        } else {
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
                    
                    $success = "Book borrowed successfully! Due date: " . date('M d, Y', strtotime($due_date));
                } else {
                    $error = "Failed to borrow book. Please try again.";
                }
            }
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Fetch available books
try {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $category_filter = isset($_GET['category']) ? $_GET['category'] : '';
    
    $query = "SELECT * FROM books WHERE available_quantity > 0";
    
    if (!empty($search)) {
        $query .= " AND (title LIKE :search OR author LIKE :search OR isbn LIKE :search)";
    }
    
    if (!empty($category_filter)) {
        $query .= " AND category = :category";
    }
    
    $query .= " ORDER BY title";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($search)) {
        $searchParam = "%$search%";
        $stmt->bindParam(':search', $searchParam);
    }
    
    if (!empty($category_filter)) {
        $stmt->bindParam(':category', $category_filter);
    }
    
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get distinct categories for filter
    $stmt = $conn->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND available_quantity > 0 ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get user's current borrow count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM borrowings WHERE user_id = :user_id AND status = 'borrowed'");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $current_borrow_count = $stmt->fetchColumn();
    
    // Get max borrow limit
    $stmt = $conn->query("SELECT setting_value FROM library_settings WHERE setting_name = 'max_borrow_limit'");
    $max_borrow_limit = (int)$stmt->fetchColumn();
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Books - BookSphere</title>
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
        
        .search-container {
            display: flex;
            margin-bottom: 20px;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            min-width: 200px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
        }
        
        .category-filter {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
        }
        
        .search-btn {
            padding: 10px 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .clear-search {
            padding: 10px 15px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .clear-search i {
            margin-right: 5px;
        }
        
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .book-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .book-cover {
            height: 200px;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        
        .book-cover i {
            font-size: 50px;
        }
        
        .book-details {
            padding: 15px;
        }
        
        .book-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 5px;
            color: var(--dark-color);
        }
        
        .book-author {
            color: #666;
            font-size: 14px;
            margin: 0 0 10px;
        }
        
        .book-meta {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .book-category {
            background-color: #f0f0f0;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .book-available {
            font-weight: 500;
            color: var(--success-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 50px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .borrow-limit {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .borrow-limit i {
            margin-right: 10px;
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
            
            .books-grid {
                grid-template-columns: 1fr;
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
            <li><a href="borrow_books.php" class="active"><i class="fas fa-book"></i> Borrow Books</a></li>
            <li><a href="my_borrowings.php"><i class="fas fa-exchange-alt"></i> My Borrowings</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> My Profile</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h2>Borrow Books</h2>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name']); ?>&background=4a90e2&color=fff" alt="User">
                <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            </div>
        </div>
        
        <?php if ($success != ''): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error !=''): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($current_borrow_count >= $max_borrow_limit): ?>
            <div class="borrow-limit">
                <i class="fas fa-exclamation-circle"></i>
                You have reached your maximum borrowing limit of <?php echo $max_borrow_limit; ?> books. 
                Please return some books before borrowing more.
            </div>
        <?php endif; ?>
        
        <form method="GET" action="borrow_books.php">
            <div class="search-container">
                <input type="text" class="search-input" name="search" placeholder="Search books..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                
                <select class="category-filter" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                            <?php echo ($category_filter == $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
                
                <?php if (!empty($search) || !empty($category_filter)): ?>
                    <a href="borrow_books.php" class="clear-search">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        </form>
        
        <?php if (empty($books)): ?>
            <div class="card empty-state">
                <i class="fas fa-book-open"></i>
                <h3>No Books Available</h3>
                <p>There are currently no books available for borrowing.</p>
            </div>
        <?php else: ?>
            <div class="books-grid">
                <?php foreach ($books as $book): ?>
                    <div class="book-card">
                        <div class="book-cover">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="book-details">
                            <span class="book-category"><?php echo $book['category'] ? htmlspecialchars($book['category']) : 'Uncategorized'; ?></span>
                            <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                            <p class="book-author"><?php echo htmlspecialchars($book['author']); ?></p>
                            <div class="book-meta">
                                <span>ISBN: <?php echo htmlspecialchars($book['isbn']); ?></span>
                                <span class="book-available"><?php echo $book['available_quantity']; ?> available</span>
                            </div>
                            <form method="POST" action="borrow_books.php">
                                <input type="hidden" name="book_id" value="<?php echo $book['book_id']; ?>">
                                <button type="submit" name="borrow" class="btn btn-primary" 
                                    <?php echo ($current_borrow_count >= $max_borrow_limit) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-book"></i> Borrow
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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