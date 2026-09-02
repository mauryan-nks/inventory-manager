<?php

namespace App\Controllers;

class Language extends BaseController
{
    public function set()
    {
        $language = (string) $this->request->getPost('_language');
        $allowed = ['en', 'hi', 'hinglish'];

        if (!in_array($language, $allowed, true)) {
            return redirect()->back()->with('error', 'Invalid language selected.');
        }

        session()->set('locale', $language);

        $redirect = (string) $this->request->getPost('redirect');
        if ($redirect !== '' && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
            return redirect()->to(site_url(ltrim($redirect, '/')));
        }

        return redirect()->back();
    }
}
