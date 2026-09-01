<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\AuditService;

class Auth extends BaseController
{
    protected AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function login()
    {
        if ($this->auth->check()) {
            return redirect()->to('/dashboard');
        }

        return view('auth/login', [
            'title' => 'Login',
        ]);
    }

    public function authenticate()
    {
        $rules = [
            'username' => 'required|min_length[3]|max_length[100]',
            'password' => 'required|min_length[1]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('error', $this->validator->getError('username') ?: $this->validator->getError('password'));
        }

        if (!$this->auth->attempt(
            (string) $this->request->getPost('username'),
            (string) $this->request->getPost('password')
        )) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid username or password.');
        }

        (new AuditService())->record('LOGIN','auth',null,'User logged in.');
        return redirect()->to('/dashboard');
    }

    public function logout()
    {
        if ($this->auth->check()) {
            (new AuditService())->record('LOGOUT','auth',null,'User logged out.');
        }
        $this->auth->logout();
        return redirect()->to('/login')->with('success', 'You have been logged out.');
    }
}
