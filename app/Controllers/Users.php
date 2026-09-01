<?php

namespace App\Controllers;

use App\Models\PermissionModel;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\AuditService;

class Users extends BaseController
{
    protected UserModel $users;
    protected PermissionModel $permissions;

    public function __construct()
    {
        $this->users = new UserModel();
        $this->permissions = new PermissionModel();
    }

    protected function requireAdmin()
    {
        if (service('session')->get('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'Administrator access required.');
        }

        return null;
    }

    public function index()
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        $q = trim((string)$this->request->getGet('q'));
        $builder = $this->users;
        if ($q !== '') {
            $builder->groupStart()->like('name', $q)->orLike('username', $q)->orLike('email', $q)->groupEnd();
        }
        return view('users/index', [
            'title' => 'Users',
            'users' => $builder->orderBy('id', 'DESC')->findAll(),
            'q' => $q,
        ]);
    }

    public function create()
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        return view('users/form', [
            'title' => 'Add User',
            'user' => null,
            'permissions' => $this->permissionList(),
            'selectedPermissions' => [],
        ]);
    }

    public function store()
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        $rules = [
            'name' => 'required|max_length[150]',
            'username' => 'required|min_length[3]|max_length[100]|is_unique[users.username]',
            'email' => 'permit_empty|valid_email|max_length[190]',
            'password' => 'required|min_length[6]|max_length[72]',
            'role' => 'required|in_list[user,security,admin]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $userId = $this->users->insert([
            'name' => trim((string) $this->request->getPost('name')),
            'username' => trim((string) $this->request->getPost('username')),
            'email' => trim((string) $this->request->getPost('email')) ?: null,
            'password' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'role' => (string) $this->request->getPost('role'),
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], true);

        $permissions = $this->request->getPost('permissions') ?? [];
        $permissions = is_array($permissions) ? $permissions : [];
        $permissions = array_values(array_intersect($permissions, array_keys($this->permissionList())));
        if ((string)$this->request->getPost('role') === 'security' && !$permissions) {
            $permissions = ['security.scan', 'security.manual_entry', 'security.history'];
        }
        $this->permissions->replaceForUser((int) $userId, $permissions);

        (new AuditService())->record('CREATE_USER','users',(int)$userId,'User created.');
        return redirect()->to('/users')->with('success', 'User created successfully.');
    }


    public function edit(int $id)
    {
        if ($response = $this->requireAdmin()) return $response;
        $user = $this->users->find($id);

        if (!$user) {
            return redirect()->to('/users')->with('error', 'User not found.');
        }

        return view('users/form', [
            'title' => 'Edit User',
            'user' => $user,
            'permissions' => $this->permissionList(),
            'selectedPermissions' => $this->permissions->getForUser($id),
        ]);
    }

    public function update(int $id)
    {
        if ($response = $this->requireAdmin()) return $response;
        $user = $this->users->find($id);

        if (!$user) {
            return redirect()->to('/users')->with('error', 'User not found.');
        }

        $rules = [
            'name' => 'required|max_length[150]',
            'username' => 'required|min_length[3]|max_length[100]',
            'email' => 'permit_empty|valid_email|max_length[190]',
            'password' => 'permit_empty|min_length[6]|max_length[72]',
            'role' => 'required|in_list[user,security,admin]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $username = trim((string) $this->request->getPost('username'));
        $duplicate = $this->users->where('username', $username)->where('id !=', $id)->first();

        if ($duplicate) {
            return redirect()->back()->withInput()->with('error', 'Username already exists.');
        }

        $data = [
            'name' => trim((string) $this->request->getPost('name')),
            'username' => $username,
            'email' => trim((string) $this->request->getPost('email')) ?: null,
            'role' => (string) $this->request->getPost('role'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $password = (string) $this->request->getPost('password');
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $this->users->update($id, $data);

        $permissions = $this->request->getPost('permissions') ?? [];
        $permissions = is_array($permissions) ? $permissions : [];
        $permissions = array_values(array_intersect($permissions, array_keys($this->permissionList())));
        $this->permissions->replaceForUser($id, $permissions);

        // If an administrator changes their own session identity, refresh the session.
        if ((int) service('session')->get('user_id') === $id) {
            service('session')->set([
                'user_name' => $data['name'],
                'username' => $data['username'],
                'role' => $data['role'],
            ]);
        }

        (new AuditService())->record('UPDATE_USER','users',$id,'User updated.',$user,$this->users->find($id));
        return redirect()->to('/users')->with('success', 'User updated successfully.');
    }

    public function delete(int $id)
    {
        if ($response = $this->requireAdmin()) return $response;
        $currentUserId = (int) service('session')->get('user_id');

        if ($id === $currentUserId) {
            return redirect()->to('/users')->with('error', 'You cannot deactivate your own account.');
        }

        $user = $this->users->find($id);

        if (!$user) {
            return redirect()->to('/users')->with('error', 'User not found.');
        }

        $this->users->update($id, [
            'status' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        (new AuditService())->record('DEACTIVATE_USER','users',$id,'User deactivated.',$user,$this->users->find($id));
        return redirect()->to('/users')->with('success', 'User deactivated successfully.');
    }

    protected function permissionList(): array
    {
        return [
            'dashboard.view' => 'View Dashboard',
            'products.view' => 'View Products',
            'products.create' => 'Create Products',
            'products.edit' => 'Edit Products',
            'products.delete' => 'Delete Products',
            'inventory.in' => 'Inventory IN',
            'inventory.out' => 'Inventory OUT',
            'inventory.view' => 'View Inventory',
            'security.scan' => 'Security Scan',
            'security.manual_entry' => 'Security Manual Entry',
            'security.history' => 'Security History',
            'reports.stock' => 'Stock Reports',
            'reports.in' => 'IN Reports',
            'reports.out' => 'OUT Reports',
            'reports.security' => 'Security Reports',
            'reports.compare' => 'IN / OUT Comparison',
            'users.view' => 'View Users',
            'users.create' => 'Create Users',
            'users.edit' => 'Edit Users',
            'users.delete' => 'Delete Users',
            'inventory.void' => 'Void Transactions',
            'audit.view' => 'View Audit Log',
            'settings.view' => 'View Settings',
            'settings.manage' => 'Manage Settings',
        ];
    }
}
