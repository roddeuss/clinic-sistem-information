<?php

namespace App\Modules\Sections\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Modules\Sections\Requests\SectionRequest;
use App\Modules\Sections\Services\SectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function __construct(
        private readonly SectionService $sectionService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.sections.index', [
            'title' => 'Sections',
            ...$this->sectionService->getIndexData($request->query()),
        ]);
    }

    public function store(SectionRequest $request): RedirectResponse
    {
        $this->sectionService->create($request->validated());

        return back()->with('status', 'Section berhasil ditambahkan.');
    }

    public function update(SectionRequest $request, Section $section): RedirectResponse
    {
        $this->sectionService->update($section, $request->validated());

        return back()->with('status', 'Section berhasil diperbarui.');
    }

    public function destroy(SectionRequest $request, Section $section): RedirectResponse
    {
        $this->sectionService->delete($section);

        return back()->with('status', 'Section berhasil diarsipkan.');
    }
}
