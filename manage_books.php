<?php
session_start();
require_once 'auth_functions.php';
require_once 'db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: login.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new book
    if (isset($_POST['add_book'])) {
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $isbn = trim($_POST['isbn']);
        $publisher = trim($_POST['publisher']);
        $publication_year = trim($_POST['publication_year']);
        $category = trim($_POST['category']);
        $quantity = (int)$_POST['quantity'];
        $description = trim($_POST['description']);
        
        try {
            $stmt = $conn->prepare("INSERT INTO books 
                                   (title, author, isbn, publisher, publication_year, category, quantity, available_quantity, description) 
                                   VALUES 
                                   (:title, :author, :isbn, :publisher, :publication_year, :category, :quantity, :quantity, :description)");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':author', $author);
            $stmt->bindParam(':isbn', $isbn);
            $stmt->bindParam(':publisher', $publisher);
            $stmt->bindParam(':publication_year', $publication_year);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':description', $description);
            
            if ($stmt->execute()) {
                $success = "Book added successfully!";
                // Clear the form by not setting $edit_book
            } else {
                $error = "Failed to add book. Please try again.";
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
    
    // Update book
    if (isset($_POST['update_book'])) {
        $book_id = (int)$_POST['book_id'];
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $isbn = trim($_POST['isbn']);
        $publisher = trim($_POST['publisher']);
        $publication_year = trim($_POST['publication_year']);
        $category = trim($_POST['category']);
        $quantity = (int)$_POST['quantity'];
        $description = trim($_POST['description']);
        
        try {
            // First get current quantity to calculate available quantity difference
            $stmt = $conn->prepare("SELECT quantity, available_quantity FROM books WHERE book_id = :book_id");
            $stmt->bindParam(':book_id', $book_id);
            $stmt->execute();
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $quantity_diff = $quantity - $current['quantity'];
            $new_available = $current['available_quantity'] + $quantity_diff;
            
            $stmt = $conn->prepare("UPDATE books SET 
                                  title = :title, 
                                  author = :author, 
                                  isbn = :isbn, 
                                  publisher = :publisher, 
                                  publication_year = :publication_year, 
                                  category = :category, 
                                  quantity = :quantity, 
                                  available_quantity = :available_quantity, 
                                  description = :description 
                                  WHERE book_id = :book_id");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':author', $author);
            $stmt->bindParam(':isbn', $isbn);
            $stmt->bindParam(':publisher', $publisher);
            $stmt->bindParam(':publication_year', $publication_year);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':available_quantity', $new_available);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':book_id', $book_id);
            
            if ($stmt->execute()) {
                $success = "Book updated successfully!";
                // Keep the form open for further editing
                $edit_book = ['book_id' => $book_id, 'title' => $title, 'author' => $author, 
                             'isbn' => $isbn, 'publisher' => $publisher, 'publication_year' => $publication_year,
                             'category' => $category, 'quantity' => $quantity, 'description' => $description];
            } else {
                $error = "Failed to update book. Please try again.";
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $book_id = (int)$_GET['delete'];
    
    try {
        // Check if book is currently borrowed
        $stmt = $conn->prepare("SELECT COUNT(*) FROM borrowings WHERE book_id = :book_id AND status = 'borrowed'");
        $stmt->bindParam(':book_id', $book_id);
        $stmt->execute();
        $borrowed_count = $stmt->fetchColumn();
        
        if ($borrowed_count > 0) {
            $error = "Cannot delete book. It is currently borrowed by users.";
        } else {
            $stmt = $conn->prepare("DELETE FROM books WHERE book_id = :book_id");
            $stmt->bindParam(':book_id', $book_id);
            
            if ($stmt->execute()) {
                $success = "Book deleted successfully!";
            } else {
                $error = "Failed to delete book. Please try again.";
            }
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Fetch all books
try {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $query = "SELECT * FROM books";
    
    if (!empty($search)) {
        $query .= " WHERE title LIKE :search OR author LIKE :search OR isbn LIKE :search";
    }
    
    $query .= " ORDER BY title";
    $stmt = $conn->prepare($query);
    
    if (!empty($search)) {
        $searchParam = "%$search%";
        $stmt->bindParam(':search', $searchParam);
    }
    
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get distinct categories for dropdown
    $stmt = $conn->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Get book data for editing
$edit_book = null;
if (isset($_GET['edit'])) {
    $book_id = (int)$_GET['edit'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM books WHERE book_id = :book_id");
        $stmt->bindParam(':book_id', $book_id);
        $stmt->execute();
        $edit_book = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Management - BookSphere</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
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
        
        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .text-center {
            text-align: center;
        }
        
        .action-buttons a {
            margin-right: 5px;
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
        
        .add-book-form {
            display: none;
            margin-bottom: 20px;
            animation: fadeIn 0.3s ease-in-out;
        }
        
        .add-book-form.show {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .toggle-form-btn {
            margin-bottom: 20px;
        }
        
        .search-container {
            display: flex;
            margin-bottom: 20px;
        }
        
        .search-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px 0 0 5px;
            font-family: 'Poppins', sans-serif;
        }
        
        .search-btn {
            padding: 10px 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0 5px 5px 0;
            cursor: pointer;
        }
        
        .search-btn:hover {
            background-color: #3a7bd5;
        }
        
        .clear-search {
            margin-left: 10px;
            padding: 10px 15px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .clear-search:hover {
            background-color: #5a6268;
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
            <li><a href="manage_books.php" class="active"><i class="fas fa-book"></i> Book Management</a></li>
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
            <h2>Book Management</h2>
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
        
        <!-- Search Form -->
        <form method="GET" action="manage_books.php">
            <div class="search-container">
                <input type="text" class="search-input" name="search" placeholder="Search books..." 
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="manage_books.php" class="clear-search">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
        
        <!-- Add Book Button -->
        <button id="toggleFormBtn" class="btn btn-primary toggle-form-btn">
            <i class="fas fa-plus"></i> Add New Book
        </button>
        
        <!-- Add Book Form (hidden by default) -->
        <div id="addBookForm" class="card add-book-form <?php echo isset($edit_book) ? 'show' : ''; ?>">
            <div class="card-header">
                <h3 class="card-title"><?php echo isset($edit_book) ? 'Edit Book' : 'Add New Book'; ?></h3>
            </div>
            <form method="POST" action="manage_books.php">
                <?php if (isset($edit_book)): ?>
                    <input type="hidden" name="book_id" value="<?php echo $edit_book['book_id']; ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="title">Title</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo isset($edit_book) ? htmlspecialchars($edit_book['title']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="author">Author</label>
                            <input type="text" class="form-control" id="author" name="author" 
                                   value="<?php echo isset($edit_book) ? htmlspecialchars($edit_book['author']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="isbn">ISBN</label>
                            <input type="text" class="form-control" id="isbn" name="isbn" 
                                   value="<?php echo isset($edit_book) ? htmlspecialchars($edit_book['isbn']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="publisher">Publisher</label>
                            <input type="text" class="form-control" id="publisher" name="publisher" 
                                   value="<?php echo isset($edit_book) ? htmlspecialchars($edit_book['publisher']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="publication_year">Publication Year</label>
                            <input type="number" class="form-control" id="publication_year" name="publication_year" 
                                   value="<?php echo isset($edit_book) ? htmlspecialchars($edit_book['publication_year']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select class="form-control" id="category" name="category">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" 
                                        <?php if (isset($edit_book) && $edit_book['category'] == $cat) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" 
                                   value="<?php echo isset($edit_book) ? htmlspecialchars($edit_book['quantity']) : '1'; ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php 
                        echo isset($edit_book) ? htmlspecialchars($edit_book['description']) : ''; 
                    ?></textarea>
                </div>
                
                <div class="text-center">
                    <?php if (isset($edit_book)): ?>
                        <button type="submit" name="update_book" class="btn btn-success">Update Book</button>
                        <a href="manage_books.php" class="btn btn-danger">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="add_book" class="btn btn-primary">Add Book</button>
                        <button type="button" id="cancelAddBtn" class="btn btn-danger">Cancel</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All Books</h3>
                <span class="badge badge-primary"><?php echo count($books); ?> books</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Available</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($books)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No books found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td><?php echo $book['book_id']; ?></td>
                                    <td><?php echo htmlspecialchars($book['title']); ?></td>
                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                    <td><?php echo $book['category'] ? htmlspecialchars($book['category']) : '-'; ?></td>
                                    <td><?php echo $book['quantity']; ?></td>
                                    <td>
                                        <?php if ($book['available_quantity'] > 0): ?>
                                            <span class="badge badge-success"><?php echo $book['available_quantity']; ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="manage_books.php?edit=<?php echo $book['book_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="manage_books.php?delete=<?php echo $book['book_id']; ?>" class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Are you sure you want to delete this book?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
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
            const toggleFormBtn = document.getElementById('toggleFormBtn');
            const addBookForm = document.getElementById('addBookForm');
            const cancelAddBtn = document.getElementById('cancelAddBtn');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            
            // Toggle form visibility
            toggleFormBtn.addEventListener('click', function() {
                addBookForm.classList.toggle('show');
                
                // Change button text and icon
                if (addBookForm.classList.contains('show')) {
                    toggleFormBtn.innerHTML = '<i class="fas fa-minus"></i> Hide Form';
                } else {
                    toggleFormBtn.innerHTML = '<i class="fas fa-plus"></i> Add New Book';
                }
            });
            
            // Cancel button functionality
            if (cancelAddBtn) {
                cancelAddBtn.addEventListener('click', function() {
                    addBookForm.classList.remove('show');
                    toggleFormBtn.innerHTML = '<i class="fas fa-plus"></i> Add New Book';
                    // Reset form if needed
                    document.querySelector('#addBookForm form').reset();
                });
            }
            
            // Mobile menu toggle
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
            
            // If editing a book, show the form and change the button
            <?php if (isset($edit_book)): ?>
                toggleFormBtn.innerHTML = '<i class="fas fa-minus"></i> Hide Form';
            <?php endif; ?>
            
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