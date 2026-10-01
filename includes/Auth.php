<?php

declare(strict_types=1);

namespace App;

use Exception;

/**
 * DetalStudio - Authentication System
 * Система авторизації та управління ролями
 */

class Auth
{

    /**
     * Login user
     */
    public static function login($username, $password, $remember = false)
    {
        if (RateLimiter::isLoginBlocked($username)) {
            throw new Exception("Забагато спроб входу. Спробуйте пізніше.");
        }

        try {
            $stmt = db()->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Update last login
                $stmt = db()->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);

                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['logged_in'] = true;

                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);

                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);
                    $updStmt = db()->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $updStmt->execute([$tokenHash, $user['id']]);
                    setcookie('remember_token', $token, time() + (86400 * 30), "/", "", false, true);
                }

                // Clear rate limit attempts
                RateLimiter::clearLoginAttempts($username);

                // Audit Log
                Audit::log('login_success', 'users', $user['id']);

                return true;
            }

            // Record failed attempt
            RateLimiter::recordLoginAttempt($username);

            // Audit Log
            Audit::log('login_failed', 'users', null, null, ['username' => $username]);

            return false;
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Logout user
     */
    public static function logout()
    {
        try {
            if (isset($_SESSION['user_id'])) {
                $stmt = db()->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
            }
            if (isset($_COOKIE['remember_token'])) {
                setcookie('remember_token', '', time() - 3600, '/');
            }
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
        }

        session_destroy();
        redirect('/login.php');
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn()
    {
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            return true;
        }

        if (isset($_COOKIE['remember_token'])) {
            try {
                $token = $_COOKIE['remember_token'];
                $tokenHash = hash('sha256', $token);
                $stmt = db()->prepare("SELECT id, full_name, role FROM users WHERE remember_token = ? AND is_active = 1");
                $stmt->execute([$tokenHash]);
                $user = $stmt->fetch();

                if ($user) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['logged_in'] = true;
                    return true;
                } else {
                    setcookie('remember_token', '', time() - 3600, '/');
                }
            } catch (Exception $e) {
                // Ignore DB error silently
            }
        }

        return false;
    }

    /**
     * Get current user ID
     */
    public static function getUserId()
    {
        return $_SESSION['user_id'] ?? 0;
    }

    /**
     * Set temporary context user ID (useful for CLI/Webhooks)
     */
    public static function setContextUserId($id)
    {
        if ($id === null) {
            unset($_SESSION['user_id']);
            unset($_SESSION['logged_in']);
        } else {
            $_SESSION['user_id'] = $id;
            $_SESSION['logged_in'] = true;
        }
    }

    /**
     * Get current user role
     */
    public static function getUserRole()
    {
        return $_SESSION['user_role'] ?? 'guest';
    }

    /**
     * Get current user name
     */
    public static function getUserName()
    {
        $name = $_SESSION['user_name'] ?? '';

        // If name is empty in session, try to refresh from DB
        if (empty($name)) {
            $userId = self::getUserId();
            if ($userId) {
                $stmt = db()->prepare("SELECT full_name FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                if ($user) {
                    $_SESSION['user_name'] = $user['full_name'];
                    return $user['full_name'];
                }
            }
        }

        return $name;
    }

    /**
     * Get role label in Ukrainian
     */
    public static function getRoleLabel($role = null)
    {
        if ($role === null) {
            $role = self::getUserRole();
        }

        $labels = [
            'director' => 'Директор',
            'manager' => 'Менеджер',
            'executor' => 'Виконавець',
            'mechanic' => 'Механік',
            'washer' => 'Мийник'
        ];
        return $labels[$role] ?? $role;
    }

    /**
     * Check if user has specific role
     */
    public static function hasRole($role)
    {
        return self::getUserRole() === $role;
    }

    /**
     * Check if user has any of the specified roles
     */
    public static function hasAnyRole($roles)
    {
        return in_array(self::getUserRole(), $roles);
    }

    /**
     * Check if user is director
     */
    public static function isDirector()
    {
        return self::hasRole('director');
    }

    /**
     * Check if user is manager
     */
    public static function isManager()
    {
        return self::hasRole('manager');
    }

    /**
     * Check if user has at least the specified role permission level
     */
    public static function isAtLeast($role)
    {
        $currentRole = self::getUserRole();

        $hierarchy = [
            'guest' => 0,
            'washer' => 10,
            'mechanic' => 10,
            'executor' => 10,
            'manager' => 20,
            'director' => 30
        ];

        $currentLevel = $hierarchy[$currentRole] ?? 0;
        $requiredLevel = $hierarchy[$role] ?? 100;

        return $currentLevel >= $requiredLevel;
    }

    /**
     * Check if user is executor
     */
    public static function isExecutor()
    {
        return self::hasAnyRole(['executor', 'mechanic', 'washer']);
    }

    /**
     * Check if user is admin (director)
     */
    public static function isAdmin()
    {
        return self::hasRole('director');
    }

    /**
     * Check if user can access resource
     */
    public static function canAccess($resource)
    {
        $role = self::getUserRole();

        $permissions = [
            'director' => ['*'], // Director can access everything
            'manager' => [
                'dashboard',
                'orders',
                'tasks',
                'clients',
                'services',
                'warehouse',
                'purchases',
                'reports'
            ],
            'executor' => [
                'dashboard',
                'tasks',
                'my_tasks'
            ],
            'mechanic' => [
                'dashboard',
                'tasks',
                'my_tasks'
            ],
            'washer' => [
                'dashboard',
                'tasks',
                'my_tasks'
            ]
        ];

        if (!isset($permissions[$role])) {
            return false;
        }

        if (in_array('*', $permissions[$role])) {
            return true;
        }

        return in_array($resource, $permissions[$role]);
    }

    /**
     * Require authentication
     */
    public static function requireAuth()
    {
        if (!self::isLoggedIn()) {
            redirect('/login.php');
        }
    }

    /**
     * Require specific role
     */
    public static function requireRole($role)
    {
        self::requireAuth();

        if (!self::hasRole($role)) {
            self::denyAccess();
        }
    }

    /**
     * Require any of specified roles
     */
    public static function requireAnyRole($roles)
    {
        self::requireAuth();

        if (!self::hasAnyRole($roles)) {
            self::denyAccess();
        }
    }

    /**
     * Deny access and show error
     */
    public static function denyAccess()
    {
        http_response_code(403);
        include __DIR__ . '/../views/errors/403.php';
        exit;
    }


    /**
     * Change user password
     */
    public static function changePassword($userId, $newPassword)
    {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = db()->prepare("UPDATE users SET password = ? WHERE id = ?");
            return $stmt->execute([$hashedPassword, $userId]);
        } catch (Exception $e) {
            error_log("Failed to change password: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all users (for management)
     */
    public static function getAllUsers()
    {
        try {
            $stmt = db()->query("SELECT id, username, email, full_name, role, phone, is_active, created_at FROM users ORDER BY full_name");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user by ID
     */
    public static function getUserById($id)
    {
        try {
            $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Failed to get user: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get current user data
     */
    public static function getUser()
    {
        return self::getUserById(self::getUserId());
    }

    /**
     * Register new user (wrapper for createUser)
     */
    public static function register($username, $password, $email, $role, $fullName = null)
    {
        return self::createUser([
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'role' => $role,
            'full_name' => $fullName ?: $username
        ]);
    }

    /**
     * Create new user
     */
    public static function createUser($data)
    {
        try {
            $required = ['username', 'password', 'email', 'full_name', 'role'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

            $stmt = db()->prepare("INSERT INTO users (username, password, email, full_name, role, phone, telegram_username, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([
                $data['username'],
                $hashedPassword,
                $data['email'],
                $data['full_name'],
                $data['role'],
                $data['phone'] ?? null,
                $data['telegram_username'] ?? null
            ]);

            return db()->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to create user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user
     */
    public static function updateUser($id, $data)
    {
        try {
            $allowedFields = ['username', 'full_name', 'email', 'role', 'phone', 'telegram_username', 'is_active', 'notification_settings'];
            $updates = [];
            $values = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (isset($data['password']) && !empty($data['password'])) {
                $updates[] = "password = ?";
                $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            if (empty($updates)) {
                return true;
            }

            $values[] = $id;
            $sql = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";

            $stmt = db()->prepare($sql);
            return $stmt->execute($values);
        } catch (Exception $e) {
            error_log("Failed to update user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete (deactivate) user
     */
    public static function deleteUser($id)
    {
        try {
            // Prevent deleting self
            if ($id == self::getUserId()) {
                return "Не можна видалити власний аккаунт";
            }

            $stmt = db()->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("Failed to delete user: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Check if user is logged in (global function)
 */
function is_logged_in()
{
    return Auth::isLoggedIn();
}

/**
 * Check if user has role (global function)
 */
function user_has_role($role)
{
    return Auth::hasRole($role);
}

/**
 * Get current user ID (global function)
 */
function current_user_id()
{
    return Auth::getUserId();
}

/**
 * Get current user role (global function)
 */
function current_user_role()
{
    return Auth::getUserRole();
}

/**
 * Get current user name (global function)
 */
function current_user_name()
{
    return Auth::getUserName();
}
