<?php

namespace App\Modules\Access\Services;

class AccessService
{
    public function __construct(
        private readonly AccessCatalogService $accessCatalogService,
    ) {
    }

    public function syncConfiguredAccess(): void
    {
        $this->accessCatalogService->syncConfiguredAccess();
    }
}
