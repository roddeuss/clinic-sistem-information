<?php

namespace App\Modules\AuditLogs\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AuditLogs\Services\AuditLogModuleService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogModuleService $auditLogModuleService,
    ) {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'clinic-admin']), 403);

        return view('modules.audit-logs.index', [
            'title' => 'Audit Logs',
            ...$this->auditLogModuleService->getIndexData($request->query()),
        ]);
    }
}
