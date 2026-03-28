<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function index(Request $request): View
    {
        $currentSessionId = $request->session()->getId();
        $table = config('session.table', 'sessions');

        $sessions = DB::table($table)
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address ?: 'Unknown IP',
                'user_agent' => $session->user_agent ?: 'Unknown device',
                'last_active_at' => CarbonImmutable::createFromTimestamp($session->last_activity),
                'is_current' => $session->id === $currentSessionId,
            ]);

        return view('pages.auth.settings.sessions', [
            'sessions' => $sessions,
        ]);
    }

    public function destroy(Request $request, string $sessionId): RedirectResponse
    {
        if ($sessionId === $request->session()->getId()) {
            return back()->withErrors([
                'session' => 'You cannot revoke your current session from this screen.',
            ]);
        }

        $deleted = DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->delete();

        if (! $deleted) {
            return back()->withErrors([
                'session' => 'The selected session could not be found.',
            ]);
        }

        return back()->with('status', 'Session revoked successfully');
    }
}
