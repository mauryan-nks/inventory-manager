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

    protected function upsertSetting(SettingModel $model, string $key, string $value): void
    {
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

    public function save()
    {
        $model = new SettingModel();
        $allowed = ['app_name', 'company_name', 'company_address', 'company_phone', 'company_email', 'company_tax', 'company_logo', 'timezone', 'whatsappjs_enabled'];

        foreach ($allowed as $key) {
            // company_logo is managed by the upload handler below. Do not erase an
            // existing logo when the form is submitted without a new file.
            if ($key === 'company_logo' && $this->request->getFile('company_logo_upload')?->isValid()) {
                continue;
            }

            $value = trim((string) $this->request->getPost($key));
            if ($key === 'company_logo' && $value === '') {
                $value = (string) ($model->where('setting_key', $key)->first()['setting_value'] ?? '');
            }
            $this->upsertSetting($model, $key, $value);
        }

        $logoFile = $this->request->getFile('company_logo_upload');
        if ($logoFile && (int)$logoFile->getError() !== UPLOAD_ERR_NO_FILE && !$logoFile->isValid()) {
            return redirect()->back()->withInput()->with('error', 'The company logo upload failed. Please choose a valid image.');
        }
        if ($logoFile && $logoFile->isValid() && !$logoFile->hasMoved()) {
            $mime = strtolower((string) $logoFile->getMimeType());
            $allowedMimes = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
            ];
            if (!isset($allowedMimes[$mime])) {
                return redirect()->back()->withInput()->with('error', 'Logo must be a PNG, JPG/JPEG or WEBP image.');
            }

            $dir = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'company';
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                return redirect()->back()->withInput()->with('error', 'Unable to create the company logo upload directory. Please check server permissions for public/uploads.');
            }
            @chmod($dir, 0775);
            if (!is_writable($dir)) {
                return redirect()->back()->withInput()->with('error', 'Company logo directory is not writable. Please grant the web server write permission to public/uploads/company.');
            }

            $oldLogo = (string) $model->value('company_logo', '');
            $filename = 'logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $allowedMimes[$mime];
            try {
                $logoFile->move($dir, $filename);
            } catch (\Throwable $e) {
                return redirect()->back()->withInput()->with('error', 'Could not save the company logo. Check write permission on public/uploads/company.');
            }
            $publicPath = 'uploads/company/' . $filename;
            $this->upsertSetting($model, 'company_logo', $publicPath);

            // Remove only logos previously managed by this uploader. External URLs
            // or other public assets are left untouched.
            if (str_starts_with($oldLogo, 'uploads/company/') && $oldLogo !== $publicPath) {
                $oldPath = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $oldLogo);
                if (is_file($oldPath)) @unlink($oldPath);
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
