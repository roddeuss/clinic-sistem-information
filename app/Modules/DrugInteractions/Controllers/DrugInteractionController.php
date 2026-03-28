<?php

namespace App\Modules\DrugInteractions\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DrugInteractionRule;
use App\Modules\DrugInteractions\Requests\DrugInteractionRequest;
use App\Modules\DrugInteractions\Services\DrugInteractionManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DrugInteractionController extends Controller
{
    public function __construct(
        private readonly DrugInteractionManagementService $drugInteractionService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.drug-interactions.index', [
            'title' => 'Drug Interactions',
            ...$this->drugInteractionService->getIndexData($request->query()),
        ]);
    }

    public function store(DrugInteractionRequest $request): RedirectResponse
    {
        $this->drugInteractionService->create($request->validated());

        return back()->with('status', 'Drug interaction rule berhasil ditambahkan.');
    }

    public function update(DrugInteractionRequest $request, DrugInteractionRule $rule): RedirectResponse
    {
        $this->drugInteractionService->update($rule, $request->validated());

        return back()->with('status', 'Drug interaction rule berhasil diperbarui.');
    }

    public function destroy(DrugInteractionRequest $request, DrugInteractionRule $rule): RedirectResponse
    {
        $this->drugInteractionService->delete($rule);

        return back()->with('status', 'Drug interaction rule berhasil diarsipkan.');
    }
}
