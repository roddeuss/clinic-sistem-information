<?php

namespace App\Modules\Backups\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemBackup;
use App\Modules\Backups\Requests\BackupRequest;
use App\Modules\Backups\Services\BackupCenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(
        private readonly BackupCenterService $backupCenterService,
    ) {
    }

    public function index(Request $request)
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        return view('modules.backups.index', [
            'title' => 'Backup Center',
            ...$this->backupCenterService->getIndexData($request->query()),
        ]);
    }

    public function store(BackupRequest $request): RedirectResponse
    {
        $this->backupCenterService->createBackup($request->validated());

        return back()->with('status', 'Backup database berhasil dibuat.');
    }

    public function download(Request $request, SystemBackup $backup): BinaryFileResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        return $this->backupCenterService->downloadBackup($backup);
    }

    public function restore(BackupRequest $request, SystemBackup $backup): RedirectResponse
    {
        $this->backupCenterService->restoreBackup($backup, $request->validated());

        return back()->with('status', 'Restore backup berhasil dijalankan.');
    }

    public function destroy(BackupRequest $request, SystemBackup $backup): RedirectResponse
    {
        $this->backupCenterService->archiveBackup($backup, $request->validated());

        return back()->with('status', 'Backup berhasil diarsipkan.');
    }
}
