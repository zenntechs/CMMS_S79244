<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['asset_id', 'reading_hours', 'reading_at', 'recorded_by', 'note'])]
class MeterReading extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'reading_hours' => 'decimal:2',
            'reading_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Reading instant expressed in the application timezone for display.
     */
    public function localReadingAt(): CarbonImmutable
    {
        return $this->reading_at->toImmutable()->setTimezone(config('app.timezone'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
