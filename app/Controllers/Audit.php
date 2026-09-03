<?php

namespace App\Controllers;

use App\Models\AuditLogModel;

class Audit extends BaseController
{
    public function index()
    {
        $rows = (new AuditLogModel())->select('audit_logs.*, users.name AS user_name')->join('users','users.id=audit_logs.user_id','left')->orderBy('audit_logs.id','DESC')->findAll();
        return view('audit/index', ['title' => 'Audit Log', 'rows' => $rows]);
    }
}
