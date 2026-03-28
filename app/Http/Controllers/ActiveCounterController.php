<?php

namespace App\Http\Controllers;

use App\Models\Counter;
use App\Services\ActiveCounterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActiveCounterController extends Controller
{
    public function update(Request $request, ActiveCounterService $activeCounterService): RedirectResponse
    {
        $payload = $request->validate([
            'counter_id' => ['required', 'integer', Rule::exists('counters', 'id')],
        ]);

        $counter = Counter::query()
            ->where('is_active', true)
            ->findOrFail($payload['counter_id']);

        $activeCounterService->setActiveCounter($counter);

        return back()->with('status', 'Counter aktif berhasil diperbarui.');
    }
}
