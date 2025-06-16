<?php
// File: includes/auth.php

/**
 * Check if user is logged in and get their role
 */
function checkAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        redirectToLogin();
        return false;
    }
    
    return true;
}

/**
 * Get current user information
 */
function getCurrentUser() {
    if (!checkAuth()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'role' => $_SESSION['user_role'],
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? ''
    ];
}

/**
 * Check if user has required permission
 */
function hasPermission($required_role) {
    $current_user = getCurrentUser();
    
    if (!$current_user) {
        return false;
    }
    
    $role_hierarchy = [
        'admin' => 3,
        'syndic' => 2,
        'resident' => 1
    ];
    
    $user_level = $role_hierarchy[$current_user['role']] ?? 0;
    $required_level = $role_hierarchy[$required_role] ?? 0;
    
    return $user_level >= $required_level;
}

/**
 * Require specific role or redirect
 */
function requireRole($required_role) {
    if (!checkAuth() || !hasPermission($required_role)) {
        redirectToLogin();
        exit();
    }
}

/**
 * Redirect to appropriate dashboard based on role
 */
function redirectToDashboard() {
    $current_user = getCurrentUser();
    
    if (!$current_user) {
        redirectToLogin();
        return;
    }
    
    $role = $current_user['role'];
    
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

/**
 * Redirect to login page
 */
function redirectToLogin() {
    header('Location: http://localhost/syndicplatform/public/login.php');
    exit();
}