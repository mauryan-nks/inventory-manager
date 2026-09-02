<?php

namespace App\Services;

use App\Models\AuditLogModel;

class AuditService
{
    protected AuditLogModel $logs;

    public function __construct()
    {
        $this->logs = new AuditLogModel();
    }

    public function record(
        string $action,
        string $module,
        ?int $recordId = null,
        ?string $description = null,
        mixed $oldData = null,
        mixed $newData = null
    ): void {
        $userId = service('session')->get('user_id');
        $this->logs->insert([
            'user_id' => $userId ? (int) $userId : null,
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'description' => $description,
            'old_data' => $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE),
            'new_data' => $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE),
            'ip_address' => service('request')->getIPAddress(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
