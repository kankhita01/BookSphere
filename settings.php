<?php
session_start();
require_once 'auth_functions.php';
require_once 'db_connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header('Location: login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    try {
        // Update each setting
        $settings = [
            'max_borrow_days' => (int)$_POST['max_borrow_days'],
            'max_renewals' => (int)$_POST['max_renewals'],
            'daily_fine' => (float)$_POST['daily_fine'],
            'max_borrow_limit' => (int)$_POST['max_borrow_limit'],
            'library_name' => trim($_POST['library_name']),
            'library_address' => trim($_POST['library_address']),
            'library_contact' => trim($_POST['library_contact']),
            'library_phone' => trim($_POST['library_phone']),
            'library_opening_hours' => trim($_POST['library_opening_hours'])
        ];
        
        foreach ($settings as $name => $value) {
            $stmt = $conn->prepare("UPDATE library_settings SET 
                                  setting_value = :value,
                                  updated_at = NOW()
                                  WHERE setting_name = :name");
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':name', $name);
            $stmt->execute();
        }
        
        $success = "Settings updated successfully!";
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Fetch current settings
try {
    $stmt = $conn->query("SELECT * FROM library_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert to associative array for easier access
    $settings_array = [];
    foreach ($settings as $setting) {
        $settings_array[$setting['setting_name']] = $setting['setting_value'];
    }
} catch(PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - BookSphere</title>
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
            transition: margin-left 0.3s;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            background: #2c3e50;
            color: #fff;
            height: 100vh;
            position: fixed;
            padding: 20px 0;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            z-index: 1000;
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
            transition: margin-left 0.3s;
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
            flex-wrap: wrap;
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
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            flex-wrap: wrap;
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
            font-size: 14px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #3a7bd5;
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .settings-section {
            margin-bottom: 30px;
        }
        
        .settings-section h3 {
            color: var(--primary-color);
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            font-size: 16px;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .setting-item {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .setting-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .setting-item label {
            font-weight: 500;
            color: #666;
            margin-bottom: 5px;
            display: block;
            font-size: 14px;
        }
        
        .setting-item input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }
        
        .setting-item input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(74, 144, 226, 0.2);
        }
        
        .setting-item small {
            display: block;
            margin-top: 5px;
            color: #777;
            font-size: 12px;
        }
        
        .text-center {
            text-align: center;
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
            transition: all 0.3s;
        }
        
        .mobile-menu-btn:hover {
            background: #3a7bd5;
        }
        
        .mobile-menu-btn:active {
            transform: scale(0.95);
        }
        
        /* Responsive styles */
        @media (max-width: 992px) {
            .settings-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }
        
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
            
            .card {
                padding: 15px;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .user-info span {
                display: none;
            }
            
            .btn {
                padding: 8px 12px;
                font-size: 13px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
        }
        
        /* Animation for form elements */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .setting-item {
            animation: fadeIn 0.3s ease-out forwards;
        }
        
        /* Delay animations for grid items */
        .setting-item:nth-child(1) { animation-delay: 0.1s; }
        .setting-item:nth-child(2) { animation-delay: 0.2s; }
        .setting-item:nth-child(3) { animation-delay: 0.3s; }
        .setting-item:nth-child(4) { animation-delay: 0.4s; }
        
        /* Custom number input arrows */
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        input[type="number"] {
            -moz-appearance: textfield;
        }
        
        /* Custom styling for the save button */
        .save-btn-container {
            position: sticky;
            bottom: 20px;
            z-index: 100;
            margin-top: 30px;
        }
        
        .save-btn {
            padding: 12px 25px;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .save-btn i {
            margin-right: 8px;
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
            <li><a href="manage_users.php"><i class="fas fa-users"></i> User Management</a></li>
            <h3>SYSTEM</h3>
            <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h2>System Settings</h2>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name']); ?>&background=4a90e2&color=fff" alt="User">
                <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="settings.php">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Library Information</h3>
                </div>
                <div class="settings-section">
                    <div class="form-group">
                        <label for="library_name">Library Name</label>
                        <input type="text" class="form-control" id="library_name" name="library_name" 
                               value="<?php echo htmlspecialchars($settings_array['library_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="library_address">Library Address</label>
                        <textarea class="form-control" id="library_address" name="library_address" rows="3"><?php 
                            echo htmlspecialchars($settings_array['library_address']); 
                        ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="library_contact">Contact Email</label>
                        <input type="email" class="form-control" id="library_contact" name="library_contact" 
                               value="<?php echo htmlspecialchars($settings_array['library_contact']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="library_phone">Contact Phone</label>
                        <input type="tel" class="form-control" id="library_phone" name="library_phone" 
                               value="<?php echo htmlspecialchars($settings_array['library_phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="library_opening_hours">Opening Hours</label>
                        <textarea class="form-control" id="library_opening_hours" name="library_opening_hours" rows="2"><?php 
                            echo htmlspecialchars($settings_array['library_opening_hours'] ?? ''); 
                        ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Borrowing Rules</h3>
                </div>
                <div class="settings-section">
                    <div class="settings-grid">
                        <div class="setting-item">
                            <label for="max_borrow_days">Maximum Borrow Days</label>
                            <input type="number" id="max_borrow_days" name="max_borrow_days" 
                                   value="<?php echo htmlspecialchars($settings_array['max_borrow_days']); ?>" min="1" required>
                            <small>How many days a book can be borrowed</small>
                        </div>
                        
                        <div class="setting-item">
                            <label for="max_renewals">Maximum Renewals</label>
                            <input type="number" id="max_renewals" name="max_renewals" 
                                   value="<?php echo htmlspecialchars($settings_array['max_renewals']); ?>" min="0" required>
                            <small>How many times a book can be renewed</small>
                        </div>
                        
                        <div class="setting-item">
                            <label for="daily_fine">Daily Fine Amount ($)</label>
                            <input type="number" step="0.01" id="daily_fine" name="daily_fine" 
                                   value="<?php echo htmlspecialchars($settings_array['daily_fine']); ?>" min="0" required>
                            <small>Fine per day for overdue books</small>
                        </div>
                        
                        <div class="setting-item">
                            <label for="max_borrow_limit">Maximum Borrow Limit</label>
                            <input type="number" id="max_borrow_limit" name="max_borrow_limit" 
                                   value="<?php echo htmlspecialchars($settings_array['max_borrow_limit']); ?>" min="1" required>
                            <small>Maximum books a user can borrow at once</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="save-btn-container text-center">
                <button type="submit" name="update_settings" class="btn btn-primary save-btn">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
        </form>
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
            
            // Add animation to form elements when they come into view
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = 1;
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, { threshold: 0.1 });
            
            document.querySelectorAll('.setting-item').forEach(item => {
                observer.observe(item);
            });
        });
    </script>
</body>
</html>