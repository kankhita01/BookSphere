<?php
session_start();
require_once 'auth_functions.php';

$error = '';
$success = '';

// Handle registration
if (isset($_POST['register'])) {
    $full_name = trim($_POST['full_name']);
    $user_id = trim($_POST['user_id']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // Simple validation
    if (empty($full_name) || empty($user_id) || empty($email) || empty($password)) {
        $error = 'All fields are required!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format!';
    } elseif (strlen($user_id) < 4 || strlen($user_id) > 5) {
        $error = 'User ID must be 4 digits for admin or 5 digits for user!';
    } elseif (isUserExists($user_id, $email)) {
        $error = 'User ID or Email already exists!';
    } else {
        if (registerUser($full_name, $user_id, $email, $password)) {
            $success = 'Registration successful! You can now login.';
        } else {
            $error = 'Registration failed! Please try again.';
        }
    }
}

// Handle login
if (isset($_POST['login'])) {
    $user_id = trim($_POST['user_id']);
    $password = trim($_POST['password']);
    
    if (empty($user_id) || empty($password)) {
        $error = 'User ID and password are required!';
    } else {
        $user = loginUser($user_id, $password);
        if ($user) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            
            // Redirect to appropriate dashboard
            if ($user['user_type'] == 'admin') {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: user_dashboard.php');
            }
            exit();
        } else {
            $error = 'Invalid User ID or password!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication - LibManage Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap');

:root {
  --primary-color: #4a90e2;
  --secondary-color: #50e3c2;
  --white-color: #FFFFFF;
  --light-gray-color: #f6f5f7;
  --dark-text-color: #333;
  --font-family: 'Poppins', sans-serif;
}

* {
	box-sizing: border-box;
}

body {
	background: var(--light-gray-color);
	display: flex;
	justify-content: center;
	align-items: center;
	flex-direction: column;
	font-family: var(--font-family);
	height: 100vh;
	margin: -20px 0 50px;
}

h1 {
	font-weight: bold;
	margin: 0;
}

p {
	font-size: 14px;
	font-weight: 100;
	line-height: 20px;
	letter-spacing: 0.5px;
	margin: 20px 0 30px;
}

span {
	font-size: 12px;
}

a {
	color: #333;
	font-size: 14px;
	text-decoration: none;
	margin: 15px 0;
}

button {
	border-radius: 20px;
	border: 1px solid var(--primary-color);
	background-color: var(--primary-color);
	color: var(--white-color);
	font-size: 12px;
	font-weight: bold;
	padding: 12px 45px;
	letter-spacing: 1px;
	text-transform: uppercase;
	transition: transform 80ms ease-in, box-shadow 0.3s ease, background-color 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    z-index: 1;
}

button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(120deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.6s ease;
    z-index: -1;
}

button:hover::before {
    left: 100%;
}

button:hover {
    box-shadow: 0 5px 15px rgba(74, 144, 226, 0.4);
}


button:active {
	transform: scale(0.95);
}

button:focus {
	outline: none;
}

button.ghost {
	background-color: transparent;
	border-color: var(--white-color);
}

form {
	background-color: var(--white-color);
	display: flex;
	align-items: center;
	justify-content: center;
	flex-direction: column;
	padding: 0 50px;
	height: 100%;
	text-align: center;
}

input {
	background-color: #eee;
	border: none;
	padding: 12px 15px;
	margin: 8px 0;
	width: 100%;
    border-radius: 8px;
}

.auth-container {
	background-color: var(--white-color);
	border-radius: 10px;
  	box-shadow: 0 14px 28px rgba(0,0,0,0.25), 0 10px 10px rgba(0,0,0,0.22);
	position: relative;
	overflow: hidden;
	width: 768px;
	max-width: 100%;
	min-height: 480px;
}

.form-container {
	position: absolute;
	top: 0;
	height: 100%;
	transition: all 0.6s ease-in-out;
}

.sign-in-container {
	left: 0;
	width: 50%;
	z-index: 2;
}

.sign-up-container {
	left: 0;
	width: 50%;
	opacity: 0;
	z-index: 1;
}

.auth-container.right-panel-active .sign-in-container {
	transform: translateX(100%);
}

.auth-container.right-panel-active .sign-up-container {
	transform: translateX(100%);
	opacity: 1;
	z-index: 5;
	animation: show 0.6s;
}

@keyframes show {
	0%, 49.99% {
		opacity: 0;
		z-index: 1;
	}
	
	50%, 100% {
		opacity: 1;
		z-index: 5;
	}
}

.overlay-container {
	position: absolute;
	top: 0;
	left: 50%;
	width: 50%;
	height: 100%;
	overflow: hidden;
	transition: transform 0.6s ease-in-out;
	z-index: 100;
}

.auth-container.right-panel-active .overlay-container{
	transform: translateX(-100%);
}

.overlay {
	background: var(--primary-color);
	background: -webkit-linear-gradient(to right, var(--secondary-color), var(--primary-color));
	background: linear-gradient(to right, var(--secondary-color), var(--primary-color));
	background-repeat: no-repeat;
	background-size: cover;
	background-position: 0 0;
	color: var(--white-color);
	position: relative;
	left: -100%;
	height: 100%;
	width: 200%;
  	transform: translateX(0);
	transition: transform 0.6s ease-in-out;
}

.auth-container.right-panel-active .overlay {
  	transform: translateX(50%);
}

.overlay-panel {
	position: absolute;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-direction: column;
	padding: 0 40px;
	text-align: center;
	top: 0;
	height: 100%;
	width: 50%;
	transform: translateX(0);
	transition: transform 0.6s ease-in-out;
}

.overlay-left {
	transform: translateX(-20%);
}

.auth-container.right-panel-active .overlay-left {
	transform: translateX(0);
}

.overlay-right {
	right: 0;
	transform: translateX(0);
}

.auth-container.right-panel-active .overlay-right {
	transform: translateX(20%);
}

.overlay-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

.social-container {
	margin: 20px 0;
}

.social-container a {
	border: 1px solid #DDDDDD;
	border-radius: 50%;
	display: inline-flex;
	justify-content: center;
	align-items: center;
	margin: 0 5px;
	height: 40px;
	width: 40px;
    transition: background-color 0.3s, color 0.3s;
}

.social-container a:hover {
    background-color: var(--primary-color);
    color: var(--white-color);
} 
    </style>
</head>
<body>
    <div class="auth-container" id="auth-container">
        <!-- Register Form -->
        <div class="form-container sign-up-container">
            <form action="login.php" method="POST">
                <h1>Create Account</h1>
                <?php if (!empty($error) && isset($_POST['register'])): ?>
                    <div class="error-message" style="color: red; margin-bottom: 15px;"><?php echo $error; ?></div>
                <?php elseif (!empty($success)): ?>
                    <div class="success-message" style="color: green; margin-bottom: 15px;"><?php echo $success; ?></div>
                <?php endif; ?>
                <div class="social-container">
                    <a href="#" class="social"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social"><i class="fab fa-google-plus-g"></i></a>
                    <a href="#" class="social"><i class="fab fa-linkedin-in"></i></a>
                </div>
                <span>or use your email for registration</span>
                <input type="text" name="full_name" placeholder="Full Name" required />
                <input type="text" name="user_id" placeholder="ID" required />
                <input type="email" name="email" placeholder="Email" required />
                <input type="password" name="password" placeholder="Password" required />
                <button type="submit" name="register">Sign Up</button>
            </form>
        </div>
        <!-- Login Form -->
        <div class="form-container sign-in-container">
            <form action="login.php" method="POST">
                <h1>Sign in</h1>
                <?php if (!empty($error) && isset($_POST['login'])): ?>
                    <div class="error-message" style="color: red; margin-bottom: 15px;"><?php echo $error; ?></div>
                <?php endif; ?>
                <div class="social-container">
                    <a href="#" class="social"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social"><i class="fab fa-google-plus-g"></i></a>
                    <a href="#" class="social"><i class="fab fa-linkedin-in"></i></a>
                </div>
                <span>or use your account</span>
                <input type="text" name="user_id" placeholder="ID" required />
                <input type="password" name="password" placeholder="Password" required />
                <button type="submit" name="login">Sign In</button>
            </form>
        </div>
        <!-- Overlay -->
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <i class="fas fa-book-reader fa-3x overlay-icon"></i>
                    <h1>Welcome Back, Bookworm!</h1>
                    <p>Your next adventure awaits. Please sign in to continue your journey.</p>
                    <button class="ghost" id="signIn">Sign In</button>
                </div>
                <div class="overlay-panel overlay-right">
                    <i class="fas fa-book-open fa-3x overlay-icon"></i>
                    <h1>Hello, Future Reader!</h1>
                    <p>Open a book, open your mind. Join our community to start your journey.</p>
                    <button class="ghost" id="signUp">Sign Up</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const signUpButton = document.getElementById('signUp');
        const signInButton = document.getElementById('signIn');
        const container = document.getElementById('auth-container');

        signUpButton.addEventListener('click', () => {
            container.classList.add("right-panel-active");
        });

        signInButton.addEventListener('click', () => {
            container.classList.remove("right-panel-active");
        });

        // Handle URL hashes
        document.addEventListener('DOMContentLoaded', () => {
            if (window.location.hash === '#register') {
                container.classList.add("right-panel-active");
            } else {
                container.classList.remove("right-panel-active");
            }
        }); 
    </script>
</body>
</html>