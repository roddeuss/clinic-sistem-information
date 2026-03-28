<?php

namespace App\Modules\Icd10\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Icd10Code;
use App\Modules\Icd10\Requests\Icd10Request;
use App\Modules\Icd10\Services\Icd10Service;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class Icd10Controller extends Controller
{
    public function __construct(
        private readonly Icd10Service $icd10Service,
    ) {
    }

    public function index(Request $request): View
    {
        return view('modules.icd10.index', $this->icd10Service->getIndexData($request->query()));
    }

    public function store(Icd10Request $request): RedirectResponse
    {
        $this->icd10Service->create($request->validated());

        return redirect()
            ->route('icd10')
            ->with('status', 'Kode ICD-10 berhasil ditambahkan.');
    }

    public function update(Icd10Request $request, Icd10Code $icd10): RedirectResponse
    {
        $this->icd10Service->update($icd10, $request->validated());

        return redirect()
            ->route('icd10')
            ->with('status', 'Kode ICD-10 berhasil diperbarui.');
    }

    public function destroy(Icd10Code $icd10): RedirectResponse
    {
        $this->icd10Service->delete($icd10);

        return redirect()
            ->route('icd10')
            ->with('status', 'Kode ICD-10 berhasil diarsipkan.');
    }
}
