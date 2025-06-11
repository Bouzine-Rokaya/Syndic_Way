<?php
// File: includes/auth.php
class RoleBasedAuth {
    private $conn;
    private $current_user;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * Check if user is logged in and get their role
     */
    public function checkAuth() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            $this->redirectToLogin();
            return false;
        }
        
        $this->current_user = [
            'id' => $_SESSION['user_id'],
            'role' => $_SESSION['user_role'],
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? ''
        ];
        
        return true;
    }
    
    /**
     * Check if user has required permission
     */
    public function hasPermission($required_role) {
        if (!$this->current_user) {
            return false;
        }
        
        $role_hierarchy = [
            'admin' => 3,
            'syndic' => 2,
            'resident' => 1
        ];
        
        $user_level = $role_hierarchy[$this->current_user['role']] ?? 0;
        $required_level = $role_hierarchy[$required_role] ?? 0;
        
        return $user_level >= $required_level;
    }
    
    /**
     * Require specific role or redirect
     */
    public function requireRole($required_role) {
        if (!$this->checkAuth() || !$this->hasPermission($required_role)) {
            $this->redirectToLogin();
            exit();
        }
    }
    
    /**
     * Get user information
     */
    public function getCurrentUser() {
        return $this->current_user;
    }
    
    /**
     * Redirect to appropriate dashboard based on role
     */
    public function redirectToDashboard() {
        $role = $this->current_user['role'];
        
        switch ($role) {
            case 'admin':
                header('Location: http://localhost/syndicplatform/admin/dashboard.php');
                break;
            case 'syndic':
                header('Location: http://localhost/syndicplatform/syndic/dashboard.php');
                break;
            case 'resident':
                header('Location: http://localhost/syndicplatform/resident/dashboard.php');
                break;
            default:
                header('Location: http://localhost/syndicplatform/public/login.php');
        }
        exit();
    }
    
    private function redirectToLogin() {
        header('Location: http://localhost/syndicplatform/public/login.php');
        exit();
    }
}