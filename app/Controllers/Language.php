<?php

namespace App\Controllers;

class Language extends BaseController
{
    public function set(string $locale)
    {
        $locale = strtolower(trim($locale));
        if (!in_array($locale, ['en', 'hi', 'hinglish'], true)) {
            return redirect()->back()->with('error', 'Unsupported language.');
        }
        service('session')->set('locale', $locale);
        $this->request->setLocale($locale === 'hinglish' ? 'en' : $locale);
        $back = (string) $this->request->getPost('redirect');
        if ($back === '' || !str_starts_with($back, '/')) $back = '/' . ltrim(uri_string(), '/');
        return redirect()->to($back);
    }
}
