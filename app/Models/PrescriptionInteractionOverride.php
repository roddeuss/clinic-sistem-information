<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionInteractionOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_id',
        'prescription_item_id',
        'drug_interaction_rule_id',
        'interaction_key',
        'interaction_title',
        'severity',
        'reason',
        'overridden_by_user_id',
        'overridden_at',
    ];

    protected function casts(): array
    {
        return [
            'overridden_at' => 'datetime',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(DrugInteractionRule::class, 'drug_interaction_rule_id');
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by_user_id');
    }
}
