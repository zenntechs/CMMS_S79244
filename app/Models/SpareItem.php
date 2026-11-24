<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'description', 'unit', 'quantity_on_hand', 'reorder_level', 'is_active'])]
class SpareItem extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:2',
            'reorder_level' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SpareMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(SpareMovement::class, 'spare_item_id');
    }
}
