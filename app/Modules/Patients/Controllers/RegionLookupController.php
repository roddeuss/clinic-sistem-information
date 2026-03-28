<?php

namespace App\Modules\Patients\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Regions\IndonesiaRegionApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RegionLookupController extends Controller
{
    public function __construct(
        private readonly IndonesiaRegionApiService $regionApiService,
    ) {
    }

    public function provinces(): JsonResponse
    {
        return $this->respond(fn () => $this->regionApiService->provinces());
    }

    public function cities(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->regionApiService->cities((string) $request->query('province_code')));
    }

    public function districts(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->regionApiService->districts((string) $request->query('city_code')));
    }

    public function villages(Request $request): JsonResponse
    {
        return $this->respond(fn () => $this->regionApiService->villages((string) $request->query('district_code')));
    }

    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json([
                'data' => $callback(),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'data' => [],
            ], 503);
        }
    }
}
