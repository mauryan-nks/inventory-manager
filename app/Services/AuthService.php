<?php

namespace App\Services;

use App\Models\PermissionModel;
use App\Models\UserModel;

class AuthService
{
    protected UserModel $users;
    protected PermissionModel $permissions;

    public function __construct()
    {
        $this->users = new UserModel();
        $this->permissions = new PermissionModel();
    }

    public function attempt(string $username, string $password): bool
    {
        $user = $this->users->findActiveByUsername(trim($username));

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        $session = service('session');
        $session->regenerate(true);

        $session->set([
            'is_logged_in' => true,
            'user_id' => (int) $user['id'],
            'user_name' => $user['name'],
            'username' => $user['username'],
            'role' => $user['role'],
        ]);

        $this->users->update($user['id'], [
            'last_login' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function logout(): void
    {
        $session = service('session');
        $session->remove([
            'is_logged_in',
            'user_id',
            'user_name',
            'username',
            'role',
        ]);
        $session->regenerate(true);
    }

    public function check(): bool
    {
        return (bool) service('session')->get('is_logged_in');
    }

    public function userId(): ?int
    {
        $id = service('session')->get('user_id');
        return $id ? (int) $id : null;
    }

    public function can(string $permission): bool
    {
        $userId = $this->userId();

        if (!$userId) {
            return false;
        }

        if (service('session')->get('role') === 'admin') {
            return true;
        }

        return $this->permissions->hasPermission($userId, $permission);
    }
}
