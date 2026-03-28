<?php

namespace App\Modules\CashierShifts\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Modules\CashierShifts\Requests\CashierShiftRequest;
use App\Modules\CashierShifts\Services\CashierShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CashierShiftController extends Controller
{
    public function __construct(
        private readonly CashierShiftService $cashierShiftService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.cashier-shifts.index', [
            'title' => 'Cashier Shifts',
            ...$this->cashierShiftService->getIndexData($request->query()),
        ]);
    }

    public function store(CashierShiftRequest $request): RedirectResponse
    {
        $this->cashierShiftService->open($request->validated());

        return back()->with('status', 'Cashier shift berhasil dibuka.');
    }

    public function close(CashierShiftRequest $request, CashierShift $cashierShift): RedirectResponse
    {
        $this->cashierShiftService->close($cashierShift, $request->validated());

        return back()->with('status', 'Cashier shift berhasil ditutup.');
    }
}
