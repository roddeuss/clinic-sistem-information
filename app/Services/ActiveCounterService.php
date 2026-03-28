<?php

namespace App\Services;

use App\Models\Counter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ActiveCounterService
{
    private const SESSION_KEY = 'active_counter_id';

    public function options(): Collection
    {
        return Counter::query()
            ->with('branch:id,name,code')
            ->where('is_active', true)
            ->orderBy('branch_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function activeCounter(): ?Counter
    {
        $counterId = session(self::SESSION_KEY);

        if (! $counterId) {
            return null;
        }

        return Counter::query()
            ->with('branch:id,name,code')
            ->where('is_active', true)
            ->find($counterId);
    }

    public function requireActiveCounter(): Counter
    {
        $counter = $this->activeCounter();

        if (! $counter) {
            throw ValidationException::withMessages([
                'active_counter' => 'Pilih counter aktif terlebih dahulu untuk melanjutkan proses layanan.',
            ]);
        }

        return $counter;
    }

    public function setActiveCounter(Counter $counter): void
    {
        session([self::SESSION_KEY => $counter->id]);
    }
}
