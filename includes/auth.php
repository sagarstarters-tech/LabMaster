<?php
/**
 * LabMaster - Authentication & Role-Based Access Control (RBAC)
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

class Auth
{
    /**
     * Check if user is currently logged in
     */
    public static function check(): bool
    {
        return !empty($_SESSION['user']['id']);
    }

    /**
     * Get currently logged-in user array
     */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Get currently logged-in user ID
     */
    public static function id(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    }

    /**
     * Get current user role slug
     */
    public static function role(): ?string
    {
        return $_SESSION['user']['role_slug'] ?? null;
    }

    /**
     * Check if user has specific permission
     */
    public static function can(string $permissionSlug): bool
    {
        if (!self::check()) {
            return false;
        }

        // Super Admin has all permissions
        if (self::role() === 'super_admin' || (int)($_SESSION['user']['role_id'] ?? 0) === 1 || ($_SESSION['user']['username'] ?? '') === 'admin') {
            return true;
        }

        // Auto-heal session if permissions array is missing
        if (!isset($_SESSION['user']['permissions']) && !empty($_SESSION['user']['role_id'])) {
            $permsRows = Database::fetchAll(
                "SELECT p.slug FROM `permissions` p 
                 JOIN `role_permissions` rp ON p.id = rp.permission_id 
                 WHERE rp.role_id = ?",
                [$_SESSION['user']['role_id']]
            );
            $_SESSION['user']['permissions'] = array_column($permsRows, 'slug');
        }

        $perms = $_SESSION['user']['permissions'] ?? [];
        return in_array($permissionSlug, $perms, true);
    }

    /**
     * Check if user has ANY of the specified permissions
     */
    public static function canAny(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (self::can($p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has ALL of the specified permissions
     */
    public static function canAll(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (!self::can($p)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Helper check: Super Administrator
     */
    public static function isSuperAdmin(): bool
    {
        return self::role() === 'super_admin' || (int)($_SESSION['user']['role_id'] ?? 0) === 1 || ($_SESSION['user']['username'] ?? '') === 'admin';
    }

    /**
     * Helper check: Administrator (Super Admin or Lab Admin)
     */
    public static function isAdmin(): bool
    {
        return self::isSuperAdmin() || self::role() === 'lab_admin';
    }

    /**
     * Helper check: Pathologist
     */
    public static function isPathologist(): bool
    {
        return self::role() === 'pathologist';
    }

    /**
     * Helper check: Laboratory Technician
     */
    public static function isTechnician(): bool
    {
        return self::role() === 'technician';
    }

    /**
     * Helper check: Receptionist
     */
    public static function isReceptionist(): bool
    {
        return self::role() === 'receptionist';
    }

    /**
     * Refresh active user session permissions & role status from database
     */
    public static function refreshPermissions(): void
    {
        if (!self::check() || empty($_SESSION['user']['id'])) {
            return;
        }

        $userId = (int)$_SESSION['user']['id'];
        $userRow = Database::fetch(
            "SELECT u.*, r.slug AS role_slug, r.name AS role_name 
             FROM `users` u 
             JOIN `roles` r ON u.role_id = r.id 
             WHERE u.id = ? LIMIT 1",
            [$userId]
        );

        if (!$userRow || (int)$userRow['is_active'] !== 1) {
            self::logout();
            set_flash('danger', 'Your account has been deactivated or modified. Please sign in again.');
            header('Location: ' . ADMIN_URL . '/login.php');
            exit;
        }

        $_SESSION['user']['role_id'] = (int)$userRow['role_id'];
        $_SESSION['user']['role_slug'] = $userRow['role_slug'];
        $_SESSION['user']['role_name'] = $userRow['role_name'];
        $_SESSION['user']['full_name'] = $userRow['full_name'];
        $_SESSION['user']['email'] = $userRow['email'];
        $_SESSION['user']['signature_image'] = $userRow['signature_image'];

        $permsRows = Database::fetchAll(
            "SELECT p.slug FROM `permissions` p 
             JOIN `role_permissions` rp ON p.id = rp.permission_id 
             WHERE rp.role_id = ?",
            [$userRow['role_id']]
        );
        $_SESSION['user']['permissions'] = array_column($permsRows, 'slug');
    }

    /**
     * Attempt login with rate-limiting & lockout
     */
    public static function attempt(string $usernameOrEmail, string $password): array
    {
        $identifier = trim($usernameOrEmail);

        $user = Database::fetch(
            "SELECT u.*, r.slug AS role_slug, r.name AS role_name 
             FROM `users` u 
             JOIN `roles` r ON u.role_id = r.id 
             WHERE u.username = ? OR u.email = ? 
             LIMIT 1",
            [$identifier, $identifier]
        );

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid username/email or password.'];
        }

        // Check if account is locked
        if (!empty($user['lockout_until']) && strtotime($user['lockout_until']) > time()) {
            $remaining = ceil((strtotime($user['lockout_until']) - time()) / 60);
            return [
                'success' => false,
                'message' => "Account is temporarily locked due to multiple failed login attempts. Try again in {$remaining} minutes."
            ];
        }

        if ((int)$user['is_active'] !== 1) {
            return ['success' => false, 'message' => 'This account has been deactivated. Please contact laboratory management.'];
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $failed = (int)$user['failed_logins'] + 1;
            $lockout = null;

            if ($failed >= 5) {
                // Lock for 15 minutes
                $lockout = date('Y-m-d H:i:s', time() + (15 * 60));
                Database::execute(
                    "UPDATE `users` SET `failed_logins` = ?, `lockout_until` = ? WHERE `id` = ?",
                    [$failed, $lockout, $user['id']]
                );
                log_audit('Account Locked', 'Auth', (string)$user['id'], "Account locked after {$failed} failed attempts.");
                return [
                    'success' => false,
                    'message' => 'Too many failed login attempts. Account locked for 15 minutes for security.'
                ];
            }

            Database::execute(
                "UPDATE `users` SET `failed_logins` = ? WHERE `id` = ?",
                [$failed, $user['id']]
            );

            log_audit('Failed Login Attempt', 'Auth', (string)$user['id'], "Failed login attempt {$failed}/5.");
            return ['success' => false, 'message' => 'Invalid username/email or password.'];
        }

        // Reset failed logins & update last login
        Database::execute(
            "UPDATE `users` SET `failed_logins` = 0, `lockout_until` = NULL, `last_login` = NOW() WHERE `id` = ?",
            [$user['id']]
        );

        // Fetch permissions for user's role
        $permsRows = Database::fetchAll(
            "SELECT p.slug FROM `permissions` p 
             JOIN `role_permissions` rp ON p.id = rp.permission_id 
             WHERE rp.role_id = ?",
            [$user['role_id']]
        );
        $permissions = array_column($permsRows, 'slug');

        // Prevent session fixation
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'                  => (int)$user['id'],
            'username'            => $user['username'],
            'email'               => $user['email'],
            'full_name'           => $user['full_name'],
            'phone'               => $user['phone'],
            'designation'         => $user['designation'],
            'qualification'       => $user['qualification'],
            'registration_number' => $user['registration_number'],
            'signature_image'     => $user['signature_image'],
            'role_id'             => (int)$user['role_id'],
            'role_slug'           => $user['role_slug'],
            'role_name'           => $user['role_name'],
            'permissions'         => $permissions
        ];

        log_audit('User Logged In', 'Auth', (string)$user['id'], "Staff {$user['full_name']} logged in successfully.");

        return ['success' => true, 'message' => 'Login successful.'];
    }

    /**
     * Terminate user session
     */
    public static function logout(): void
    {
        if (self::check()) {
            log_audit('User Logged Out', 'Auth', (string)self::id(), "Staff {$_SESSION['user']['full_name']} logged out.");
        }
        unset($_SESSION['user']);
        session_regenerate_id(true);
    }

    /**
     * Guard: Require user to be logged in
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            set_flash('warning', 'Please sign in to access the laboratory administration portal.');
            header('Location: ' . ADMIN_URL . '/login.php');
            exit;
        }
    }

    /**
     * Guard: Require specific permission
     */
    public static function requirePermission(string $permissionSlug): void
    {
        self::requireLogin();

        if (!self::can($permissionSlug)) {
            set_flash('danger', 'Access Denied: You do not possess the required permission (' . $permissionSlug . ') for this operation.');
            header('Location: ' . ADMIN_URL . '/index.php');
            exit;
        }
    }
}
