<?php

namespace App\Controllers;

class Language extends BaseController
{
    public function set(?string $language = null)
    {
        // Accept both GET /language/{locale} and legacy POST /language requests.
        $language = $language !== null ? $language : (string) $this->request->getPost('_language');
        $allowed = ['en', 'hi', 'hinglish'];

        if (!in_array($language, $allowed, true)) {
            return redirect()->back()->with('error', 'Invalid language selected.');
        }

        session()->set('locale', $language);

        $redirect = (string) ($this->request->getGet('redirect') ?: $this->request->getPost('redirect'));
        if ($redirect !== '' && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
            return redirect()->to(site_url(ltrim($redirect, '/')));
        }

        return redirect()->back();
    }
}
