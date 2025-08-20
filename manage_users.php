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
    // Add new user
    if (isset($_POST['add_user'])) {
        $user_id = trim($_POST['user_id']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $user_type = trim($_POST['user_type']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users 
                                   (user_id, full_name, email, password, user_type, phone, address) 
                                   VALUES 
                                   (:user_id, :full_name, :email, :password, :user_type, :phone, :address)");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':user_type', $user_type);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            
            if ($stmt->execute()) {
                $success = "User added successfully!";
            } else {
                $error = "Failed to add user. Please try again.";
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
    
    // Update user
    if (isset($_POST['update_user'])) {
        $original_id = trim($_POST['original_id']);
        $user_id = trim($_POST['user_id']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $user_type = trim($_POST['user_type']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        try {
            $stmt = $conn->prepare("UPDATE users SET 
                                  user_id = :user_id, 
                                  full_name = :full_name, 
                                  email = :email, 
                                  user_type = :user_type, 
                                  phone = :phone, 
                                  address = :address 
                                  WHERE user_id = :original_id");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':user_type', $user_type);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':original_id', $original_id);
            
            if ($stmt->execute()) {
                $success = "User updated successfully!";
                // Keep the form open for further editing
                $edit_user = [
                    'user_id' => $user_id,
                    'full_name' => $full_name,
                    'email' => $email,
                    'user_type' => $user_type,
                    'phone' => $phone,
                    'address' => $address
                ];
            } else {
                $error = "Failed to update user. Please try again.";
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
    
    // Update password
    if (isset($_POST['update_password'])) {
        $user_id = trim($_POST['user_id']);
        $new_password = trim($_POST['new_password']);
        
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET 
                                  password = :password 
                                  WHERE user_id = :user_id");
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                $success = "Password updated successfully!";
            } else {
                $error = "Failed to update password. Please try again.";
            }
        } catch(PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $user_id = $_GET['delete'];
    
    try {
        // Check if user has active borrowings
        $stmt = $conn->prepare("SELECT COUNT(*) FROM borrowings WHERE user_id = :user_id AND status = 'borrowed'");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $active_borrowings = $stmt->fetchColumn();
        
        if ($active_borrowings > 0) {
            $error = "Cannot delete user. They have active book borrowings.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id);
            
            if ($stmt->execute()) {
                $success = "User deleted successfully!";
            } else {
                $error = "Failed to delete user. Please try again.";
            }
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Fetch all users
try {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $query = "SELECT * FROM users";
    
    if (!empty($search)) {
        $query .= " WHERE user_id LIKE :search OR full_name LIKE :search OR email LIKE :search";
    }
    
    $query .= " ORDER BY full_name";
    $stmt = $conn->prepare($query);
    
    if (!empty($search)) {
        $searchParam = "%$search%";
        $stmt->bindParam(':search', $searchParam);
    }
    
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Get user data for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    $user_id = $_GET['edit'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get user data for password change
$password_user = null;
if (isset($_GET['change_password'])) {
    $user_id = $_GET['change_password'];
    
    try {
        $stmt = $conn->prepare("SELECT user_id, full_name FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $password_user = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>User Management - BookSphere</title>
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
        
        .add-user-form {
            margin-bottom: 20px;
            display: none;
        }
        
        .add-user-form.show {
            display: block;
        }
        
        .password-form {
            margin-bottom: 20px;
            display: none;
        }
        
        .password-form.show {
            display: block;
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
        
        .user-type-admin {
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .user-type-user {
            color: var(--success-color);
            font-weight: 500;
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
            <h3>USERS</h3>
            <li><a href="manage_users.php" class="active"><i class="fas fa-users"></i> User Management</a></li>
            <h3>SYSTEM</h3>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h2>User Management</h2>
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
        <form method="GET" action="manage_users.php">
            <div class="search-container">
                <input type="text" class="search-input" name="search" placeholder="Search users..." 
                       value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="manage_users.php" class="clear-search">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
        
        <!-- Add User Button -->
        <button id="toggleFormBtn" class="btn btn-primary toggle-form-btn">
            <i class="fas fa-plus"></i> Add New User
        </button>
        
        <!-- Add User Form (hidden by default) -->
        <div id="addUserForm" class="card add-user-form <?php echo isset($edit_user) ? 'show' : ''; ?>">
            <div class="card-header">
                <h3 class="card-title"><?php echo isset($edit_user) ? 'Edit User' : 'Add New User'; ?></h3>
            </div>
            <form method="POST" action="manage_users.php">
                <?php if (isset($edit_user)): ?>
                    <input type="hidden" name="original_id" value="<?php echo $edit_user['user_id']; ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="user_id">User ID</label>
                            <input type="text" class="form-control" id="user_id" name="user_id" 
                                   value="<?php echo isset($edit_user) ? htmlspecialchars($edit_user['user_id']) : ''; ?>" required>
                            <small class="text-muted">4 digits for admin, 5 digits for regular user</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo isset($edit_user) ? htmlspecialchars($edit_user['full_name']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo isset($edit_user) ? htmlspecialchars($edit_user['email']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <?php if (!isset($edit_user)): ?>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="user_type">User Type</label>
                            <select class="form-control" id="user_type" name="user_type" required>
                                <option value="admin" <?php echo (isset($edit_user) && $edit_user['user_type'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="user" <?php echo (isset($edit_user) && $edit_user['user_type'] == 'user') ? 'selected' : ''; ?>>Regular User</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   value="<?php echo isset($edit_user) ? htmlspecialchars($edit_user['phone']) : ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2"><?php 
                        echo isset($edit_user) ? htmlspecialchars($edit_user['address']) : ''; 
                    ?></textarea>
                </div>
                
                <div class="text-center">
                    <?php if (isset($edit_user)): ?>
                        <button type="submit" name="update_user" class="btn btn-success">Update User</button>
                        <a href="manage_users.php" class="btn btn-danger">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                        <button type="button" id="cancelAddBtn" class="btn btn-danger">Cancel</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Password Change Form (hidden by default) -->
        <?php if (isset($password_user)): ?>
            <div id="passwordForm" class="card password-form show">
                <div class="card-header">
                    <h3 class="card-title">Change Password for <?php echo htmlspecialchars($password_user['full_name']); ?></h3>
                </div>
                <form method="POST" action="manage_users.php">
                    <input type="hidden" name="user_id" value="<?php echo $password_user['user_id']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                        <a href="manage_users.php" class="btn btn-danger">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">All Users</h3>
                <span class="badge badge-primary"><?php echo count($users); ?> users</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No users found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['user_id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php if ($user['user_type'] == 'admin'): ?>
                                            <span class="user-type-admin">Admin</span>
                                        <?php else: ?>
                                            <span class="user-type-user">User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $user['phone'] ? htmlspecialchars($user['phone']) : '-'; ?></td>
                                    <td class="action-buttons">
                                        <a href="manage_users.php?edit=<?php echo $user['user_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="manage_users.php?change_password=<?php echo $user['user_id']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-key"></i> Password
                                        </a>
                                        <a href="manage_users.php?delete=<?php echo $user['user_id']; ?>" class="btn btn-danger btn-sm" 
                                           onclick="return confirm('Are you sure you want to delete this user?')">
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
            const addUserForm = document.getElementById('addUserForm');
            const cancelAddBtn = document.getElementById('cancelAddBtn');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            
            // Toggle form visibility
            if (toggleFormBtn) {
                toggleFormBtn.addEventListener('click', function() {
                    addUserForm.classList.toggle('show');
                    
                    // Change button text and icon
                    if (addUserForm.classList.contains('show')) {
                        toggleFormBtn.innerHTML = '<i class="fas fa-minus"></i> Hide Form';
                    } else {
                        toggleFormBtn.innerHTML = '<i class="fas fa-plus"></i> Add New User';
                    }
                });
            }
            
            // Cancel button functionality
            if (cancelAddBtn) {
                cancelAddBtn.addEventListener('click', function() {
                    addUserForm.classList.remove('show');
                    toggleFormBtn.innerHTML = '<i class="fas fa-plus"></i> Add New User';
                    // Reset form if needed
                    document.querySelector('#addUserForm form').reset();
                });
            }
            
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
            
            // If editing a user or changing password, show the appropriate form
            <?php if (isset($edit_user)): ?>
                toggleFormBtn.innerHTML = '<i class="fas fa-minus"></i> Hide Form';
            <?php endif; ?>
            
            // Password confirmation validation
            const passwordForm = document.getElementById('passwordForm');
            if (passwordForm) {
                const form = passwordForm.querySelector('form');
                form.addEventListener('submit', function(e) {
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('Passwords do not match!');
                    }
                });
            }
        });
    </script>
</body>
</html>