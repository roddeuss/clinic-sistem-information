<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    public function index()
    {
        return view('dashboard', [
            'title' => 'Clinic Dashboard',
            ...$this->dashboardService->getOverview(),
        ]);
    }
}
