<?php

namespace App\Controllers;

use App\Models\SettingModel;
use App\Services\AuditService;

class Settings extends BaseController
{
    public function index()
    {
        return view('settings/index', [
            'title' => 'Settings',
            'settings' => (new SettingModel())->orderBy('setting_key')->findAll(),
        ]);
    }

    public function save()
    {
        $model = new SettingModel();
        $allowed = ['app_name', 'timezone', 'whatsappjs_enabled'];

        foreach ($allowed as $key) {
            $value = trim((string) $this->request->getPost($key));
            $existing = $model->where('setting_key', $key)->first();
            $data = [
                'setting_key' => $key,
                'setting_value' => $value,
                'updated_by' => (int) service('session')->get('user_id'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($existing) {
                $model->update($existing['id'], $data);
            } else {
                $model->insert($data);
            }
        }

        $timezone = trim((string) $this->request->getPost('timezone'));
        if ($timezone !== '' && in_array($timezone, timezone_identifiers_list(), true)) {
            // Runtime setting is intentionally limited to PHP's known timezones.
            date_default_timezone_set($timezone);
        }

        (new AuditService())->record('UPDATE_SETTINGS', 'settings', null, 'Application settings updated.');
        return redirect()->to('/settings')->with('success', 'Settings saved.');
    }
}
