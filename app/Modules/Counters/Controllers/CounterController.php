<?php

namespace App\Modules\Counters\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Modules\Counters\Requests\CounterRequest;
use App\Modules\Counters\Services\CounterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CounterController extends Controller
{
    public function __construct(
        private readonly CounterService $counterService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.counters.index', [
            'title' => 'Counters',
            ...$this->counterService->getIndexData($request->query()),
        ]);
    }

    public function store(CounterRequest $request): RedirectResponse
    {
        $this->counterService->create($request->validated());

        return back()->with('status', 'Counter berhasil ditambahkan.');
    }

    public function update(CounterRequest $request, Counter $counter): RedirectResponse
    {
        $this->counterService->update($counter, $request->validated());

        return back()->with('status', 'Counter berhasil diperbarui.');
    }

    public function destroy(CounterRequest $request, Counter $counter): RedirectResponse
    {
        $this->counterService->delete($counter);

        return back()->with('status', 'Counter berhasil diarsipkan.');
    }
}
