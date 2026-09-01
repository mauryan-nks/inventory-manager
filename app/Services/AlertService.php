<?php

namespace App\Services;

use App\Models\SettingModel;

class AlertService
{
    protected SettingModel $settings;

    public function __construct()
    {
        $this->settings = new SettingModel();
    }

    public function whatsappJsReady(): bool
    {
        return $this->settings->value('whatsappjs_enabled', '0') === '1';
    }

    public function send(string $event, string $message): bool
    {
        // Deliberately not connected yet. WhatsAppJS endpoint/auth/payload will be wired later.
        return false;
    }
}
