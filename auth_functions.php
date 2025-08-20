<?php
require_once 'db_connection.php';

function registerUser($full_name, $user_id, $email, $password) {
    global $conn;
    
    // Determine user type based on ID length
    $user_type = (strlen($user_id) == 4) ? 'admin' : 'user';
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $conn->prepare("INSERT INTO users (user_id, full_name, email, password, user_type) 
                               VALUES (:user_id, :full_name, :email, :password, :user_type)");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':user_type', $user_type);
        
        return $stmt->execute();
    } catch(PDOException $e) {
        return false;
    }
}

function loginUser($user_id, $password) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    } catch(PDOException $e) {
        return false;
    }
}

function isUserExists($user_id, $email) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE user_id = :user_id OR email = :email");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    } catch(PDOException $e) {
        return true; // Assume user exists if there's an error to prevent duplicates
    }
}
?>