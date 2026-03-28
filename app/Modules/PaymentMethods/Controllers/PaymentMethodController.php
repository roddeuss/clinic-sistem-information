<?php

namespace App\Modules\PaymentMethods\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Modules\PaymentMethods\Requests\PaymentMethodRequest;
use App\Modules\PaymentMethods\Services\PaymentMethodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly PaymentMethodService $paymentMethodService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.payment-methods.index', [
            'title' => 'Payment Methods',
            ...$this->paymentMethodService->getIndexData($request->query()),
        ]);
    }

    public function store(PaymentMethodRequest $request): RedirectResponse
    {
        $this->paymentMethodService->create($request->validated());

        return back()->with('status', 'Payment method berhasil ditambahkan.');
    }

    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->paymentMethodService->update($paymentMethod, $request->validated());

        return back()->with('status', 'Payment method berhasil diperbarui.');
    }

    public function destroy(PaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->paymentMethodService->delete($paymentMethod);

        return back()->with('status', 'Payment method berhasil diarsipkan.');
    }
}
