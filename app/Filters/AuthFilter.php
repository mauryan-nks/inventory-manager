<?php

namespace App\Filters;

use App\Services\AuthService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = new AuthService();

        if (!$auth->check()) {
            return redirect()->to('/login')->with('error', 'Please login first.');
        }

        if (!empty($arguments)) {
            foreach ($arguments as $permission) {
                if ($auth->can($permission)) {
                    return null;
                }
            }

            return redirect()->to('/dashboard')->with('error', 'You do not have permission to access this page.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
